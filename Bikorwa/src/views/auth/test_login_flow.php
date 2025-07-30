<?php
session_start();
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/../../../src/config/config.php';

echo "<h2>Login Flow Test</h2>";

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    echo "<h3>Testing Login for: " . htmlspecialchars($username) . "</h3>";
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = ? AND actif = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>User found in database:</strong></p>";
        if ($user) {
            echo "<pre>" . print_r($user, true) . "</pre>";
            
            if (password_verify($password, $user['password'])) {
                echo "<p>✅ Password verification: SUCCESS</p>";
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['nom'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                echo "<p>✅ Session variables set</p>";
                echo "<p><strong>Session data:</strong></p>";
                echo "<pre>" . print_r($_SESSION, true) . "</pre>";
                
                // Determine redirect
                if ($user['role'] === 'gestionnaire') {
                    $redirect = BASE_URL . '/src/views/dashboard/index.php';
                    echo "<p>✅ Redirect for gestionnaire: " . $redirect . "</p>";
                } elseif ($user['role'] === 'receptionniste') {
                    $redirect = BASE_URL . '/src/views/dashboard/receptionniste.php';
                    echo "<p>✅ Redirect for receptionniste: " . $redirect . "</p>";
                }
                
                echo "<p><a href='" . $redirect . "'>Test Dashboard Access</a></p>";
                
            } else {
                echo "<p>❌ Password verification: FAILED</p>";
            }
        } else {
            echo "<p>❌ No user found with username: " . htmlspecialchars($username) . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}
?>

<form method="POST">
    <h3>Test Login</h3>
    <p>
        <label>Username:</label><br>
        <input type="text" name="username" value="admin" required>
    </p>
    <p>
        <label>Password:</label><br>
        <input type="password" name="password" value="admin123" required>
    </p>
    <p>
        <button type="submit">Test Login</button>
    </p>
</form>

<hr>
<p><a href="login.php">Go to Real Login Page</a></p>
<p><a href="debug_session.php">Check Current Session</a></p>
