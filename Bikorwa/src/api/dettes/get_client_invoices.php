<?php
// API endpoint to fetch client invoices for debt management
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

// Get client ID from request
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if (!$client_id) {
    echo json_encode(["success" => false, "message" => "ID client non fourni"]);
    exit;
}

try {
    // Get unpaid or partially paid invoices for this client
    $query = "SELECT v.id, v.numero_facture, v.montant_total, v.montant_paye, v.date_vente, 
             (v.montant_total - v.montant_paye) as montant_restant
             FROM ventes v 
             WHERE v.client_id = ? 
             AND v.statut_vente = 'active'
             AND v.statut_paiement IN ('partiel', 'credit')
             AND NOT EXISTS (
                 SELECT 1 FROM dettes d 
                 WHERE d.vente_id = v.id 
                 AND d.statut IN ('active', 'partiellement_payee')
             )
             ORDER BY v.date_vente DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["success" => true, "invoices" => $invoices]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de base de données"]);
}
