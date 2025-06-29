<?php
/**
 * Authentication check for protected pages
 * This file should be included at the top of all protected pages
 */

// Initialize session with proper configuration
require_once __DIR__ . '/session_init.php';

// Check if user is logged in using the helper function
if (!is_user_logged_in()) {
    // Save the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: ../auth/login.php');
    exit;
}

// Check if user account is active
if (isset($_SESSION['user_active']) && $_SESSION['user_active'] !== true) {
    // Log them out
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: ../auth/login.php');
    exit;
}

// Define user access level constants for easier permission checking
define('IS_ADMIN', has_role('gestionnaire'));
define('IS_STAFF', has_role('receptionniste'));
