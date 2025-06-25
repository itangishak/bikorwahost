<?php
/**
 * BIKORWA SHOP - API for deleting a client
 */
header('Content-Type: application/json');
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Check access permissions
if (!$auth->hasAccess('clients')) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
    ]);
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;

// Process only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit;
}

// Validate client ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID client invalide.'
    ]);
    exit;
}

$client_id = (int)$_POST['id'];

try {
    // Check if client exists and get name for logging
    $check_query = "SELECT nom FROM clients WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(1, $client_id);
    $check_stmt->execute();
    
    $client = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode([
            'success' => false,
            'message' => 'Client non trouvé.'
        ]);
        exit;
    }
    
    $client_nom = $client['nom'];
    
    // Start transaction
    $conn->beginTransaction();
    
    // Check for foreign key constraints
    // This would check if the client is referenced in other tables like orders, invoices, etc.
    // Add checks for other tables as needed
    
    /* Example:
    $check_fk_query = "SELECT COUNT(*) as count FROM commandes WHERE client_id = ?";
    $check_fk_stmt = $conn->prepare($check_fk_query);
    $check_fk_stmt->bindParam(1, $client_id);
    $check_fk_stmt->execute();
    
    $fk_result = $check_fk_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fk_result['count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce client ne peut pas être supprimé car il est référencé dans des commandes.'
        ]);
        exit;
    }
    */
    
    // Delete the client
    $query = "DELETE FROM clients WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id);
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de la suppression du client.');
    }
    
    // Log the action
    $action = "Suppression du client: $client_nom (ID: $client_id)";
    $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details) 
              VALUES (?, ?, 'client', ?, NOW(), ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $current_user_id);
    $stmt->bindParam(2, $action);
    $stmt->bindParam(3, $client_id);
    $details = "Client supprimé: $client_nom";
    $stmt->bindParam(4, $details);
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de l\'enregistrement de l\'activité.');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Client supprimé avec succès.'
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    // Check for foreign key constraint violation
    if ($e->getCode() == '23000') {
        echo json_encode([
            'success' => false,
            'message' => 'Ce client ne peut pas être supprimé car il est référencé dans d\'autres enregistrements.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
