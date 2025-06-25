<?php
/**
 * API endpoint to get invoices (ventes) for a specific client
 * Used in the debt management module to associate debts with specific invoices
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

// Check if client_id is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du client requis'
    ]);
    exit;
}

$client_id = (int) $_GET['client_id'];

try {
    // Get client's invoices (ventes)
    // Prioritize invoices without associated debts or with partially paid debts
    $sql = "SELECT v.*, 
                  (SELECT COUNT(*) FROM dettes d WHERE d.vente_id = v.id AND d.statut != 'annulee') as has_active_dette
           FROM ventes v 
           WHERE v.client_id = ? AND v.statut = 'complété'
           ORDER BY has_active_dette ASC, v.date_vente DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success with invoices
    echo json_encode([
        'success' => true,
        'invoices' => $invoices
    ]);
    
} catch (PDOException $e) {
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible de récupérer les factures du client'
    ]);
}
