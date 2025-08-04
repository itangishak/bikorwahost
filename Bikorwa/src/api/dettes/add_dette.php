<?php
// API endpoint to add a new debt
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
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$vente_id = !empty($_POST['vente_id']) ? intval($_POST['vente_id']) : null;
$montant_initial = isset($_POST['montant_initial']) ? floatval($_POST['montant_initial']) : 0;
$date_creation = !empty($_POST['date_creation']) ? $_POST['date_creation'] : date('Y-m-d');
$date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
$note = $_POST['note'] ?? '';

// Validate required fields
if (!$client_id || $montant_initial <= 0) {
    echo json_encode(["success" => false, "message" => "Veuillez remplir tous les champs obligatoires"]);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Insert new debt
    $query = "INSERT INTO dettes (client_id, vente_id, montant_initial, montant_restant, date_creation, date_echeance, note)
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $vente_id, $vente_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindParam(3, $montant_initial, PDO::PARAM_STR);
    $stmt->bindParam(4, $montant_initial, PDO::PARAM_STR); // montant_restant = montant_initial for new debt
    $stmt->bindParam(5, $date_creation, PDO::PARAM_STR);
    $stmt->bindParam(6, $date_echeance, $date_echeance ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindParam(7, $note, PDO::PARAM_STR);
    
    $stmt->execute();
    $dette_id = $conn->lastInsertId();
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'ajout', 'dette', ?, ?)";
    
    $details = "Ajout d'une nouvelle dette pour le client #$client_id d'un montant de $montant_initial BIF";
    if ($vente_id) {
        $details .= " liée à la vente #$vente_id";
    }
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $dette_id, PDO::PARAM_INT);
    $log_stmt->bindParam(3, $details, PDO::PARAM_STR);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Dette ajoutée avec succès",
        "dette_id" => $dette_id
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur lors de l'ajout de la dette"]);
}
