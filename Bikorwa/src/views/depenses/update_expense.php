<?php
/**
 * BIKORWA SHOP - Update Expense AJAX Handler
 */

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize auth
    $auth = new Auth($conn);
    
    // Verify session and permissions
    if (!$auth->isLoggedIn()) {
        throw new Exception('Accès non autorisé. Veuillez vous connecter.');
    }
    
    // Allow access to both gestionnaires and users with depenses permission
    $userRole = $_SESSION['role'] ?? '';
    if (!$auth->hasAccess('depenses') && $userRole !== 'gestionnaire') {
        throw new Exception('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.');
    }

    // Validate required fields
    $required = ['id', 'date_depense', 'categorie_id', 'montant', 'description', 'mode_paiement'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Champ requis: $field");
        }
    }

    // Validate mode_paiement is one of the allowed values
    $allowed_modes = ['Espèces', 'Cheque', 'Virement', 'Carte', 'Mobile Money'];
    if (!in_array($_POST['mode_paiement'], $allowed_modes)) {
        throw new Exception("Le mode de paiement sélectionné n'est pas valide.");
    }

    // Sanitize inputs
    $id = (int)$_POST['id'];
    $date = $_POST['date_depense'];
    $categoryId = (int)$_POST['categorie_id'];
    $amount = (float)$_POST['montant'];
    $description = trim($_POST['description']);
    $paymentMode = $_POST['mode_paiement'];
    $reference = $_POST['reference_paiement'] ?? null;
    $note = $_POST['note'] ?? null;

    if ($amount <= 0) {
        throw new Exception('Montant doit être positif');
    }
    
    // Get current user ID for logging
    $current_user_id = $_SESSION['user_id'] ?? 0;
    
    // First get original expense details for logging
    $stmt = $conn->prepare("SELECT description, montant FROM depenses WHERE id = ?");
    $stmt->execute([$id]);
    $originalExpense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$originalExpense) {
        throw new Exception('Dépense non trouvée.');
    }

    // Update the expense
    $updateQuery = "UPDATE depenses SET 
                    date_depense = ?, 
                    categorie_id = ?, 
                    montant = ?, 
                    description = ?, 
                    mode_paiement = ?, 
                    reference_paiement = ?,
                    note = ?
                    WHERE id = ?";
                    
    $stmt = $conn->prepare($updateQuery);
    $result = $stmt->execute([
        $date, 
        $categoryId, 
        $amount, 
        $description, 
        $paymentMode, 
        $reference,
        $note,
        $id
    ]);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Database error: " . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    // Log the action in journal_activites
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                  VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_details = "Modification d'une dépense de {$originalExpense['montant']} à {$amount} BIF - {$originalExpense['description']} à {$description}";
    $log_result = $log_stmt->execute([$current_user_id, 'modification', 'depenses', $id, $log_details]);
    
    if (!$log_result) {
        $logError = $log_stmt->errorInfo();
        throw new Exception("Failed to log activity: " . ($logError[2] ?? 'Unknown error'));
    }
    
    // Set success response
    $response['success'] = true;
    $response['message'] = "La dépense a été mise à jour avec succès.";

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
