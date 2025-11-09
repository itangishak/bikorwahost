<?php
/**
 * Database connection configuration
 */

require_once __DIR__ . '/../src/config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo instanceof PDO) {
    $message = 'Connection failed';
    if (function_exists('error_log')) {
        error_log('Database bootstrap failure: ' . ($database->getLastError() ?? 'unknown error'));
    }

    if (php_sapi_name() === 'cli') {
        throw new RuntimeException($message . ': ' . ($database->getLastError() ?? 'unable to reach database'));
    }

    die($message);
}

// Constants
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . $host . '/Bikorwa');
}
if (!defined('CURRENCY')) {
    define('CURRENCY', 'BIF');
}
