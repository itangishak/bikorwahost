<?php
session_start();

echo "<h2>Session Debug Information</h2>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

echo "<h3>All Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['logged_in'])) {
    echo "<h3>Login Status:</h3>";
    echo "<p>Logged in: " . ($_SESSION['logged_in'] ? 'Yes' : 'No') . "</p>";
    echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
    echo "<p>Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";
    echo "<p>Name: " . ($_SESSION['name'] ?? 'Not set') . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "<p>Last Activity: " . ($_SESSION['last_activity'] ?? 'Not set') . "</p>";
} else {
    echo "<p><strong>No active session found</strong></p>";
}

echo "<hr>";
echo "<a href='login.php'>Go to Login</a> | ";
echo "<a href='../dashboard/index.php'>Go to Dashboard</a> | ";
echo "<a href='logout.php'>Logout</a>";
?>
