<?php
include '../config.php';
header('Content-Type: application/json');

session_start(); // Start session to access logged-in user

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['template_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_STRING);
$file = $_FILES['template_file'];

// Check for file upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File size exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File size exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $message = $error_messages[$file['error']] ?? 'Unknown upload error';
    echo json_encode(['status' => 'error', 'message' => $message, 'error_code' => $file['error']]);
    exit;
}

// Validate MIME type for .pptx files
if ($file['type'] !== 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
    echo json_encode(['status' => 'error', 'message' => 'Only .pptx files are allowed', 'file_type' => $file['type']]);
    exit;
}

// Validate event_name
if (empty($event_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Event name is required']);
    exit;
}

// Check if templates directory exists and is writable
$template_dir = __DIR__ . '/../templates/'; // Adjusted path: templates is outside actions directory
if (!is_dir($template_dir)) {
    if (!mkdir($template_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create templates directory']);
        exit;
    }
} elseif (!is_writable($template_dir)) {
    echo json_encode(['status' => 'error', 'message' => 'Templates directory is not writable']);
    exit;
}

$file_name = uniqid() . '_' . basename($file['name']);
$target_file = $template_dir . $file_name;

// Log file paths for debugging
error_log("Temporary file: {$file['tmp_name']}");
error_log("Target file: {$target_file}");

// Fetch user_id from management table
$uploaded_by = null;
if (isset($_SESSION['user_id'])) {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT id FROM management WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $uploaded_by = $_SESSION['user_id'];
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    $url_token = bin2hex(random_bytes(50));
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("INSERT INTO templates (event_name, template_file, url_token, visibility, uploaded_by) VALUES (?, ?, ?, 1, ?)");
    $stmt->bind_param("sssi", $event_name, $file_name, $url_token, $uploaded_by);

    // Check for duplicate event_name
    $check_stmt = $conn->prepare("SELECT id FROM templates WHERE event_name = ?");
    $check_stmt->bind_param("s", $event_name);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        $conn->close();
        unlink($target_file);
        echo json_encode(['status' => 'error', 'message' => 'Event name already exists']);
        exit;
    }
    $check_stmt->close();

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode(['status' => 'success', 'message' => 'Template uploaded successfully']);
    } else {
        $stmt->close();
        $conn->close();
        unlink($target_file);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    $error = error_get_last();
    echo json_encode([
        'status' => 'error',
        'message' => 'File upload failed',
        'target_file' => $target_file,
        'tmp_file' => $file['tmp_name'],
        'php_error' => $error ? $error['message'] : 'No PHP error reported'
    ]);
}
?>