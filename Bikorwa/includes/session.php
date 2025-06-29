<?php
/**
 * Legacy session.php compatibility shim
 * This file redirects to the new SessionManager for backward compatibility
 */

// Include the new session manager
require_once __DIR__ . '/session_manager.php';

// Ensure global session manager is available
global $sessionManager;
if (!isset($sessionManager)) {
    $sessionManager = SessionManager::getInstance();
    $sessionManager->startSession();
}

// Legacy function compatibility
function startDbSession() {
    global $sessionManager;
    return $sessionManager->startSession();
}

function destroySession() {
    global $sessionManager;
    return $sessionManager->destroySession();
}

function regenerateSessionId($deleteOldSession = true) {
    global $sessionManager;
    return $sessionManager->regenerateSessionId($deleteOldSession);
}
