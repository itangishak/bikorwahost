<?php
// API endpoint to fetch debt details with payment history
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

// Get debt ID from request
$dette_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$dette_id) {
    echo json_encode(["success" => false, "message" => "ID dette non fourni"]);
    exit;
}

try {
    // Get debt details
    $query = "SELECT d.*, c.nom as client_nom, v.numero_facture
              FROM dettes d
              LEFT JOIN clients c ON d.client_id = c.id
              LEFT JOIN ventes v ON d.vente_id = v.id
              WHERE d.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        echo json_encode(["success" => false, "message" => "Dette non trouvée"]);
        exit;
    }
    
    // Get payment history
    $payments_query = "SELECT pd.*, u.nom as utilisateur_nom 
                       FROM paiements_dettes pd
                       LEFT JOIN users u ON pd.utilisateur_id = u.id
                       WHERE pd.dette_id = ?
                       ORDER BY pd.date_paiement DESC";
    
    $payments_stmt = $conn->prepare($payments_query);
    $payments_stmt->bindParam(1, $dette_id, PDO::PARAM_INT);
    $payments_stmt->execute();
    
    $paiements = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true, 
        "dette" => $dette,
        "paiements" => $paiements
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données"]);
}
