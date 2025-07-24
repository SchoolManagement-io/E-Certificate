<?php

// Prevent direct access to this file
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.1 403 Forbidden');
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Direct access attempt blocked\n", FILE_APPEND);
    die('Access denied');
}

require_once 'actions/vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\Drawing\File;
use PhpOffice\PhpPresentation\DocumentLayout;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateUniqueToken($length = 20) {
    return bin2hex(random_bytes($length / 2));
}

function getBaseUrl() {
    static $base_url = null;
    if ($base_url !== null) {
        return $base_url;
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $full_url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $pattern = "/\/e_certificate\//";
    if (preg_match($pattern, $full_url, $matches, PREG_OFFSET_CAPTURE)) {
        $base_url = substr($full_url, 0, $matches[0][1] + strlen($matches[0][0]));
    } else {
        $base_url = "$protocol://$_SERVER[HTTP_HOST]/";
    }
    return $base_url;
}

function generate_promo($conn, $participant_id, $name) {
    $prefix = strtoupper($name[0] ?? 'X');
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $char_length = strlen($characters) - 1;
    
    $stmt = $conn->prepare("SELECT id FROM participants WHERE promo_code = ?");
    $stmt->bind_param("s", $promo_code);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, $char_length)];
        }
        $promo_code = $prefix . $code;

        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE participants SET promo_code = ? WHERE id = ?");
            $stmt->bind_param("si", $promo_code, $participant_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success 
                ? ['status' => 'success', 'message' => 'Promo code generated']
                : ['status' => 'error', 'message' => 'Failed to update promo code'];
        }
    }
    $stmt->close();
    return ['status' => 'error', 'message' => 'Failed to generate unique promo code'];
}

function generate_reference($conn, $participant_id) {
    $year = date('Y');
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $char_length = strlen($characters) - 1;

    $stmt = $conn->prepare("SELECT id FROM participants WHERE certificate_ref = ?");
    $stmt->bind_param("s", $certificate_ref);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[random_int(0, $char_length)];
        }
        $certificate_ref = $year . $code;

        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE participants SET certificate_ref = ? WHERE id = ?");
            $stmt->bind_param("si", $certificate_ref, $participant_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success 
                ? ['status' => 'success', 'message' => 'Certificate reference generated']
                : ['status' => 'error', 'message' => 'Failed to update certificate reference'];
        }
    }
    $stmt->close();
    return ['status' => 'error', 'message' => 'Failed to generate unique certificate reference'];
}

function generate_qr($conn, $participant_id, $certificate_ref) {
    $data = getBaseUrl() . "e_certificate/index.php?page=verify_certificate&ref=" . urlencode($certificate_ref);
    $filename = "$certificate_ref.png";
    $qrDir = __DIR__ . '/qrcodes/';
    $qrPath = $qrDir . $filename;

    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0777, true);
    }

    try {
        $builder = new Builder(
            writer: new PngWriter(),
            size: 300,
            margin: 10,
        );

        $result = $builder->build(data: $data);
        $result->saveToFile($qrPath);

        if (file_exists($qrPath)) {
            $stmt = $conn->prepare("UPDATE participants SET qr_codes = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $participant_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success 
                ? ['status' => 'success', 'message' => 'QR code generated and saved']
                : ['status' => 'error', 'message' => 'Failed to update QR code in database'];
        }
        throw new Exception('Failed to generate QR code');
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'QR code generation error: ' . $e->getMessage()];
    }
}

