<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// Helper to get ID from query string since we're using query params for routing simplicity without rewrite rules
$id = $_GET['id'] ?? null;

if ($method === 'GET') {
    $yearMonth = $_GET['year_month'] ?? null;
    $query = 'SELECT Expenses.*, Categories.name as category_name FROM Expenses LEFT JOIN Categories ON Expenses.category_id = Categories.id WHERE user_id = ?';
    $params = [$user['id']];
    
    if ($yearMonth) {
        $query .= ' AND year_month = ?';
        $params[] = $yearMonth;
    }
    $query .= ' ORDER BY date DESC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['expenses' => $expenses]);
} 
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $receiptPath = null;
    if (!empty($input['receipt_base64']) && !empty($input['receipt_name'])) {
        $ext = strtolower(pathinfo($input['receipt_name'], PATHINFO_EXTENSION));
        $filename = time() . '-' . rand(100, 999) . '.' . $ext;
        
        $base64 = $input['receipt_base64'];
        if (strpos($base64, ',') !== false) {
            $base64 = explode(',', $base64)[1];
        }
        
        $data = base64_decode($base64);
        file_put_contents(__DIR__ . '/uploads/' . $filename, $data);
        $receiptPath = $filename;
    }

    $stmt = $pdo->prepare("
        INSERT INTO Expenses (user_id, category_id, amount, date, year_month, description, receipt_file_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $input['category_id'],
        $input['amount'],
        $input['date'],
        $input['year_month'],
        $input['description'] ?? '',
        $receiptPath
    ]);
    
    http_response_code(201);
    echo json_encode(['id' => $pdo->lastInsertId()]);
}
elseif ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare('DELETE FROM Expenses WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    echo json_encode(['success' => true]);
}
elseif ($method === 'PUT' && $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("
        UPDATE Expenses SET category_id = ?, amount = ?, date = ?, year_month = ?, description = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([
        $input['category_id'],
        $input['amount'],
        $input['date'],
        $input['year_month'],
        $input['description'] ?? '',
        $id,
        $user['id']
    ]);
    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed or Missing ID']);
}
