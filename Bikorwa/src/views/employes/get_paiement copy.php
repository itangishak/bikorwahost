<?php
// Get payment details for view/edit

// Disable error display for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';
require_once './../../../src/models/Employe.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);
$authController = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Check if the request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de paiement invalide.'
    ]);
    exit;
}

$id = $_GET['id'];

try {
    // Get payment details with employee and user info
    $query = "SELECT p.*, 
                    e.nom AS employe_nom, 
                    e.poste AS employe_poste,
                    u.nom AS utilisateur_nom
              FROM salaires p
              JOIN employes e ON p.employe_id = e.id
              JOIN users u ON p.utilisateur_id = u.id
              WHERE p.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paiement) {
        echo json_encode([
            'success' => true,
            'paiement' => $paiement
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Paiement non trouvé.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
