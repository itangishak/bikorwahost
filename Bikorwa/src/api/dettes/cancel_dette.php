<?php
// API endpoint to cancel a debt
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Non autorisé"]);
    exit;
}

// Check if user has gestionnaire role
if (!$auth->isManager()) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Seuls les gestionnaires peuvent annuler des dettes"]);
    exit;
}

// Get user ID for logging
$user_id = $_SESSION['user_id'] ?? 0;

// Get POST data
$dette_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate required fields
if (!$dette_id) {
    echo json_encode(["success" => false, "message" => "ID dette non fourni"]);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get current debt information including vente_id
    $query = "SELECT statut, vente_id FROM dettes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $stmt->execute();
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        throw new Exception("Dette non trouvée");
    }
    
    // Check if debt can be canceled
    if ($dette['statut'] === 'annulee') {
        throw new Exception("Cette dette est déjà annulée");
    }
    
    if ($dette['statut'] === 'payee') {
        throw new Exception("Une dette déjà payée ne peut pas être annulée");
    }
    
    // Update debt status
    $update_query = "UPDATE dettes SET statut = 'annulee' WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // Update associated sale (vente) if exists
    if (!empty($dette['vente_id'])) {
        // Format current date and time
        $date_now = date('Y-m-d H:i:s');
        
        // Create note for the cancellation
        $vente_note = "Dette annulée le $date_now par l'utilisateur #$user_id.";
        
        // Update vente record
        $vente_query = "UPDATE ventes SET statut_paiement = 'paye', note = CONCAT(IFNULL(note, ''), ' ', ?) WHERE id = ?";
        $vente_stmt = $conn->prepare($vente_query);
        $vente_stmt->bindParam(1, $vente_note, PDO::PARAM_STR);
        $vente_stmt->bindParam(2, $dette['vente_id'], PDO::PARAM_INT);
        $vente_stmt->execute();
    }
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'annulation', 'dette', ?, ?)";
    
    $details = "Annulation de la dette #$dette_id";
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $dette_id, PDO::PARAM_INT);
    $log_stmt->bindParam(3, $details, PDO::PARAM_STR);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Dette annulée avec succès"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur lors de l'annulation de la dette"]);
}
