<?php
/**
 * Traitement de la connexion (AJAX)
 * KUBIKOTI BAR
 */

// Clean any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON content type
header('Content-Type: application/json');

// Include core configuration
require_once __DIR__ . '/../../../src/config/config.php';
require_once __DIR__ . '/../../../includes/session.php';

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

    // Initialize database connection with better error handling
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn instanceof PDO) {
        logError('Database connection failed - not a PDO instance');
        send_json_response(false, 'Erreur de connexion à la base de données.', null, 500);
    }
    
    // Test database connection
    try {
        $conn->query('SELECT 1');
    } catch (Exception $e) {
        logError('Database connection test failed: ' . $e->getMessage());
        send_json_response(false, 'Erreur de connexion à la base de données.', null, 500);
    }

    try {
        // Log the login attempt
        logError('Login attempt for username: ' . $username);
        
        // Prepare and execute query
        $stmt = $conn->prepare("SELECT id, username, password, nom, role, actif FROM users WHERE username = :username AND actif = 1");
        if (!$stmt) {
            throw new Exception("SQL prepare error: " . implode(" ", $conn->errorInfo()));
        }

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log user found status
        if ($user) {
            logError('User found: ' . $user['username'] . ', role: ' . $user['role']);
        } else {
            logError('No user found with username: ' . $username);
        }

        if ($user && password_verify($password, $user['password'])) {
            logError('Password verification successful for user: ' . $username);
            // User is already active (checked in query), but double-check
            if (!$user['actif']) {
                logError('User account is inactive: ' . $username);
                send_json_response(false, 'Votre compte est désactivé. Veuillez contacter l\'administrateur.', null, 401);
            }
            
            // Regenerate session ID for security (only if headers not sent)
            if (!headers_sent()) {
                session_regenerate_id(true);
            }

            // Set basic session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['nom'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            // Determine redirect URL based on role
            $redirect = BASE_URL . '/src/views/dashboard/';
            if ($user['role'] === 'receptionniste') {
                $redirect .= 'receptionniste.php';
            } else {
                $redirect .= 'index.php';
            }

            send_json_response(true, 'Connexion réussie. Redirection en cours...', $redirect, 200, session_id());
        } else {
            // Invalid credentials - log the failure
            if ($user) {
                logError('Password verification failed for user: ' . $username);
                send_json_response(false, "Mot de passe incorrect.", null, 401);
            } else {
                logError('User not found: ' . $username);
                send_json_response(false, "Nom d'utilisateur introuvable.", null, 401);
            }
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