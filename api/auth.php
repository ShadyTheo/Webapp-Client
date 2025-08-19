<?php
require_once 'config.php';

session_start();

function login() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    $users = readJsonFile(USERS_FILE);
    
    foreach ($users as $user) {
        if ($user['username'] === $input['username'] && 
            password_verify($input['password'], $user['password'])) {
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
            return;
        }
    }
    
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true]);
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return false;
    }
    return true;
}

function checkAdmin() {
    if (!checkAuth()) return false;
    
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        return false;
    }
    return true;
}

function getCurrentUser() {
    if (!checkAuth()) return;
    
    echo json_encode([
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]
    ]);
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($method === 'POST') login();
        break;
    case 'logout':
        if ($method === 'POST') logout();
        break;
    case 'current':
        if ($method === 'GET') getCurrentUser();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
}
?>