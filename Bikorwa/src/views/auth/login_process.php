<?php
/**
 * Traitement de la connexion (AJAX)
 * KUBIKOTI BAR
 */

// Prevent headers from being sent
declare(strict_types=1);

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Prevent any output
header('Content-Type: application/json');

// Include core configuration and helpers
require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../src/config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../src/utils/Auth.php';
require_once __DIR__ . '/../../../src/models/User.php';
require_once __DIR__ . '/../../../src/controllers/AuthController.php';

// Log function for debugging
function logError($message) {
    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/../../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . ' - Login Error: ' . $message . PHP_EOL, 3, $logDir . '/login_errors.log');
}

// Helper function to send JSON response and exit
function send_json_response($success, $message, $redirectUrl = null, $statusCode = 200, $sessionId = null) {
    // Clean any output buffering
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
    // Start PHP session
require_once __DIR__ . '/../../../includes/session_manager.php';
$sessionManager = SessionManager::getInstance();
$sessionManager->startSession();

    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(false, 'Méthode non autorisée.', null, 405);
    }

    // Get and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (function_exists('sanitize_input')) {
        $username = sanitize_input($username);
    }

    // Validate input
    if (empty($username) || empty($password)) {
        send_json_response(false, 'Veuillez remplir tous les champs.', null, 400);
    }

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn instanceof PDO) {
        throw new Exception('Failed to get database connection');
    }

    try {
        // Prepare and execute query
        $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username");
        if (!$stmt) {
            throw new Exception("SQL prepare error: " . implode(" ", $conn->errorInfo()));
        }

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Handle successful login using SessionManager
            $sessionManager->loginUser($user);

            // Log activity
            $auth = new Auth($conn);
            $activity = "User " . $user['nom'] . " logged in successfully.";
            $auth->logActivity("Login", "User", $user['id'], $activity);

            // Determine redirect URL
            $redirect = BASE_URL . '/src/views/dashboard/index.php';
            if ($user['role'] === 'receptionniste') {
                $redirect = BASE_URL . '/src/views/dashboard/receptionniste.php';
            }

            $welcomeMessage = 'Connexion réussie. Redirection en cours...';
            if (isset($user['nom'])) {
                $welcomeMessage = 'Connexion réussie. Bienvenue, ' . htmlspecialchars($user['nom']) . ' !';
            }

            send_json_response(true, $welcomeMessage, $redirect, 200, session_id());
        } else {
            // Invalid credentials
            send_json_response(false, $user ? "Mot de passe incorrect." : "Nom d'utilisateur introuvable.", null, 401);
        }
    } catch (Exception $e) {
        logError('Database error: ' . $e->getMessage());
        send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
    }
} catch (Exception $e) {
    logError('Login process error: ' . $e->getMessage());
    send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
} finally {
    // Clean up any output buffering if we get here
    while (ob_get_level()) {
        ob_end_clean();
    }
}
?>