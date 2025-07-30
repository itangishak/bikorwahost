<?php
/**
 * Initialization script for all view files
 * This ensures consistent session management across the application
 */

// Start output buffering with callback to clean headers
ob_start(function($buffer) {
    // Remove any whitespace/newlines that could affect headers
    return preg_replace('/^\s+/', '', $buffer);
}, 1);

// Include bootstrap
require_once __DIR__ . '/bootstrap.php';

// Global session manager instance
global $sessionManager;

// Helper functions for quick access to session data
function getCurrentUser() {
    global $sessionManager;
    return [
        'id' => $sessionManager->getUserId(),
        'username' => $sessionManager->getUsername(),
        'name' => $sessionManager->getFullName(),
        'role' => $sessionManager->getUserRole(),
        'active' => $sessionManager->isUserActive(),
        'logged_in' => $sessionManager->isLoggedIn()
    ];
}

function requireAuth() {
    global $sessionManager;
    
    // Clean output buffer completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!$sessionManager->isLoggedIn()) {
        header('Location: ' . BASE_URL . '/src/views/auth/login.php');
        exit;
    }
}

function requireRole($role) {
    global $sessionManager;
    
    // Clean output buffer completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    requireAuth();
    if ($sessionManager->getUserRole() !== $role) {
        header('Location: ' . BASE_URL . '/src/views/auth/login.php');
        exit;
    }
}

function requireManager() {
    requireRole('gestionnaire');
}

function requireReceptionist() {
    requireRole('receptionniste');
}
