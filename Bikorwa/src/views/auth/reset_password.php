<?php
/**
 * Reset Password Script for BIKORWA SHOP
 */

// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';

// Function to display result
function displayMessage($message, $success = true) {
    echo "<div style=\"margin: 20px; padding: 15px; border: 1px solid " . ($success ? "green" : "red") . "; background-color: " . ($success ? "#f0fff0" : "#fff0f0") . ";\">";    
    echo "<h3>" . ($success ? "Success" : "Error") . "</h3>";
    echo "<p>$message</p>";
    echo "</div>";
}

echo "<html><head><title>Reset Password - BIKORWA SHOP</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} form{margin:20px;}</style></head><body>";
echo "<h1>Reset Password - BIKORWA SHOP</h1>";

if (isset($_POST['reset_password'])) {
    try {
        // Connect to database
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            displayMessage("Failed to connect to database", false);
            exit;
        }
        
        // Get username
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        
        // Validate input
        if (empty($username) || empty($new_password)) {
            displayMessage("Username and new password are required", false);
            exit;
        }
        
        // Check if user exists
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() === 0) {
            displayMessage("User '$username' not found", false);
            exit;
        }
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the password
        $update_query = "UPDATE users SET password = ? WHERE username = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([$hashed_password, $username]);
        
        if ($update_stmt->rowCount() > 0) {
            displayMessage("Password for user '$username' has been reset successfully. You can now log in with the new password.");
            
            // Display a link to the login page
            echo "<p><a href='login.php'>Go to Login Page</a></p>";
        } else {
            displayMessage("Failed to update password. No changes were made.", false);
        }
    } catch (PDOException $e) {
        displayMessage("Database error: " . $e->getMessage(), false);
    }
} else {
    // Display reset form
    echo "<form method='post' action='reset_password.php'>";
    echo "<div><label for='username'>Username:</label><br>";
    echo "<input type='text' id='username' name='username' value='admin' required></div><br>";
    echo "<div><label for='new_password'>New Password:</label><br>";
    echo "<input type='password' id='new_password' name='new_password' required></div><br>";
    echo "<div><button type='submit' name='reset_password'>Reset Password</button></div>";
    echo "</form>";
}

echo "</body></html>";
?>
