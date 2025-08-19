<?php
require_once 'config.php';
require_once 'auth.php';

if (!checkAuth()) {
    exit;
}

$mediaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$mediaId) {
    http_response_code(400);
    echo 'Media ID required';
    exit;
}

$media = readJsonFile(MEDIA_FILE);
$mediaItem = null;

foreach ($media as $item) {
    if ($item['id'] === $mediaId) {
        $mediaItem = $item;
        break;
    }
}

if (!$mediaItem) {
    http_response_code(404);
    echo 'Media not found';
    exit;
}

$filePath = UPLOADS_DIR . '/' . $mediaItem['filename'];

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Set appropriate headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $mediaItem['name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($filePath);
?>