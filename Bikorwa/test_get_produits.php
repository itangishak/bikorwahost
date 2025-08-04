<?php
// Test the get_produits API endpoint
session_start();

// Set test session data
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'gestionnaire';
$_SESSION['user_id'] = 1;

// Include the API file
require_once './src/api/produits/get_produits.php';
?>
