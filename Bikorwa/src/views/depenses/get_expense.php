<?php
/**
 * BIKORWA SHOP - Get Expense AJAX Handler
 * This file processes the AJAX request to retrieve expense details.
 */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Une erreur est survenue.'
];

try {
    // Check if ID parameter is present
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID de dépense non spécifié.');
    }

    $expense_id = intval($_GET['id']);

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize auth
    $auth = new Auth($conn);

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        throw new Exception('Accès non autorisé. Veuillez vous connecter.');
    }
    
    // Allow access to both gestionnaires and users with depenses permission
    $userRole = $_SESSION['role'] ?? '';
    if (!$auth->hasAccess('depenses') && $userRole !== 'gestionnaire') {
        throw new Exception('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.');
    }

    // Fetch expense details
    $query = "SELECT d.*, c.nom as categorie_nom 
              FROM depenses d 
              LEFT JOIN categories c ON d.categorie_id = c.id 
              WHERE d.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$expense_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Dépense non trouvée.');
    }
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set success response
    $response['success'] = true;
    $response['message'] = 'Dépense récupérée avec succès.';
    $response['expense'] = $expense;

} catch (Exception $e) {
    // Set error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
?>
