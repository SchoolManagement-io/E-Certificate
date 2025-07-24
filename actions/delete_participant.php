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
    $stmt = $conn->prepare("SELECT certificate_file FROM participants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $participant = $result->fetch_assoc();
    $stmt->close();

    if ($participant) {
        $file_path = __DIR__ . '/certificates/' . $participant['certificate_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $conn->prepare("DELETE FROM participants WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            echo json_encode(['status' => 'success', 'message' => 'Participant deleted']);
        } else {
            $stmt->close();
            $conn->close();
            echo json_encode(['status' => 'error', 'message' => 'Deletion failed']);
        }
    } else {
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Participant not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>