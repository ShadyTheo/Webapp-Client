<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database file paths
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('USERS_FILE', DATA_DIR . '/users.json');
define('LIBRARIES_FILE', DATA_DIR . '/libraries.json');
define('MEDIA_FILE', DATA_DIR . '/media.json');

// Create directories if they don't exist
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

// Initialize default data files
function initializeDataFiles() {
    if (!file_exists(USERS_FILE)) {
        $defaultUsers = [
            [
                'id' => 1,
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin'
            ]
        ];
        file_put_contents(USERS_FILE, json_encode($defaultUsers, JSON_PRETTY_PRINT));
    }
    
    if (!file_exists(LIBRARIES_FILE)) {
        file_put_contents(LIBRARIES_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    
    if (!file_exists(MEDIA_FILE)) {
        file_put_contents(MEDIA_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
}

// Helper functions
function readJsonFile($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

function writeJsonFile($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function generateUniqueId() {
    return time() . rand(1000, 9999);
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
}

// Initialize on first load
initializeDataFiles();
?>