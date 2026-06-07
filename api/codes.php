<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
require_once 'jwt.php';

$user = auth_middleware();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $group = $_GET['group'] ?? '';
    if (!$group) {
        // Return all groups if not specified
        $stmt = $pdo->query("SELECT DISTINCT code_group FROM CodeMaster ORDER BY code_group ASC");
        echo json_encode(['groups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, code_value, display_name FROM CodeMaster WHERE code_group = ? ORDER BY code_value ASC");
    $stmt->execute([$group]);
    echo json_encode(['codes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} 
elseif ($method === 'POST') {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['code_group']) || empty($input['code_value']) || empty($input['display_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO CodeMaster (code_group, code_value, display_name) VALUES (?, ?, ?)");
        $stmt->execute([$input['code_group'], $input['code_value'], $input['display_name']]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Code already exists in this group']);
    }
}
elseif ($method === 'DELETE') {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit;
    }
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM CodeMaster WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
}
