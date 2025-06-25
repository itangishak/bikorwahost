<?php
/**
 * API endpoint to get client's unpaid sales for select dropdown
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

// Check if client_id is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID client requis'
    ]);
    exit;
}

$client_id = (int) $_GET['client_id'];

try {
    // Get unpaid/partially paid sales for this client
    $sql = "SELECT id, numero_facture, montant_total, date_vente 
            FROM ventes 
            WHERE client_id = ? 
            AND statut_paiement IN ('partiel', 'credit') 
            AND statut_vente = 'active'
            ORDER BY date_vente DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sales' => $sales
    ]);
} catch (PDOException $e) {
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible de récupérer les ventes'
    ]);
}
