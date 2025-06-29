<?php
/**
 * Simple authentication check for protected pages
 * This file should be included at the top of all protected pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Save the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Check if user account is active
if (isset($_SESSION['user_active']) && $_SESSION['user_active'] !== true) {
    // Log them out
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Define user access level constants for easier permission checking
define('IS_ADMIN', isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'gestionnaire');
define('IS_STAFF', isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'receptionniste');

// Update last activity timestamp to keep session alive
$_SESSION['last_activity'] = time();
