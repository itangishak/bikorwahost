<?php
require_once __DIR__ . '/../../../src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Admin User Setup</h2>";
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "<p>✅ Admin user already exists:</p>";
        echo "<ul>";
        echo "<li>ID: " . $adminUser['id'] . "</li>";
        echo "<li>Username: " . $adminUser['username'] . "</li>";
        echo "<li>Name: " . $adminUser['nom'] . "</li>";
        echo "<li>Role: " . $adminUser['role'] . "</li>";
        echo "<li>Active: " . ($adminUser['actif'] ? 'Yes' : 'No') . "</li>";
        echo "</ul>";
        
        // Update admin user to be active and have gestionnaire role if needed
        $stmt = $conn->prepare("UPDATE users SET role = 'gestionnaire', actif = 1 WHERE username = 'admin'");
        $stmt->execute();
        echo "<p>✅ Updated admin user to have 'gestionnaire' role and be active</p>";
    } else {
        // Create admin user with gestionnaire role
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, nom, role, actif) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'Administrator', 'gestionnaire', 1]);
        echo "<p>✅ Created admin user:</p>";
        echo "<ul>";
        echo "<li>Username: admin</li>";
        echo "<li>Password: admin123</li>";
        echo "<li>Name: Administrator</li>";
        echo "<li>Role: gestionnaire</li>";
        echo "</ul>";
    }
    
    echo "<h3>How it works:</h3>";
    echo "<p>• The admin user is stored in database with 'gestionnaire' role</p>";
    echo "<p>• During login, if username is 'admin', the system treats it as 'gestionnaire'</p>";
    echo "<p>• This gives admin full access to the gestionnaire dashboard</p>";
    echo "<p>• No database schema changes needed</p>";
    
    echo "<hr>";
    echo "<p><strong>You can now login with: admin / admin123</strong></p>";
    echo "<a href='login.php'>Go to Login</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
