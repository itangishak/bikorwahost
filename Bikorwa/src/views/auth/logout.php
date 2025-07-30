<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . BASE_URL . '/src/views/auth/login.php');
exit;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Déconnexion</title>
</head>
<body>
    <p>Déconnexion en cours...</p>
</body>
</html>