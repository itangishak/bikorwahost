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
    // Base directory of the project
    $baseDir = dirname(__DIR__, 3);

    // Include configuration files with error checking
    $configFiles = [
        $baseDir . '/src/config/config.php',
        $baseDir . '/src/config/database.php',
        $baseDir . '/src/utils/Auth.php',
        $baseDir . '/src/models/User.php',
        $baseDir . '/src/controllers/AuthController.php'
    ];

    foreach ($configFiles as $file) {
        if (file_exists($file)) {
            require_once $file;
            logError('Successfully included: ' . $file);
        } else {
            logError('File not found: ' . $file);
            send_json_response(false, 'Erreur de configuration du système.', null, 500);
        }
    }

    // Include optional files (functions and session)
    $optionalFiles = [
        $baseDir . '/includes/functions.php',
        $baseDir . '/includes/session.php'
    ];

    foreach ($optionalFiles as $file) {
        if (file_exists($file)) {
            require_once $file;
            logError('Successfully included optional file: ' . $file);
        } else {
            logError('Optional file not found: ' . $file);
        }
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

    // Test database connection
    if (!testDatabaseConnection()) {
        send_json_response(false, 'Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.', null, 500);
    }

    // Initialize session if function exists
    if (function_exists('startDbSession')) {
        try {
            $currentSessionId = startDbSession();
            logError('Database session initialized: ' . $currentSessionId);
        } catch (Exception $e) {
            logError('Database session initialization failed: ' . $e->getMessage());
            // Continue without database session
        }
    }

    // Vérifier si la requête est de type POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(false, 'Méthode non autorisée.', null, 405);
    }

    // Log incoming request data for debugging (without passwords)
    logError('Login attempt for username: ' . ($_POST['username'] ?? 'not provided'));

    // Récupérer et nettoyer les données du formulaire
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Use sanitize_input if function exists, otherwise use trim
    if (function_exists('sanitize_input')) {
        $username = sanitize_input($username);
    }

    // Vérifier que les champs ne sont pas vides
    if (empty($username) || empty($password)) {
        send_json_response(false, 'Veuillez remplir tous les champs.', null, 400);
    }

    // --- Start of integrated login logic ---
    $database = new Database();
    $conn = $database->getConnection(); // Get PDO connection

    if (!$conn) {
        send_json_response(false, 'Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.', null, 500);
    }

    try {
        // SQL query using prepared statement
        // Assuming 'email' in your provided code maps to 'username' in the project's 'users' table
        $stmt = $conn->prepare("SELECT id, username, password, nom, role FROM users WHERE username = :username");

        if (!$stmt) {
            logError("SQL prepare error: " . implode(" ", $conn->errorInfo()));
            send_json_response(false, "Erreur interne du serveur.", null, 500);
        }

        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if password matches the stored hash
            if (password_verify($password, $user['password'])) {
                // Store session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nom']; // Assuming 'nom' is the equivalent of 'firstname'
                $_SESSION['user_role'] = $user['role']; // This will be 'gestionnaire' or 'receptionniste'
                $_SESSION['last_activity'] = time();

                // Log the activity using the project's Auth class
                $auth = new Auth($conn); // Re-initialize Auth with the PDO connection
                $activity = "User " . $user['nom'] . " logged in successfully.";
                $auth->logActivity("Login", "User", $user['id'], $activity);

                // Determine redirect URL based on user role
                $redirect = BASE_URL . '/src/views/dashboard/index.php'; // Default for gestionnaire
                if ($user['role'] === 'receptionniste') {
                    $redirect = BASE_URL . '/src/views/dashboard/receptionniste.php';
                }

                $welcomeMessage = 'Connexion réussie. Redirection en cours...';
                if (isset($user['nom'])) {
                    $welcomeMessage = 'Connexion réussie. Bienvenue, ' . htmlspecialchars($user['nom']) . ' !';
                }

                send_json_response(true, $welcomeMessage, $redirect, 200, session_id());
            } else {
                send_json_response(false, "Mot de passe incorrect.", null, 401);
            }
        } else {
            send_json_response(false, "Nom d'utilisateur introuvable.", null, 401);
        }
    } catch (Exception $e) {
        logError('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        logError('Stack trace: ' . $e->getTraceAsString());
        send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
    }
    // --- End of integrated login logic ---

} catch (Exception $e) {
    // Log detailed error
    logError('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    logError('Stack trace: ' . $e->getTraceAsString());

    // Send a generic error message to the client
    send_json_response(false, 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.', null, 500);
} finally {
    // Clean up any output buffering if we get here
    while (ob_get_level()) {
        ob_end_clean();
    }
}
?>