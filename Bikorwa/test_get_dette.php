<?php
// Simple test for get_dette.php API
session_start();

// Simulate a logged-in session (adjust these values based on your actual session structure)
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'gestionnaire';

// Test the API
$test_id = 1; // Change this to an actual debt ID in your database
$url = "http://localhost/src/api/dettes/get_dette.php?id=" . $test_id;

echo "<h2>Testing get_dette.php API</h2>";
echo "<p>URL: $url</p>";

// Use cURL to test the API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>Response (HTTP $httpCode):</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode JSON
$data = json_decode($response, true);
if ($data) {
    echo "<h3>Parsed JSON:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
} else {
    echo "<p>Failed to parse JSON response</p>";
}
?>
