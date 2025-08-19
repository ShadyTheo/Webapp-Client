<?php
require_once 'config.php';
require_once 'auth.php';

function getLibraries() {
    if (!checkAuth()) return;
    
    $libraries = readJsonFile(LIBRARIES_FILE);
    echo json_encode($libraries);
}

function createLibrary() {
    if (!checkAdmin()) return;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Library name required']);
        return;
    }
    
    $libraries = readJsonFile(LIBRARIES_FILE);
    
    $newLibrary = [
        'id' => (int)generateUniqueId(),
        'name' => $input['name'],
        'description' => $input['description'] ?? '',
        'createdAt' => date('c')
    ];
    
    $libraries[] = $newLibrary;
    
    if (writeJsonFile(LIBRARIES_FILE, $libraries)) {
        echo json_encode(['success' => true, 'library' => $newLibrary]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save library']);
    }
}

function deleteLibrary() {
    if (!checkAdmin()) return;
    
    $libraryId = (int)$_GET['id'];
    
    // Delete library
    $libraries = readJsonFile(LIBRARIES_FILE);
    $libraries = array_filter($libraries, function($library) use ($libraryId) {
        return $library['id'] !== $libraryId;
    });
    
    // Delete associated media files and records
    $media = readJsonFile(MEDIA_FILE);
    $mediaToDelete = array_filter($media, function($item) use ($libraryId) {
        return $item['libraryId'] === $libraryId;
    });
    
    // Delete physical files
    foreach ($mediaToDelete as $item) {
        if (file_exists(UPLOADS_DIR . '/' . $item['filename'])) {
            unlink(UPLOADS_DIR . '/' . $item['filename']);
        }
    }
    
    // Remove media records
    $media = array_filter($media, function($item) use ($libraryId) {
        return $item['libraryId'] !== $libraryId;
    });
    
    if (writeJsonFile(LIBRARIES_FILE, array_values($libraries)) && 
        writeJsonFile(MEDIA_FILE, array_values($media))) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete library']);
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getLibraries();
        break;
    case 'POST':
        createLibrary();
        break;
    case 'DELETE':
        deleteLibrary();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>