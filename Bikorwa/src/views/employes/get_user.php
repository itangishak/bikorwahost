<?php
/**
 * User Details Retriever
 * Fetches user details by ID for AJAX operations
 * 
 * This file responds to AJAX requests from the utilisateurs.php page
 * to get user details for viewing and editing
 */

// Include required files
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';
if (session_status() === PHP_SESSION_NONE) {
    if (isset($_GET['PHPSESSID'])) {
        session_id($_GET['PHPSESSID']);
    } elseif (isset($_POST['PHPSESSID'])) {
        session_id($_POST['PHPSESSID']);
    }
}
require_once './../../../includes/session.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);
$authController = new AuthController();

// Check if user is logged in and has access to user management
if (!$auth->isLoggedIn() || !$auth->hasAccess('utilisateurs')) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé.'
    ]);
    exit;
}

// Set default response
$response = [
    'success' => false,
    'message' => 'ID utilisateur non spécifié.'
];

// Check if ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        // Get user details
        $stmt = $conn->prepare("
            SELECT id, username, nom, role, email, telephone, 
                   actif, date_creation, derniere_connexion
            FROM users 
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $response = [
                'success' => true,
                'user' => $user
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Utilisateur introuvable.'
            ];
        }
        
    } catch (PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
