<?php
// ---- Edit these if your setup is different --------------------------
const DB_HOST = 'localhost';
const DB_NAME = 'momocycle1';
const DB_USER = 'root';      // XAMPP default. On AWS use the app user you created.
const DB_PASS = 'admin123';          // XAMPP default is an empty password.
const BASE_URL = '/momocycle-ug';   // folder name inside htdocs
// ----------------------------------------------------------------------

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check config/db.php and that MySQL is running.');
}
