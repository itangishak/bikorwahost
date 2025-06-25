<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Display errors for debugging purposes

// Set JSON content type
header('Content-Type: application/json');

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../../../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Function to log debug information
function debugLog($message) {
    global $logDir;
    $logFile = $logDir . '/ajax_debug.log';
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . "\n", 3, $logFile);
}

// Log the request information
debugLog('Request method: ' . $_SERVER['REQUEST_METHOD']);
debugLog('Request URI: ' . $_SERVER['REQUEST_URI']);
debugLog('POST data: ' . print_r($_POST, true));

// Initialize response
$response = [
    'success' => true,
    'message' => 'Debug test successful',
    'action' => $_POST['action'] ?? 'none',
    'id' => $_POST['id'] ?? 'none',
    'server_time' => date('Y-m-d H:i:s'),
    'post_data' => $_POST
];

// Log the response we're about to send
debugLog('Response: ' . json_encode($response));

// Return the response
echo json_encode($response);
