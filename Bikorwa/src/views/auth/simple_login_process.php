<?php
/**
 * Simple Login Process (bypassing complex session manager)
 * KUBIKOTI BAR
 */

// Clean any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON content type
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start simple PHP session
session_start();

// Include core configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Log function for debugging
function logError($message) {
    $logDir = __DIR__ . '/../../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . ' - Simple Login: ' . $message . PHP_EOL, 3, $logDir . '/simple_login.log');
}

// Helper function to send JSON response and exit
function send_json_response($success, $message, $redirectUrl = null, $statusCode = 200, $sessionId = null) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($redirectUrl) {
        $response['redirectUrl'] = $redirectUrl;
    }
    if ($sessionId) {
        $response['sessionId'] = $sessionId;
    }
    echo json_encode($response);
    exit;
}

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(false, 'Méthode non autorisée.', null, 405);
    }

    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate input
    if (empty($username) || empty($password)) {
        send_json_response(false, 'Veuillez remplir tous les champs.', null, 400);
    }

    logError('Simple login attempt for username: ' . $username);

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn instanceof PDO) {
        logError('Database connection failed');
        send_json_response(false, 'Erreur de connexion à la base de données.', null, 500);
    }

    // Search for user
    $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username AND actif = 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        logError('Password verification successful for user: ' . $username);
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set simple session variables
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_active'] = $user['actif'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['simple_auth'] = true; // Flag to indicate simple auth is used
        
        logError('Simple session variables set for user: ' . $username);
        logError('Session ID: ' . session_id());

        // Log activity
        try {
            $logStmt = $conn->prepare("INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) VALUES (?, ?, ?, ?, ?)");
            $logStmt->execute([$user['id'], 'Login', 'User', $user['id'], 'User ' . $user['nom'] . ' logged in successfully (simple auth).']);
        } catch (Exception $e) {
            logError('Failed to log activity: ' . $e->getMessage());
        }

        // Determine redirect URL
        $redirect = BASE_URL . '/src/views/auth/simple_dashboard_test.php'; // Use simple dashboard for testing
        
        $welcomeMessage = 'Connexion réussie. Bienvenue, ' . htmlspecialchars($user['nom']) . ' !';

        send_json_response(true, $welcomeMessage, $redirect, 200, session_id());
        
    } else {
        if ($user) {
            logError('Password verification failed for user: ' . $username);
            send_json_response(false, "Mot de passe incorrect.", null, 401);
        } else {
            logError('User not found: ' . $username);
            send_json_response(false, "Nom d'utilisateur introuvable.", null, 401);
        }
    }

} catch (Exception $e) {
    logError('Simple login error: ' . $e->getMessage());
    send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande.', null, 500);
}
?>
