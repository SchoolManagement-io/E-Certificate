<?php
include '../config.php';
include '../functions.php';
include '../ppt_to_pdf.php';
header('Content-Type: application/json');

function bulk_backend($conn, $first_id, $last_id, $event_name) {
    $conn->begin_transaction();
    try {
        // Retrieve all pending tasks within the ID range for the event
        $stmt = $conn->prepare("SELECT id, participant_id, name, email FROM pending_tasks WHERE participant_id BETWEEN ? AND ? AND event_name = ? AND status = 'pending'");
        $stmt->bind_param("iis", $first_id, $last_id, $event_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $tasks = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($tasks)) {
            $conn->rollback();
            return ['status' => 'error', 'message' => 'No pending tasks found for the specified range'];
        }

        foreach ($tasks as $task) {
            $task_id = $task['id'];
            $participant_id = $task['participant_id'];
            $name = $task['name'];

            // Generate promo code
            $promo_result = generate_promo($conn, $participant_id, $name);
            if ($promo_result['status'] !== 'success') {
                $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
                $error_message = $promo_result['message'];
                $stmt->bind_param("si", $error_message, $task_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM participants WHERE id = $participant_id");
                continue;
            }

            // Generate certificate reference
            $ref_result = generate_reference($conn, $participant_id);
            if ($ref_result['status'] !== 'success') {
                $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
                $error_message = $ref_result['message'];
                $stmt->bind_param("si", $error_message, $task_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM participants WHERE id = $participant_id");
                continue;
            }

            // Generate QR code
            $stmt = $conn->prepare("SELECT certificate_ref FROM participants WHERE id = ?");
            $stmt->bind_param("i", $participant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $certificate_ref = $result->fetch_assoc()['certificate_ref'];
            $stmt->close();

            $qr_result = generate_qr($conn, $participant_id, $certificate_ref);
            if ($qr_result['status'] !== 'success') {
                $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
                $error_message = $qr_result['message'];
                $stmt->bind_param("si", $error_message, $task_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM participants WHERE id = $participant_id");
                continue;
            }

            // Generate certificate
            $cert_result = make_certificate($conn, $participant_id);
            if ($cert_result['status'] !== 'success') {
                $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
                $error_message = $cert_result['message'];
                $stmt->bind_param("si", $error_message, $task_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM participants WHERE id = $participant_id");
                continue;
            }

            // Mail promo code
            $mail_result = mail_promo_code($conn, $participant_id);
            if ($mail_result['status'] !== 'success') {
                $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
                $error_message = $mail_result['message'];
                $stmt->bind_param("si", $error_message, $task_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM participants WHERE id = $participant_id");
                continue;
            }

            // Mark task as completed
            $stmt = $conn->prepare("DELETE FROM pending_tasks WHERE id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        return ['status' => 'success', 'message' => 'Bulk backend tasks completed successfully'];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Bulk backend processing failed: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Bulk backend processing failed: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$first_id = filter_input(INPUT_POST, 'first_id', FILTER_VALIDATE_INT);
$last_id = filter_input(INPUT_POST, 'last_id', FILTER_VALIDATE_INT);
$event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$first_id || !$last_id || !$event_name) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid first_id, last_id, or event_name']);
    exit;
}

$conn = getDatabaseConnection();
$result = bulk_backend($conn, $first_id, $last_id, $event_name);
$conn->close();
echo json_encode($result);
?>