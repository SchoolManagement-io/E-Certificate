<?php
include '../config.php';
header('Content-Type: application/json');

$filter = filter_input(INPUT_GET, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$event_filter = filter_input(INPUT_GET, 'event_filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$query = "SELECT id, name, email, course_name, department_name, college_name, event_name, promo_code, certificate_ref, certificate_file, qr_codes FROM participants";
$conditions = [];
$params = [];
$types = '';

if ($filter) {
    $conditions[] = "(name LIKE ? OR email LIKE ? OR college_name LIKE ?)";
    $params[] = "%$filter%";
    $params[] = "%$filter%";
    $params[] = "%$filter%";
    $types .= 'sss';
}

if ($event_filter) {
    $conditions[] = "event_name = ?";
    $params[] = $event_filter;
    $types .= 's';
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY id DESC";
$conn = getDatabaseConnection();
$stmt = $conn->prepare($query);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}

$result->free();
$stmt->close();
$conn->close();

$result = [];
$sno = 1;
foreach ($participants as $participant) {
    $result[] = array_merge($participant, ['sno' => $sno++]);
}

echo json_encode($result);
?>