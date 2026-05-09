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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT year_month FROM Closings');
    $closedMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['closed_months' => $closedMonths]);
}
elseif ($method === 'POST') {
    if (!isset($user['role']) || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Admins only']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $yearMonth = $input['year_month'] ?? '';
    $isClosed = $input['is_closed'] ?? false;

    if (empty($yearMonth)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing year_month']);
        exit;
    }

    if ($isClosed) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO Closings (year_month) VALUES (?)');
        $stmt->execute([$yearMonth]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM Closings WHERE year_month = ?');
        $stmt->execute([$yearMonth]);
    }

    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
