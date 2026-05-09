<?php
$dbPath = __DIR__ . '/app.db';
$isNew = !file_exists($dbPath);

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($isNew) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS Users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user'
            )
        ");

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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
