<?php
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailCertificate($conn, $participant_id, $email, $certificate_ref, $institution_name, $event_name) {
    require_once __DIR__ . '/../mail_credentials.php';

    $stmt = $conn->prepare("SELECT certificate_file FROM participants WHERE id = ? AND certificate_ref = ?");
    $stmt->bind_param("is", $participant_id, $certificate_ref);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Participant or certificate reference not found'];
    }
    $participant = $result->fetch_assoc();
    $stmt->close();

    $certificate_file = $participant['certificate_file'];
    $certificate_path = __DIR__ . '/../certificates/' . $certificate_file;

    if (!file_exists($certificate_path)) {
        return ['status' => 'error', 'message' => 'Certificate file not found'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, $institution_name);
        $mail->addAddress($email);
        $mail->addAttachment($certificate_path, $certificate_file);
        $mail->isHTML(true);
        $mail->Subject = 'Your Certificate for ' . $event_name;

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; padding: 20px 0; background-color: teal; color: white; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .footer { text-align: center; padding: 10px 0; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>$institution_name</h1>
        </div>
        <div class="content">
            <h2>Thanks for Participating in $event_name</h2>
            <p>Dear Participant,</p>
            <p>We are pleased to provide you with your certificate. Please find it attached to this email.</p>
            <p>Should you have any questions, feel free to contact us.</p>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply.</p>
            <p>© 2025 Certificate System | All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->send();
        return ['status' => 'success', 'message' => 'Certificate mailed successfully'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Failed to send certificate: ' . $mail->ErrorInfo];
    }
}
?>