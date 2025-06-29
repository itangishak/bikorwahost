<?php
require_once __DIR__ . '/../../config/config.php';

// Use SessionManager for logout
require_once __DIR__ . '/../../../includes/session_manager.php';
$sessionManager = SessionManager::getInstance();
$sessionManager->startSession();

// Logout user using SessionManager
$sessionManager->logoutUser();

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