<?php
require_once __DIR__ . '/../../../src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Users in Database</h2>";
    
    $stmt = $conn->prepare("SELECT id, username, nom, role, actif FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p>No users found in database</p>";
    } else {
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
    }
    
    // Check table structure
    echo "<h3>Users Table Structure</h3>";
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
