<?php
require_once 'config.php';
require_once 'auth.php';

function getMedia() {
    if (!checkAuth()) return;
    
    $libraryId = isset($_GET['library']) ? (int)$_GET['library'] : null;
    $topic = $_GET['topic'] ?? null;
    
    $media = readJsonFile(MEDIA_FILE);
    
    if ($libraryId) {
        $media = array_filter($media, function($item) use ($libraryId) {
            return $item['libraryId'] === $libraryId;
        });
    }
    
    if ($topic) {
        $media = array_filter($media, function($item) use ($topic) {
            return $item['topic'] === $topic;
        });
    }
    
    // Add full URL to files
    $media = array_map(function($item) {
        $item['url'] = '/api/uploads/' . $item['filename'];
        return $item;
    }, $media);
    
    echo json_encode(array_values($media));
}

function uploadMedia() {
    if (!checkAdmin()) return;
    
    if (!isset($_FILES['files']) || !isset($_POST['libraryId']) || !isset($_POST['topic'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Files, library ID, and topic required']);
        return;
    }
    
    $libraryId = (int)$_POST['libraryId'];
    $topic = $_POST['topic'];
    $files = $_FILES['files'];
    
    // Verify library exists
    $libraries = readJsonFile(LIBRARIES_FILE);
    $libraryExists = false;
    foreach ($libraries as $library) {
        if ($library['id'] === $libraryId) {
            $libraryExists = true;
            break;
        }
    }
    
    if (!$libraryExists) {
        http_response_code(400);
        echo json_encode(['error' => 'Library not found']);
        return;
    }
    
    $media = readJsonFile(MEDIA_FILE);
    $uploadedFiles = [];
    
    // Handle multiple files
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        
        // Validate file type
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg'
        ];
        
        if (!in_array($fileType, $allowedTypes)) {
            continue; // Skip unsupported files
        }
        
        // Validate file size (max 100MB)
        if ($fileSize > 100 * 1024 * 1024) {
            continue; // Skip files too large
        }
        
        // Generate unique filename
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = generateUniqueId() . '_' . sanitizeFilename(pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $extension;
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpName, UPLOADS_DIR . '/' . $uniqueFileName)) {
            $mediaItem = [
                'id' => (int)generateUniqueId(),
                'name' => $fileName,
                'filename' => $uniqueFileName,
                'type' => strpos($fileType, 'video/') === 0 ? 'video' : 'image',
                'libraryId' => $libraryId,
                'topic' => $topic,
                'uploadedAt' => date('c'),
                'size' => $fileSize
            ];
            
            $media[] = $mediaItem;
            $uploadedFiles[] = $mediaItem;
        }
    }
    
    if (writeJsonFile(MEDIA_FILE, $media)) {
        echo json_encode(['success' => true, 'files' => $uploadedFiles]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save media records']);
    }
}

function deleteMedia() {
    if (!checkAdmin()) return;
    
    $mediaId = (int)$_GET['id'];
    
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
        echo json_encode(['error' => 'Media not found']);
        return;
    }
    
    // Delete physical file
    if (file_exists(UPLOADS_DIR . '/' . $mediaItem['filename'])) {
        unlink(UPLOADS_DIR . '/' . $mediaItem['filename']);
    }
    
    // Remove from database
    $media = array_filter($media, function($item) use ($mediaId) {
        return $item['id'] !== $mediaId;
    });
    
    if (writeJsonFile(MEDIA_FILE, array_values($media))) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete media']);
    }
}

function getTopics() {
    if (!checkAuth()) return;
    
    $libraryId = isset($_GET['library']) ? (int)$_GET['library'] : null;
    
    $media = readJsonFile(MEDIA_FILE);
    
    if ($libraryId) {
        $media = array_filter($media, function($item) use ($libraryId) {
            return $item['libraryId'] === $libraryId;
        });
    }
    
    $topics = array_unique(array_column($media, 'topic'));
    echo json_encode(array_values($topics));
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'topics':
        if ($method === 'GET') getTopics();
        break;
    default:
        switch ($method) {
            case 'GET':
                getMedia();
                break;
            case 'POST':
                uploadMedia();
                break;
            case 'DELETE':
                deleteMedia();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
}
?>