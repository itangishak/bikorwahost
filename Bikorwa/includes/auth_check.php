<?php
/**
 * Authentication check for protected pages
 * This file should be included at the top of all protected pages
 */

require_once __DIR__ . '/session.php';

// Start session using database-backed handler
startDbSession();

// The following block created a mock session for development purposes.
// It granted "gestionnaire" privileges when no session existed, which caused
// privilege leakage across users sharing the same browser. This code has been
// removed to ensure that permissions are strictly tied to the authenticated
// user's session.

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
    // Save the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Set flash message
    if (function_exists('setFlashMessage')) {
        setFlashMessage('warning', 'Veuillez vous connecter pour accéder à cette page.');
    }
    
    // Redirect to login page - using a relative path to avoid issues
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Check if user account is active
if (isset($_SESSION['user_active']) && $_SESSION['user_active'] !== true) {
    // Log them out
    session_unset();
    session_destroy();
    
    // Start new session for flash message
    startDbSession();
    
    // Set flash message
    if (function_exists('setFlashMessage')) {
        setFlashMessage('danger', 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.');
    }
    
    // Redirect to login page - using a relative path to avoid issues
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Define user access level constants for easier permission checking
define('IS_ADMIN', $_SESSION['user_role'] === 'gestionnaire');
define('IS_STAFF', $_SESSION['user_role'] === 'receptionniste');

// Update last activity timestamp to keep session alive
$_SESSION['last_activity'] = time();
