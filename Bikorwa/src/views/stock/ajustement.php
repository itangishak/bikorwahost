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

// Set page information
$page_title = "Ajustement de Stock";
$active_page = "stock";


// Initialize variables
$message = "";
$messageType = "";

// AJAX handler for get_product_batches
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
            throw new Exception("Produit introuvable.");
        }
        
        // Get batches with remaining quantity (for FIFO tracking)
        $query = "SELECT 
                    ms.id,
                    ms.quantite as quantite_initiale,
                    ms.quantity_remaining as quantite_restante,
                    ms.prix_unitaire,
                    ms.valeur_totale,
                    ms.date_mouvement,
                    ms.reference,
                    ms.note
                  FROM mouvements_stock ms
                  WHERE ms.produit_id = :product_id 
                  AND ms.type_mouvement = 'entree' 
                  AND ms.quantity_remaining > 0
                  ORDER BY ms.date_mouvement ASC";
        
        $stmt = $pdo->prepare($query);
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
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// AJAX handler for process_adjustment
if (isset($_GET['action']) && $_GET['action'] === 'process_adjustment') {
    header('Content-Type: application/json');
    
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
        exit;
    }
    
    try {
        // Start a transaction
        $pdo->beginTransaction();
        
        // Extract and validate data
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $adjustment_type = isset($_POST['adjustment_type']) ? trim($_POST['adjustment_type']) : '';
        $quantity = isset($_POST['quantity']) ? floatval(str_replace(',', '.', $_POST['quantity'])) : 0;
        $price = isset($_POST['price']) ? floatval(str_replace(',', '.', $_POST['price'])) : 0;
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';
        
        // Validate required fields
        if ($product_id <= 0 || empty($adjustment_type) || $quantity <= 0) {
            throw new Exception("Tous les champs obligatoires doivent être remplis avec des valeurs valides.");
        }
        
        // Validate adjustment type
        if (!in_array($adjustment_type, ['increase', 'decrease'])) {
            throw new Exception("Type d'ajustement invalide.");
        }
        
        // If it's a stock increase, validate price
        if ($adjustment_type === 'increase' && $price <= 0) {
            throw new Exception("Le prix unitaire doit être supérieur à zéro pour un ajout de stock.");
        }
        
        // Get product details
        $query = "SELECT p.*, COALESCE(s.quantite, 0) as stock_total
                  FROM produits p
                  LEFT JOIN stock s ON p.id = s.produit_id
                  WHERE p.id = :product_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception("Produit introuvable.");
        }
        
        // Process based on adjustment type
        if ($adjustment_type === 'increase') {
            // Add stock - Create a new batch with the current price
            $valeur_totale = $quantity * $price;
            $reference = 'ADJ-IN-' . date('YmdHis');
            
            // Insert into mouvements_stock table
            $query = "INSERT INTO mouvements_stock 
                      (produit_id, type_mouvement, quantite, quantity_remaining, prix_unitaire, valeur_totale, 
                       date_mouvement, utilisateur_id, note, reference)
                      VALUES 
                      (:produit_id, 'entree', :quantite, :quantity_remaining, :prix_unitaire, :valeur_totale, 
                       NOW(), :utilisateur_id, :note, :reference)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'produit_id' => $product_id,
                'quantite' => $quantity,
                'quantity_remaining' => $quantity, // Full quantity available initially
                'prix_unitaire' => $price,
                'valeur_totale' => $valeur_totale,
                'utilisateur_id' => $_SESSION['user_id'],
                'note' => $note ?: 'Ajustement de stock (Ajout)',
                'reference' => $reference
            ]);
            
            // Update stock table
            $new_stock = $product['stock_total'] + $quantity;
            $query = "UPDATE stock SET quantite = :quantite WHERE produit_id = :produit_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'quantite' => $new_stock,
                'produit_id' => $product_id
            ]);
            
            // If no rows affected, insert new stock record
            if ($stmt->rowCount() === 0) {
                $query = "INSERT INTO stock (produit_id, quantite) VALUES (:produit_id, :quantite)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'produit_id' => $product_id,
                    'quantite' => $quantity
                ]);
            }
        } else {
            // Decrease stock - Using FIFO logic
            if ($quantity > $product['stock_total']) {
                throw new Exception("Quantité d'ajustement trop grande. Stock disponible: " . $product['stock_total'] . " " . $product['unite_mesure']);
            }
            
            // Get batches with remaining quantity (for FIFO processing)
            $query = "SELECT 
                        id, quantity_remaining, prix_unitaire
                      FROM mouvements_stock 
                      WHERE produit_id = :product_id 
                      AND type_mouvement = 'entree' 
                      AND quantity_remaining > 0
                      ORDER BY date_mouvement ASC";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $remaining_to_remove = $quantity;
            $total_value_removed = 0;
            $reference = 'ADJ-OUT-' . date('YmdHis');
            
            // Process each batch according to FIFO
            foreach ($batches as $batch) {
                if ($remaining_to_remove <= 0) {
                    break;
                }
                
                $batch_quantity = floatval($batch['quantity_remaining']);
                $batch_price = floatval($batch['prix_unitaire']);
                
                // Determine how much to take from this batch
                $take_from_batch = min($batch_quantity, $remaining_to_remove);
                $value_from_batch = $take_from_batch * $batch_price;
                
                // Update the batch's remaining quantity
                $new_remaining = $batch_quantity - $take_from_batch;
                $stmt = $pdo->prepare("UPDATE mouvements_stock SET quantity_remaining = :quantity_remaining WHERE id = :id");
                $stmt->execute([
                    'quantity_remaining' => $new_remaining,
                    'id' => $batch['id']
                ]);
                
                // Add to the total value removed
                $total_value_removed += $value_from_batch;
                $remaining_to_remove -= $take_from_batch;
            }
            
            // Record the stock decrease
            $query = "INSERT INTO mouvements_stock 
                      (produit_id, type_mouvement, quantite, quantity_remaining, prix_unitaire, valeur_totale, 
                       date_mouvement, utilisateur_id, note, reference)
                      VALUES 
                      (:produit_id, 'sortie', :quantite, 0, :prix_unitaire, :valeur_totale, 
                       NOW(), :utilisateur_id, :note, :reference)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'produit_id' => $product_id,
                'quantite' => $quantity,
                'prix_unitaire' => $total_value_removed / $quantity, // Average price of removed items
                'valeur_totale' => $total_value_removed,
                'utilisateur_id' => $_SESSION['user_id'],
                'note' => $note ?: 'Ajustement de stock (Retrait)',
                'reference' => $reference
            ]);
            
            // Update stock table
            $new_stock = $product['stock_total'] - $quantity;
            $query = "UPDATE stock SET quantite = :quantite WHERE produit_id = :produit_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'quantite' => $new_stock,
                'produit_id' => $product_id
            ]);
        }
        
        // Log the action in journal_activites
        $action_type = $adjustment_type === 'increase' ? 'ajout_stock' : 'retrait_stock';
        $details = sprintf(
            "Ajustement de stock pour %s (Code: %s): %s%.2f %s. %s", 
            $product['nom'], 
            $product['code'], 
            $adjustment_type === 'increase' ? '+' : '-',
            $quantity,
            $product['unite_mesure'],
            $note
        );
        
        $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                  VALUES (:utilisateur_id, :action, 'stock', :entite_id, :details)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'utilisateur_id' => $_SESSION['user_id'],
            'action' => $action_type,
            'entite_id' => $product_id,
            'details' => $details
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Ajustement de stock effectué avec succès.",
            'product_id' => $product_id,
            'adjustment_type' => $adjustment_type,
            'quantity' => $quantity,
            'new_stock' => $new_stock
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

