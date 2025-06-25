<?php
// Simple test script for AJAX
// This will return pure JSON with no PHP errors or warnings to interfere

// Set the content type to JSON
header('Content-Type: application/json');

// Turn off error display
ini_set('display_errors', 0);
error_reporting(0);

// Return a simple success response
echo json_encode([
    'success' => true,
    'message' => 'Test AJAX call successful',
    'time' => date('Y-m-d H:i:s'),
    'post_data' => $_POST,
    'get_data' => $_GET
]);
