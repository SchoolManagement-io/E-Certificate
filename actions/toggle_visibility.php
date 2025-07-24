<?php
include '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['visibility'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $visibility = filter_input(INPUT_POST, 'visibility', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
    
    if ($id === false || $visibility === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        exit;
    }

    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("UPDATE templates SET visibility = ? WHERE id = ?");
    $stmt->bind_param("ii", $visibility, $id);
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode(['status' => 'success', 'message' => 'Visibility updated']);
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>