<?php
require_once __DIR__ . '/../../../src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Creating Admin User with Gestionnaire Role</h2>";
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "<p>Admin user exists with current data:</p>";
        echo "<pre>" . print_r($adminUser, true) . "</pre>";
        
        // Update admin to have gestionnaire role
        $stmt = $conn->prepare("UPDATE users SET role = 'gestionnaire', actif = 1 WHERE username = 'admin'");
        $stmt->execute();
        echo "<p>✅ Updated admin user to have 'gestionnaire' role</p>";
        
        // Verify update
        $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users WHERE username = 'admin'");
        $stmt->execute();
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Updated admin user data:</p>";
        echo "<pre>" . print_r($updatedUser, true) . "</pre>";
        
    } else {
        // Create new admin user with gestionnaire role
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, nom, role, actif) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'Administrator', 'gestionnaire', 1]);
        echo "<p>✅ Created new admin user with gestionnaire role</p>";
        
        // Show created user
        $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users WHERE username = 'admin'");
        $stmt->execute();
        $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Created admin user data:</p>";
        echo "<pre>" . print_r($newUser, true) . "</pre>";
    }
    
    echo "<h3>All Users in Database:</h3>";
    $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users ORDER BY id");
    $stmt->execute();
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th></tr>";
    foreach ($allUsers as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['nom'] . "</td>";
        echo "<td><strong>" . $user['role'] . "</strong></td>";
        echo "<td>" . ($user['actif'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Admin (Full Access):</strong> admin / admin123</p>";
    echo "<p><strong>Role:</strong> gestionnaire (full privileges)</p>";
    
    echo "<br><a href='login.php'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
