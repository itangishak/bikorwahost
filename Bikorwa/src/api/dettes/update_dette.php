<?php
// API endpoint to update an existing debt
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
$dette_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$vente_id = !empty($_POST['vente_id']) ? intval($_POST['vente_id']) : null;
$montant_initial = isset($_POST['montant_initial']) ? floatval($_POST['montant_initial']) : 0;
$date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
$note = $_POST['note'] ?? '';

// Validate required fields
if (!$dette_id || !$client_id || $montant_initial <= 0) {
    echo json_encode(["success" => false, "message" => "Veuillez remplir tous les champs obligatoires"]);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get current debt info for comparison and logging
    $current_query = "SELECT montant_initial, montant_restant FROM dettes WHERE id = ?";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $current_stmt->execute();
    $current_dette = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_dette) {
        throw new Exception("Dette non trouvée");
    }
    
    // Calculate new remaining amount based on proportional adjustment
    $proportion = $montant_initial / $current_dette['montant_initial'];
    $new_montant_restant = round($current_dette['montant_restant'] * $proportion, 2);
    
    // Update debt
    $query = "UPDATE dettes 
              SET client_id = ?, 
                  vente_id = ?, 
                  montant_initial = ?, 
                  montant_restant = ?,
                  date_echeance = ?, 
                  note = ?
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $vente_id, $vente_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindParam(3, $montant_initial, PDO::PARAM_STR);
    $stmt->bindParam(4, $new_montant_restant, PDO::PARAM_STR);
    $stmt->bindParam(5, $date_echeance, $date_echeance ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindParam(6, $note, PDO::PARAM_STR);
    $stmt->bindParam(7, $dette_id, PDO::PARAM_INT);
    
    $stmt->execute();
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'modification', 'dette', ?, ?)";
    
    $details = "Modification de la dette #$dette_id. Montant initial changé de {$current_dette['montant_initial']} à $montant_initial BIF";
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $dette_id, PDO::PARAM_INT);
    $log_stmt->bindParam(3, $details, PDO::PARAM_STR);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Dette mise à jour avec succès"
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
    echo json_encode(["success" => false, "message" => "Erreur lors de la mise à jour de la dette"]);
}
