<?php
// API endpoint to add a payment for a debt
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

    // Get user ID for logging
    $user_id = $_SESSION['user_id'] ?? 0;

    // Get POST data
    $dette_id = isset($_POST['dette_id']) ? intval($_POST['dette_id']) : 0;
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
    $methode_paiement = $_POST['methode_paiement'] ?? '';
    $reference = $_POST['reference'] ?? '';
    $note = $_POST['note_paiement'] ?? '';

    // Validate required fields
    if (!$dette_id || $montant <= 0 || empty($methode_paiement)) {
        echo json_encode([
            "success" => false, 
            "message" => "Veuillez remplir tous les champs obligatoires",
            "debug" => [
                "dette_id" => $dette_id,
                "montant" => $montant,
                "methode_paiement" => $methode_paiement,
                "post_data" => $_POST
            ]
        ]);
        exit;
    }

    // Start transaction
    $conn->beginTransaction();
    
    // Get current debt information including vente_id
    $query = "SELECT client_id, montant_restant, statut, montant_initial, vente_id FROM dettes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $stmt->execute();
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        throw new Exception("Dette non trouvée");
    }
    
    if ($dette['statut'] == 'payee') {
        throw new Exception("Cette dette est déjà entièrement payée");
    }
    
    if ($dette['statut'] == 'annulee') {
        throw new Exception("Cette dette a été annulée");
    }
    
    if ($montant > $dette['montant_restant']) {
        throw new Exception("Le montant du paiement ne peut pas dépasser le montant restant (" . $dette['montant_restant'] . " BIF)");
    }
    
    // Insert payment record
    $payment_query = "INSERT INTO paiements_dettes (dette_id, montant, methode_paiement, reference, note, utilisateur_id)
                      VALUES (?, ?, ?, ?, ?, ?)";
    
    $payment_stmt = $conn->prepare($payment_query);
    $payment_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $payment_stmt->bindParam(2, $montant, PDO::PARAM_STR);
    $payment_stmt->bindParam(3, $methode_paiement, PDO::PARAM_STR);
    $payment_stmt->bindParam(4, $reference, PDO::PARAM_STR);
    $payment_stmt->bindParam(5, $note, PDO::PARAM_STR);
    $payment_stmt->bindParam(6, $user_id, PDO::PARAM_INT);
    $payment_stmt->execute();
    
    $payment_id = $conn->lastInsertId();
    
    // Update debt amount and status
    $nouveau_montant_restant = $dette['montant_restant'] - $montant;
    $nouveau_statut = ($nouveau_montant_restant <= 0) ? 'payee' : 'partiellement_payee';
    
    $update_query = "UPDATE dettes SET montant_restant = ?, statut = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(1, $nouveau_montant_restant, PDO::PARAM_STR);
    $update_stmt->bindParam(2, $nouveau_statut, PDO::PARAM_STR);
    $update_stmt->bindParam(3, $dette_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // If there's a vente_id associated with this debt, update the vente status
    if ($dette['vente_id']) {
        $vente_id = $dette['vente_id'];
        
        // Check if all debts for this sale are now paid
        $check_query = "SELECT COUNT(*) as unpaid_count FROM dettes WHERE vente_id = ? AND statut IN ('active', 'partiellement_payee')";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(1, $vente_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check_result['unpaid_count'] == 0) {
            // All debts are paid, update vente status to 'payee'
            $vente_update_query = "UPDATE ventes SET statut_paiement = 'payee' WHERE id = ?";
            $vente_update_stmt = $conn->prepare($vente_update_query);
            $vente_update_stmt->bindParam(1, $vente_id, PDO::PARAM_INT);
            $vente_update_stmt->execute();
        }
    }
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'paiement', 'dette', ?, ?)";
    
    $details = "Paiement de $montant BIF pour la dette #$dette_id";
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $dette_id, PDO::PARAM_INT);
    $log_stmt->bindParam(3, $details, PDO::PARAM_STR);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Paiement enregistré avec succès",
        "payment_id" => $payment_id
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database error in add_paiement.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur de base de données: " . $e->getMessage(),
        "debug" => [
            "error_code" => $e->getCode(),
            "dette_id" => $dette_id ?? 'not_set'
        ]
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("General error in add_paiement.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur générale: " . $e->getMessage()
    ]);
}
?>
