<?php
/**
 * Comprehensive Session Flow Debug Script
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the PHP session before any output is sent
session_start();

echo "<html><head><title>Session Flow Debug - KUBIKOTI BAR</title></head><body>";
echo "<h1>Session Flow Debug</h1>";

echo "<h2>Step 1: Basic PHP Session</h2>";
echo "<p>✅ PHP session started</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . " (1=disabled, 2=active, 3=none)</p>";

echo "<h2>Step 2: Include Session Manager</h2>";
try {
    require_once __DIR__ . '/../../../includes/session.php';
    echo "<p>✅ Session manager included</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error including session manager: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 3: Check Global SessionManager</h2>";
global $sessionManager;
if (isset($sessionManager)) {
    echo "<p>✅ Global sessionManager exists</p>";
    echo "<p><strong>Class:</strong> " . get_class($sessionManager) . "</p>";
    
    // Test individual methods
    echo "<h3>Testing SessionManager Methods:</h3>";
    
    try {
        $hasUserId = $sessionManager->has('user_id');
        echo "<p><strong>has('user_id'):</strong> " . ($hasUserId ? 'true' : 'false') . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error with has('user_id'): " . $e->getMessage() . "</p>";
    }
    
    try {
        $userId = $sessionManager->get('user_id');
        echo "<p><strong>get('user_id'):</strong> " . var_export($userId, true) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error with get('user_id'): " . $e->getMessage() . "</p>";
    }
    
    try {
        $loggedIn = $sessionManager->get('logged_in');
        echo "<p><strong>get('logged_in'):</strong> " . var_export($loggedIn, true) . " (type: " . gettype($loggedIn) . ")</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error with get('logged_in'): " . $e->getMessage() . "</p>";
    }
    
    try {
        $isLoggedIn = $sessionManager->isLoggedIn();
        echo "<p><strong>isLoggedIn():</strong> " . ($isLoggedIn ? 'true' : 'false') . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error with isLoggedIn(): " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Global sessionManager not found</p>";
}

echo "<h2>Step 4: Raw Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Step 5: Test Authentication Functions</h2>";

// Test requireAuth function
if (function_exists('requireAuth')) {
    echo "<p>✅ requireAuth function exists</p>";
    
    // Capture any redirect attempts
    ob_start();
    try {
        requireAuth();
        $output = ob_get_contents();
        ob_end_clean();
        echo "<p style='color: green;'>✅ requireAuth() passed - no redirect</p>";
        if (!empty($output)) {
            echo "<p><strong>Output captured:</strong> " . htmlspecialchars($output) . "</p>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red;'>❌ requireAuth() threw exception: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ requireAuth function not found</p>";
}

// Test requireManager function
if (function_exists('requireManager')) {
    echo "<p>✅ requireManager function exists</p>";
    
    // Capture any redirect attempts
    ob_start();
    try {
        requireManager();
        $output = ob_get_contents();
        ob_end_clean();
        echo "<p style='color: green;'>✅ requireManager() passed - no redirect</p>";
        if (!empty($output)) {
            echo "<p><strong>Output captured:</strong> " . htmlspecialchars($output) . "</p>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red;'>❌ requireManager() threw exception: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ requireManager function not found</p>";
}

echo "<h2>Step 6: Simulate Login</h2>";
if (isset($_POST['simulate_login'])) {
    echo "<p>🔄 Simulating login...</p>";
    
    // Set session variables exactly as login_process.php does
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['user_name'] = 'Administrateur';
    $_SESSION['user_role'] = 'gestionnaire';
    $_SESSION['user_active'] = true;
    $_SESSION['logged_in'] = 'true';
    $_SESSION['login_time'] = time();
    
    echo "<p>✅ Session variables set</p>";
    echo "<p><a href='debug_session_flow.php'>Refresh to test</a></p>";
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='simulate_login'>Simulate Login</button>";
    echo "</form>";
}

echo "<h2>Step 7: Test Dashboard Access</h2>";
echo "<p><a href='../dashboard/index.php' target='_blank'>Try Dashboard (opens in new tab)</a></p>";

echo "<h2>Step 8: Clear Session</h2>";
if (isset($_POST['clear_session'])) {
    session_destroy();
    echo "<p>✅ Session cleared</p>";
    echo "<p><a href='debug_session_flow.php'>Refresh</a></p>";
} else {
    echo "<form method='post'>";
    echo "<button type='submit' name='clear_session'>Clear Session</button>";
    echo "</form>";
}

echo "<hr>";
echo "<p><a href='login.php'>Login Page</a> | <a href='test_session.php'>Session Test</a></p>";
echo "</body></html>";
?>
