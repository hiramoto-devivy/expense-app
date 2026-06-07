<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
require_once 'jwt.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, username, display_name, role, bank_code, branch_code, account_type, account_number, account_holder FROM Users WHERE username = ? AND password = ?');
$stmt->execute([$username, $password]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $token = jwt_sign([
        'id' => $user['id'], 
        'username' => $user['username'], 
        'display_name' => $user['display_name'],
        'role' => $user['role'],
        'bank_code' => $user['bank_code'],
        'branch_code' => $user['branch_code'],
        'account_type' => $user['account_type'],
        'account_number' => $user['account_number'],
        'account_holder' => $user['account_holder']
    ], 30);
    echo json_encode(['token' => $token, 'user' => $user]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
