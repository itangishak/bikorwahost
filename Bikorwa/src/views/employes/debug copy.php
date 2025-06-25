<?php
// Debug script for testing AJAX communication
header('Content-Type: application/json');

// Create a log directory if it doesn't exist
$logDir = __DIR__ . '/../../../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log the request data
$log_message = date('Y-m-d H:i:s') . " - Debug Info:\n";
$log_message .= "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log_message .= "QUERY_STRING: " . $_SERVER['QUERY_STRING'] . "\n";
$log_message .= "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
$log_message .= "POST data: " . print_r($_POST, true) . "\n";
$log_message .= "GET data: " . print_r($_GET, true) . "\n";
$log_message .= "HEADERS: " . print_r(getallheaders(), true) . "\n";
$log_message .= "----------------------------------------------------\n";

// Write to log file
error_log($log_message, 3, $logDir . '/ajax_debug.log');

// Return a success response
echo json_encode([
    'success' => true,
    'message' => 'Debug data logged successfully',
    'received' => [
        'post' => $_POST,
        'get' => $_GET,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI']
    ]
]);
