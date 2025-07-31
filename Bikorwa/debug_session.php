<?php
// Debug script to check session state and authentication
session_start();

echo "<h2>Session Debug Information</h2>";
echo "<pre>";

echo "=== SESSION VARIABLES ===\n";
print_r($_SESSION);

echo "\n=== SESSION STATUS ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";

echo "\n=== AUTHENTICATION CHECK ===\n";
echo "isset(\$_SESSION['logged_in']): " . (isset($_SESSION['logged_in']) ? 'true' : 'false') . "\n";
echo "\$_SESSION['logged_in'] value: " . (isset($_SESSION['logged_in']) ? var_export($_SESSION['logged_in'], true) : 'not set') . "\n";
echo "isset(\$_SESSION['role']): " . (isset($_SESSION['role']) ? 'true' : 'false') . "\n";
echo "\$_SESSION['role'] value: " . (isset($_SESSION['role']) ? var_export($_SESSION['role'], true) : 'not set') . "\n";
echo "isset(\$_SESSION['user_id']): " . (isset($_SESSION['user_id']) ? 'true' : 'false') . "\n";
echo "\$_SESSION['user_id'] value: " . (isset($_SESSION['user_id']) ? var_export($_SESSION['user_id'], true) : 'not set') . "\n";

echo "\n=== MANUAL AUTH CHECK ===\n";
$isLoggedIn = isset($_SESSION['logged_in']) && ($_SESSION['logged_in'] === true || $_SESSION['logged_in'] === 'true' || $_SESSION['logged_in'] === 1 || $_SESSION['logged_in'] === '1');
echo "Manual isLoggedIn check: " . ($isLoggedIn ? 'true' : 'false') . "\n";

$hasGestionnaireRole = isset($_SESSION['role']) && $_SESSION['role'] === 'gestionnaire';
echo "Manual gestionnaire role check: " . ($hasGestionnaireRole ? 'true' : 'false') . "\n";

echo "\n=== COOKIES ===\n";
print_r($_COOKIE);

echo "\n=== SERVER INFO ===\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";

echo "</pre>";

// Test if we can access utilisateurs.php
$utilisateurs_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/Bikorwa/src/views/employes/utilisateurs.php";
echo "<p><a href='{$utilisateurs_url}' target='_blank'>Test utilisateurs.php access</a></p>";

echo "<p><a href='javascript:history.back()'>Go back</a></p>";
?>
