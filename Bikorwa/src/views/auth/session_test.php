<?php
session_start();

// Test session persistence
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}

// Test BASE_URL
require_once __DIR__ . '/../../../src/config/config.php';

echo "<h2>Session Test</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Counter: " . $_SESSION['test_counter'] . "</p>";
echo "<p>BASE_URL: " . BASE_URL . "</p>";

echo "<h3>All Session Variables:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<p><a href='session_test.php'>Refresh Page</a></p>";
echo "<p><a href='session_test.php?clear=1'>Clear Session</a></p>";

if (isset($_GET['clear'])) {
    session_destroy();
    echo "<p>Session cleared!</p>";
}
?>
