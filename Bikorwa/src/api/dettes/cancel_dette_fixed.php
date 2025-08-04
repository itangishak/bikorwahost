<?php
// API endpoint to cancel a debt
// Enhanced error handling and session management
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

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
        echo json_encode([
            "success" => false, 
            "message" => "Non autorisé - Session expirée",
            "debug" => [
                "session_status" => session_status(),
                "logged_in" => $_SESSION['logged_in'] ?? 'not_set',
                "user_id" => $_SESSION['user_id'] ?? 'not_set'
            ]
        ]);
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

    // Get debt ID from request
    $dette_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$dette_id) {
        echo json_encode(["success" => false, "message" => "ID dette non fourni"]);
        exit;
    }

    // Start transaction
    $conn->beginTransaction();
    
    // Check if debt exists and is not already cancelled or paid
    $query = "SELECT id, statut, montant_restant, vente_id FROM dettes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        throw new Exception("Dette non trouvée");
    }
    
    if ($dette['statut'] == 'annulee') {
        throw new Exception("Cette dette est déjà annulée");
    }
    
    if ($dette['statut'] == 'payee') {
        throw new Exception("Impossible d'annuler une dette déjà payée");
    }
    
    // Update debt status to cancelled
    $update_query = "UPDATE dettes SET statut = 'annulee' WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // If there's a vente_id associated with this debt, update the vente status if needed
    if ($dette['vente_id']) {
        $vente_id = $dette['vente_id'];
        
        // Check if all debts for this sale are now cancelled or paid
        $check_query = "SELECT COUNT(*) as active_count FROM dettes WHERE vente_id = ? AND statut IN ('active', 'partiellement_payee')";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(1, $vente_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check_result['active_count'] == 0) {
            // No active debts remain, check if any are paid
            $paid_check_query = "SELECT COUNT(*) as paid_count FROM dettes WHERE vente_id = ? AND statut = 'payee'";
            $paid_check_stmt = $conn->prepare($paid_check_query);
            $paid_check_stmt->bindParam(1, $vente_id, PDO::PARAM_INT);
            $paid_check_stmt->execute();
            $paid_result = $paid_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($paid_result['paid_count'] > 0) {
                // Some debts were paid, mark as paid
                $vente_status = 'payee';
            } else {
                // All debts were cancelled, mark as cancelled
                $vente_status = 'annulee';
            }
            
            $vente_update_query = "UPDATE ventes SET statut_paiement = ? WHERE id = ?";
            $vente_update_stmt = $conn->prepare($vente_update_query);
            $vente_update_stmt->bindParam(1, $vente_status, PDO::PARAM_STR);
            $vente_update_stmt->bindParam(2, $vente_id, PDO::PARAM_INT);
            $vente_update_stmt->execute();
        }
    }
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'annulation', 'dette', ?, ?)";
    
    $details = "Annulation de la dette #$dette_id (montant restant: " . $dette['montant_restant'] . " BIF)";
    
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
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database error in cancel_dette.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur de base de données: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("General error in cancel_dette.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur générale: " . $e->getMessage()
    ]);
}
?>
