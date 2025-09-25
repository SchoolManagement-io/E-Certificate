<?php
require_once 'config.php'; // If still needed for other credentials or database connection

function convertToPdf($conn, $participant_id, $certificate_ref) {
    // Fetch participant data
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

    $pptxFile = __DIR__ . '/certificates/' . $participant['certificate_file'];
    if (!file_exists($pptxFile)) {
        return ['status' => 'error', 'message' => 'PPTX file not found'];
    }

    try {
        // Define paths
        $outputDir = __DIR__ . '/certificates/';
        $pdfFile = $outputDir . $certificate_ref . '.pdf';

        // LibreOffice command
        $libreOfficePath = 'soffice';
        $command = "\"$libreOfficePath\" --headless --convert-to pdf --outdir \"$outputDir\" \"$pptxFile\" 2>&1";

        // Execute the command
        exec($command, $output, $returnVar);

        // Check if conversion was successful
        if ($returnVar !== 0 || !file_exists($pdfFile)) {
            error_log('LibreOffice conversion error: ' . implode("\n", $output));
            return ['status' => 'error', 'message' => 'Conversion failed: ' . implode("\n", $output)];
        }

        // Update database with PDF filename
        $certificate_file = $certificate_ref . '.pdf';
        $stmt = $conn->prepare("UPDATE participants SET certificate_file = ? WHERE id = ? AND certificate_ref = ?");
        $stmt->bind_param("sis", $certificate_file, $participant_id, $certificate_ref);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['status' => 'error', 'message' => 'Failed to update certificate file in database'];
        }
        $stmt->close();

        // Delete the original PPTX file
        if (file_exists($pptxFile)) {
            unlink($pptxFile);
        }

        return ['status' => 'success', 'message' => 'Certificate converted to PDF successfully', 'file' => $certificate_file];
    } catch (Exception $e) {
        error_log('Error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        return ['status' => 'error', 'message' => 'Conversion error: ' . $e->getMessage()];
    }
}
?>