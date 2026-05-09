<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';
require_once 'jwt.php';

$user = auth_middleware();

$stmt = $pdo->query('SELECT * FROM Categories');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['categories' => $categories]);
