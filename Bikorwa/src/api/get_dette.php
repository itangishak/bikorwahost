<?php
/**
 * API endpoint to get a single dette with details
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

// Check if dette_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de dette requis'
    ]);
    exit;
}

$dette_id = (int) $_GET['id'];

try {
    // Get dette details
    $sql = "SELECT d.*, c.nom as client_nom, v.numero_facture,
            (SELECT MAX(date_paiement) FROM paiements_dettes WHERE dette_id = d.id) as date_dernier_paiement
            FROM dettes d
            LEFT JOIN clients c ON d.client_id = c.id
            LEFT JOIN ventes v ON d.vente_id = v.id
            WHERE d.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dette_id]);
    $dette = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        echo json_encode([
            'success' => false,
            'message' => 'Dette non trouvée'
        ]);
        exit;
    }
    
    // Get paiements for this dette
    $sql_paiements = "SELECT p.*, u.nom as utilisateur_nom
                     FROM paiements_dettes p
                     LEFT JOIN users u ON p.utilisateur_id = u.id
                     WHERE p.dette_id = ?
                     ORDER BY p.date_paiement DESC";
    
    $stmt_paiements = $pdo->prepare($sql_paiements);
    $stmt_paiements->execute([$dette_id]);
    $paiements = $stmt_paiements->fetchAll(PDO::FETCH_ASSOC);
    
    // Add paiements to dette data
    $dette['paiements'] = $paiements;
    
    echo json_encode([
        'success' => true,
        'dette' => $dette
    ]);
} catch (PDOException $e) {
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible de récupérer les détails de la dette'
    ]);
}
