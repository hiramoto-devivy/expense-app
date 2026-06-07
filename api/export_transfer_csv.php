<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'jwt.php';

$user = auth_middleware();
if (!isset($user['role']) || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$data = $input['data'] ?? [];

// Clear any previous output (warnings, etc)
if (ob_get_length()) ob_clean();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=export.csv');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://temp', 'r+');

// CSV Headers as requested
fputcsv($output, ['振込コード', '振込日', '銀行コード', '支店コード', '種目', '口座番号', '名義人', '振込金額']);

foreach ($data as $row) {
    // Format transfer_date to MMDD (taking last 4 chars if YYYYMMDD or parsing YYYY-MM-DD)
    $tDate = $row['transfer_date'] ?? '';
    $tDate = str_replace('-', '', $tDate); // Remove dashes if any
    if (strlen($tDate) >= 4) {
        $tDate = substr($tDate, -4); // Get MMDD
    }
    
    // Map account_type to codes if not already codes
    $aType = $row['account_type'] ?? '';
    if ($aType === '普通') $aType = '1';
    elseif ($aType === '当座') $aType = '2';

    fputcsv($output, [
        $row['transfer_code'] ?? '',
        $tDate,
        $row['bank_code'] ?? '',
        $row['branch_code'] ?? '',
        $aType,
        $row['account_number'] ?? '',
        $row['account_holder'] ?? '',
        $row['amount'] ?? 0
    ]);
}

rewind($output);
fpassthru($output);
fclose($output);
exit;
