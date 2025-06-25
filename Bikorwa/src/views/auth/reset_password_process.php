<?php
/**
 * Password Reset Processing
 * BIKORWA SHOP
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the browser

// Start output buffering to capture any errors
ob_start();

// Log function for debugging
function logError($message) {
    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/../../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . ' - Password Reset: ' . $message, 3, $logDir . '/password_reset.log');
}

// Helper function to send JSON response and exit
function send_json_response($success, $message, $statusCode = 200) {
    // Clean any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    // Include necessary files
    require_once './../../../src/config/config.php';
    require_once './../../../src/config/database.php';
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(false, 'Méthode non autorisée.', 405);
    }
    
    // Get and sanitize input
    $username = sanitize_input($_POST['username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($new_password) || empty($confirm_password)) {
        send_json_response(false, 'Tous les champs sont obligatoires.', 400);
    }
    
    if ($new_password !== $confirm_password) {
        send_json_response(false, 'Les mots de passe ne correspondent pas.', 400);
    }
    
    if (strlen($new_password) < 6) {
        send_json_response(false, 'Le mot de passe doit contenir au moins 6 caractères.', 400);
    }
    
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        logError('Database connection failed');
        send_json_response(false, 'Erreur de connexion à la base de données.', 500);
    }
    
    // Check if user exists
    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() === 0) {
        send_json_response(false, 'Utilisateur non trouvé.', 404);
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the password
    $update_query = "UPDATE users SET password = ? WHERE username = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute([$hashed_password, $username]);
    
    if ($update_stmt->rowCount() > 0) {
        logError("Password reset successful for user: $username");
        send_json_response(true, 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.');
    } else {
        logError("Password reset failed for user: $username");
        send_json_response(false, 'Échec de la réinitialisation du mot de passe.', 500);
    }
} catch (Exception $e) {
    logError('Exception: ' . $e->getMessage());
    send_json_response(false, 'Une erreur s\'est produite lors de la réinitialisation du mot de passe.', 500);
}
?>
