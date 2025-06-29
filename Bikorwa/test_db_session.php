<?php
/**
 * Test database session system
 */

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/includes/session_db_manager.php';

try {
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo instanceof PDO) {
        throw new Exception('Failed to get database connection');
    }
    
    // Create session manager
    $sessionManager = new DatabaseSessionManager($pdo);
    
    // Test credentials
    $test_username = 'admin';
    $test_password = 'admin123';
    
    echo "<h1>Database Session Test</h1>";
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username");
    $stmt->execute(['username' => $test_username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($test_password, $user['password'])) {
        echo "<div style='color: green;'>✓ User found and password verified</div>";
        
        // Test login
        $loginResult = $sessionManager->loginUser($user);
        echo "<div style='color: " . ($loginResult ? 'green' : 'red') . ";'>" . 
             ($loginResult ? '✓' : '✗') . " Login attempt: " . 
             ($loginResult ? 'SUCCESS' : 'FAILED') . "</div>";
        
        if ($loginResult) {
            // Test session check
            $isLoggedIn = $sessionManager->isLoggedIn($user['id']);
            echo "<div style='color: " . ($isLoggedIn ? 'green' : 'red') . ";'>" . 
                 ($isLoggedIn ? '✓' : '✗') . " Session check: " . 
                 ($isLoggedIn ? 'LOGGED IN' : 'NOT LOGGED IN') . "</div>";
            
            // Get current user data
            $currentUser = $sessionManager->getCurrentUser();
            if ($currentUser) {
                echo "<h3>Current User Data:</h3>";
                echo "<pre>" . print_r($currentUser, true) . "</pre>";
            }
            
            // Test role check
            $isManager = $sessionManager->hasRole('gestionnaire', $user['id']);
            echo "<div style='color: " . ($isManager ? 'green' : 'red') . ";'>" . 
                 ($isManager ? '✓' : '✗') . " Role check (gestionnaire): " . 
                 ($isManager ? 'HAS ROLE' : 'NO ROLE') . "</div>";
            
            // Test session exists
            $sessionExists = $sessionManager->sessionExists($user['id']);
            echo "<div style='color: " . ($sessionExists ? 'green' : 'red') . ";'>" . 
                 ($sessionExists ? '✓' : '✗') . " Session exists in DB: " . 
                 ($sessionExists ? 'YES' : 'NO') . "</div>";
            
            // Show all session data
            echo "<h3>All Session Data:</h3>";
            echo "<pre>" . print_r($sessionManager->getAllData(), true) . "</pre>";
            
            // Test logout
            echo "<h3>Testing Logout:</h3>";
            $logoutResult = $sessionManager->logout($user['id']);
            echo "<div style='color: " . ($logoutResult ? 'green' : 'red') . ";'>" . 
                 ($logoutResult ? '✓' : '✗') . " Logout attempt: " . 
                 ($logoutResult ? 'SUCCESS' : 'FAILED') . "</div>";
            
            // Check if session still exists after logout
            $sessionExistsAfterLogout = $sessionManager->sessionExists($user['id']);
            echo "<div style='color: " . (!$sessionExistsAfterLogout ? 'green' : 'red') . ";'>" . 
                 (!$sessionExistsAfterLogout ? '✓' : '✗') . " Session cleaned up: " . 
                 (!$sessionExistsAfterLogout ? 'YES' : 'NO') . "</div>";
        }
        
    } else {
        echo "<div style='color: red;'>✗ User not found or password incorrect</div>";
        
        // Show available users
        $stmt = $pdo->prepare("SELECT id, username, nom, role, actif FROM users LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Available Users:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . $u['id'] . "</td>";
            echo "<td>" . $u['username'] . "</td>";
            echo "<td>" . $u['nom'] . "</td>";
            echo "<td>" . $u['role'] . "</td>";
            echo "<td>" . ($u['actif'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Clean up expired sessions
    $cleanupResult = $sessionManager->cleanupExpiredSessions();
    echo "<br><div style='color: " . ($cleanupResult ? 'green' : 'red') . ";'>" . 
         ($cleanupResult ? '✓' : '✗') . " Cleanup expired sessions: " . 
         ($cleanupResult ? 'SUCCESS' : 'FAILED') . "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    echo "<div style='color: red;'>File: " . $e->getFile() . "</div>";
    echo "<div style='color: red;'>Line: " . $e->getLine() . "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>
