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

// Admin check
if (!isset($user['role']) || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Admins only']);
    exit;
}

try {
    // Join Expenses, Users, and Categories to get a complete picture
    $stmt = $pdo->query("
        SELECT 
            e.id, 
            u.username, 
            e.date, 
            c.name as category, 
            e.amount, 
            e.description,
            e.year_month
        FROM Expenses e
        JOIN Users u ON e.user_id = u.id
        JOIN Categories c ON e.category_id = c.id
        ORDER BY e.date ASC, u.id ASC
    ");
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=all_expenses_' . date('Ymd') . '.csv');

    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility (Japanese characters support)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Headers
    fputcsv($output, ['ID', 'ユーザー名', '日付', 'カテゴリ', '金額', '説明', '対象月']);

    // CSV Data
    foreach ($expenses as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error during export']);
}
