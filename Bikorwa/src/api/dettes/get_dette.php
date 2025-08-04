<?php
// API endpoint to fetch debt details with payment history
// Enhanced error handling and session management
error_reporting(0);
ini_set('display_errors', 0);

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conditional session manager loading
if (!isset($_SESSION['SESSION_MANAGER_LOADED'])) {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Auth.php';
    $_SESSION['SESSION_MANAGER_LOADED'] = true;
} else {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Auth.php';
}

// Set JSON header
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Initialize authentication
    $auth = new Auth($conn);

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        $response['message'] = "Session expirée. Veuillez vous reconnecter.";
        $response['debug'] = [
            'session_status' => session_status(),
            'session_data' => $_SESSION ?? null,
            'logged_in_check' => $auth->isLoggedIn()
        ];
        echo json_encode($response);
        exit;
    }

    // Get debt ID from request
    $dette_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$dette_id) {
        throw new Exception("ID dette non fourni", 400);
    }

    try {
        // Get debt details
        $query = "SELECT d.*, c.nom as client_nom, v.numero_facture
                  FROM dettes d
                  LEFT JOIN clients c ON d.client_id = c.id
                  LEFT JOIN ventes v ON d.vente_id = v.id
                  WHERE d.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $dette = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dette) {
            http_response_code(404);
            $response['message'] = "Dette non trouvée";
            $response['debug'] = [
                'dette_id' => $dette_id,
                'query' => $query,
                'user_id' => $_SESSION['user_id'] ?? null
            ];
            echo json_encode($response);
            exit;
        }
        
        // Get payment history
        $payments_query = "SELECT pd.*, u.nom as utilisateur_nom 
                           FROM paiements_dettes pd
                           LEFT JOIN users u ON pd.utilisateur_id = u.id
                           WHERE pd.dette_id = ?
                           ORDER BY pd.date_paiement DESC";
        
        $payments_stmt = $conn->prepare($payments_query);
        $payments_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
        $payments_stmt->execute();
        
        $paiements = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['dette'] = $dette;
        $response['paiements'] = $paiements;
        
    } catch (PDOException $e) {
        error_log("Database error in get_dette.php: " . $e->getMessage());
        http_response_code(500);
        $response['message'] = "Erreur de base de données: " . $e->getMessage();
        $response['debug'] = [
            'session' => $_SESSION ?? null,
            'request' => $_GET,
            'error' => $e->getTraceAsString()
        ];
    } catch (Exception $e) {
        error_log("General error in get_dette.php: " . $e->getMessage());
        http_response_code($e->getCode() ?: 500);
        $response['message'] = "Erreur générale: " . $e->getMessage();
        $response['debug'] = [
            'session' => $_SESSION ?? null,
            'request' => $_GET,
            'error' => $e->getTraceAsString()
        ];
    }

} catch (Exception $e) {
    error_log("Fatal error in get_dette.php: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    $response['message'] = "Erreur fatale lors de l'initialisation: " . $e->getMessage();
    $response['debug'] = [
        'session' => $_SESSION ?? null,
        'request' => $_GET,
        'error' => $e->getTraceAsString()
    ];
}

echo json_encode($response);
