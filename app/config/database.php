<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

$dbHost = (string) env('DB_HOST', '127.0.0.1');
$dbPort = (string) env('DB_PORT', '3306');
$dbName = (string) env('DB_NAME', 'victory_mmls');
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', '');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    if ((bool) env('APP_DEBUG', false)) {
        exit(
            'Database connection failed: ' .
            htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        );
    }

    exit('Database connection failed.');
}