<?php
session_start();
include '../config.php';
include '../functions.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
header('Content-Type: application/json');

function insert_participant($conn, $data, $event_name) {
    $name = $data['Name'];
    $email = $data['Email'];
    $course_name = $data['Course Name'] ?: null;
    $department_name = $data['Department Name'] ?: null;
    $college_name = $data['College Name'] ?: 'N/A';

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Invalid Name or Email'];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id FROM templates WHERE event_name = ?");
        $stmt->bind_param("s", $event_name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            $conn->rollback();
            return ['status' => 'error', 'message' => 'Event not found'];
        }
        $stmt->close();

        $promo_code = 'TEMP_PROMO';
        $certificate_ref = 'TEMP_REF';
        $certificate_file = null;
        $qr_code = null;
        $stmt = $conn->prepare("INSERT INTO participants (name, email, course_name, department_name, college_name, event_name, promo_code, certificate_ref, qr_codes, certificate_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $name, $email, $course_name, $department_name, $college_name, $event_name, $promo_code, $certificate_ref, $qr_code, $certificate_file);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            return ['status' => 'error', 'message' => 'Insert failed: ' . $conn->error];
        }
        $participant_id = $conn->insert_id;
        $stmt->close();

        // Insert into pending_tasks
        $stmt = $conn->prepare("INSERT INTO pending_tasks (participant_id, name, email, course_name, department_name, college_name, event_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $participant_id, $name, $email, $course_name, $department_name, $college_name, $event_name);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            $conn->query("DELETE FROM participants WHERE id = $participant_id");
            return ['status' => 'error', 'message' => 'Failed to queue tasks: ' . $conn->error];
        }
        $stmt->close();

        $conn->commit();
        return ['status' => 'success', 'message' => 'Inserted', 'participant_id' => $participant_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'accept') {
    $event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $successful_entries = $_SESSION['import_progress']['successful_entries'] ?? 0;
    $first_id = $_SESSION['import_progress']['first_id'] ?? null;
    $last_id = $_SESSION['import_progress']['last_id'] ?? null;
    unset($_SESSION['import_progress']);
    echo json_encode([
        'status' => 'success',
        'message' => "Successfully registered $successful_entries into $event_name. The promo codes will be sent to their emails.",
        'first_id' => $first_id,
        'last_id' => $last_id
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'decline') {
    $event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $conn = getDatabaseConnection();
    $response = ['status' => 'success', 'message' => 'Entries declined'];
    
    if (isset($_SESSION['import_progress']['first_id']) && isset($_SESSION['import_progress']['last_id'])) {
        $first_id = $_SESSION['import_progress']['first_id'];
        $last_id = $_SESSION['import_progress']['last_id'];
        $stmt = $conn->prepare("DELETE FROM participants WHERE id BETWEEN ? AND ? AND event_name = ?");
        $stmt->bind_param("iis", $first_id, $last_id, $event_name);
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            error_log("Decline: Deleted $affected_rows rows for event '$event_name' between IDs $first_id and $last_id");
            // Also delete from pending_tasks
            $stmt = $conn->prepare("DELETE FROM pending_tasks WHERE participant_id BETWEEN ? AND ? AND event_name = ?");
            $stmt->bind_param("iis", $first_id, $last_id, $event_name);
            $stmt->execute();
            $stmt->close();
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to delete entries: ' . $stmt->error];
            error_log("Decline error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response = ['status' => 'error', 'message' => 'No entries to delete'];
        error_log("Decline error: first_id or last_id not set in session");
    }
    
    $conn->close();
    unset($_SESSION['import_progress']);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'progress') {
    if (!isset($_SESSION['import_progress'])) {
        echo json_encode(['status' => 'complete', 'rows' => [], 'current_row' => 0, 'total_rows' => 0, 'successful_entries' => 0, 'failed_entries' => 0]);
        exit;
    }
    echo json_encode($_SESSION['import_progress']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file']) || !isset($_POST['event_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$file = $_FILES['file'];
if (!in_array($file['type'], ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
    echo json_encode(['status' => 'error', 'message' => 'Only .xls or .xlsx files allowed']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB']);
    exit;
}

$event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$spreadsheet = IOFactory::load($file['tmp_name']);
$sheet = $spreadsheet->getActiveSheet();
$headers = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0];
$expected_headers = ['Name', 'Email', 'Course Name', 'Department Name', 'College Name'];

// Check for case-sensitive spelling of provided headers
foreach ($headers as $header) {
    if ($header !== null && !in_array($header, $expected_headers)) {
        echo json_encode(['status' => 'error', 'message' => 'Please check spelling of Headers.']);
        exit;
    }
}

// Check for mandatory headers
if (!in_array('Name', $headers) || !in_array('Email', $headers)) {
    echo json_encode(['status' => 'error', 'message' => 'Please ensure to import proper excel file having Name, Email in header with values.']);
    exit;
}

// Map headers to expected sequence
$header_map = [];
foreach ($expected_headers as $expected) {
    $index = array_search($expected, $headers);
    $header_map[$expected] = $index !== false ? $index : null;
}

$_SESSION['import_progress'] = [
    'status' => 'processing',
    'current_row' => 0,
    'total_rows' => $sheet->getHighestRow() - 1,
    'successful_entries' => 0,
    'failed_entries' => 0,
    'rows' => [],
    'first_id' => null,
    'last_id' => null
];

$conn = getDatabaseConnection();
$first_id = null;

for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
    $row_data = $sheet->rangeToArray('A' . $row . ':' . $sheet->getHighestColumn() . $row)[0];
    $data = [];
    foreach ($expected_headers as $header) {
        $index = $header_map[$header];
        $data[$header] = $index !== null && isset($row_data[$index]) && $row_data[$index] !== '' ? $row_data[$index] : ($header === 'College Name' ? 'N/A' : null);
    }

    if (empty($data['Name']) || empty($data['Email'])) {
        $_SESSION['import_progress']['rows'][] = [
            'data' => $data,
            'status' => 'error',
            'error' => 'Missing Name or Email'
        ];
        $_SESSION['import_progress']['failed_entries']++;
    } else {
        $result = insert_participant($conn, $data, $event_name);
        if ($result['status'] === 'success') {
            $_SESSION['import_progress']['successful_entries']++;
            $_SESSION['import_progress']['rows'][] = [
                'data' => $data,
                'status' => 'success'
            ];
            if ($first_id === null) {
                $first_id = $result['participant_id'];
            }
            $_SESSION['import_progress']['last_id'] = $result['participant_id'];
        } else {
            $_SESSION['import_progress']['rows'][] = [
                'data' => $data,
                'status' => 'error',
                'error' => $result['message']
            ];
            $_SESSION['import_progress']['failed_entries']++;
        }
    }

    $_SESSION['import_progress']['current_row'] = $row - 1;
    if ($first_id !== null) {
        $_SESSION['import_progress']['first_id'] = $first_id;
    }
}

$_SESSION['import_progress']['status'] = 'complete';
$conn->close();
echo json_encode(['status' => 'success', 'message' => 'Processing started']);
?>