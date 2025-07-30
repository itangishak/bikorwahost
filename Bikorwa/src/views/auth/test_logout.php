<?php
session_start();

echo "<h2>Logout Test</h2>";
echo "<p>Current session status: " . session_status() . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session data:</p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

if (isset($_SESSION['logged_in'])) {
    echo "<p>User is logged in as: " . ($_SESSION['username'] ?? 'Unknown') . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Unknown') . "</p>";
} else {
    echo "<p>No active session found</p>";
}

echo "<hr>";
echo "<a href='logout.php'>Test Logout</a><br>";
echo "<a href='login.php'>Go to Login</a>";
?>
