<?php
/**
 * BIKORWA SHOP - Delete Expense AJAX Handler
 * This file processes the AJAX request to delete an expense.
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
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée.');
    }

    // Check if ID parameter is present
    if (!isset($data['id']) || empty($data['id'])) {
        throw new Exception('ID de dépense non spécifié.');
    }

    $expense_id = intval($data['id']);

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

    // Get current user ID for logging
    $current_user_id = $_SESSION['user_id'] ?? 0;

    // First get expense details for logging
    $stmt = $conn->prepare("SELECT description, montant FROM depenses WHERE id = ?");
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        throw new Exception('Dépense non trouvée.');
    }

    // Delete the expense
    $stmt = $conn->prepare("DELETE FROM depenses WHERE id = ?");
    $result = $stmt->execute([$expense_id]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Database error: " . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    // Log the action in journal_activites
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                  VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_details = "Suppression d'une dépense de {$expense['montant']} BIF - {$expense['description']}";
    $log_result = $log_stmt->execute([$current_user_id, 'suppression', 'depenses', $expense_id, $log_details]);
    
    if (!$log_result) {
        $logError = $log_stmt->errorInfo();
        throw new Exception("Failed to log activity: " . ($logError[2] ?? 'Unknown error'));
    }
    
    // Set success response
    $response['success'] = true;
    $response['message'] = "La dépense a été supprimée avec succès.";

} catch (Exception $e) {
    // Set error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
?>
