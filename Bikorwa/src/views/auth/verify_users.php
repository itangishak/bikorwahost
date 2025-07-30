<?php
/**
 * Script to verify users and create default admin if needed
 * KUBIKOTI BAR
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

echo "<html><head><title>User Verification - KUBIKOTI BAR</title></head><body>";
echo "<h1>User Verification and Setup</h1>";

try {
    // Test database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if users table exists
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            echo "<p style='color: red;'>❌ Users table does not exist!</p>";
            echo "<p>Please run the database creation script first.</p>";
            exit;
        }
        echo "<p style='color: green;'>✅ Users table exists</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error checking users table: " . $e->getMessage() . "</p>";
        exit;
    }
    
    // Count existing users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $userCount = $result['count'];
    
    echo "<p>👥 Found $userCount user(s) in the database</p>";
    
    // List existing users
    if ($userCount > 0) {
        echo "<h2>Existing Users:</h2>";
        $stmt = $conn->query("SELECT id, username, nom, role, actif, date_creation FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th><th>Created</th><th>Actions</th></tr>";
        
        foreach ($users as $user) {
            $activeStatus = $user['actif'] ? '✅ Active' : '❌ Inactive';
            $activeColor = $user['actif'] ? 'green' : 'red';
            
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td style='color: $activeColor;'>$activeStatus</td>";
            echo "<td>" . $user['date_creation'] . "</td>";
            echo "<td>";
            if (!$user['actif']) {
                echo "<a href='?activate=" . $user['id'] . "' style='color: green;'>Activate</a>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Handle user activation
    if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
        $userId = (int)$_GET['activate'];
        $stmt = $conn->prepare("UPDATE users SET actif = 1 WHERE id = ?");
        if ($stmt->execute([$userId])) {
            echo "<p style='color: green;'>✅ User activated successfully!</p>";
            echo "<script>setTimeout(function(){ window.location.href = 'verify_users.php'; }, 2000);</script>";
        } else {
            echo "<p style='color: red;'>❌ Failed to activate user</p>";
        }
    }
    
    // Create default admin if no users exist
    if ($userCount == 0) {
        echo "<h2>Creating Default Admin User</h2>";
        
        $username = 'admin';
        $password = 'admin123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $nom = 'Administrator';
        $role = 'gestionnaire';
        $email = 'admin@kubikoti.com';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, nom, role, email, actif) VALUES (?, ?, ?, ?, ?, 1)");
        
        if ($stmt->execute([$username, $hashedPassword, $nom, $role, $email])) {
            echo "<p style='color: green;'>✅ Default admin user created successfully!</p>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
            echo "<p style='color: orange;'>⚠️ Please change this password after first login!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create default admin user</p>";
        }
    }
    
    // Test password verification for existing users
    if ($userCount > 0) {
        echo "<h2>Password Verification Test</h2>";
        echo "<form method='post'>";
        echo "<p>Test login credentials:</p>";
        echo "<p>Username: <input type='text' name='test_username' value='admin' required></p>";
        echo "<p>Password: <input type='password' name='test_password' value='admin123' required></p>";
        echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
        echo "</form>";
        
        if (isset($_POST['test_login'])) {
            $testUsername = $_POST['test_username'];
            $testPassword = $_POST['test_password'];
            
            $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = ? AND actif = 1");
            $stmt->execute([$testUsername]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p style='color: blue;'>🔍 User found: " . htmlspecialchars($user['nom']) . " (" . $user['role'] . ")</p>";
                
                if (password_verify($testPassword, $user['password'])) {
                    echo "<p style='color: green;'>✅ Password verification successful!</p>";
                    echo "<p>Login should work with these credentials.</p>";
                } else {
                    echo "<p style='color: red;'>❌ Password verification failed!</p>";
                    echo "<p>The password hash in database doesn't match the provided password.</p>";
                    
                    // Offer to reset password
                    echo "<form method='post' style='margin-top: 10px;'>";
                    echo "<input type='hidden' name='reset_user' value='" . $user['id'] . "'>";
                    echo "<input type='hidden' name='reset_password' value='$testPassword'>";
                    echo "<button type='submit' name='do_reset' style='background: orange; color: white;'>Reset Password for " . htmlspecialchars($user['username']) . "</button>";
                    echo "</form>";
                }
            } else {
                echo "<p style='color: red;'>❌ User not found or inactive</p>";
            }
        }
        
        // Handle password reset
        if (isset($_POST['do_reset'])) {
            $resetUserId = (int)$_POST['reset_user'];
            $newPassword = $_POST['reset_password'];
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $resetUserId])) {
                echo "<p style='color: green;'>✅ Password reset successfully!</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to reset password</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>← Back to Login</a></p>";
echo "</body></html>";
?>
