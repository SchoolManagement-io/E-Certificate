<?php
session_start();
require_once '../config.php';

$conn = getDatabaseConnection();
$certificate_ref = isset($_GET['ref']) ? mysqli_real_escape_string($conn, $_GET['ref']) : '';
$output = '';

if ($certificate_ref) {
    $sql = "SELECT p.name, p.email, p.course_name, p.department_name, p.college_name, p.event_name, p.promo_code, p.certificate_ref, p.registered_at, m.institution_name 
            FROM participants p 
            LEFT JOIN templates t ON p.event_name = t.event_name 
            LEFT JOIN management m ON t.uploaded_by = m.id 
            WHERE p.certificate_ref = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $certificate_ref);
    $stmt->execute();
    $result = $stmt->get_result();
    $participant = $result->fetch_assoc();

    if ($participant) {
        $output .= '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url(../assets/view_bg.jpg) no-repeat center center fixed;
            background-size: cover;
            color: black;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items center;
            min-height: 100%;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 30px;
            width: 500px;
            min-width: 400px;
            height: 100%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid #ddd;
        }
        .header {
            color: teal;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .institution {
            color: #333;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .verified {
            background-color: blue;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .details {
            text-align: left;
            margin: 20px 0;
            border-top: 1px solid teal;
            border-bottom: 1px solid teal;
            padding: 15px 0;
        }
        .details div {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 16px;
        }
        .details .label {
            font-weight: bold;
            color: teal;
        }
        .download-btn {
            background-color: teal;
            color: white;
            font-weight: bold;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .download-btn:hover {
            background-color:rgb(8, 95, 110);
        }
        .footer {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
        }
        @media (max-width: 600px) {
            .card {
                padding: 15px;
                min-width: calc(100% - 100px);
                min-height: calc(100% - 100px);
            }
            .header {
                font-size: 22px;
            }
            .verified {
                font-size: 14px;
                padding: 3px 10px;
            }
            .details div {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">Certificate Verification</div>
        <div class="institution">' . htmlspecialchars($participant['institution_name']) . '</div>
        <div class="verified">✔ VERIFIED</div>
        <div class="details">
            <div><span class="label">Participant Name:</span> <span>' . htmlspecialchars($participant['name']) . '</span></div>
            <div><span class="label">Email:</span> <span>' . htmlspecialchars($participant['email']) . '</span></div>';
        if ($participant['course_name']) {
            $output .= '<div><span class="label">Course Name:</span> <span>' . htmlspecialchars($participant['course_name']) . '</span></div>';
        }
        if ($participant['department_name']) {
            $output .= '<div><span class="label">Department Name:</span> <span>' . htmlspecialchars($participant['department_name']) . '</span></div>';
        }
        if ($participant['college_name'] !== 'N/A') {
            $output .= '<div><span class="label">College Name:</span> <span>' . htmlspecialchars($participant['college_name']) . '</span></div>';
        }
        $output .= '<div><span class="label">Certificate Reference:</span> <span>' . htmlspecialchars($participant['certificate_ref']) . '</span></div>';
        $output .= '<div><span class="label">Event Name:</span> <span>' . htmlspecialchars($participant['event_name']) . '</span></div>
            <div><span class="label">Issue Date:</span> <span>' . date('F d, Y', strtotime($participant['registered_at'])) . '</span></div>
        </div>
        <a href="?page=download_certificate&promo_code=' . urlencode($participant['promo_code']) . '" class="download-btn">Download Certificate</a>
        <div class="footer">Thank you for participating in ' . htmlspecialchars($participant['event_name']) .  '. <br>This certificate has been verified as authentic by ' . htmlspecialchars($participant['institution_name']) . '.</div>
    </div>
</body>
</html>';
    } else {
        $output = '<p>Invalid Certificate Reference Number</p>';
    }
    $stmt->close();
} else {
    $output = '<p>No reference number provided</p>';
}
$conn->close();
echo $output;
?>