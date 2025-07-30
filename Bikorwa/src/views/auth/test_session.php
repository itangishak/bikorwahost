<?php
/**
 * Session Test Script
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include session manager
require_once __DIR__ . '/../../../includes/session.php';

echo "<html><head><title>Session Test - KUBIKOTI BAR</title></head><body>";
echo "<h1>Session Test</h1>";

echo "<h2>Session Status</h2>";
echo "<p><strong>Session Started:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

echo "<h2>Session Manager Status</h2>";
global $sessionManager;
if (isset($sessionManager)) {
    echo "<p><strong>SessionManager exists:</strong> Yes</p>";
    echo "<p><strong>Is Logged In:</strong> " . ($sessionManager->isLoggedIn() ? 'Yes' : 'No') . "</p>";
    
    if ($sessionManager->isLoggedIn()) {
        echo "<p><strong>User ID:</strong> " . $sessionManager->getUserId() . "</p>";
        echo "<p><strong>Username:</strong> " . $sessionManager->getUsername() . "</p>";
        echo "<p><strong>User Role:</strong> " . $sessionManager->getUserRole() . "</p>";
        echo "<p><strong>Full Name:</strong> " . $sessionManager->getFullName() . "</p>";
    }
} else {
    echo "<p><strong>SessionManager exists:</strong> No</p>";
}

echo "<h2>Raw Session Variables</h2>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    echo htmlspecialchars($key) . " => " . htmlspecialchars(print_r($value, true)) . "\n";
}
echo "</pre>";

echo "<h2>Authentication Test</h2>";
if (function_exists('requireAuth')) {
    try {
        requireAuth();
        echo "<p style='color: green;'>✅ requireAuth() passed - user is authenticated</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ requireAuth() failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ requireAuth() function not found</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Login Page</a> | <a href='../dashboard/index.php'>Dashboard</a></p>";
echo "</body></html>";
?>
