<?php
// Direct test of the get_produits functionality in index.php
session_start();

// Set up session for testing
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'gestionnaire';
$_SESSION['user_id'] = 1;

// Set GET parameters to simulate the AJAX request
$_GET['action'] = 'get_produits';
$_GET['with_stock'] = 'true';
$_GET['page'] = '1';

echo "<h2>Direct API Test</h2>";
echo "<p>Testing get_produits action in index.php...</p>";

// Capture the output
ob_start();

// Include the index.php file which should handle the get_produits action
try {
    include './src/views/ventes/index.php';
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$output = ob_get_clean();

echo "<h3>API Response:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($output);
echo "</pre>";

// Also test if the response is valid JSON
echo "<h3>JSON Validation:</h3>";
$json_data = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color: green;'>✓ Valid JSON response</p>";
    echo "<p>Success: " . ($json_data['success'] ? 'true' : 'false') . "</p>";
    if (isset($json_data['produits'])) {
        echo "<p>Products count: " . count($json_data['produits']) . "</p>";
    }
    if (isset($json_data['message'])) {
        echo "<p>Message: " . $json_data['message'] . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Invalid JSON response</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
}
?>
