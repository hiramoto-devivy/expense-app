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
    $yearMonth = $_GET['year_month'] ?? null;
    $targetUserId = $_GET['target_user_id'] ?? null;

    if ($yearMonth && $user['role'] === 'admin') {
        // Return all closings for this month
        $stmt = $pdo->prepare('SELECT user_id FROM Closings WHERE year_month = ?');
        $stmt->execute([$yearMonth]);
        $closings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['closings' => $closings]);
    } else {
        $targetUserId = $targetUserId ?? $user['id'];
        if ($user['role'] !== 'admin') {
            $targetUserId = $user['id'];
        }
        $stmt = $pdo->prepare('SELECT year_month FROM Closings WHERE user_id = ?');
        $stmt->execute([$targetUserId]);
        $closedMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['closed_months' => $closedMonths]);
    }
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
    $targetUserId = $input['target_user_id'] ?? null;

    if (empty($yearMonth) || empty($targetUserId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing year_month or target_user_id']);
        exit;
    }

    if ($targetUserId === 'all') {
        if ($isClosed) {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO Closings (year_month, user_id) SELECT ?, id FROM Users');
            $stmt->execute([$yearMonth]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM Closings WHERE year_month = ?');
            $stmt->execute([$yearMonth]);
        }
    } else {
        if ($isClosed) {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO Closings (year_month, user_id) VALUES (?, ?)');
            $stmt->execute([$yearMonth, $targetUserId]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM Closings WHERE year_month = ? AND user_id = ?');
            $stmt->execute([$yearMonth, $targetUserId]);
        }
    }

    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
