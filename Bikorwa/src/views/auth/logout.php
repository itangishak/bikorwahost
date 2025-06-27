<?php
require_once __DIR__ . '/../../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Get the login URL
$loginUrl = BASE_URL . '/src/views/auth/login.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Déconnexion</title>
    <script>
        // Clear the session ID from session storage
        sessionStorage.removeItem('sessionId');
        
        // Redirect to the login page
        window.location.href = "<?php echo $loginUrl; ?>";
    </script>
</head>
<body>
    <p>Déconnexion en cours...</p>
</body>
</html>