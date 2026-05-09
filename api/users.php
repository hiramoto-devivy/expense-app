<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
require_once 'jwt.php';

$user = auth_middleware();

// Admin check
if (!isset($user['role']) || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Admins only']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id, username, role FROM Users ORDER BY id DESC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users]);
}
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([
            $input['username'],
            $input['password'],
            $input['role'] ?? 'user'
        ]);
        
        http_response_code(201);
        echo json_encode(['id' => $pdo->lastInsertId(), 'success' => true]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Username might already exist']);
    }
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
