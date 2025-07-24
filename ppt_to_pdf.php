<?php
require_once 'cloud_credentials.php';
require_once 'actions/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
        $client = new Client([
            'verify' => __DIR__ . '/certificates/cacert.pem', // Path to the CA bundle
        ]);
        $apiKey = CLOUDCONVERT_API_KEY;

        // Create a new job with all tasks
        $jobResponse = $client->post('https://api.cloudconvert.com/v2/jobs', [
            'headers' => ['Authorization' => "Bearer $apiKey"],
            'json' => [
                'tasks' => [
                    'import-1' => [
                        'operation' => 'import/upload',
                        'filename' => $participant['certificate_file']
                    ],
                    'convert-1' => [
                        'operation' => 'convert',
                        'input' => ['import-1'],
                        'input_format' => 'pptx',
                        'output_format' => 'pdf'
                    ],
                    'export-1' => [
                        'operation' => 'export/url',
                        'input' => ['convert-1']
                    ]
                ]
            ]
        ]);

        $jobData = json_decode($jobResponse->getBody(), true);
        $jobId = $jobData['data']['id'];

        // Get the upload URL for the import task
        $importTaskId = $jobData['data']['tasks'][0]['id'];
        $uploadResponse = $client->get("https://api.cloudconvert.com/v2/tasks/$importTaskId", [
            'headers' => ['Authorization' => "Bearer $apiKey"]
        ]);
        $uploadData = json_decode($uploadResponse->getBody(), true);
        $uploadUrl = $uploadData['data']['result']['form']['url'];
        $parameters = $uploadData['data']['result']['form']['parameters'];

        // Upload the PPTX file using the import task's URL
        $multipart = [];
        foreach ($parameters as $key => $val) {
            $multipart[] = ['name' => $key, 'contents' => $val];
        }
        $multipart[] = [
            'name' => 'file',
            'contents' => fopen($pptxFile, 'r'),
            'filename' => $participant['certificate_file']
        ];

        $client->post($uploadUrl, ['multipart' => $multipart]);

        // Poll job status
        $maxAttempts = 15;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            sleep(2); // Wait between polls
            $jobResult = $client->get("https://api.cloudconvert.com/v2/jobs/$jobId", [
                'headers' => ['Authorization' => "Bearer $apiKey"]
            ]);
            $jobData = json_decode($jobResult->getBody(), true);
            $status = $jobData['data']['status'];

            if ($status === 'finished') {
                break;
            }
            if ($status === 'error' || in_array('error', array_column($jobData['data']['tasks'], 'status'))) {
                return ['status' => 'error', 'message' => 'Conversion job failed: ' . json_encode($jobData['data']['message'])];
            }
            $attempt++;
        }

        if ($attempt >= $maxAttempts) {
            return ['status' => 'error', 'message' => 'Conversion job timed out'];
        }

        // Get PDF URL
        $exportTask = array_filter($jobData['data']['tasks'], function($task) {
            return $task['operation'] === 'export/url';
        });
        $exportTask = array_values($exportTask)[0];
        $pdfUrl = $exportTask['result']['files'][0]['url'];

        // Download and save PDF
        $outputDir = __DIR__ . '/certificates/';
        $pdfFile = $outputDir . $certificate_ref . '.pdf';
        $pdfContent = $client->get($pdfUrl)->getBody()->getContents();
        file_put_contents($pdfFile, $pdfContent);

        if (!file_exists($pdfFile)) {
            return ['status' => 'error', 'message' => 'Failed to save PDF file'];
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
    } catch (RequestException $e) {
        error_log('Guzzle error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        return ['status' => 'error', 'message' => 'Conversion error: ' . $e->getMessage()];
    }
}
?>