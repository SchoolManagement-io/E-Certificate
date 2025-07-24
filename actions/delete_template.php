<?php
include '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    
    if ($id === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit;
    }

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT template_file FROM templates WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result->fetch_assoc();
    $stmt->close();

    if ($template) {
        $file_path = __DIR__ . '/templates/' . $template['template_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $conn->prepare("DELETE FROM templates WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            echo json_encode(['status' => 'success', 'message' => 'Template deleted']);
        } else {
            $stmt->close();
            $conn->close();
            echo json_encode(['status' => 'error', 'message' => 'Deletion failed']);
        }
    } else {
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Template not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>