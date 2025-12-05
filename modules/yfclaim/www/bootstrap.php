<?php
/**
 * YFClaim Module Bootstrap
 * Initializes database connection and autoloader for YFClaim module
 */

// Load autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

// Load database config
$dbConfig = require __DIR__ . '/../../../config/database.php';

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    error_log('YFClaim database error: ' . $e->getMessage());
    die('Database connection failed');
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
