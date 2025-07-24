<?php
include '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($event_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Event name is required']);
    exit;
}

$conn = getDatabaseConnection();

// Check if event_name exists
$stmt = $conn->prepare("SELECT id FROM templates WHERE event_name = ?");
$stmt->bind_param("s", $event_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Event not found']);
    exit;
}
$stmt->close();

$url_token = bin2hex(random_bytes(50));
$stmt = $conn->prepare("UPDATE templates SET url_token = ? WHERE event_name = ?");
$stmt->bind_param("ss", $url_token, $event_name);
if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    $url = "?page=event_registration&event_name=" . urlencode($event_name);
    echo json_encode(['status' => 'success', 'message' => 'URL generated', 'url' => $url]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'URL generation failed: ' . $conn->error]);
}
?>