<?php
// API endpoint to add a new sale with FIFO inventory management
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/db_connect.php';

// Set header to JSON
header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Get the user ID
$utilisateur_id = $_SESSION['user_id'];

try {
    // Start a transaction
    $conn->begin_transaction();

    // Extract data from the POST request
    $client_id = isset($_POST['client_id']) && !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $date_vente = $_POST['date_vente'] ?? date('Y-m-d H:i:s');
    $montant_total = floatval($_POST['montant_total']);
    $montant_paye = floatval($_POST['montant_paye']);
    $statut_paiement = $_POST['statut_paiement'];
    $note = $_POST['note'] ?? '';
    
    // Validate payment status
    if (!in_array($statut_paiement, ['paye', 'partiel', 'credit'])) {
        throw new Exception('Statut de paiement invalide');
    }
    
    // Parse products from JSON
    $produits = json_decode($_POST['produits'], true);
    if (!$produits || !is_array($produits) || count($produits) === 0) {
        throw new Exception('Aucun produit dans la vente');
    }
    
    // Generate invoice number (format: INV-YYYYMMDD-XXXX)
    $today = date('Ymd');
    $query = "SELECT MAX(SUBSTRING(numero_facture, 13)) as max_num 
              FROM ventes 
              WHERE numero_facture LIKE 'INV-$today-%'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $next_num = intval($row['max_num'] ?? 0) + 1;
    $numero_facture = "INV-$today-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    
    // Insert sale into ventes table
    $query = "INSERT INTO ventes (numero_facture, date_vente, client_id, utilisateur_id, 
                montant_total, montant_paye, statut_paiement, note)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiiidss", $numero_facture, $date_vente, $client_id, $utilisateur_id, 
                      $montant_total, $montant_paye, $statut_paiement, $note);
    $stmt->execute();
    $vente_id = $stmt->insert_id;
    $stmt->close();
    
    // Create debt record if payment is partial or on credit
    if ($statut_paiement === 'partiel' || $statut_paiement === 'credit') {
        $montant_restant = $montant_total - $montant_paye;
        
        // Check if we have a client
        if (!$client_id) {
            throw new Exception('Un client est requis pour les ventes à crédit ou paiement partiel');
        }
        
        $query = "INSERT INTO dettes (client_id, vente_id, montant_initial, montant_restant, date_creation, statut)
                  VALUES (?, ?, ?, ?, NOW(), ?)";
        $statut_dette = $montant_paye > 0 ? 'partiellement_payee' : 'active';
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iidds", $client_id, $vente_id, $montant_total, $montant_restant, $statut_dette);
        $stmt->execute();
        $stmt->close();
    }
    
    // Insert each product and update stock with FIFO logic
    $total_benefice = 0;
    
    foreach ($produits as $produit) {
        $produit_id = intval($produit['id']);
        $prix_vente = floatval($produit['prix']);
        $quantite = floatval($produit['quantite']);
        
        // Calculate total for this product
        $montant_produit = $prix_vente * $quantite;
        
        // FIFO Implementation: Get the oldest stock entries first
        $queryStockEntries = "SELECT 
                                id, 
                                quantite, 
                                quantity_remaining, 
                                prix_unitaire 
                              FROM 
                                mouvements_stock 
                              WHERE 
                                produit_id = ? 
                                AND type_mouvement = 'entree' 
                                AND quantity_remaining > 0 
                              ORDER BY 
                                date_mouvement ASC";
        
        $stmtStockEntries = $conn->prepare($queryStockEntries);
        $stmtStockEntries->bind_param("i", $produit_id);
        $stmtStockEntries->execute();
        $resultStockEntries = $stmtStockEntries->get_result();
        $stmtStockEntries->close();
        
        // Verify if there's enough stock
        $totalAvailableStock = 0;
        $stockEntries = [];
        
        while ($entry = $resultStockEntries->fetch_assoc()) {
            $totalAvailableStock += $entry['quantity_remaining'];
            $stockEntries[] = $entry;
        }
        
        if ($totalAvailableStock < $quantite) {
            throw new Exception("Stock insuffisant pour le produit ID: $produit_id");
        }
        
        // Calculate weighted average cost (FIFO method)
        $remaining_quantity = $quantite;
        $total_cost = 0;
        $batches_used = [];
        
        foreach ($stockEntries as $entry) {
            if ($remaining_quantity <= 0) break;
            
            $used_from_batch = min($entry['quantity_remaining'], $remaining_quantity);
            $cost_from_batch = $used_from_batch * $entry['prix_unitaire'];
            
            $total_cost += $cost_from_batch;
            $remaining_quantity -= $used_from_batch;
            
            $batches_used[] = [
                'mouvement_id' => $entry['id'],
                'quantite_utilisee' => $used_from_batch,
                'quantite_restante' => $entry['quantity_remaining'] - $used_from_batch,
                'prix_unitaire' => $entry['prix_unitaire']
            ];
        }
        
        $prix_achat_moyen = $total_cost / $quantite;
        $benefice_produit = ($prix_vente - $prix_achat_moyen) * $quantite;
        $total_benefice += $benefice_produit;
        
        // Insert sale detail
        $query = "INSERT INTO details_ventes (vente_id, produit_id, quantite, prix_unitaire, 
                    montant_total, prix_achat_unitaire, benefice)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiddddd", $vente_id, $produit_id, $quantite, $prix_vente, 
                         $montant_produit, $prix_achat_moyen, $benefice_produit);
        $stmt->execute();
        $stmt->close();
        
        // Update stock for each batch used (FIFO)
        foreach ($batches_used as $batch) {
            // Update the remaining quantity in the stock entry
            $query = "UPDATE mouvements_stock 
                      SET quantity_remaining = ? 
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("di", $batch['quantite_restante'], $batch['mouvement_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Create stock movement for this sale (use sale date for synchronization)
        $query = "INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, 
                    prix_unitaire, valeur_totale, reference, date_mouvement, utilisateur_id, note)
                  VALUES (?, 'sortie', ?, ?, ?, ?, ?, ?, ?)";
        $reference = "Vente #" . $numero_facture;
        $valeur_totale = $prix_vente * $quantite;
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("idddssis", $produit_id, $quantite, $prix_vente, $valeur_totale, 
                         $reference, $date_vente, $utilisateur_id, $note);
        $stmt->execute();
        $stmt->close();
        
        // Update the main stock table
        $query = "UPDATE stock 
                  SET quantite = quantite - ?, 
                      date_mise_a_jour = NOW() 
                  WHERE produit_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("di", $quantite, $produit_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Log the activity
    $action = "Nouvelle vente créée";
    $entite = "ventes";
    $details = "Vente #{$numero_facture} créée pour un montant de {$montant_total} BIF";
    
    $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issis", $utilisateur_id, $action, $entite, $vente_id, $details);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Vente enregistrée avec succès',
        'vente_id' => $vente_id,
        'numero_facture' => $numero_facture
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
