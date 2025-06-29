<?php
/**
 * Simple test to check login functionality
 */

// Start session
session_start();

// Include configuration
require_once __DIR__ . '/src/config/config.php';
require_once __DIR__ . '/src/config/database.php';

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn instanceof PDO) {
        throw new Exception('Failed to get database connection');
    }
    
    // Test credentials
    $test_username = 'admin';
    $test_password = 'password';
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username");
    $stmt->execute(['username' => $test_username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<h2>User Found:</h2>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Name: " . $user['nom'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Active: " . ($user['actif'] ? 'Yes' : 'No') . "\n";
        echo "Password Hash: " . substr($user['password'], 0, 20) . "...\n";
        echo "</pre>";
        
        // Test password verification
        $password_valid = password_verify($test_password, $user['password']);
        echo "<h3>Password Test:</h3>";
        echo "Testing password '" . $test_password . "': " . ($password_valid ? 'VALID' : 'INVALID') . "<br>";
        
        if ($password_valid) {
            // Simulate login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['nom'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_active'] = $user['actif'];
            $_SESSION['logged_in'] = true;
            
            echo "<h3>Session Data Set:</h3>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            
            echo "<br><a href='src/views/dashboard/index.php' class='btn btn-primary'>Go to Dashboard</a>";
        }
    } else {
        echo "<h2>No user found with username: " . $test_username . "</h2>";
        
        // Show all users
        $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users LIMIT 5");
        $stmt->execute();
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Available Users:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th></tr>";
        foreach ($all_users as $u) {
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
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
.btn { padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
</style>
