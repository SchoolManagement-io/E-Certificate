<?php
include '../config.php';
include '../functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$course_name = filter_input(INPUT_POST, 'course_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$department_name = filter_input(INPUT_POST, 'department_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$college_name = filter_input(INPUT_POST, 'college_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'N/A';
$event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (empty($name) || empty($email) || empty($event_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Name, email, and event name are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

$conn = getDatabaseConnection();
$conn->begin_transaction();

try {
    // Check if event_name exists
    $stmt = $conn->prepare("SELECT id FROM templates WHERE event_name = ?");
    $stmt->bind_param("s", $event_name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Event not found']);
        exit;
    }
    $stmt->close();

    // Insert participant with placeholders
    $promo_code = 'TEMP_PROMO';
    $certificate_ref = 'TEMP_REF';
    $qr_code = null;
    $certificate_file = null;
    $stmt = $conn->prepare("INSERT INTO participants (name, email, course_name, department_name, college_name, event_name, promo_code, certificate_ref, qr_codes, certificate_file, registered_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssssss", $name, $email, $course_name, $department_name, $college_name, $event_name, $promo_code, $certificate_ref, $qr_code, $certificate_file);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $conn->error]);
        exit;
    }
    $participant_id = $conn->insert_id;
    $stmt->close();

    // Queue the backend tasks
    $stmt = $conn->prepare("INSERT INTO pending_tasks (participant_id, name, email, course_name, department_name, college_name, event_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $participant_id, $name, $email, $course_name, $department_name, $college_name, $event_name);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Failed to queue tasks: ' . $conn->error]);
        exit;
    }
    $stmt->close();

    // Commit the transaction
    $conn->commit();
    $conn->close();

    // Return success message immediately
    echo json_encode(['status' => 'success', 'message' => "Successfully registered for $event_name. To access your certificate, a promo code will be sent to $email shortly.", 'participant_id' => $participant_id]);
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}
?>