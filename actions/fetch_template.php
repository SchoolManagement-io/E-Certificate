<?php
include '../config.php';
header('Content-Type: application/json');

$conn = getDatabaseConnection();
$query = "SELECT id, event_name, template_file, url_token AS url, visibility FROM templates ORDER BY id DESC";
$result = $conn->query($query);

$templates = [];
while ($row = $result->fetch_assoc()) {
    // Explicitly cast visibility to boolean for JSON compatibility
    $row['visibility'] = (bool)$row['visibility'];
    $templates[] = $row;
}
$result->free();
$conn->close();

$result = [];
$sno = 1;
foreach ($templates as $template) {
    $result[] = array_merge($template, ['sno' => $sno++]);
}

echo json_encode($result);
?>