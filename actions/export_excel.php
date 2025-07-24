<?php
require 'vendor/autoload.php';
include '../config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$conn = getDatabaseConnection();

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$event_filter = isset($_GET['event_filter']) ? $_GET['event_filter'] : '';
$columns = isset($_GET['columns']) ? explode(',', $_GET['columns']) : [
    'sno', 'name', 'email', 'course_dept', 'college_name', 'event_name', 'promo_code', 'certificate_ref'
];

$column_headers = [
    'sno' => 'S.No.',
    'name' => 'Name',
    'email' => 'Email',
    'course_dept' => 'Course/Department',
    'college_name' => 'College',
    'event_name' => 'Event',
    'promo_code' => 'Promo',
    'certificate_ref' => 'Certificate Ref.'
];

$column_widths = [];
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$row = 1;
$col_index = 'A';
foreach ($columns as $column) {
    if (isset($column_headers[$column])) {
        $sheet->setCellValue($col_index . $row, $column_headers[$column]);
        $column_widths[$col_index] = strlen($column_headers[$column]);
        $col_index++;
    }
}

$query = "SELECT p.name, p.email, COALESCE(p.course_name, p.department_name, '') AS course_dept, p.college_name, p.event_name, p.promo_code, p.certificate_ref
          FROM participants p
          JOIN templates t ON p.event_name = t.event_name
          WHERE 1=1";
$params = [];
$types = '';

if (!empty($filter)) {
    $query .= " AND (p.name LIKE ? OR p.email LIKE ? OR p.college_name LIKE ?)";
    $filter_param = "%$filter%";
    $params = [$filter_param, $filter_param, $filter_param];
    $types .= 'sss';
}

if (!empty($event_filter)) {
    $query .= " AND p.event_name = ?";
    $params[] = $event_filter;
    $types .= 's';
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$row = 2;
$sno = 1; // Serial number counter
while ($data = $result->fetch_assoc()) {
    $col_index = 'A';
    foreach ($columns as $column) {
        if (isset($column_headers[$column])) {
            $value = ($column === 'sno') ? $sno : ($data[$column] !== null ? $data[$column] : '');
            $sheet->setCellValue($col_index . $row, $value);
            $column_widths[$col_index] = max($column_widths[$col_index] ?? 0, strlen($value));
            $col_index++;
        }
    }
    $row++;
    $sno++;
}

$stmt->close();
$conn->close();

foreach ($column_widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width + 2);
}

$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())
    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$filename = 'participants_export_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>