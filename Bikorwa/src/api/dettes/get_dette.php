<?php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

$response = ['success' => false, 'message' => ''];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed', 500);
    }

    // Initialize authentication
    $auth = new Auth($conn);

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        throw new Exception('Session expirée ou non autorisée', 401);
    }

    // Get debt ID from request
    $dette_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$dette_id) {
        throw new Exception('ID dette non fourni', 400);
    }

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
        throw new Exception('Dette non trouvée', 404);
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
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
    $response['debug'] = [
        'session_status' => session_status(),
        'logged_in' => $_SESSION['logged_in'] ?? 'not_set',
        'user_id' => $_SESSION['user_id'] ?? 'not_set',
        'role' => $_SESSION['role'] ?? 'not_set',
        'request_id' => $_GET['id'] ?? 'not_set',
        'error_code' => $e->getCode(),
        'error_line' => $e->getLine(),
        'error_file' => $e->getFile()
    ];
    
    // Log the error for debugging
    error_log("get_dette.php Error: " . $e->getMessage() . " on line " . $e->getLine());
}

echo json_encode($response);