// Include header
include('../layouts/header.php');
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?= $page_title ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/src/views/dashboard/index.php">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/src/views/stock/inventaire.php">Stock</a></li>
                    <li class="breadcrumb-item active">Ajustement</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
    
        <!-- Flash message -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Main card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ajustement de Stock</h3>
                <div class="card-tools">
                    <a href="<?= BASE_URL ?>/src/views/stock/inventaire.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Retour
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Product search section -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="product-search">Rechercher un produit</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="product-search" placeholder="Nom, code ou description...">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button" id="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Tapez au moins 2 caractères pour lancer la recherche</small>
                        </div>
                        
                        <div id="product-results" class="list-group mt-2" style="max-height: 300px; overflow-y: auto; display: none;"></div>
                    </div>
                    
                    <!-- Product info section -->
                    <div class="col-md-6">
                        <div id="product-info" class="card d-none">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-box mr-2"></i> <span id="product-name">-</span></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Code:</strong> <span id="product-code">-</span></p>
                                        <p><strong>Unité:</strong> <span id="product-unit">-</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Stock total:</strong> <span id="product-stock">-</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Adjustment form -->
                <div class="row mt-3" id="adjustment-form" style="display: none;">
                    <div class="col-md-8 offset-md-2">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Ajuster le stock</h3>
                            </div>
                            <div class="card-body">
                                <form id="stock-adjustment-form">
                                    <input type="hidden" id="product-id" name="product_id">
                                    
                                    <input type="hidden" name="adjustment_type" id="adjustment-type" value="increase">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="quantity">Quantité <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="quantity" name="quantity" required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="unit-display">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="price-group">
                                                <label for="price">Prix unitaire <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="price" name="price">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">BIF</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="note">Note / Raison</label>
                                        <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="form-group text-center">
                                        <button type="submit" class="btn btn-primary px-5">
                                            <i class="fas fa-save mr-1"></i> Enregistrer l'ajustement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Preview Modal -->
