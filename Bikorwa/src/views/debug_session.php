<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Session Debug Information</h1>
    <h2>Session Status: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></h2>
    
    <h3>Session Variables:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h3>Cookies:</h3>
    <pre><?php print_r($_COOKIE); ?></pre>
    
    <p><a href="/login.php">Go to Login</a></p>
</body>
</html>
