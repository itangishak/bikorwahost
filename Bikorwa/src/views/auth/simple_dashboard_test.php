<?php
/**
 * Simple Dashboard Test - Bypass Complex Session Manager
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start basic PHP session
session_start();

echo "<html><head><title>Simple Dashboard Test - KUBIKOTI BAR</title></head><body>";
echo "<h1>Simple Dashboard Test</h1>";

echo "<h2>Session Check</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

echo "<h3>Raw Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Simple Authentication Check:</h3>";

// Simple authentication check without session manager
$isAuthenticated = false;
$authMessage = "";

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if (isset($_SESSION['logged_in']) && ($_SESSION['logged_in'] === true || $_SESSION['logged_in'] === 'true' || $_SESSION['logged_in'] === 1 || $_SESSION['logged_in'] === '1')) {
        $isAuthenticated = true;
        $authMessage = "✅ User is authenticated";
    } else {
        $authMessage = "❌ User ID exists but logged_in flag is: " . var_export($_SESSION['logged_in'], true);
    }
} else {
    $authMessage = "❌ No user_id in session";
}

echo "<p style='color: " . ($isAuthenticated ? 'green' : 'red') . ";'>$authMessage</p>";

if ($isAuthenticated) {
    echo "<h2>User Information</h2>";
    echo "<p><strong>User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "</p>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($_SESSION['username'] ?? 'N/A') . "</p>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($_SESSION['user_name'] ?? 'N/A') . "</p>";
    echo "<p><strong>Role:</strong> " . htmlspecialchars($_SESSION['user_role'] ?? 'N/A') . "</p>";
    echo "<p><strong>Login Time:</strong> " . date('Y-m-d H:i:s', $_SESSION['login_time'] ?? 0) . "</p>";
    
    echo "<h2>Dashboard Content</h2>";
    echo "<p>🎉 Welcome to the dashboard! You are successfully logged in.</p>";
    echo "<p>This proves that the session data is correct and the issue is with the session manager logic.</p>";
    
} else {
    echo "<h2>Not Authenticated</h2>";
    echo "<p>You need to log in first.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
}

echo "<h2>Actions</h2>";
echo "<p><a href='debug_session_flow.php'>Debug Session Flow</a></p>";
echo "<p><a href='login.php'>Login Page</a></p>";
echo "<p><a href='../dashboard/index.php'>Try Real Dashboard</a></p>";

// Logout option
if (isset($_POST['logout'])) {
    session_destroy();
    echo "<p style='color: orange;'>Session destroyed. <a href='simple_dashboard_test.php'>Refresh</a></p>";
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='logout'>Logout (Destroy Session)</button>";
    echo "</form>";
}

echo "</body></html>";
?>
