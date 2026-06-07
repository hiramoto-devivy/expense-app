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
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$yearMonth = $_GET['year_month'] ?? date('Y-m');

try {
    // Get all users and their total expenses for the selected month
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.bank_code, 
            u.branch_code, 
            u.account_type, 
            cm.display_name as account_type_name,
            u.account_number, 
            u.account_holder,
            (SELECT COALESCE(SUM(amount), 0) FROM Expenses WHERE user_id = u.id AND year_month = ?) as total_amount
        FROM Users u
        LEFT JOIN CodeMaster cm ON cm.code_group = 'account_type' AND cm.code_value = u.account_type
        ORDER BY u.id ASC
    ");
    $stmt->execute([$yearMonth]);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['summary' => $summary]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
