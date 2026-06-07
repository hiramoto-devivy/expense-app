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

if ($method === 'POST') {
    $tempInput = json_decode(file_get_contents('php://input'), true);
    if (isset($tempInput['_method'])) {
        $method = strtoupper($tempInput['_method']);
    } elseif (isset($_GET['_method'])) {
        $method = strtoupper($_GET['_method']);
    }
}

if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.username, 
            u.display_name,
            u.role, 
            u.bank_code, 
            u.branch_code, 
            u.account_type, 
            cm.display_name as account_type_name,
            u.account_number, 
            u.account_holder 
        FROM Users u
        LEFT JOIN CodeMaster cm ON cm.code_group = 'account_type' AND cm.code_value = u.account_type
        ORDER BY u.id ASC
    ");
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
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, display_name, role, bank_code, branch_code, account_type, account_number, account_holder) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['username'],
            $input['password'],
            $input['display_name'] ?? null,
            $input['role'] ?? 'user',
            $input['bank_code'] ?? null,
            $input['branch_code'] ?? null,
            $input['account_type'] ?? null,
            $input['account_number'] ?? null,
            $input['account_holder'] ?? null
        ]);
        
        http_response_code(201);
        echo json_encode(['id' => $pdo->lastInsertId(), 'success' => true]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Username might already exist']);
    }
}
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user ID']);
        exit;
    }

    $sql = "UPDATE Users SET username = ?, display_name = ?, role = ?, bank_code = ?, branch_code = ?, account_type = ?, account_number = ?, account_holder = ?";
    $params = [
        $input['username'],
        $input['display_name'] ?? null,
        $input['role'],
        $input['bank_code'] ?? null,
        $input['branch_code'] ?? null,
        $input['account_type'] ?? null,
        $input['account_number'] ?? null,
        $input['account_holder'] ?? null
    ];

    if (!empty($input['password'])) {
        $sql .= ", password = ?";
        $params[] = $input['password'];
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true]);
}
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user ID']);
        exit;
    }

    if ($id == $user['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete yourself']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM Users WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
