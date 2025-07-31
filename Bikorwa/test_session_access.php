<?php
// Test script to verify session access for gestionnaire role

// Include necessary files
require_once './includes/session.php';
require_once './includes/init.php';
require_once './src/config/database.php';

// Test current session state
echo "<h2>Session Test Results</h2>";

// Check if session manager is available
global $sessionManager;
if (isset($sessionManager)) {
    echo "<p>✓ Session Manager initialized</p>";
    
    // Check login status
    $isLoggedIn = $sessionManager->isLoggedIn();
    echo "<p>Logged in: " . ($isLoggedIn ? 'Yes' : 'No') . "</p>";
    
    if ($isLoggedIn) {
        $userRole = $sessionManager->getUserRole();
        $userId = $sessionManager->getUserId();
        echo "<p>User ID: $userId</p>";
        echo "<p>Role: $userRole</p>";
        
        // Test role check
        $hasGestionnaireRole = ($userRole === 'gestionnaire');
        echo "<p>Has 'gestionnaire' role: " . ($hasGestionnaireRole ? 'Yes' : 'No') . "</p>";
        
        // Test direct session access
        echo "<h3>Direct Session Variables:</h3>";
        echo "<pre>";
        echo "\$_SESSION['role']: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
        echo "\$_SESSION['user_id']: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
        echo "\$_SESSION['logged_in']: " . ($_SESSION['logged_in'] ?? 'NOT SET') . "\n";
        echo "</pre>";
    }
} else {
    echo "<p>✗ Session Manager not available</p>";
}

// Test database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
    echo "<p>✓ Database connection successful</p>";
    
    // Check if users exist
    $stmt = $conn->query("SELECT id, username, role FROM utilisateurs LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Users:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test links
echo "<h3>Test Links:</h3>";
echo "<a href='src/views/employes/utilisateurs.php'>Test utilisateurs.php access</a><br>";
echo "<a href='src/views/auth/login.php'>Go to Login</a><br>";
echo "<a href='src/views/dashboard/index.php'>Go to Dashboard</a>";
?>
