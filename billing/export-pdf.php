<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = trim($_POST['filename'] ?? 'document.pdf');
    // Clean filename for HTTP header security
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    if (substr(strtolower($filename), -4) !== '.pdf') {
        $filename .= '.pdf';
    }

    $rawBase64 = $_POST['pdf_data'] ?? '';
    if (!empty($rawBase64)) {
        $pdfData = base64_decode($rawBase64);
        if ($pdfData !== false && strlen($pdfData) > 0) {
            // Clean any previous output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Stream natively as a clean file attachment to avoid browser .tmp residue
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdfData));
            header('Cache-Control: private, no-transform, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $pdfData;
            exit;
        }
    }
}

http_response_code(400);
echo "Invalid PDF Export Request";
