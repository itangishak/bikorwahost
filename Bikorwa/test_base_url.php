<?php
require_once './src/config/config.php';

echo "<h2>BASE_URL Configuration Test</h2>";
echo "<p><strong>Current BASE_URL:</strong> " . BASE_URL . "</p>";
echo "<p><strong>Expected path based on your URL:</strong> /bikorwahost/Bikorwa</p>";

echo "<h3>Server Variables:</h3>";
echo "<ul>";
echo "<li><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "</li>";
echo "<li><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "</li>";
echo "<li><strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "</li>";
echo "<li><strong>DOCUMENT_ROOT:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "</li>";
echo "</ul>";

echo "<h3>Test API URL:</h3>";
$api_url = BASE_URL . '/src/api/produits/get_produits.php';
echo "<p>Constructed API URL: <a href='$api_url' target='_blank'>$api_url</a></p>";

echo "<h3>Correct BASE_URL should probably be:</h3>";
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$correct_base_url = $protocol . $host . '/bikorwahost/Bikorwa';
echo "<p><strong>$correct_base_url</strong></p>";

echo "<h3>Test Correct API URL:</h3>";
$correct_api_url = $correct_base_url . '/src/api/produits/get_produits.php';
echo "<p>Correct API URL: <a href='$correct_api_url' target='_blank'>$correct_api_url</a></p>";
?>