<div class="modal fade" id="preview-modal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Confirmer l'ajustement</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Produit</h6>
                        <p id="preview-product">-</p>
                        
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Quantité</h6>
                        <p id="preview-quantity">-</p>
                        
                        <h6 class="font-weight-bold" id="preview-price-label">Prix unitaire</h6>
                        <p id="preview-price">-</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <h6 class="font-weight-bold">Note</h6>
                        <p id="preview-note">-</p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> En cas de retrait de stock, le système utilisera la méthode FIFO (premier entré, premier sorti) pour déterminer quels lots seront affectés.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirm-adjustment">
                    <i class="fas fa-check mr-1"></i> Confirmer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="success-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Ajustement réussi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                <h4>L'ajustement de stock a été effectué avec succès!</h4>
                <p class="mb-0">Le stock a été mis à jour selon la méthode FIFO.</p>
            </div>
            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/src/views/stock/inventaire.php" class="btn btn-outline-secondary">
                    Retour à l'inventaire
                </a>
                <button type="button" class="btn btn-success" data-dismiss="modal" id="new-adjustment-btn">
                    Nouvel ajustement
                </button>
            </div>
        </div>
    </div>
</div>

<?php include('../layouts/footer.php'); ?>

<script>
// Custom notification function
function showNotification(type, message) {
    // Create notification element
    var notification = $('<div class="alert alert-' + type + ' alert-dismissible fade show notification-toast" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
    '</div>');
    
    // Add custom styling
    notification.css({
        'position': 'fixed',
        'top': '20px',
        'right': '20px',
        'z-index': '9999',
        'min-width': '300px',
        'box-shadow': '0 4px 8px rgba(0,0,0,0.1)'
    });
    
    // Append to body
    $('body').append(notification);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        notification.fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
}

