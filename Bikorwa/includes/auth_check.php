<?php
/**
 * Authentication check for protected pages
 * This file should be included at the top of all protected pages
 */

// Start session at the beginning of each protected page
require_once __DIR__ . '/session_manager.php';
$sessionManager = SessionManager::getInstance();
$sessionManager->startSession();

// The following block created a mock session for development purposes.
// It granted "gestionnaire" privileges when no session existed, which caused
// privilege leakage across users sharing the same browser. This code has been
// removed to ensure that permissions are strictly tied to the authenticated
// user's session.

// Check if user is logged in using SessionManager
if (!$sessionManager->isLoggedIn()) {
    // Save the requested URL for redirection after login
    $sessionManager->set('redirect_after_login', $_SERVER['REQUEST_URI']);
    
    // Set flash message
    if (function_exists('setFlashMessage')) {
        setFlashMessage('warning', 'Veuillez vous connecter pour accéder à cette page.');
    }
    
    // Redirect to login page - using a relative path to avoid issues
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Check if user account is active
if (!$sessionManager->isUserActive()) {
    // Log them out using SessionManager
    $sessionManager->logoutUser();
    
    // Start new session for flash message
    $sessionManager->startSession();
    
    // Set flash message
    if (function_exists('setFlashMessage')) {
        setFlashMessage('danger', 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.');
    }
    
    // Redirect to login page - using a relative path to avoid issues
    header('Location: ../../src/views/auth/login.php');
    exit;
}

// Define user access level constants for easier permission checking
define('IS_ADMIN', $sessionManager->isManager());
define('IS_STAFF', $sessionManager->isReceptionist());

// Session activity is automatically updated by SessionManager
