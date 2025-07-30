<?php
/**
 * AJAX endpoint to get product batch information for FIFO inventory tracking
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and config
require_once('./../../config/database.php');
require_once('./../../config/config.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Set header to JSON
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action'
    ]);
    exit;
}

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Set header to JSON
header('Content-Type: application/json');

// Check if product ID is provided
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du produit requis'
    ]);
    exit;
}

try {
    $product_id = intval($_GET['product_id']);
    
    // Get product info for reference
    $query = "SELECT p.nom, p.code, p.unite_mesure, COALESCE(s.quantite, 0) as stock_total
              FROM produits p
              LEFT JOIN stock s ON p.id = s.produit_id
              WHERE p.id = :product_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception("Produit introuvable.");
    }
    
    // Get batches with remaining quantity (for FIFO tracking)
    $query = "SELECT
                ms.id,
                ms.quantite as quantite_initiale,
                ms.quantity_remaining as quantite_restante,
                ms.prix_unitaire,
                ms.prix_vente,
                ms.valeur_totale,
                ms.date_mouvement,
                ms.reference,
                ms.note
              FROM mouvements_stock ms
              WHERE ms.produit_id = :product_id 
              AND ms.type_mouvement = 'entree' 
              AND ms.quantity_remaining > 0
              ORDER BY ms.date_mouvement ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $batches = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $batches[] = $row;
    }
    
    // Format data for response
    $response = [
        'success' => true,
        'product' => $product,
        'batches' => $batches,
        'total_batches' => count($batches)
    ];
    
    // Calculate total value of remaining stock
    $total_value = 0;
    foreach ($batches as $batch) {
        $total_value += $batch['prix_unitaire'] * $batch['quantite_restante'];
    }
    $response['total_batch_value'] = $total_value;
    
    // Send success response
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
