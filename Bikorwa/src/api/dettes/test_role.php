<?php
// Test script to check current user's role and session data
// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

header('Content-Type: application/json');

$response = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? null,
    'role_raw' => $_SESSION['role'] ?? 'NOT_SET',
    'role_lowercase' => strtolower($_SESSION['role'] ?? ''),
    'is_gestionnaire_raw' => ($_SESSION['role'] ?? '') === 'gestionnaire',
    'is_gestionnaire_lower' => strtolower($_SESSION['role'] ?? '') === 'gestionnaire',
    'user_id' => $_SESSION['user_id'] ?? 'NOT_SET',
    'logged_in' => $_SESSION['logged_in'] ?? 'NOT_SET'
];

// Initialize database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        $auth = new Auth($conn);
        $response['auth_logged_in'] = $auth->isLoggedIn();
        $response['auth_has_access_dettes'] = $auth->hasAccess('dettes');
    } else {
        $response['database_error'] = 'Failed to connect to database';
    }
} catch (Exception $e) {
    $response['database_error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
