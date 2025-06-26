<?php
require_once __DIR__ . '/../../config/config.php';

// Debug - check if session exists
if (isset($_SESSION)) {
    error_log('Session exists before logout: ' . print_r($_SESSION, true));
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

error_log('Redirecting to: ' . BASE_URL . '/src/views/auth/login.php');

// Redirect to login page
header('Location: ' . BASE_URL . '/src/views/auth/login.php');
exit;
?>
