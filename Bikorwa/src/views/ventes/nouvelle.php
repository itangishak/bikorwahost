<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and config
require_once('../../config/database.php');
require_once('../../config/config.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// AJAX handler for get_clients - Integrated directly in this file
if (isset($_GET['action']) && $_GET['action'] === 'get_clients') {
    header('Content-Type: application/json');
    
    // Set default values and get search parameters
    $search = $_GET['search'] ?? '';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Build the base query
    $query = "SELECT * FROM clients WHERE 1=1";
    $count_query = "SELECT COUNT(*) AS total FROM clients WHERE 1=1";
    $params = [];
    $count_params = [];
    
    // Add search conditions if any
    if (!empty($search)) {
        $query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR adresse LIKE ?)";
        $count_query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR adresse LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param, $search_param, $search_param);
        array_push($count_params, $search_param, $search_param, $search_param, $search_param);
    }
    
    // Add order by and pagination
    $query .= " ORDER BY date_creation DESC, id DESC LIMIT ? OFFSET ?";
    
    try {
        // Execute count query for pagination
        $count_stmt = $pdo->prepare($count_query);

        // Bind parameters for count query if any
        if (!empty($count_params)) {
            for ($i = 0; $i < count($count_params); $i++) {
                $count_stmt->bindParam($i + 1, $count_params[$i]);
            }
        }

        $count_stmt->execute();
        $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_rows = $result['total'];
        $total_pages = ceil($total_rows / $items_per_page);

        // Make sure current page is valid
        if ($current_page < 1) $current_page = 1;
        if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

        // Recalculate offset based on validated current page
        $offset = ($current_page - 1) * $items_per_page;

        // Execute the main query
        $stmt = $pdo->prepare($query);

        // Bind parameters if any
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindParam($i + 1, $params[$i]);
        }

        // Bind pagination parameters
        $param_index = count($params) + 1;
        $stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
        $stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the data
        echo json_encode([
            'success' => true,
            'clients' => $clients,
            'total_count' => $total_rows
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
        exit;
    }
}

