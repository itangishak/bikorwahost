<?php
/**
 * Simple test file to verify SessionManager works properly
 */

// Include the session manager
require_once __DIR__ . '/includes/session_manager.php';

// Start output buffering to catch any output
ob_start();

try {
    // Test the SessionManager
    $sessionManager = SessionManager::getInstance();
    
    // Test session start
    $started = $sessionManager->startSession();
    
    // Set a test value
    $sessionManager->set('test_key', 'test_value');
    
    // Get the test value
    $testValue = $sessionManager->get('test_key');
    
    // Generate CSRF token
    $csrfToken = $sessionManager->generateCSRFToken();
    
    // Clear output buffer
    ob_end_clean();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'SessionManager working properly',
        'session_started' => $started,
        'test_value' => $testValue,
        'csrf_token' => substr($csrfToken, 0, 10) . '...', // Only show first 10 chars for security
        'session_id' => session_id()
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
