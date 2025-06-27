<?php
/**
 * Traitement de la connexion (AJAX)
 * KUBIKOTI BAR
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
    error_log(date('Y-m-d H:i:s') . ' - Login Error: ' . $message . PHP_EOL, 3, $logDir . '/login_errors.log');
}

// Function to test database connection
function testDatabaseConnection() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            logError('Database connection successful');
            return true;
        } else {
            logError('Database connection failed: Connection object is null');
            return false;
        }
    } catch (Exception $e) {
        logError('Database connection failed: ' . $e->getMessage());
        return false;
    }
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
    // Inclure les fichiers de configuration
    require_once './../../../src/config/config.php';
    require_once './../../../src/config/database.php';
    require_once './../../../includes/session.php';
    require_once './../../../src/utils/Auth.php';
    require_once './../../../src/models/User.php';
    require_once './../../../src/controllers/AuthController.php';

    // Initialiser la session stockée en base de données
    $currentSessionId = startDbSession();

    // Test database connection and log result
    $dbConnected = testDatabaseConnection();
    if (!$dbConnected) {
        send_json_response(false, 'Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.', null, 500);
    }

    try {
        // Vérifier si la requête est de type POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json_response(false, 'Méthode non autorisée.', null, 405); // 405 Method Not Allowed
        }

        // Log incoming request data for debugging (without passwords)
        logError('Login attempt for username: ' . ($_POST['username'] ?? 'not provided'));

        // Récupérer et nettoyer les données du formulaire
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Ne pas nettoyer le mot de passe pour ne pas altérer les caractères spéciaux

        // Vérifier que les champs ne sont pas vides
        if (empty($username) || empty($password)) {
            send_json_response(false, 'Veuillez remplir tous les champs.', null, 400); // 400 Bad Request
        }

        // Initialiser le contrôleur d'authentification
        $authController = new AuthController();

        // Tenter la connexion
        $result = $authController->login($username, $password); // Pass the username and password to the login method

        // Traiter le résultat
        if ($result['success']) {
            // Connexion réussie
            // Session variables like $_SESSION['user_id'], $_SESSION['username'] should be set within $authController->login()
            $_SESSION['flash_message'] = 'Connexion réussie. Bienvenue, ' . htmlspecialchars($result['user']['nom']) . '!'; // Keep for non-JS fallback or direct page load after login
            $_SESSION['flash_type'] = 'success';
            
            // Log session information for debugging
            error_log('Session after login: ' . print_r($_SESSION, true));
            
            // Determine redirect URL based on user role
            $redirect = '../dashboard/index.php';
            if (isset($result['user']['role']) && $result['user']['role'] === 'receptionniste') {
                $redirect = '../dashboard/receptionniste.php';
            }

            // Make sure to use the correct redirect path and return the session ID
            $sessionToken = session_id();
            send_json_response(true, 'Connexion réussie. Redirection en cours...', $redirect, 200, $sessionToken);
        } else {
            // Échec de la connexion
            send_json_response(false, $result['message'] ?? 'Échec de la connexion.', null, 401); // 401 Unauthorized
        }
    } catch (Exception $e) {
        // Log detailed error
        logError('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        // Send a generic error message to the client
        send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
    } finally {
        // Clean up any output buffering if we get here
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
} catch (Exception $e) {
    // Log detailed error
    logError('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    // Send a generic error message to the client
    send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
} finally {
    // Clean up any output buffering if we get here
    while (ob_get_level()) {
        ob_end_clean();
    }
}
?>
