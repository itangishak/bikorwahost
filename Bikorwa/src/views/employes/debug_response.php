<?php
// Turn on error reporting for debugging only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create log file if needed
$logDir = __DIR__ . '/../../../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/ajax_debug.log';

// Function to log debug information
function debugLog($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}

// Log the request method and headers
debugLog('--- NEW REQUEST ---');
debugLog('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
debugLog('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
debugLog('HTTP_X_REQUESTED_WITH: ' . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'Not set'));
debugLog('POST data: ' . print_r($_POST, true));

// Buffer the output to catch any errors or unexpected output
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Get the buffer contents and clear it
$buffer = ob_get_clean();

// Check if anything was output before our JSON
if (!empty($buffer)) {
    debugLog('UNEXPECTED OUTPUT BEFORE JSON: ' . $buffer);
    
    // Include this unexpected output in our response for debugging
    $response = [
        'success' => false,
        'message' => 'PHP output detected before JSON response',
        'php_output' => $buffer,
        'action' => $_POST['action'] ?? 'none',
        'id' => $_POST['id'] ?? 'none'
    ];
} else {
    $response = [
        'success' => true,
        'message' => 'Debug response test successful',
        'action' => $_POST['action'] ?? 'none',
        'id' => $_POST['id'] ?? 'none',
        'is_ajax' => !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ];
}

// Log the response we're about to send
debugLog('Response: ' . json_encode($response));

// Return the response
echo json_encode($response);
?>
