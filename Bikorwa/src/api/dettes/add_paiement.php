<?php
// API endpoint to add a payment for a debt
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
    echo json_encode(["success" => false, "message" => "Veuillez remplir tous les champs obligatoires"]);
    exit;
}

try {
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
    
    // Verify amount is not greater than remaining amount
    if ($montant > $dette['montant_restant']) {
        throw new Exception("Le montant du paiement ne peut pas dépasser le montant restant");
    }
    
    // Calculate new remaining amount
    $new_montant_restant = $dette['montant_restant'] - $montant;
    
    // Determine new status
    $new_status = $dette['statut'];
    if ($new_montant_restant <= 0) {
        $new_status = 'payee';
    } else if ($dette['statut'] === 'active') {
        $new_status = 'partiellement_payee';
    }
    
    // Add payment record
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
    
    // Update debt status and remaining amount
    $update_query = "UPDATE dettes SET montant_restant = ?, statut = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(1, $new_montant_restant, PDO::PARAM_STR);
    $update_stmt->bindParam(2, $new_status, PDO::PARAM_STR);
    $update_stmt->bindParam(3, $dette_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // Update associated sale (vente) if exists
    if (!empty($dette['vente_id'])) {
        // Determine sale payment status
        $vente_status = 'credit';
        if ($new_status === 'payee') {
            $vente_status = 'paye';
        } else if ($new_status === 'partiellement_payee') {
            $vente_status = 'partiel';
        }
        
        // Format current date and time
        $date_now = date('Y-m-d H:i:s');
        
        // Create note for the payment
        $vente_note = "Paiement de $montant BIF le $date_now. ";
        if ($new_status === 'payee') {
            $vente_note .= "Dette complètement réglée.";
        } else {
            $vente_note .= "Reste à payer: $new_montant_restant BIF.";
        }
        
        // Update vente record
        $vente_query = "UPDATE ventes SET statut_paiement = ?, note = CONCAT(IFNULL(note, ''), ' ', ?) WHERE id = ?";
        $vente_stmt = $conn->prepare($vente_query);
        $vente_stmt->bindParam(1, $vente_status, PDO::PARAM_STR);
        $vente_stmt->bindParam(2, $vente_note, PDO::PARAM_STR);
        $vente_stmt->bindParam(3, $dette['vente_id'], PDO::PARAM_INT);
        $vente_stmt->execute();
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
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur lors de l'enregistrement du paiement"]);
}
