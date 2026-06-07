<?php
$dbPath = __DIR__ . '/app.db';
$isNew = !file_exists($dbPath);

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            display_name TEXT,
            role TEXT DEFAULT 'user',
            bank_code TEXT,
            branch_code TEXT,
            account_type TEXT,
            account_number TEXT,
            account_holder TEXT
        )
    ");

    // Add columns to existing table if they don't exist
    $columns = ['display_name', 'bank_code', 'branch_code', 'account_type', 'account_number', 'account_holder'];
    foreach ($columns as $column) {
        try {
            $pdo->exec("ALTER TABLE Users ADD COLUMN $column TEXT");
        } catch (PDOException $e) {
            // Ignore if column already exists
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            date TEXT NOT NULL,
            year_month TEXT NOT NULL,
            description TEXT,
            receipt_file_path TEXT,
            FOREIGN KEY (user_id) REFERENCES Users(id),
            FOREIGN KEY (category_id) REFERENCES Categories(id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Closings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            year_month TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            UNIQUE(year_month, user_id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS CodeMaster (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code_group TEXT NOT NULL,
            code_value TEXT NOT NULL,
            display_name TEXT NOT NULL,
            UNIQUE(code_group, code_value)
        )
    ");

    if ($isNew) {
        // Insert default user
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', 'admin', 'admin']);
        $stmt->execute(['user1', 'password', 'user']);

        // Insert default categories
        $categories = ['交通費', '交際費', '消耗品', '出張費'];
        $stmt = $pdo->prepare("INSERT INTO Categories (name) VALUES (?)");
        foreach ($categories as $cat) {
            $stmt->execute([$cat]);
        }
    }

    // Ensure initial codes exist
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM CodeMaster WHERE code_group = 'account_type'");
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtCode = $pdo->prepare("INSERT INTO CodeMaster (code_group, code_value, display_name) VALUES (?, ?, ?)");
        $stmtCode->execute(['account_type', '1', '普通']);
        $stmtCode->execute(['account_type', '2', '当座']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
