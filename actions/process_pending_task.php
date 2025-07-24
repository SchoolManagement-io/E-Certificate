<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../ppt_to_pdf.php';
require_once 'send_certificate.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;

if ($task_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid task ID.']);
    $conn->close();
    exit;
}

// Fetch the specific task
$sql = "SELECT id, participant_id, email, event_name FROM pending_tasks WHERE id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
$stmt->close();

if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found or already processed.']);
    $conn->close();
    exit;
}

$participant_id = $task['participant_id'];
$email = $task['email'];
$event_name = $task['event_name'];

// Fetch participant details
$sql = "SELECT p.certificate_ref, p.certificate_file, m.institution_name 
        FROM participants p 
        LEFT JOIN templates t ON p.event_name = t.event_name 
        LEFT JOIN management m ON t.uploaded_by = m.id 
        WHERE p.id = ? AND p.event_name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $participant_id, $event_name);
$stmt->execute();
$participant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($participant) {
    $certificate_file = $participant['certificate_file'];
    $certificate_path = __DIR__ . '/../certificates/' . $certificate_file;

    try {
        if ($certificate_file && file_exists($certificate_path) && pathinfo($certificate_path, PATHINFO_EXTENSION) === 'pdf') {
            $mail_result = mailCertificate($conn, $participant_id, $email, $participant['certificate_ref'], $participant['institution_name'], $event_name);
            if ($mail_result['status'] === 'success') {
                $status = 'completed';
                $error_message = null;
            } else {
                $status = 'failed';
                $error_message = $mail_result['message'];
            }
        } elseif ($certificate_file && file_exists($certificate_path) && pathinfo($certificate_path, PATHINFO_EXTENSION) === 'pptx') {
            $conversion_result = convertToPdf($conn, $participant_id, $participant['certificate_ref']);
            if ($conversion_result['status'] === 'success') {
                $mail_result = mailCertificate($conn, $participant_id, $email, $participant['certificate_ref'], $participant['institution_name'], $event_name);
                if ($mail_result['status'] === 'success') {
                    $status = 'completed';
                    $error_message = null;
                } else {
                    $status = 'failed';
                    $error_message = $mail_result['message'];
                }
            } else {
                $status = 'failed';
                $error_message = 'Conversion failed: ' . $conversion_result['message'];
            }
        } else {
            $status = 'failed';
            $error_message = 'Certificate file not found or invalid';
        }
    } catch (Exception $e) {
        $status = 'failed';
        $error_message = 'Error processing task: ' . $e->getMessage();
    }
} else {
    $status = 'failed';
    $error_message = 'Participant not found';
}

// Update task status
$sql = "UPDATE pending_tasks SET status = ?, error_message = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $error_message, $task_id);
$stmt->execute();
$stmt->close();

// Log errors
if ($status === 'failed') {
    error_log("Task $task_id failed: $error_message", 3, __DIR__ . '/../debug_log.txt');
}

echo json_encode(['status' => $status, 'message' => $error_message ?? 'Task processed successfully.']);
$conn->close();
?>