function make_certificate($conn, $participant_id) {
    $stmt = $conn->prepare("SELECT name, course_name, department_name, college_name, certificate_ref, promo_code, event_name, qr_codes FROM participants WHERE id = ?");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Participant not found'];
    }
    $participant = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT template_file FROM templates WHERE event_name = ?");
    $stmt->bind_param("s", $participant['event_name']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Template not found'];
    }
    $template = $result->fetch_assoc();
    $stmt->close();

    $templatePath = __DIR__ . '/templates/' . $template['template_file'];
    if (!file_exists($templatePath)) {
        return ['status' => 'error', 'message' => 'Template file not found'];
    }

    try {
        $oPresentation = IOFactory::load($templatePath);
        $newPresentation = new PhpPresentation();
        $newPresentation->removeSlideByIndex(0);

        $templateLayout = $oPresentation->getLayout();
        $layout = $newPresentation->getLayout();
        $layout->setCx($templateLayout->getCx());
        $layout->setCy($templateLayout->getCy());
        $layout->setDocumentLayout($templateLayout->getDocumentLayout(), true);

        $newSlide = $newPresentation->createSlide();
        $templateSlide = $oPresentation->getSlide(0);
        if (!$templateSlide) {
            return ['status' => 'error', 'message' => 'No slide found in template'];
        }

        $placeholders = [
            '{PARTICIPANT_NAME}' => $participant['name'],
            '{COURSE_NAME}' => $participant['course_name'] ?? 'N/A',
            '{DEPARTMENT_NAME}' => $participant['department_name'] ?? 'N/A',
            '{COLLEGE_NAME}' => $participant['college_name'] ?? 'N/A',
            '{CERTIFICATE_REF}' => $participant['certificate_ref'],
            '{EVENT_NAME}' => $participant['event_name']
        ];

        foreach ($templateSlide->getShapeCollection() as $shape) {
            if ($shape instanceof RichText) {
                $hasQrPlaceholder = false;
                foreach ($shape->getParagraphs() as $paragraph) {
                    foreach ($paragraph->getRichTextElements() as $textElement) {
                        if (method_exists($textElement, 'getText') && str_contains($textElement->getText() ?? '', '{QR_CODE}')) {
                            $hasQrPlaceholder = true;
                            break 2;
                        }
                    }
                }

                if ($hasQrPlaceholder && $participant['qr_codes']) {
                    $qrPath = __DIR__ . '/qrcodes/' . $participant['qr_codes'];
                    if (file_exists($qrPath)) {
                        $newShape = new File();
                        $newShape->setPath($qrPath)
                                 ->setWidth(100)
                                 ->setHeight(100)
                                 ->setOffsetX($shape->getOffsetX())
                                 ->setOffsetY($shape->getOffsetY());
                        $newSlide->addShape($newShape);
                    }
                } else {
                    $newShape = clone $shape;
                    foreach ($newShape->getParagraphs() as $paragraph) {
                        foreach ($paragraph->getRichTextElements() as $textElement) {
                            if (method_exists($textElement, 'getText') && $textElement->getText() !== null) {
                                $textElement->setText(str_replace(
                                    array_keys($placeholders),
                                    array_values($placeholders),
                                    $textElement->getText()
                                ));
                            }
                        }
                    }
                    $newSlide->addShape($newShape);
                }
            } else {
                $newSlide->addShape(clone $shape);
            }
        }

        $outputDir = __DIR__ . '/certificates/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputPptx = "$outputDir{$participant['certificate_ref']}.pptx";
        $oWriterPPTX = IOFactory::createWriter($newPresentation, 'PowerPoint2007');
        $tempFile = tempnam(sys_get_temp_dir(), 'pptx_');
        $oWriterPPTX->save($tempFile);

        if (file_exists($tempFile) && rename($tempFile, $outputPptx)) {
            $certificate_file = "{$participant['certificate_ref']}.pptx";
            $stmt = $conn->prepare("UPDATE participants SET certificate_file = ? WHERE id = ?");
            $stmt->bind_param("si", $certificate_file, $participant_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success 
                ? ['status' => 'success', 'message' => 'Certificate generated as PPTX', 'file' => $certificate_file]
                : ['status' => 'error', 'message' => 'Failed to update certificate file in database'];
        }
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        return ['status' => 'error', 'message' => 'Failed to move temporary PPTX file'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Certificate generation error: ' . $e->getMessage()];
    }
}

function mail_promo_code($conn, $participant_id) {
    require_once __DIR__ . '/mail_credentials.php';

    $stmt = $conn->prepare("SELECT name, email, promo_code, certificate_ref, event_name FROM participants WHERE id = ?");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['status' => 'error', 'message' => 'Participant not found'];
    }
    $participant = $result->fetch_assoc();
    $stmt->close();

    $download_url = getBaseUrl() . "e_certificate/index.php?page=download_certificate&promo_code=" . urlencode($participant['promo_code']);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, 'Certificate System');
        $mail->addAddress($participant['email'], $participant['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Certificate Promo Code';

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { text-align: center; padding: 20px 0; background-color: #007bff; color: #ffffff; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px 0; color: #666666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Certificate System</h1>
        </div>
        <div class="content">
            <h2>Hello {$participant['name']},</h2>
            <p>Congratulations on successfully registering for <strong>{$participant['event_name']}</strong>!</p>
            <p>Your unique promo code is: <strong>{$participant['promo_code']}</strong></p>
            <p>Use this code to download your certificate.</p>
            <a href="$download_url" class="button">Download Certificate</a>
        </div>
        <div class="footer">
            <p>This is an automated email. Please do not reply.</p>
            <p>© 2025 Certificate System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->send();
        return ['status' => 'success', 'message' => 'Promo code mailed successfully'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Failed to send promo code: ' . $mail->ErrorInfo];
    }
}
?>