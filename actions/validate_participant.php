<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

$conn = getDatabaseConnection();
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$promo_code = isset($_POST['promo_code']) ? sanitizeInput($_POST['promo_code']) : '';

if (empty($email) || empty($promo_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and promo code are required.']);
    $conn->close();
    exit;
}

$sql = "SELECT p.id, p.email, p.certificate_ref, p.certificate_file, p.event_name, p.name, p.course_name, p.department_name, p.college_name, m.institution_name 
        FROM participants p 
        LEFT JOIN templates t ON p.event_name = t.event_name 
        LEFT JOIN management m ON t.uploaded_by = m.id 
        WHERE p.promo_code = ? AND p.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $promo_code, $email);
$stmt->execute();
$result = $stmt->get_result();
$participant = $result->fetch_assoc();
$stmt->close();

if ($participant) {
    // Insert task into pending_tasks
    $sql = "INSERT INTO pending_tasks (participant_id, name, email, course_name, department_name, college_name, event_name, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issssss",
        $participant['id'],
        $participant['name'],
        $participant['email'],
        $participant['course_name'],
        $participant['department_name'],
        $participant['college_name'],
        $participant['event_name']
    );
    $stmt->execute();
    $task_id = $conn->insert_id;
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'email' => $email,
        'task_id' => $task_id,
        'message' => 'Participant validated successfully.'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Please check your email or promo code again.']);
}

$conn->close();
?>