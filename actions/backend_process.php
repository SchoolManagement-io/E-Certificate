<?php
include '../config.php';
include '../functions.php';
include '../ppt_to_pdf.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$participant_id = filter_input(INPUT_POST, 'participant_id', FILTER_VALIDATE_INT);
if (!$participant_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid participant ID']);
    exit;
}

$conn = getDatabaseConnection();
$conn->begin_transaction();

try {
    // Retrieve the pending task
    $stmt = $conn->prepare("SELECT id, name, event_name FROM pending_tasks WHERE participant_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'No pending task found for participant']);
        exit;
    }
    $task = $result->fetch_assoc();
    $task_id = $task['id'];
    $name = $task['name'];
    $event_name = $task['event_name'];
    $stmt->close();

    // Generate promo code
    $promo_result = generate_promo($conn, $participant_id, $name);
    if ($promo_result['status'] !== 'success') {
        $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
        $error_message = $promo_result['message'];
        $stmt->bind_param("si", $error_message, $task_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("DELETE FROM participants WHERE id = $participant_id");
        $conn->commit();
        $conn->close();
        error_log("Promo code generation failed for participant ID $participant_id: " . $promo_result['message']);
        echo json_encode(['status' => 'error', 'message' => $promo_result['message']]);
        exit;
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
        $conn->commit();
        $conn->close();
        error_log("Certificate reference generation failed for participant ID $participant_id: " . $ref_result['message']);
        echo json_encode(['status' => 'error', 'message' => $ref_result['message']]);
        exit;
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
        $conn->commit();
        $conn->close();
        error_log("QR code generation failed for participant ID $participant_id: " . $qr_result['message']);
        echo json_encode(['status' => 'error', 'message' => $qr_result['message']]);
        exit;
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
        $conn->commit();
        $conn->close();
        error_log("Certificate generation failed for participant ID $participant_id: " . $cert_result['message']);
        echo json_encode(['status' => 'error', 'message' => $cert_result['message']]);
        exit;
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
        $conn->commit();
        $conn->close();
        error_log("Email sending failed for participant ID $participant_id: " . $mail_result['message']);
        echo json_encode(['status' => 'error', 'message' => $mail_result['message']]);
        exit;
    }

    // Mark task as completed
    $stmt = $conn->prepare("DELETE FROM pending_tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $conn->close();
    echo json_encode(['status' => 'success', 'message' => 'Backend tasks completed successfully']);
} catch (Exception $e) {
    $stmt = $conn->prepare("UPDATE pending_tasks SET status = 'failed', error_message = ? WHERE id = ?");
    $error_message = 'Transaction failed: ' . $e->getMessage();
    $stmt->bind_param("si", $error_message, $task_id);
    $stmt->execute();
    $stmt->close();
    $conn->query("DELETE FROM participants WHERE id = $participant_id");
    $conn->commit();
    $conn->close();
    error_log("Backend processing failed for participant ID $participant_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Backend processing failed: ' . $e->getMessage()]);
}
?>