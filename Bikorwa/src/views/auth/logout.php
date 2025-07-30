<?php
session_start();

// Include config for BASE_URL
require_once __DIR__ . '/../../../src/config/config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . BASE_URL . '/src/views/auth/login.php');
exit();
?>