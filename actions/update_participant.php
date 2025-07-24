<?php
include '../config.php';
include '../functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$participant_id = filter_input(INPUT_POST, 'participant_id', FILTER_VALIDATE_INT);
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$course_name = filter_input(INPUT_POST, 'course_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$department_name = filter_input(INPUT_POST, 'department_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$college_name = filter_input(INPUT_POST, 'college_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'N/A';

if (!$participant_id || empty($name) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Participant ID, Name, and Email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

$conn = getDatabaseConnection();
$conn->begin_transaction();

try {
    // Check if participant exists
    $stmt = $conn->prepare("SELECT id, certificate_file FROM participants WHERE id = ?");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Participant not found']);
        exit;
    }
    $existing_data = $result->fetch_assoc();
    $stmt->close();

    // Handle certificate file upload
    $certificate_file = $existing_data['certificate_file'];
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['size'] > 0) {
        $file = $_FILES['certificate_file'];
        $allowed_extensions = ['ppt', 'pptx', 'pdf'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            $conn->rollback();
            $conn->close();
            echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Only .ppt, .pptx, or .pdf allowed']);
            exit;
        }

        $upload_dir = '../certificates/';
        $original_filename = pathinfo($file['name'], PATHINFO_FILENAME);
        $certificate_file = $file['name']; // Use original filename
        $upload_path = $upload_dir . $certificate_file;

        // Check for filename conflicts
        $counter = 1;
        while (file_exists($upload_path) && $certificate_file !== $existing_data['certificate_file']) {
            $certificate_file = $original_filename . '_' . $counter . '.' . $extension;
            $upload_path = $upload_dir . $certificate_file;
            $counter++;
        }

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $conn->rollback();
            $conn->close();
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload certificate file']);
            exit;
        }

        // Delete old certificate file if exists and different from the new one
        if ($existing_data['certificate_file'] && file_exists($upload_dir . $existing_data['certificate_file']) && $existing_data['certificate_file'] !== $certificate_file) {
            unlink($upload_dir . $existing_data['certificate_file']);
        }
    }

    // Set course_name or department_name based on role
    if ($role === 'Student') {
        $department_name = null;
    } else if ($role === 'Faculty') {
        $course_name = null;
    }

    // Update participant
    $stmt = $conn->prepare("UPDATE participants SET name = ?, email = ?, course_name = ?, department_name = ?, college_name = ?, certificate_file = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $name, $email, $course_name, $department_name, $college_name, $certificate_file, $participant_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $conn->error]);
        exit;
    }
    $stmt->close();

    // Update pending_tasks if exists
    $stmt = $conn->prepare("UPDATE pending_tasks SET name = ?, email = ?, course_name = ?, department_name = ?, college_name = ? WHERE participant_id = ? AND status = 'pending'");
    $stmt->bind_param("sssssi", $name, $email, $course_name, $department_name, $college_name, $participant_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $conn->close();
    echo json_encode(['status' => 'success', 'message' => 'Participant updated successfully']);
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}
?>