<?php
/**
 * Simple login test script
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../../includes/session.php';

echo "<html><head><title>Login Test - KUBIKOTI BAR</title></head><body>";
echo "<h1>Login Test</h1>";

// Process login test
if (isset($_POST['test_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    echo "<h2>Testing Login Process</h2>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
    echo "<p><strong>Password:</strong> " . str_repeat('*', strlen($password)) . "</p>";
    
    try {
        // Initialize database connection
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn instanceof PDO) {
            echo "<p style='color: red;'>❌ Database connection failed</p>";
        } else {
            echo "<p style='color: green;'>✅ Database connection successful</p>";
            
            // Test database connection
            try {
                $conn->query('SELECT 1');
                echo "<p style='color: green;'>✅ Database query test successful</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Database query test failed: " . $e->getMessage() . "</p>";
            }
            
            // Search for user
            $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<p style='color: blue;'>👤 User found:</p>";
                echo "<ul>";
                echo "<li>ID: " . $user['id'] . "</li>";
                echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
                echo "<li>Name: " . htmlspecialchars($user['nom']) . "</li>";
                echo "<li>Role: " . htmlspecialchars($user['role']) . "</li>";
                echo "<li>Active: " . ($user['actif'] ? 'Yes' : 'No') . "</li>";
                echo "</ul>";
                
                if (!$user['actif']) {
                    echo "<p style='color: red;'>❌ User account is inactive</p>";
                } else {
                    // Test password verification
                    if (password_verify($password, $user['password'])) {
                        echo "<p style='color: green;'>✅ Password verification successful</p>";
                        
                        // Test session creation
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['nom'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_active'] = $user['actif'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        
                        echo "<p style='color: green;'>✅ Session variables set successfully</p>";
                        echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
                        
                        // Display session variables
                        echo "<h3>Session Variables:</h3>";
                        echo "<ul>";
                        foreach ($_SESSION as $key => $value) {
                            if (strpos($key, 'user') !== false || $key === 'logged_in' || $key === 'login_time') {
                                echo "<li><strong>$key:</strong> " . htmlspecialchars($value) . "</li>";
                            }
                        }
                        echo "</ul>";
                        
                        // Test redirect URL
                        $redirect = BASE_URL . '/src/views/dashboard/index.php';
                        if ($user['role'] === 'receptionniste') {
                            $redirect = BASE_URL . '/src/views/dashboard/receptionniste.php';
                        }
                        
                        echo "<p><strong>Redirect URL:</strong> <a href='$redirect'>$redirect</a></p>";
                        echo "<p style='color: green;'>🎉 Login test completed successfully!</p>";
                        
                    } else {
                        echo "<p style='color: red;'>❌ Password verification failed</p>";
                        echo "<p>The provided password does not match the stored hash.</p>";
                    }
                }
            } else {
                echo "<p style='color: red;'>❌ User not found with username: " . htmlspecialchars($username) . "</p>";
                
                // Show available users
                $stmt = $conn->query("SELECT username, nom, actif FROM users ORDER BY username");
                $users = $stmt->fetchAll();
                
                if (count($users) > 0) {
                    echo "<p><strong>Available users:</strong></p>";
                    echo "<ul>";
                    foreach ($users as $u) {
                        $status = $u['actif'] ? '✅' : '❌';
                        echo "<li>$status " . htmlspecialchars($u['username']) . " (" . htmlspecialchars($u['nom']) . ")</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>No users found in database. Please run the user verification script first.</p>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// Login form
echo "<h2>Test Login Credentials</h2>";
echo "<form method='post'>";
echo "<p>Username: <input type='text' name='username' value='admin' required></p>";
echo "<p>Password: <input type='password' name='password' value='admin123' required></p>";
echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
echo "</form>";

echo "<hr>";
echo "<p><a href='verify_users.php'>User Verification</a> | <a href='login.php'>Login Page</a></p>";
echo "</body></html>";
?>
