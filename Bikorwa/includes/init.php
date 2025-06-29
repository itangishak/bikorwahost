<?php
/**
 * Initialization script for all view files
 * This ensures consistent session management across the application
 */

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
    if (!$sessionManager->isLoggedIn()) {
        header('Location: ' . BASE_URL . '/src/views/auth/login.php');
        exit;
    }
}

function requireRole($role) {
    global $sessionManager;
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
