<?php
/**
 * Authentication check for protected pages
 * This file should be included at the top of all protected pages
 */

// Use the centralized session manager
require_once __DIR__ . '/session.php';

// Redirect to login if the user is not authenticated
if (!$sessionManager->isLoggedIn()) {
    // Preserve requested URL for post-login redirect
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../auth/login.php');
    exit;
}

// Ensure the account is active
if (!$sessionManager->isUserActive()) {
    $sessionManager->destroySession();
    header('Location: ../auth/login.php');
    exit;
}

// Define constants for convenience
define('IS_ADMIN', $sessionManager->isManager());
define('IS_STAFF', $sessionManager->isReceptionist());
