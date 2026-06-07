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

// Handle method override
if ($method === 'POST') {
    $tempInput = json_decode(file_get_contents('php://input'), true);
    if (isset($tempInput['_method'])) {
        $method = strtoupper($tempInput['_method']);
    } elseif (isset($_GET['_method'])) {
        $method = strtoupper($_GET['_method']);
    }
}

// Helper to get ID from query string since we're using query params for routing simplicity without rewrite rules
$id = $_GET['id'] ?? null;

function is_month_closed($pdo, $yearMonth, $userId) {
    $stmt = $pdo->prepare('SELECT 1 FROM Closings WHERE year_month = ? AND user_id = ?');
    $stmt->execute([$yearMonth, $userId]);
    return $stmt->fetch() !== false;
}

if ($method === 'GET') {
    $yearMonth = $_GET['year_month'] ?? null;
    $targetUserId = $_GET['target_user_id'] ?? $user['id'];
    if ($user['role'] !== 'admin') {
        $targetUserId = $user['id'];
    }

    $query = 'SELECT Expenses.*, Categories.name as category_name FROM Expenses LEFT JOIN Categories ON Expenses.category_id = Categories.id WHERE user_id = ?';
    $params = [$targetUserId];
    
    if ($yearMonth) {
        $query .= ' AND year_month = ?';
        $params[] = $yearMonth;
    }
    $query .= ' ORDER BY year_month ASC, date ASC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['expenses' => $expenses]);
} 
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $receiptPath = null;
    if (!empty($input['receipt_base64']) && !empty($input['receipt_name'])) {
        $expenseDate = isset($input['expenses']) && count($input['expenses']) > 0 ? $input['expenses'][0]['date'] : $input['date'];
        $yearMonthDir = str_replace('-', '', substr($expenseDate, 0, 7));
        $targetDir = __DIR__ . '/uploads/' . $yearMonthDir . '/';
        
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $ext = strtolower(pathinfo($input['receipt_name'], PATHINFO_EXTENSION));
        $newFilename = date('Ymd_His') . '_' . $user['id'] . '.' . $ext;
        
        $base64 = $input['receipt_base64'];
        if (strpos($base64, ',') !== false) {
            $base64 = explode(',', $base64)[1];
        }
        
        $data = base64_decode($base64);
        file_put_contents($targetDir . $newFilename, $data);
        $receiptPath = $yearMonthDir . '/' . $newFilename;
    }

    $insertedIds = [];
    $pdo->beginTransaction();
    try {
        if (isset($input['expenses']) && is_array($input['expenses'])) {
            $items = $input['expenses'];
        } else {
            $items = [$input];
        }

        $stmt = $pdo->prepare("
            INSERT INTO Expenses (user_id, category_id, amount, date, year_month, description, receipt_file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            if (is_month_closed($pdo, $item['year_month'], $user['id'])) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['error' => 'This month is closed for one of the expenses']);
                exit;
            }

            $stmt->execute([
                $user['id'],
                $item['category_id'],
                $item['amount'],
                $item['date'],
                $item['year_month'],
                $item['description'] ?? '',
                $receiptPath
            ]);
            $insertedIds[] = $pdo->lastInsertId();
        }
        
        $pdo->commit();
        http_response_code(201);
        echo json_encode(['ids' => $insertedIds]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save expenses']);
    }
}
elseif ($method === 'DELETE' && $id) {
    // Check if the expense's month is closed
    $stmtCheck = $pdo->prepare('SELECT year_month, user_id FROM Expenses WHERE id = ?');
    $stmtCheck->execute([$id]);
    $exp = $stmtCheck->fetch();
    if ($exp && is_month_closed($pdo, $exp['year_month'], $exp['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'This month is closed']);
        exit;
    }

    // Get file path before deleting from DB
    $stmtFile = $pdo->prepare('SELECT receipt_file_path FROM Expenses WHERE id = ?');
    $stmtFile->execute([$id]);
    $filePath = $stmtFile->fetchColumn();

    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare('DELETE FROM Expenses WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM Expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
    }

    // Delete file if it exists
    if ($filePath) {
        $fullPath = __DIR__ . '/uploads/' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    echo json_encode(['success' => true]);
}
elseif ($method === 'PUT' && $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmtCheck = $pdo->prepare('SELECT year_month, user_id FROM Expenses WHERE id = ?');
    $stmtCheck->execute([$id]);
    $exp = $stmtCheck->fetch();
    if (!$exp) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        exit;
    }
    if (is_month_closed($pdo, $exp['year_month'], $exp['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'The original month is closed']);
        exit;
    }
    if (is_month_closed($pdo, $input['year_month'], $exp['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'The target month is closed']);
        exit;
    }

    $updateReceiptSql = "";
    $params = [
        $input['category_id'],
        $input['amount'],
        $input['date'],
        $input['year_month'],
        $input['description'] ?? ''
    ];

    if (!empty($input['receipt_base64']) && !empty($input['receipt_name'])) {
        // Delete old file if exists
        $stmtOld = $pdo->prepare('SELECT receipt_file_path FROM Expenses WHERE id = ?');
        $stmtOld->execute([$id]);
        $oldPath = $stmtOld->fetchColumn();
        if ($oldPath) {
            $fullOldPath = __DIR__ . '/uploads/' . $oldPath;
            if (file_exists($fullOldPath)) {
                unlink($fullOldPath);
            }
        }

        $expenseDate = $input['date'];
        $yearMonthDir = str_replace('-', '', substr($expenseDate, 0, 7));
        $targetDir = __DIR__ . '/uploads/' . $yearMonthDir . '/';
        
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $ext = strtolower(pathinfo($input['receipt_name'], PATHINFO_EXTENSION));
        $newFilename = date('Ymd_His') . '_' . $user['id'] . '.' . $ext;
        
        $base64 = $input['receipt_base64'];
        if (strpos($base64, ',') !== false) {
            $base64 = explode(',', $base64)[1];
        }
        
        $data = base64_decode($base64);
        file_put_contents($targetDir . $newFilename, $data);
        
        $updateReceiptSql = ", receipt_file_path = ?";
        $params[] = $yearMonthDir . '/' . $newFilename;
    }

    $params[] = $id;

    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("
            UPDATE Expenses SET category_id = ?, amount = ?, date = ?, year_month = ?, description = ?
            $updateReceiptSql
            WHERE id = ?
        ");
        $stmt->execute($params);
    } else {
        $params[] = $user['id'];
        $stmt = $pdo->prepare("
            UPDATE Expenses SET category_id = ?, amount = ?, date = ?, year_month = ?, description = ?
            $updateReceiptSql
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute($params);
    }
    echo json_encode(['success' => true]);
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed or Missing ID']);
}
