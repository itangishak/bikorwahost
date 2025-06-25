<?php
/**
 * Database connection configuration
 */

// Database connection parameters
$host = 'localhost:3306';
$dbname = 'bumadste_bikorwa_shop';
$username = 'bumadste_bikorwa';
$password = 'Bikorwa2024!';
$charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Connect to database
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // If connection fails, display error and terminate
    die('Connection failed: ' . $e->getMessage());
}

// Constants
define('BASE_URL', '/');
define('CURRENCY', 'BIF');
?>