$(document).ready(function() {
    // Product search with typeahead
    let searchTimeout;
    $('#product-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        let search = $(this).val().trim();
        
        if (search.length < 2) {
            $('#product-results').hide();
            return;
        }
        
        // Show loading
        $('#product-results').html('<div class="p-3 text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Recherche en cours...</div>').show();
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: '<?= BASE_URL ?>/src/api/produits/get_produits.php',
                type: 'GET',
                // We want to search among all products regardless of their
                // current stock level, so we don't filter by available stock
                data: { search: search, with_stock: false },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotification('success', 'Produits trouvés avec succès!');
                        let html = '';
                        
                        if (response.produits.length === 0) {
                            html = '<div class="p-3 text-center text-muted">Aucun produit trouvé</div>';
                        } else {
                            $.each(response.produits, function(index, product) {
                                // Use exactly what's in the database without any fallback
                                const uniteMesure = product.unite_mesure;
                                
                                html += `<a href="#" class="list-group-item list-group-item-action product-item" 
                                           data-id="${product.id}" 
                                           data-nom="${product.nom}" 
                                           data-code="${product.code}" 
                                           data-unite="${uniteMesure}" 
                                           data-stock="${product.quantite_stock}">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">${product.nom}</h6>
                                                    <small>Code: ${product.code} | Stock: ${product.quantite_stock} ${uniteMesure}</small>
                                                </div>
                                                <span class="badge badge-${product.quantite_stock > 0 ? 'success' : 'danger'} badge-pill">
                                                    ${product.quantite_stock > 0 ? 'En stock' : 'Rupture'}
                                                </span>
                                            </div>
                                        </a>`;
                            });
                        }
                        
                        $('#product-results').html(html);
                    } else {
                        showNotification('danger', 'Erreur: ' + (response.message || 'Erreur inconnue'));
                        $('#product-results').html('<div class="p-3 text-center text-danger">Erreur: ' + (response.message || 'Erreur inconnue') + '</div>');
                    }
                },
                error: function() {
                    showNotification('danger', 'Erreur de connexion');
                    $('#product-results').html('<div class="p-3 text-center text-danger">Erreur de connexion</div>');
                }
            });
        }, 300);
    });
    
    // Handle product selection
    $(document).on('click', '.product-item', function(e) {
        e.preventDefault();
        
        // Get product data
        const productId = $(this).data('id');
        const productName = $(this).data('nom');
        const productCode = $(this).data('code');
        const productUnit = $(this).data('unite');
        // Don't use any fallback - display exactly what's in the database
        const productStock = $(this).data('stock');
        
        // Update product info card
        $('#product-name').text(productName);
        $('#product-code').text(productCode);
        $('#product-unit').text(productUnit);
        $('#product-stock').text(productStock + ' ' + productUnit);
        $('#product-info').removeClass('d-none');
        
        // Update form fields
        $('#product-id').val(productId);
        $('#unit-display').text(productUnit);
        
        // Show adjustment form
        $('#adjustment-form').show();
        
        // Hide search results
        $('#product-results').hide();
    });
    
    
    // Handle form submission
    $('#stock-adjustment-form').submit(function(e) {
        e.preventDefault();
        
        // Get form data
        const productId = $('#product-id').val();
        const productName = $('#product-name').text();
        const productCode = $('#product-code').text();
        const productUnit = $('#product-unit').text();
        const adjustmentType = $('#adjustment-type').val();
        const quantity = $('#quantity').val();
        const price = $('#price').val();
        const note = $('#note').val();
        
        // Validate
        if (!productId) {
            showNotification('danger', 'Veuillez sélectionner un produit.');
            return;
        }
        
        if (!quantity || parseFloat(quantity) <= 0) {
            showNotification('danger', 'Veuillez entrer une quantité valide.');
            return;
        }
        
        if (adjustmentType === 'increase' && (!price || parseFloat(price) <= 0)) {
            showNotification('danger', 'Veuillez entrer un prix unitaire valide.');
            return;
        }
        
        // Update preview modal
        $('#preview-product').html(`<strong>${productName}</strong> (Code: ${productCode})`);
        // Use the exact unit from the database without any fallback
        $('#preview-quantity').text(quantity + ' ' + productUnit);
        
        if (adjustmentType === 'increase') {
            $('#preview-price-label').show();
            $('#preview-price').text(formatCurrency(price) + ' BIF').show();
        } else {
            $('#preview-price-label').hide();
            $('#preview-price').hide();
        }
        
        $('#preview-note').text(note || '-');
        
        // Show preview modal
        $('#preview-modal').modal('show');
    });
    
    // Handle confirmation
    $('#confirm-adjustment').click(function() {
        const button = $(this);
        const originalText = button.html();
        
        // Disable button and show loading
        button.html('<i class="fas fa-spinner fa-spin mr-1"></i> Traitement...');
        button.attr('disabled', true);
        
        // Get form data
        const formData = new FormData($('#stock-adjustment-form')[0]);
        
        // Send AJAX request
        $.ajax({
            url: '<?= BASE_URL ?>/src/views/stock/ajustement.php?action=process_adjustment',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                if (response.success) {
                    showNotification('success', 'Ajustement effectué avec succès!');
                    // Show success modal
                    $('#success-modal').modal('show');
                    
                    // Reset form
                    $('#stock-adjustment-form')[0].reset();
                } else {
                    // Show error message
                    showNotification('danger', response.message || 'Une erreur est survenue');
                }
            },
            error: function() {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                // Show error message
                showNotification('danger', 'Erreur de connexion');
            }
        });
    });
    
    // Reset form on new adjustment button click
    $('#new-adjustment-btn').click(function() {
        $('#stock-adjustment-form')[0].reset();
    });
    
    // Format currency function
    function formatCurrency(value) {
        return parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
});
</script>

<style>
/* Custom styles for the FIFO adjustment page */
.product-item:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

</style>