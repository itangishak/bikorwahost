<?php
/**
 * BIKORWA SHOP - Add Expense AJAX Handler
 * This file processes the AJAX request to add a new expense.
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
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée.');
    }

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
    $userRole = $_SESSION['user_role'] ?? '';
    if (!$auth->hasAccess('depenses') && $userRole !== 'gestionnaire') {
        throw new Exception('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.');
    }

    // Get current user ID for logging actions
    $user_id = $_SESSION['user_id'] ?? 0;

    // Validate required fields
    $required_fields = ['date_depense', 'montant', 'categorie_id', 'mode_paiement', 'description'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ '$field' est obligatoire.");
        }
    }

    // Validate montant (amount) is positive
    if (!is_numeric($_POST['montant']) || floatval($_POST['montant']) <= 0) {
        throw new Exception("Le montant doit être un nombre positif.");
    }

    // Validate categorie_id exists in database
    $stmt = $conn->prepare("SELECT id FROM categories_depenses WHERE id = ?");
    $stmt->execute([$_POST['categorie_id']]);
    if ($stmt->rowCount() === 0) {
        throw new Exception("La catégorie sélectionnée n'existe pas.");
    }

    // Validate mode_paiement is one of the allowed values
    $allowed_modes = ['Espèces', 'Cheque', 'Virement', 'Carte', 'Mobile Money'];
    if (!in_array($_POST['mode_paiement'], $allowed_modes)) {
        throw new Exception("Le mode de paiement sélectionné n'est pas valide.");
    }

    // Prepare data for insertion
    $date_depense = $_POST['date_depense'];
    $montant = floatval($_POST['montant']);
    $categorie_id = intval($_POST['categorie_id']);
    $mode_paiement = $_POST['mode_paiement'];
    $description = $_POST['description'];
    $reference_paiement = $_POST['reference_paiement'] ?? null;
    $note = $_POST['note'] ?? null;

    // Insert new expense into database (uses user_id)
    $query = "INSERT INTO depenses (date_depense, montant, categorie_id, description, mode_paiement, 
                                   reference_paiement, note, utilisateur_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    
    try {
        $result = $stmt->execute([
            $date_depense, 
            $montant, 
            $categorie_id, 
            $description, 
            $mode_paiement, 
            $reference_paiement, 
            $note, 
            $user_id
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Database error: " . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        $expense_id = $conn->lastInsertId();
        
        // Log the action in journal_activites (uses utilisateur_id)
        $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                      VALUES (?, ?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_details = "Ajout d'une dépense de $montant BIF - $description";
        $log_result = $log_stmt->execute([$user_id, 'ajout', 'depenses', $expense_id, $log_details]);
        
        if (!$log_result) {
            $logError = $log_stmt->errorInfo();
            throw new Exception("Failed to log activity: " . ($logError[2] ?? 'Unknown error'));
        }
        
        // Set success response
        $response['success'] = true;
        $response['message'] = "La dépense a été ajoutée avec succès.";
        $response['expense_id'] = $expense_id;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }

} catch (Exception $e) {
    // Set error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