// AJAX handler for get_produits - Integrated directly in this file
if (isset($_GET['action']) && $_GET['action'] === 'get_produits') {
    header('Content-Type: application/json');
    
    try {
        // Get the page and search parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) ? addslashes($_GET['search']) : '';
        $with_stock = isset($_GET['with_stock']) && $_GET['with_stock'] === 'true';
        
        // Base query for products - Use INNER JOIN with stock for with_stock=true to ensure results have stock
        $baseQuery = "FROM produits p";
        
        // Use INNER JOIN when filtering for products with stock, otherwise LEFT JOIN
        if ($with_stock) {
            $baseQuery .= " INNER JOIN stock s ON p.id = s.produit_id AND s.quantite > 0";
        } else {
            $baseQuery .= " LEFT JOIN stock s ON p.id = s.produit_id";
        }
        
        $baseQuery .= " LEFT JOIN (
                          SELECT produit_id, prix_achat, prix_vente
                          FROM prix_produits
                          WHERE date_fin IS NULL
                      ) pp ON p.id = pp.produit_id
                      WHERE p.actif = 1";
        
        // Add search condition if provided
        if (!empty($search)) {
            $baseQuery .= " AND (p.nom LIKE '%$search%' OR p.code LIKE '%$search%' OR p.description LIKE '%$search%')";
        }
        
        // Add stock condition if requested
        if ($with_stock) {
            $baseQuery .= " AND (s.quantite > 0)";
        }
        
        // Count total products
        $countQuery = "SELECT COUNT(*) as total $baseQuery";
        $countStmt = $pdo->query($countQuery);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = $countResult['total'];
        
        // Fetch products with pagination
        $query = "SELECT 
                    p.id,
                    p.code,
                    p.nom,
                    p.description,
                    COALESCE(s.quantite, 0) as quantite_stock,
                    COALESCE(pp.prix_achat, 0) as prix_achat,
                    COALESCE(pp.prix_vente, 0) as prix_vente,
                    CASE WHEN s.quantite > 0 THEN true ELSE false END as has_stock
                  $baseQuery
                  ORDER BY p.nom";
        $query .= " LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($query);
        
        $produits = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get available stock with FIFO batches
            if ($with_stock && $row['quantite_stock'] > 0) {
                // Get the FIFO batches for this product
                $fifoQuery = "SELECT
                                ms.produit_id,
                                ms.quantity_remaining,
                                ms.prix_unitaire,
                                ms.prix_vente,
                                ms.date_mouvement
                               FROM
                                mouvements_stock ms
                               WHERE 
                                ms.produit_id = :id 
                                AND ms.type_mouvement = 'entree'
                                AND ms.quantity_remaining > 0 
                               ORDER BY 
                                ms.date_mouvement ASC";
                
                $stmtFifo = $pdo->prepare($fifoQuery);
                $stmtFifo->bindParam(":id", $row['id'], PDO::PARAM_INT);
                $stmtFifo->execute();
                
                $batches = [];
                while ($batch = $stmtFifo->fetch(PDO::FETCH_ASSOC)) {
                    $batches[] = [
                        'quantity'   => $batch['quantity_remaining'],
                        'prix_achat' => $batch['prix_unitaire'],
                        'prix_vente' => $batch['prix_vente']
                    ];
                }
                
                $row['batches'] = $batches;
            }
            
            $produits[] = $row;
        }
        
        // Return the response
        echo json_encode([
            'success' => true,
            'produits' => $produits,
            'total_count' => $totalCount,
        ]);
        exit;
        
    } catch (Exception $e) {
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// AJAX handler for add_vente - Integrated directly in this file
if (isset($_GET['action']) && $_GET['action'] === 'add_vente') {
    header('Content-Type: application/json');
    
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
        $pdo->beginTransaction();
        
        // Extract data from the POST request
        $client_id = isset($_POST['client_id']) && !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        $date_vente = $_POST['date_vente'] ?? date('Y-m-d H:i:s');
        $montant_total = floatval($_POST['montant_total']);
        $montant_paye = floatval($_POST['montant_paye']);
        $statut_paiement = $_POST['statut_paiement'];
        $note = $_POST['note'] ?? '';
        $montant_restant = $montant_total - $montant_paye;
        
        // Validate payment status
        if (!in_array($statut_paiement, ['paye', 'partiel', 'credit'])) {
            throw new Exception('Statut de paiement invalide');
        }
        
        // Parse products from JSON
        $produits = json_decode($_POST['produits'], true);
        if (!$produits || !is_array($produits) || count($produits) === 0) {
            throw new Exception('Aucun produit dans la vente');
        }

        // Check credit limit if sale on credit
        if ($statut_paiement === 'credit') {
            if (!$client_id) {
                throw new Exception('Un client est requis pour les ventes à crédit');
            }

            $limit_query = "SELECT limite_credit FROM clients WHERE id = ?";
            $limit_stmt = $pdo->prepare($limit_query);
            $limit_stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $limit_stmt->execute();
            $client_limit = $limit_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client_limit) {
                throw new Exception('Client non trouvé.');
            }

            $limite_credit = floatval($client_limit['limite_credit']);

            $dette_query = "SELECT SUM(montant_restant) AS total_restant FROM dettes WHERE client_id = ? AND statut != 'annulee'";
            $dette_stmt = $pdo->prepare($dette_query);
            $dette_stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $dette_stmt->execute();
            $dette_result = $dette_stmt->fetch(PDO::FETCH_ASSOC);
            $total_restant = $dette_result && $dette_result['total_restant'] ? floatval($dette_result['total_restant']) : 0;

            if ($total_restant + $montant_restant > $limite_credit) {
                throw new Exception('Limite de crédit dépassée pour ce client.');
            }
        }
        
        // Generate unique invoice number format IN-YYYYMMDD-XXXX
        $date_format = date('Ymd');
        
        // Get the highest invoice number for today to avoid duplicates
        $query = "SELECT MAX(SUBSTRING_INDEX(numero_facture, '-', -1)) as max_count 
                  FROM ventes 
                  WHERE numero_facture LIKE 'IN-{$date_format}-%'";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If there are existing invoices today, increment the highest number
        if ($result['max_count']) {
            $count = intval($result['max_count']) + 1;
        } else {
            $count = 1; // Start with 1 if no invoices exist for today
        }
        
        $count_formatted = str_pad($count, 4, '0', STR_PAD_LEFT);
        $numero_facture = "IN-{$date_format}-{$count_formatted}";
        
        // Double-check to make sure this invoice number doesn't already exist
        $checkQuery = "SELECT COUNT(*) as exists_count FROM ventes WHERE numero_facture = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(1, $numero_facture, PDO::PARAM_STR);
        $checkStmt->execute();
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // If it somehow still exists, append a unique identifier
        if ($checkResult['exists_count'] > 0) {
            $numero_facture = $numero_facture . '-' . uniqid();
        }
        
        // Insert sale into ventes table
        $query = "INSERT INTO ventes (numero_facture, date_vente, client_id, utilisateur_id, 
                    montant_total, montant_paye, statut_paiement, note)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $numero_facture, PDO::PARAM_STR);
        $stmt->bindParam(2, $date_vente, PDO::PARAM_STR); 
        $stmt->bindParam(3, $client_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $utilisateur_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $montant_total, PDO::PARAM_STR);
        $stmt->bindParam(6, $montant_paye, PDO::PARAM_STR);
        $stmt->bindParam(7, $statut_paiement, PDO::PARAM_STR);
        $stmt->bindParam(8, $note, PDO::PARAM_STR);
        $stmt->execute();
        $vente_id = $pdo->lastInsertId();
        
        // Create debt record if payment is partial or on credit
        if ($statut_paiement === 'partiel' || $statut_paiement === 'credit') {
            
            // Check if we have a client
            if (!$client_id) {
                throw new Exception('Un client est requis pour les ventes à crédit ou paiement partiel');
            }
            
            $query = "INSERT INTO dettes (client_id, vente_id, montant_initial, montant_restant, date_creation, statut)
                      VALUES (?, ?, ?, ?, NOW(), ?)";
            $statut_dette = $montant_paye > 0 ? 'partiellement_payee' : 'active';
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $vente_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $montant_total, PDO::PARAM_STR);
            $stmt->bindParam(4, $montant_restant, PDO::PARAM_STR);
            $stmt->bindParam(5, $statut_dette, PDO::PARAM_STR);
            $stmt->execute();
        }
        
        // Insert each product and update stock with FIFO logic
        $total_benefice = 0;
        
        foreach ($produits as $produit) {
            $produit_id = intval($produit['id']);
            $prix_vente = floatval($produit['prix']);
            $quantite = floatval($produit['quantite']);
            
            // Calculate total for this product
            $montant_produit = $prix_vente * $quantite;
            
            // First check the total stock in the stock table for a quick validation
            $queryTotalStock = "SELECT quantite FROM stock WHERE produit_id = ?";
            $stmtTotalStock = $pdo->prepare($queryTotalStock);
            $stmtTotalStock->bindParam(1, $produit_id, PDO::PARAM_INT);
            $stmtTotalStock->execute();
            $totalStockRow = $stmtTotalStock->fetch(PDO::FETCH_ASSOC);
            
            if (!$totalStockRow || floatval($totalStockRow['quantite']) < $quantite) {
                throw new Exception('Stock insuffisant pour le produit ID: ' . $produit_id);
            }
            
            // FIFO Implementation: Get the oldest stock entries first
            $queryStockEntries = "SELECT 
                                    id, 
                                    quantite, 
                                    prix_unitaire,
                                    quantity_remaining
                                FROM mouvements_stock 
                                WHERE produit_id = ? 
                                    AND type_mouvement = 'entree' 
                                    AND quantity_remaining > 0 
                                ORDER BY date_mouvement ASC";
            $stmtStockEntries = $pdo->prepare($queryStockEntries);
            $stmtStockEntries->bindParam(1, $produit_id, PDO::PARAM_INT);
            $stmtStockEntries->execute();
            $stockEntries = $stmtStockEntries->fetchAll(PDO::FETCH_ASSOC);
            
            // Double check with FIFO batches - if there's a discrepancy with the main stock table
            // this allows for a safety check but trusts the main stock table first
            if (count($stockEntries) === 0) {
                // If we have no entries in mouvements_stock but stock table says we have inventory,
                // we'll trust the stock table and continue
                $stockEntries = [
                    [
                        'id' => 0, // Dummy ID
                        'quantite' => $totalStockRow['quantite'],
                        'prix_unitaire' => 0, // We don't know the purchase price
                        'quantity_remaining' => $totalStockRow['quantite']
                    ]
                ];
            }
            
            // Calculate weighted average cost (FIFO method) - Improved FIFO tracking
            $remaining_quantity = $quantite;
            $total_cost = 0;
            $batches_used = [];
            $batch_details = []; // For logging/debugging purposes
            
            foreach ($stockEntries as $entry) {
                if ($remaining_quantity <= 0) break;
                
                // Skip dummy entries (from fallback mechanism)
                if ($entry['id'] == 0) {
                    // If using fallback, use current purchase price or a default
                    // Get the latest purchase price from prix_produits
                    $queryLatestPrice = "SELECT prix_achat FROM prix_produits WHERE produit_id = ? AND date_fin IS NULL LIMIT 1";
                    $stmtLatestPrice = $pdo->prepare($queryLatestPrice);
                    $stmtLatestPrice->bindParam(1, $produit_id, PDO::PARAM_INT);
                    $stmtLatestPrice->execute();
                    $latestPrice = $stmtLatestPrice->fetch(PDO::FETCH_ASSOC);
                    
                    $prix_achat = $latestPrice ? floatval($latestPrice['prix_achat']) : $prix_vente * 0.7; // Default to 70% of selling price if no data
                    
                    $used_from_batch = min($entry['quantity_remaining'], $remaining_quantity);
                    $cost_from_batch = $used_from_batch * $prix_achat;
                    
                    $total_cost += $cost_from_batch;
                    $remaining_quantity -= $used_from_batch;
                    
                    // For dummy entries, we don't track in batches_used since there's no real mouvement_id to update
                    $batch_details[] = [
                        'type' => 'dummy',
                        'quantite_utilisee' => $used_from_batch,
                        'prix_unitaire' => $prix_achat
                    ];
                    continue;
                }
                
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
                
                $batch_details[] = [
                    'type' => 'real',
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
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(1, $vente_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $produit_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $quantite, PDO::PARAM_STR);
            $stmt->bindParam(4, $prix_vente, PDO::PARAM_STR);
            $stmt->bindParam(5, $montant_produit, PDO::PARAM_STR);
            $stmt->bindParam(6, $prix_achat_moyen, PDO::PARAM_STR);
            $stmt->bindParam(7, $benefice_produit, PDO::PARAM_STR);
            $stmt->execute();
            
            // Update stock quantities for each batch used
            foreach ($batches_used as $batch) {
                // Update quantity_remaining in mouvements_stock
                $queryUpdateBatch = "UPDATE mouvements_stock 
                                    SET quantity_remaining = ? 
                                    WHERE id = ?";
                $stmtUpdateBatch = $pdo->prepare($queryUpdateBatch);
                $stmtUpdateBatch->bindParam(1, $batch['quantite_restante'], PDO::PARAM_STR);
                $stmtUpdateBatch->bindParam(2, $batch['mouvement_id'], PDO::PARAM_INT);
                $stmtUpdateBatch->execute();
                
                // Log movement details for debugging
                $logMessage = "FIFO: Used " . $batch['quantite_utilisee'] . " units from batch ID " . 
                              $batch['mouvement_id'] . ", remaining: " . $batch['quantite_restante'];
                $queryLog = "INSERT INTO journal_activites (utilisateur_id, action, details, date_action) 
                           VALUES (?, 'FIFO_DEBUG', ?, NOW())";
                $stmtLog = $pdo->prepare($queryLog);
                $stmtLog->bindParam(1, $utilisateur_id, PDO::PARAM_INT);
                $stmtLog->bindParam(2, $logMessage, PDO::PARAM_STR);
                $stmtLog->execute();
            }
            
            // Create a reference for traceability using the invoice number
            // This allows cancellation logic to reliably locate the movement
            // record when reversing the sale.
            $reference = 'Vente #' . $numero_facture;
            
            // Insert sortie record in mouvements_stock
            $queryInsertSortie = "INSERT INTO mouvements_stock 
                                (produit_id, type_mouvement, quantite, 
                                prix_unitaire, valeur_totale, utilisateur_id, quantity_remaining, reference) 
                                VALUES (?, 'sortie', ?, ?, ?, ?, 0, ?)";
            $stmtInsertSortie = $pdo->prepare($queryInsertSortie);
            $stmtInsertSortie->bindParam(1, $produit_id, PDO::PARAM_INT);
            $stmtInsertSortie->bindParam(2, $quantite, PDO::PARAM_STR);
            $stmtInsertSortie->bindParam(3, $prix_achat_moyen, PDO::PARAM_STR);
            $valeur_totale_sortie = $prix_achat_moyen * $quantite;
            $stmtInsertSortie->bindParam(4, $valeur_totale_sortie, PDO::PARAM_STR);
            $stmtInsertSortie->bindParam(5, $utilisateur_id, PDO::PARAM_INT);
            $stmtInsertSortie->bindParam(6, $reference, PDO::PARAM_STR);
            $stmtInsertSortie->execute();
            
            // Add detailed information to help debug FIFO processing
            $debugInfo = json_encode([
                'product_id' => $produit_id,
                'quantity_sold' => $quantite,
                'batches_details' => $batch_details,
                'average_cost' => $prix_achat_moyen,
                'total_cost' => $total_cost
            ]);
            
            $queryLogDetail = "INSERT INTO journal_activites (utilisateur_id, action, details, date_action) 
                              VALUES (?, 'FIFO_SALE_DETAIL', ?, NOW())";
            $stmtLogDetail = $pdo->prepare($queryLogDetail);
            $stmtLogDetail->bindParam(1, $utilisateur_id, PDO::PARAM_INT);
            $stmtLogDetail->bindParam(2, $debugInfo, PDO::PARAM_STR);
            $stmtLogDetail->execute();
            
            // Update the main stock table
            $query = "UPDATE stock 
                      SET quantite = quantite - ?, 
                          date_mise_a_jour = NOW() 
                      WHERE produit_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(1, $quantite, PDO::PARAM_STR);
            $stmt->bindParam(2, $produit_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Log the activity
        $action = "Nouvelle vente créée";
        $entite = "ventes";
        $details = "Vente #{$numero_facture} créée pour un montant de {$montant_total} BIF";
        
        $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $utilisateur_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $action, PDO::PARAM_STR);
        $stmt->bindParam(3, $entite, PDO::PARAM_STR);
        $stmt->bindParam(4, $vente_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $details, PDO::PARAM_STR);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Vente enregistrée avec succès',
            'vente_id' => $vente_id,
            'numero_facture' => $numero_facture
        ]);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// AJAX handler for get_product_batches - Integrated directly in this file
if (isset($_GET['action']) && $_GET['action'] === 'get_product_batches') {
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
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode([
                'success' => false,
                'message' => 'Produit non trouvé'
            ]);
            exit;
        }
        
        // Get FIFO batches for the product
        $batchQuery = "SELECT
                        ms.id,
                        ms.date_mouvement,
                        ms.quantite as quantite_initiale,
                        ms.quantity_remaining as quantite_restante,
                        ms.prix_unitaire,
                        ms.prix_vente,
                        ms.reference
                       FROM mouvements_stock ms
                       WHERE ms.produit_id = :product_id 
                         AND ms.type_mouvement = 'entree'
                         AND ms.quantity_remaining > 0
                       ORDER BY ms.date_mouvement ASC";
        
        $batchStmt = $pdo->prepare($batchQuery);
        $batchStmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $batchStmt->execute();
        
        $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total value of batches
        $total_value = 0;
        foreach ($batches as $batch) {
            $total_value += $batch['quantite_restante'] * $batch['prix_unitaire'];
        }
        
        echo json_encode([
            'success' => true,
            'product' => $product,
            'batches' => $batches,
            'total_batch_value' => $total_value
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Set page information
$page_title = "Nouvelle Vente";
$active_page = "ventes";

include('../layouts/header.php');
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Nouvelle Vente</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/src/views/ventes/index.php">Ventes</a></li>
                        <li class="breadcrumb-item active">Nouvelle vente</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Ajouter des produits à la vente</h3>
                </div>
                <div class="card-body">
                    <form id="vente-form">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="client">Client (optionnel)</label>
                                    <select class="form-control select2" id="client" name="client_id">
                                        <option value="">Sélectionner un client</option>
                                        <!-- Les clients seront chargés par AJAX -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date-vente">Date de vente</label>
                                    <input type="datetime-local" class="form-control" id="date-vente" name="date_vente" value="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Produits</h3>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label for="produit">Produit</label>
                                            <select class="form-control select2-products" id="produit">
                                                <option value="">Rechercher un produit...</option>
                                                <!-- Les produits seront chargés par AJAX -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="prix">Prix unitaire</label>
                                            <input type="number" class="form-control" id="prix">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="quantite">Quantité</label>
                                            <input type="number" class="form-control" id="quantite" min="0.01" step="0.01" value="1">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="stock-disponible">Stock disponible</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="stock-disponible" readonly>
                                                <!-- Le bouton FIFO sera ajouté ici dynamiquement -->
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <button type="button" class="btn btn-primary btn-block" id="ajouter-produit">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="produits-table">
                                        <thead>
                                            <tr>
                                                <th>Produit</th>
                                                <th>Prix unitaire</th>
                                                <th>Quantité</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Les produits ajoutés seront affichés ici -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-right">Total:</th>
                                                <th id="total-montant">0 BIF</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Paiement</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="montant-total">Montant total</label>
                                            <input type="number" class="form-control" id="montant-total" name="montant_total" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="montant-paye">Montant payé</label>
                                            <input type="number" class="form-control" id="montant-paye" name="montant_paye" min="0" value="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="statut-paiement">Statut du paiement</label>
                                            <select class="form-control" id="statut-paiement" name="statut_paiement" required readonly disabled>
                                                <option value="paye">Payé</option>
                                                <option value="partiel">Paiement partiel</option>
                                                <option value="credit">Crédit</option>
                                            </select>
                                            <input type="hidden" name="statut_paiement" id="statut-paiement-hidden">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="note">Note</label>
                                    <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mb-3">
                            <button type="button" id="preview-btn" class="btn btn-primary btn-lg">
                                <i class="fas fa-eye mr-1"></i> Prévisualiser la vente
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal de prévisualisation -->
<div class="modal fade" id="preview-modal" tabindex="-1" role="dialog" aria-labelledby="preview-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="preview-modal-label">Prévisualiser la vente</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Client:</strong> <span id="preview-client">Aucun</span></p>
                        <p><strong>Date de vente:</strong> <span id="preview-date"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Montant total:</strong> <span id="preview-montant-total"></span> BIF</p>
                        <p><strong>Statut du paiement:</strong> <span id="preview-statut-paiement"></span></p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="preview-produits">
                            <!-- Les produits seront affichés ici -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total:</th>
                                <th id="preview-total"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <p><strong>Note:</strong> <span id="preview-note">-</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmer-vente">
                    <i class="fas fa-check mr-1"></i> Confirmer la vente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de succès -->
<div class="modal fade" id="success-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white">Vente enregistrée avec succès</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>La vente a été enregistrée avec succès!</p>
                <p>Numéro de facture: <strong id="facture-number"></strong></p>
            </div>
            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/src/views/ventes/index.php" class="btn btn-secondary">Liste des ventes</a>
                <a href="<?= BASE_URL ?>/src/views/ventes/nouvelle.php" class="btn btn-primary">Nouvelle vente</a>
                <a href="#" class="btn btn-info" id="print-facture" target="_blank">Imprimer facture</a>
            </div>
        </div>
    </div>
</div>

<!-- Include the batch modal component -->
<?php include('batch_modal.php'); ?>

<?php include('../layouts/footer.php'); ?>

<!-- Define BASE_URL for JavaScript first -->
<script>
    var BASE_URL = "<?php echo BASE_URL; ?>";
    console.log("BASE_URL initialized:", BASE_URL); // Debug log
</script>

<!-- Include Select2 libraries -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Include Toastr libraries -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Inline JavaScript (consolidated from nouvelle_vente_script.js) -->
<script>
$(document).ready(function() {
    // Debug output
    console.log("Document ready!");
    console.log("jQuery version:", $.fn.jquery);
    console.log("Select2 loaded:", typeof $.fn.select2 === 'function');
    console.log("Product selector exists:", $('#produit').length > 0);
    
    // Initialize Select2 for clients
    $('#client').select2({
        placeholder: 'Sélectionner un client',
        allowClear: true,
        ajax: {
            url: '<?= BASE_URL ?>/src/views/ventes/nouvelle.php?action=get_clients',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                
                return {
                    results: $.map(data.clients, function(client) {
                        return {
                            id: client.id,
                            text: client.nom + ' (' + client.telephone + ')'
                        };
                    }),
                    pagination: {
                        more: (params.page * 10) < data.total_count
                    }
                };
            },
            cache: true
        }
    });
    
    // Initialize Select2 for products
    $('.select2-products').select2({
        placeholder: 'Rechercher un produit',
        allowClear: true,
        ajax: {
            url: '<?= BASE_URL ?>/src/views/ventes/nouvelle.php?action=get_produits',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    page: params.page || 1,
                    with_stock: true
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                
                return {
                    results: $.map(data.produits, function(produit) {
                        return {
                            id: produit.id,
                            text: produit.nom + ' - ' + produit.code,
                            produit: produit
                        };
                    }),
                    pagination: {
                        more: (params.page * 10) < data.total_count
                    }
                };
            },
            cache: false
        }
    });
    
    // Update product info when selected
    $('#produit').on('select2:select', function(e) {
        var data = e.params.data;
        var produit = data.produit;
        
        if (produit) {
            // Update product price and stock info
            $('#prix').val(produit.prix_vente);
            $('#stock-disponible').val(produit.quantite_stock);
            
            // Check if stock exists and add FIFO button
            if (produit.quantite_stock > 0) {
                // Remove existing button if any
                $('.fifo-btn').remove();
                
                // Add FIFO button
                var fifoBtn = $('<div class="input-group-append"><button class="btn btn-outline-info fifo-btn" type="button" data-product-id="' + produit.id + '"><i class="fas fa-boxes"></i></button></div>');
                $('#stock-disponible').parent().append(fifoBtn);
            } else {
                // Remove FIFO button if no stock
                $('.fifo-btn').remove();
            }
        } else {
            $('#prix').val('');
            $('#stock-disponible').val('');
            $('.fifo-btn').remove();
        }
    });
    
    // Reset form fields when product is cleared
    $('#produit').on('select2:clear', function() {
        $('#prix').val('');
        $('#quantite').val('1');
        $('#stock-disponible').val('');
        $('.fifo-btn').remove();
        
        // Reset payment fields
        $('#montant-total').val('0');
        $('#total-montant').text('0 BIF');
        $('#montant-paye').val('0');
    });
    
    // Update current product price display when quantity changes
    $('#quantite').on('input', function() {
        // Only update the current form, not the cart totals
        var price = parseFloat($('#prix').val()) || 0;
        var quantity = parseFloat($(this).val()) || 0;
        
        // Validate quantity input
        if (quantity <= 0) {
            $(this).val('1');
            quantity = 1;
        }
        
        // Check for stock availability
        var stockDisponible = parseFloat($('#stock-disponible').val()) || 0;
        if (quantity > stockDisponible) {
            toastr.warning('La quantité dépasse le stock disponible de ' + stockDisponible);
        }
    });
    
    // Show FIFO batches when button is clicked
    $(document).on('click', '.fifo-btn', function() {
        var productId = $(this).data('product-id');
        if (productId) {
            loadFifoBatches(productId);
        }
    });
    
    // Add product to sale
    $('#ajouter-produit').on('click', function() {
        var productSelect = $('#produit');
        var product = productSelect.select2('data')[0];
        
        if (!product) {
            toastr.error('Veuillez sélectionner un produit');
            return;
        }
        
        var prix = parseFloat($('#prix').val());
        var quantite = parseFloat($('#quantite').val());
        var stockDisponible = parseFloat($('#stock-disponible').val());
        
        if (isNaN(prix) || prix <= 0) {
            toastr.error('Le prix doit être supérieur à 0');
            return;
        }
        
        if (isNaN(quantite) || quantite <= 0) {
            toastr.error('La quantité doit être supérieure à 0');
            return;
        }
        
        if (quantite > stockDisponible) {
            toastr.error('La quantité demandée dépasse le stock disponible');
            return;
        }
        
        // Calculate total
        var total = prix * quantite;
        
        // Check if product is already in the table
        var existingRow = $('#produits-table tbody tr[data-id="' + product.id + '"]');
        if (existingRow.length > 0) {
            // Update existing row
            var currentQuantity = parseFloat(existingRow.find('.product-quantity').text());
            var newQuantity = currentQuantity + quantite;
            
            if (newQuantity > stockDisponible) {
                toastr.error('La quantité totale dépasse le stock disponible');
                return;
            }
            
            var newTotal = prix * newQuantity;
            
            existingRow.find('.product-quantity').text(newQuantity.toFixed(2));
            existingRow.find('.product-total').text(newTotal.toFixed(2) + ' BIF');
            existingRow.data('total', newTotal);
        } else {
            // Add new row
            $('#produits-table tbody').append(
                '<tr data-id="' + product.id + '" data-price="' + prix + '" data-total="' + total + '">' +
                    '<td>' + product.text + '</td>' +
                    '<td>' + prix.toFixed(2) + ' BIF</td>' +
                    '<td class="product-quantity">' + quantite.toFixed(2) + '</td>' +
                    '<td class="product-total">' + total.toFixed(2) + ' BIF</td>' +
                    '<td><button type="button" class="btn btn-sm btn-danger remove-product"><i class="fas fa-trash"></i></button></td>' +
                '</tr>'
            );
        }
        
        // Reset product fields
        productSelect.val(null).trigger('change');
        $('#prix').val('');
        $('#quantite').val('1');
        $('#stock-disponible').val('');
        $('.fifo-btn').remove();
        
        // Update total amount and ensure payment fields reflect the actual cart total
        updateTotal();
        
        // Set initial payment amount to 0 and update payment status
        $('#montant-paye').val('0');
        updatePaymentStatus();
    });
    
    // Remove product from the sale
    $(document).on('click', '.remove-product', function() {
        $(this).closest('tr').remove();
        updateTotal();
    });
    
    // Update total amount
    function updateTotal() {
        var total = 0;
        $('#produits-table tbody tr').each(function() {
            total += parseFloat($(this).data('total'));
        });
        
        $('#total-montant').text(total.toFixed(2) + ' BIF');
        $('#montant-total').val(total.toFixed(2));
        
        // Update payment status based on amount paid
        updatePaymentStatus();
    }
    
    // Update payment status based on amount paid
    $('#montant-paye').on('input', function() {
        updatePaymentStatus();
    });
    
    function updatePaymentStatus() {
        var total = parseFloat($('#montant-total').val()) || 0;
        var paid = parseFloat($('#montant-paye').val()) || 0;
        
        // Ensure payment is not negative
        if (paid < 0) {
            $('#montant-paye').val('0');
            paid = 0;
        }
        
        // Determine payment status based on amount paid
        var status = '';
        if (paid === 0) {
            status = 'credit';
        } else if (paid < total) {
            status = 'partiel';
        } else {
            status = 'paye';
        }
        
        // Update both the visual select and the hidden input
        $('#statut-paiement').val(status);
        $('#statut-paiement-hidden').val(status);
        
        // Update display text
        var statusText = {
            'paye': 'Payé',
            'partiel': 'Paiement partiel',
            'credit': 'Crédit'
        }[status];
        
        // Apply styling based on status
        $('#statut-paiement').removeClass('text-success text-warning text-danger')
            .addClass(status === 'paye' ? 'text-success' : (status === 'partiel' ? 'text-warning' : 'text-danger'));
    }
    
    // Preview sale before confirming
    $('#preview-btn').on('click', function() {
        var produits = $('#produits-table tbody tr');
        if (produits.length === 0) {
            toastr.error('Veuillez ajouter au moins un produit à la vente');
            return;
        }
        
        // Get client name
        var clientId = $('#client').val();
        var clientName = clientId ? $('#client').select2('data')[0].text : 'Aucun';
        
        // Format date
        var dateVente = $('#date-vente').val();
        var formattedDate = new Date(dateVente).toLocaleString('fr-FR');
        
        // Get payment status
        var statutPaiement = $('#statut-paiement').val();
        var statutPaiementText = {
            'paye': 'Payé',
            'partiel': 'Paiement partiel',
            'credit': 'Crédit'
        }[statutPaiement];
        
        // Get note
        var note = $('#note').val() || '-';
        
        // Update preview modal
        $('#preview-client').text(clientName);
        $('#preview-date').text(formattedDate);
        $('#preview-montant-total').text($('#montant-total').val());
        $('#preview-statut-paiement').text(statutPaiementText);
        $('#preview-note').text(note);
        
        // Clear and repopulate products table
        var previewProducts = $('#preview-produits');
        previewProducts.empty();
        
        produits.each(function() {
            var produitName = $(this).find('td:first').text();
            var prix = parseFloat($(this).data('price')).toFixed(2);
            var quantite = $(this).find('.product-quantity').text();
            var total = parseFloat($(this).data('total')).toFixed(2);
            
            previewProducts.append(
                '<tr>' +
                    '<td>' + produitName + '</td>' +
                    '<td>' + prix + '</td>' +
                    '<td>' + quantite + '</td>' +
                    '<td>' + total + '</td>' +
                '</tr>'
            );
        });
        
        // Set preview total
        $('#preview-total').text($('#montant-total').val() + ' BIF');
        
        // Show preview modal
        $('#preview-modal').modal('show');
    });
    
    // Confirm sale
    $('#confirmer-vente').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        // Disable button and show loading
        button.html('<i class="fas fa-spinner fa-spin"></i> Traitement en cours...');
        button.attr('disabled', true);
        
        // Prepare form data
        var formData = new FormData();
        
        // Add client ID if selected
        var clientId = $('#client').val();
        if (clientId) {
            formData.append('client_id', clientId);
        }
        
        // Add date, payment info, and note
        formData.append('date_vente', $('#date-vente').val());
        formData.append('montant_total', $('#montant-total').val());
        formData.append('montant_paye', $('#montant-paye').val());
        formData.append('statut_paiement', $('#statut-paiement-hidden').val());
        formData.append('note', $('#note').val());
        
        // Add products
        var produits = [];
        $('#produits-table tbody tr').each(function() {
            var produitId = $(this).data('id');
            var prix = parseFloat($(this).data('price'));
            var quantite = parseFloat($(this).find('.product-quantity').text());
            
            produits.push({
                id: produitId,
                prix: prix,
                quantite: quantite
            });
        });
        
        formData.append('produits', JSON.stringify(produits));
        
        // Submit form via AJAX
        $.ajax({
            url: BASE_URL + '/src/views/ventes/nouvelle.php?action=add_vente',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                if (response.success) {
                    // Show success message
                    toastr.success('Vente enregistrée avec succès');
                    
                    // Update success modal and show it
                    $('#facture-number').text(response.numero_facture);
                    $('#print-facture').attr('href', BASE_URL + '/src/views/ventes/facture.php?id=' + response.vente_id);
                    $('#success-modal').modal('show');
                } else {
                    // Show error message
                    toastr.error(response.message || 'Une erreur est survenue');
                }
            },
            error: function(xhr) {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                // Show error message
                var errorMessage = 'Une erreur est survenue';
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing response', e);
                }
                
                toastr.error(errorMessage);
            }
        });
    });
    
    // Function to load and display FIFO batches for a product
    function loadFifoBatches(productId) {
        // Show loading in the modal
        $('#batch-list').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement des lots...</td></tr>');
        $('#batch-modal').modal('show');
        
        // Fetch batch data via AJAX
        $.ajax({
            url: '<?= BASE_URL ?>/src/views/ventes/nouvelle.php?action=get_product_batches',
            type: 'GET',
            data: { product_id: productId },
            dataType: 'json',
            success: function(response) {
                // Update product info
                $('#batch-product-name').text(response.product.nom);
                $('#batch-product-code').text(response.product.code);
                $('#batch-product-unit').text(response.product.unite_mesure);
                $('#batch-product-stock').text(response.product.stock_total + ' ' + response.product.unite_mesure);
                
                // Clear and populate batch list
                $('#batch-list').empty();
                if (response.batches.length === 0) {
                    $('#batch-list').html('<tr><td colspan="5" class="text-center">Aucun lot disponible</td></tr>');
                } else {
                    $.each(response.batches, function(index, batch) {
                        var date = new Date(batch.date_mouvement).toLocaleDateString('fr-FR');
                        var valeur = (parseFloat(batch.prix_unitaire) * parseFloat(batch.quantite_restante)).toFixed(2);
                        
                        $('#batch-list').append(
                            '<tr' + (index === 0 ? ' class="table-primary"' : '') + '>' +
                                '<td>' + date + '</td>' +
                                '<td>' + parseFloat(batch.quantite_restante).toFixed(2) + ' ' + response.product.unite_mesure + '</td>' +
                                '<td>' + parseFloat(batch.prix_unitaire).toFixed(2) + ' BIF</td>' +
                                '<td>' + valeur + ' BIF</td>' +
                                '<td>' + (batch.reference || '-') + '</td>' +
                            '</tr>'
                        );
                    });
                }
                
                // Update total value
                $('#batch-total-value').text(parseFloat(response.total_batch_value).toFixed(2) + ' BIF');
            },
            error: function(xhr) {
                // Show error message
                $('#batch-list').html('<tr><td colspan="5" class="text-center text-danger">Erreur lors du chargement des lots</td></tr>');
                console.error('Error loading batches:', xhr);
            }
        });
    }
});
</script>