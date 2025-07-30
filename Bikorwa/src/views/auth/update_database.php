<?php
require_once __DIR__ . '/../../../src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Updating Database Schema</h2>";
    
    // Update the role enum to include admin
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM('receptionniste', 'gestionnaire', 'admin') NOT NULL";
    $conn->exec($sql);
    echo "<p>✅ Updated role enum to include 'admin'</p>";
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists == 0) {
        // Create admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, nom, role, actif) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPassword, 'Administrator', 'admin', 1]);
        echo "<p>✅ Created admin user (username: admin, password: admin123)</p>";
    } else {
        // Update existing admin user to have admin role
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE username = 'admin'");
        $stmt->execute();
        echo "<p>✅ Updated existing admin user to have 'admin' role</p>";
    }
    
    // Show updated users
    echo "<h3>Updated Users</h3>";
    $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['nom'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($user['actif'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Database update completed successfully!</strong></p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
