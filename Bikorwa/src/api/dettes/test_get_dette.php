<?php
// Test script for get_dette.php API endpoint
// This will help debug the edit functionality issue

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

echo "<h2>Testing get_dette.php API</h2>";

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<p style='color: red;'>Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>Database connection successful</p>";

// Initialize authentication
$auth = new Auth($conn);

// Check session status
echo "<h3>Session Information:</h3>";
echo "<pre>";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
echo "Is Logged In: " . ($auth->isLoggedIn() ? 'Yes' : 'No') . "\n";
echo "</pre>";

// Get first debt ID for testing
$test_query = "SELECT id FROM dettes LIMIT 1";
$test_stmt = $conn->prepare($test_query);
$test_stmt->execute();
$test_debt = $test_stmt->fetch(PDO::FETCH_ASSOC);

if ($test_debt) {
    $test_id = $test_debt['id'];
    echo "<h3>Testing with Debt ID: $test_id</h3>";
    
    // Simulate the API call
    $_GET['id'] = $test_id;
    
    echo "<p>Simulating API call to get_dette.php with ID: $test_id</p>";
    echo "<p><a href='get_dette.php?id=$test_id' target='_blank'>Click here to test API directly</a></p>";
    
} else {
    echo "<p style='color: orange;'>No debts found in database for testing</p>";
}

// Check if clients exist
$clients_query = "SELECT COUNT(*) as count FROM clients";
$clients_stmt = $conn->prepare($clients_query);
$clients_stmt->execute();
$clients_count = $clients_stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Database Status:</h3>";
echo "<p>Clients count: " . $clients_count['count'] . "</p>";

$debts_query = "SELECT COUNT(*) as count FROM dettes";
$debts_stmt = $conn->prepare($debts_query);
$debts_stmt->execute();
$debts_count = $debts_stmt->fetch(PDO::FETCH_ASSOC);

echo "<p>Debts count: " . $debts_count['count'] . "</p>";
?>
