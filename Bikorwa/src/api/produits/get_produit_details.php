<?php
// API endpoint to get detailed product information including FIFO batches

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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du produit requis'
    ]);
    exit;
}

try {
    $produit_id = intval($_GET['id']);
    
    // Get basic product info
    $query = "SELECT 
                p.id,
                p.code,
                p.nom,
                p.description,
                p.categorie_id,
                c.nom AS categorie_nom,
                p.unite_mesure,
                COALESCE(s.quantite, 0) AS quantite_stock,
                pp.prix_achat,
                pp.prix_vente,
                p.date_creation,
                p.actif
              FROM 
                produits p
              LEFT JOIN 
                categories c ON p.categorie_id = c.id
              LEFT JOIN 
                stock s ON p.id = s.produit_id
              LEFT JOIN (
                SELECT 
                  produit_id, 
                  prix_achat, 
                  prix_vente
                FROM 
                  prix_produits
                WHERE 
                  date_fin IS NULL OR date_fin = (
                    SELECT MAX(date_fin)
                    FROM prix_produits pp2
                    WHERE pp2.produit_id = prix_produits.produit_id
                  )
              ) pp ON p.id = pp.produit_id
              WHERE 
                p.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $produit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produit = $result->fetch_assoc();
    $stmt->close();
    
    if (!$produit) {
        throw new Exception("Produit introuvable");
    }
    
    // Get FIFO batches (only if stock > 0)
    $produit['batches'] = [];
    if ($produit['quantite_stock'] > 0) {
        $query = "SELECT 
                    id,
                    quantite AS quantite_initiale,
                    quantity_remaining AS quantite_restante,
                    prix_unitaire,
                    valeur_totale,
                    date_mouvement,
                    reference
                  FROM 
                    mouvements_stock
                  WHERE 
                    produit_id = ? 
                    AND type_mouvement = 'entree' 
                    AND quantity_remaining > 0
                  ORDER BY 
                    date_mouvement ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $produit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($batch = $result->fetch_assoc()) {
            $produit['batches'][] = $batch;
        }
        $stmt->close();
        
        // Calculate weighted average cost using FIFO
        $total_cost = 0;
        $total_quantity = 0;
        
        foreach ($produit['batches'] as $batch) {
            $batch_cost = $batch['prix_unitaire'] * $batch['quantite_restante'];
            $total_cost += $batch_cost;
            $total_quantity += $batch['quantite_restante'];
        }
        
        $produit['cout_moyen_pondere'] = $total_quantity > 0 ? $total_cost / $total_quantity : 0;
        $produit['valeur_stock_total'] = $total_cost;
    } else {
        $produit['cout_moyen_pondere'] = 0;
        $produit['valeur_stock_total'] = 0;
    }
    
    // Get price history
    $query = "SELECT 
                id,
                prix_achat,
                prix_vente,
                date_debut,
                date_fin
              FROM 
                prix_produits
              WHERE 
                produit_id = ?
              ORDER BY 
                date_debut DESC
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $produit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prix_history = [];
    
    while ($prix = $result->fetch_assoc()) {
        $prix_history[] = $prix;
    }
    $stmt->close();
    
    $produit['prix_history'] = $prix_history;
    
    // Get recent stock movements
    $query = "SELECT 
                id,
                type_mouvement,
                quantite,
                prix_unitaire,
                valeur_totale,
                date_mouvement,
                reference
              FROM 
                mouvements_stock
              WHERE 
                produit_id = ?
              ORDER BY 
                date_mouvement DESC
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $produit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $mouvements = [];
    
    while ($mouvement = $result->fetch_assoc()) {
        $mouvements[] = $mouvement;
    }
    $stmt->close();
    
    $produit['mouvements_recents'] = $mouvements;
    
    // Return the product details
    echo json_encode([
        'success' => true,
        'produit' => $produit
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
