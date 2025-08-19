<?php
require_once 'config.php';
require_once 'auth.php';

function getUsers() {
    if (!checkAdmin()) return;
    
    $users = readJsonFile(USERS_FILE);
    
    // Remove passwords from response
    $safeUsers = array_map(function($user) {
        unset($user['password']);
        return $user;
    }, $users);
    
    echo json_encode($safeUsers);
}

function createUser() {
    if (!checkAdmin()) return;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['role'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, password, and role required']);
        return;
    }
    
    $users = readJsonFile(USERS_FILE);
    
    // Check if username exists
    foreach ($users as $user) {
        if ($user['username'] === $input['username']) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already exists']);
            return;
        }
    }
    
    $newUser = [
        'id' => (int)generateUniqueId(),
        'username' => $input['username'],
        'password' => password_hash($input['password'], PASSWORD_DEFAULT),
        'role' => in_array($input['role'], ['admin', 'client']) ? $input['role'] : 'client'
    ];
    
    $users[] = $newUser;
    
    if (writeJsonFile(USERS_FILE, $users)) {
        unset($newUser['password']);
        echo json_encode(['success' => true, 'user' => $newUser]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save user']);
    }
}

function deleteUser() {
    if (!checkAdmin()) return;
    
    $userId = (int)$_GET['id'];
    
    if ($userId === 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete default admin user']);
        return;
    }
    
    $users = readJsonFile(USERS_FILE);
    $users = array_filter($users, function($user) use ($userId) {
        return $user['id'] !== $userId;
    });
    
    if (writeJsonFile(USERS_FILE, array_values($users))) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user']);
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUsers();
        break;
    case 'POST':
        createUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>