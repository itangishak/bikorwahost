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

// Check if user has 'gestionnaire' role - only gestionnaire can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gestionnaire') {
    // Redirect to appropriate dashboard based on role
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'receptionniste') {
        header('Location: ../dashboard/receptionniste.php');
    } else {
        header('Location: ../auth/login.php');
    }
    exit;
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// AJAX handler for canceling a sale
if (isset($_POST['action']) && $_POST['action'] === 'cancel_sale') {
    header('Content-Type: application/json');
    
    try {
        // Get the sale ID
        $vente_id = isset($_POST['vente_id']) ? intval($_POST['vente_id']) : 0;
        $raison = isset($_POST['raison']) ? trim($_POST['raison']) : '';
        
        if ($vente_id <= 0) {
            throw new Exception('ID de vente invalide');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if the sale exists and is active
        $query = "SELECT v.*, 
                       (SELECT COUNT(*) FROM details_ventes WHERE vente_id = v.id) AS product_count
                 FROM ventes v 
                 WHERE v.id = ? AND v.statut_vente = 'active'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            throw new Exception('Vente introuvable ou déjà annulée');
        }
        
        // Get all products from this sale
        $query = "SELECT dv.*, p.nom AS produit_nom 
                 FROM details_ventes dv
                 JOIN produits p ON dv.produit_id = p.id
                 WHERE dv.vente_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each product, restore stock using FIFO in reverse (newest first)
        foreach ($produits as $produit) {
            $produit_id = $produit['produit_id'];
            $quantite = $produit['quantite'];
            $montant_total = $produit['montant_total'];
            
            // Get the movement record for this sale
            $query = "SELECT id FROM mouvements_stock 
                     WHERE produit_id = ? AND reference = ? AND type_mouvement = 'sortie'";
            $reference = "Vente #" . $vente['numero_facture'];
            $stmt = $pdo->prepare($query);
            $stmt->execute([$produit_id, $reference]);
            $mouvement_vente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mouvement_vente) {
                // Update stock table
                $query = "UPDATE stock SET 
                         quantite = quantite + ?, 
                         date_mise_a_jour = NOW() 
                         WHERE produit_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$quantite, $produit_id]);
                
                // Create reverse movement for cancellation (entry back to stock)
                $query = "INSERT INTO mouvements_stock 
                         (produit_id, type_mouvement, quantite, prix_unitaire, 
                          valeur_totale, reference, utilisateur_id, note, quantity_remaining) 
                         VALUES (?, 'entree', ?, ?, ?, ?, ?, ?, ?)";
                
                $prix_unitaire = $produit['prix_unitaire'];
                $reference_annulation = "Annulation vente #" . $vente['numero_facture'];
                $utilisateur_id = $_SESSION['user_id'];
                $note = "Annulation: " . $raison;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $produit_id, $quantite, $prix_unitaire, $montant_total,
                    $reference_annulation, $utilisateur_id, $note, $quantite
                ]);
                
                // Restore quantity_remaining in original batches if they still exist
                // This is reverse FIFO - we restore the newest batches first
                $query = "SELECT id, quantity_remaining, quantite 
                         FROM mouvements_stock 
                         WHERE produit_id = ? AND type_mouvement = 'entree' AND quantity_remaining < quantite
                         ORDER BY date_mouvement DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$produit_id]);
                $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_to_restore = $quantite;
                
                foreach ($batches as $batch) {
                    if ($remaining_to_restore <= 0) break;
                    
                    $used_from_batch = min($batch['quantite'] - $batch['quantity_remaining'], $remaining_to_restore);
                    $new_remaining = $batch['quantity_remaining'] + $used_from_batch;
                    
                    // Update the batch
                    $query = "UPDATE mouvements_stock 
                             SET quantity_remaining = ? 
                             WHERE id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$new_remaining, $batch['id']]);
                    
                    $remaining_to_restore -= $used_from_batch;
                }
            }
        }
        
        // Update sale status to canceled
        $query = "UPDATE ventes SET statut_vente = 'annulee' WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);

        // Save the cancellation reason in the note field
        $query = "UPDATE ventes SET note = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$raison, $vente_id]);
        
        // Update any debt records if they exist
        $query = "UPDATE dettes SET statut = 'annulee' WHERE vente_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);
        
        // Log the activity
        $query = "INSERT INTO journal_activites 
                 (utilisateur_id, action, entite, entite_id, details) 
                 VALUES (?, ?, ?, ?, ?)";
        $action = "Vente annulée";
        $entite = "ventes";
        $details = "Vente #{$vente['numero_facture']} annulée. Raison: " . $raison;
        $utilisateur_id = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$utilisateur_id, $action, $entite, $vente_id, $details]);
        
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vente annulée avec succès'
        ]);
        exit;
        
    } catch (Exception $e) {
        // Rollback the transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
        exit;
    }
}

// AJAX handler for getting sales
if (isset($_GET['action']) && $_GET['action'] === 'get_ventes') {
    header('Content-Type: application/json');
    
    try {
        // Set default values and get search parameters
        $search = $_GET['search'] ?? '';
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $items_per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($current_page - 1) * $items_per_page;
        $status_filter = $_GET['status'] ?? 'all';
        
        // Build the base query
        $query = "SELECT v.*, 
                      c.nom AS client_nom, 
                      u.nom AS utilisateur_nom,
                      (SELECT COUNT(*) FROM details_ventes WHERE vente_id = v.id) AS product_count
                 FROM ventes v 
                 LEFT JOIN clients c ON v.client_id = c.id 
                 LEFT JOIN users u ON v.utilisateur_id = u.id 
                 WHERE 1=1";
                 
        $count_query = "SELECT COUNT(*) AS total FROM ventes v 
                       LEFT JOIN clients c ON v.client_id = c.id 
                       LEFT JOIN users u ON v.utilisateur_id = u.id 
                       WHERE 1=1";
                       
        $params = [];
        $count_params = [];
        
        // Add status filter if provided
        if ($status_filter !== 'all') {
            $query .= " AND v.statut_vente = ?";
            $count_query .= " AND v.statut_vente = ?";
            array_push($params, $status_filter);
            array_push($count_params, $status_filter);
        }
        
        // Add search conditions if any
        if (!empty($search)) {
            $query .= " AND (v.numero_facture LIKE ? OR c.nom LIKE ? OR u.nom LIKE ?)";
            $count_query .= " AND (v.numero_facture LIKE ? OR c.nom LIKE ? OR u.nom LIKE ?)";
            $search_param = "%$search%";
            array_push($params, $search_param, $search_param, $search_param);
            array_push($count_params, $search_param, $search_param, $search_param);
        }
        
        // Add order by without pagination parameters (will be added directly)
        $query .= " ORDER BY v.date_vente DESC";
        
        // Execute count query for pagination
        $count_stmt = $pdo->prepare($count_query);
        
        // Bind parameters for count query if any
        if (!empty($count_params)) {
            $count_stmt->execute($count_params);
        } else {
            $count_stmt->execute();
        }
        
        $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_rows = $result['total'];
        $total_pages = ceil($total_rows / $items_per_page);
        
        // Make sure current page is valid
        if ($current_page < 1) $current_page = 1;
        if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
        
        // Recalculate offset based on validated current page
        $offset = ($current_page - 1) * $items_per_page;
        
        // Add pagination directly to the SQL query to avoid binding issues
        $query .= " LIMIT " . (int)$items_per_page . " OFFSET " . (int)$offset;
        
        // Execute the main query
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the data
        echo json_encode([
            'success' => true,
            'ventes' => $ventes,
            'total_count' => $total_rows,
            'current_page' => $current_page,
            'total_pages' => $total_pages
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

// AJAX handler for getting sale details
if (isset($_GET['action']) && $_GET['action'] === 'get_vente_details') {
    header('Content-Type: application/json');
    
    try {
        $vente_id = isset($_GET['vente_id']) ? intval($_GET['vente_id']) : 0;
        
        if ($vente_id <= 0) {
            throw new Exception('ID de vente invalide');
        }
        
        // Get sale information
        $query = "SELECT v.*, 
                      c.nom AS client_nom, 
                      c.telephone AS client_telephone,
                      c.adresse AS client_adresse,
                      u.nom AS utilisateur_nom
                 FROM ventes v 
                 LEFT JOIN clients c ON v.client_id = c.id 
                 LEFT JOIN users u ON v.utilisateur_id = u.id 
                 WHERE v.id = ?";
                 
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vente) {
            throw new Exception('Vente introuvable');
        }
        
        // Get sale details (products)
        $query = "SELECT dv.*, 
                      p.nom AS produit_nom,
                      p.code AS produit_code,
                      p.unite_mesure
                 FROM details_ventes dv 
                 JOIN produits p ON dv.produit_id = p.id 
                 WHERE dv.vente_id = ?";
                 
        $stmt = $pdo->prepare($query);
        $stmt->execute([$vente_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the data
        echo json_encode([
            'success' => true,
            'vente' => $vente,
            'produits' => $produits
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

// Include header layout only (not main.php)
require_once __DIR__ . '/../layouts/header.php';

// Add Toastr CSS for notifications and custom styles
echo <<<EOT
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<!-- Custom styles -->
<style>
    /* Custom badge styles with light backgrounds and dark text */
    .badge-light-success {
        background-color: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }
    .badge-light-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    .badge-light-danger {
        background-color: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }
    .badge-light-primary {
        background-color: #cfe2ff;
        color: #0a58ca;
        border: 1px solid #b6d4fe;
    }
    .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
    }
</style>
EOT;
?>
<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Gestion des Ventes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                        <li class="breadcrumb-item active">Ventes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Filter and search options -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filtres et recherche</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status-filter">Statut</label>
                                <select id="status-filter" class="form-control">
                                    <option value="all">Tous</option>
                                    <option value="active">Actives</option>
                                    <option value="annulee">Annulées</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="search-input">Recherche</label>
                                <input type="text" id="search-input" class="form-control" placeholder="Rechercher par numéro, client ou utilisateur...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button id="search-button" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> Rechercher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales List Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Ventes</h3>
                    <div class="card-tools">
                        <a href="./nouvelle.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Nouvelle Vente
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>N° Facture</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Payé</th>
                                <th>Statut Paiement</th>
                                <th>Statut Vente</th>
                                <th>Utilisateur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ventes-list">
                            <!-- Sales will be loaded here via JavaScript -->
                            <tr>
                                <td colspan="10" class="text-center">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right" id="pagination">
                        <!-- Pagination will be loaded here via JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- View Sale Modal -->
<div class="modal fade" id="view-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Détails de la Vente</h4>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>N° Facture:</strong> <span id="view-numero-facture"></span></p>
                        <p><strong>Date:</strong> <span id="view-date"></span></p>
                        <p><strong>Statut:</strong> <span id="view-statut-vente" class="badge"></span></p>
                        <p><strong>Note:</strong> <span id="view-note"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Client:</strong> <span id="view-client"></span></p>
                        <p><strong>Montant Total:</strong> <span id="view-montant-total"></span> BIF</p>
                        <p><strong>Montant Payé:</strong> <span id="view-montant-paye"></span> BIF</p>
                        <p><strong>Statut Paiement:</strong> <span id="view-statut-paiement" class="badge"></span></p>
                    </div>
                </div>
                <h5 class="mt-4">Produits</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix</th>
                                <th>Montant</th>
                                <th>Bénéfice</th>
                            </tr>
                        </thead>
                        <tbody id="view-produits">
                            <!-- Products will be loaded here via JavaScript -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total:</th>
                                <th id="view-total-amount"></th>
                                <th id="view-total-profit"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a id="print-facture" href="#" target="_blank" class="btn btn-primary">
                    <i class="fas fa-print"></i> Imprimer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Sale Modal -->
<div class="modal fade" id="cancel-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h4 class="modal-title">Annuler la Vente</h4>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="cancel-form">
                <div class="modal-body">
                    <input type="hidden" id="cancel-vente-id" name="vente_id">
                    <input type="hidden" name="action" value="cancel_sale">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Attention! Cette action annulera la vente et restaurera les quantités en stock. Cette action est irréversible.
                    </div>
                    
                    <div class="form-group">
                        <label for="cancel-raison">Raison de l'annulation <span class="text-danger">*</span></label>
                        <textarea id="cancel-raison" name="raison" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <p><strong>Informations de la vente:</strong></p>
                        <p>N° Facture: <span id="cancel-numero-facture"></span></p>
                        <p>Montant: <span id="cancel-montant"></span> BIF</p>
                        <p>Client: <span id="cancel-client"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Confirmer l'Annulation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Page specific script -->
<!-- Ensure jQuery is available for the page scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Define BASE_URL variable for JavaScript use
var BASE_URL = window.location.origin + '/Bikorwa';

$(function() {
    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
    
    // Variables
    let currentPage = 1;
    const itemsPerPage = 10;
    let totalPages = 0;
    
    // Load sales on page load
    loadVentes();
    
    // Search button click event
    $('#search-button').on('click', function() {
        currentPage = 1;
        loadVentes();
    });
    
    // Enter key in search input
    $('#search-input').on('keyup', function(e) {
        if (e.key === 'Enter') {
            currentPage = 1;
            loadVentes();
        }
    });
    
    // Status filter change event
    $('#status-filter').on('change', function() {
        currentPage = 1;
        loadVentes();
    });

    // Initialize delegated event handlers
    setupViewButtons();
    setupCancelButtons();

    // Function to load sales with pagination and filters
    function loadVentes() {
        const search = $('#search-input').val();
        const status = $('#status-filter').val();
        
        // Show loading indicator
        $('#ventes-list').html('<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement des données...</td></tr>');
        
        // Make AJAX request
        $.ajax({
            url: 'index.php?action=get_ventes',
            type: 'GET',
            data: {
                search: search,
                page: currentPage,
                limit: itemsPerPage,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update pagination
                    totalPages = response.total_pages;
                    updatePagination();
                    
                    // Clear table
                    $('#ventes-list').empty();
                    
                    // Check if there are sales to display
                    if (response.ventes.length === 0) {
                        $('#ventes-list').html('<tr><td colspan="10" class="text-center">Aucune vente trouvée</td></tr>');
                        return;
                    }
                    
                    // Populate table with sales
                    $.each(response.ventes, function(index, vente) {
                        const date = new Date(vente.date_vente).toLocaleString('fr-FR');
                        const client = vente.client_nom ? vente.client_nom : 'Client anonyme';
                        
                        // Determine payment status class
                        let paymentStatusClass = '';
                        if (vente.statut_paiement === 'paye') {
                            paymentStatusClass = 'badge-light-success';
                        } else if (vente.statut_paiement === 'partiel') {
                            paymentStatusClass = 'badge-light-warning';
                        } else if (vente.statut_paiement === 'credit') {
                            paymentStatusClass = 'badge-light-danger';
                        }
                        
                        // Determine sale status class
                        let saleStatusClass = vente.statut_vente === 'active' ? 'badge-light-primary' : 'badge-light-danger';
                        
                        // Create row
                        const row = `
                            <tr data-id="${vente.id}" data-client="${client}" data-montant="${vente.montant_total}" data-facture="${vente.numero_facture}">
                                <td>${vente.id}</td>
                                <td>${vente.numero_facture}</td>
                                <td>${date}</td>
                                <td>${client}</td>
                                <td>${parseFloat(vente.montant_total).toLocaleString('fr-FR')} BIF</td>
                                <td>${parseFloat(vente.montant_paye).toLocaleString('fr-FR')} BIF</td>
                                <td><span class="badge ${paymentStatusClass} text-black">${vente.statut_paiement}</span></td>
                                <td><span class="badge ${saleStatusClass} text-black">${vente.statut_vente}</span></td>
                                <td>${vente.utilisateur_nom}</td>
                                <td>
                                    <button class="btn btn-sm btn-info view-btn" data-id="${vente.id}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ${vente.statut_vente === 'active' ? `
                                    <button class="btn btn-sm btn-danger cancel-btn" data-id="${vente.id}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    ` : ''}
                                    <a href="./facture.php?id=${vente.id}" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        `;
                        
                        $('#ventes-list').append(row);
                    });
                    

                    
                } else {
                    // Show error
                    $('#ventes-list').html(`<tr><td colspan="10" class="text-center text-danger">${response.message || 'Erreur lors du chargement des ventes'}</td></tr>`);
                    toastr.error(response.message || 'Erreur lors du chargement des ventes');
                }
            },
            error: function(xhr) {
                console.error('Error loading sales:', xhr);
                $('#ventes-list').html('<tr><td colspan="10" class="text-center text-danger">Erreur lors du chargement des ventes</td></tr>');
                toastr.error('Erreur lors du chargement des ventes');
            }
        });
    }
    
    // Function to update pagination controls
    function updatePagination() {
        const pagination = $('#pagination');
        pagination.empty();
        
        // Don't show pagination if only one page
        if (totalPages <= 1) {
            return;
        }
        
        // Previous button
        pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">«</a>
            </li>
        `);
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        
        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        // Next button
        pagination.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">»</a>
            </li>
        `);
        
        // Add click handler for pagination links
        $('.page-link').on('click', function(e) {
            e.preventDefault();
            if ($(this).parent().hasClass('disabled')) return;
            
            const page = parseInt($(this).data('page'));
            if (page && page !== currentPage) {
                currentPage = page;
                loadVentes();
            }
        });
    }
    
    // Function to setup view buttons
    function setupViewButtons() {
        $('#ventes-list').on('click', '.view-btn', function() {
            const vente_id = $(this).data('id');
            
            // Show loading in modal
            $('#view-produits').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement des détails...</td></tr>');
            $('#view-modal').modal('show');
            
            // Fetch sale details
            $.ajax({
                url: 'index.php?action=get_vente_details',
                type: 'GET',
                data: { vente_id: vente_id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const vente = response.vente;
                        const produits = response.produits;
                        
                        // Format date
                        const date = new Date(vente.date_vente).toLocaleString('fr-FR');
                        
                        // Update sale info
                        $('#view-numero-facture').text(vente.numero_facture);
                        $('#view-date').text(date);
                        
                        // Status badge
                        let statut_class = vente.statut_vente === 'active' ? 'badge-light-primary' : 'badge-light-danger';
                        $('#view-statut-vente').removeClass().addClass('badge ' + statut_class).text(vente.statut_vente);
                        
                        // Payment status badge
                        let payment_class = '';
                        if (vente.statut_paiement === 'paye') {
                            payment_class = 'badge-light-success';
                        } else if (vente.statut_paiement === 'partiel') {
                            payment_class = 'badge-light-warning';
                        } else if (vente.statut_paiement === 'credit') {
                            payment_class = 'badge-light-danger';
                        }
                        $('#view-statut-paiement').removeClass().addClass('badge ' + payment_class).text(vente.statut_paiement);
                        
                        // Client info
                        $('#view-client').text(vente.client_nom || 'Client anonyme');
                        
                        // Amounts
                        $('#view-montant-total').text(parseFloat(vente.montant_total).toLocaleString('fr-FR'));
                        $('#view-montant-paye').text(parseFloat(vente.montant_paye).toLocaleString('fr-FR'));
                        
                        // Note
                        $('#view-note').text(vente.note || '-');
                        
                        // Print link (using relative path)
                        $('#print-facture').attr('href', './facture.php?id=' + vente.id);
                        
                        // Clear products table
                        $('#view-produits').empty();
                        
                        // Check if there are products to display
                        if (produits.length === 0) {
                            $('#view-produits').html('<tr><td colspan="5" class="text-center">Aucun produit trouvé</td></tr>');
                            return;
                        }
                        
                        // Calculate totals
                        let totalAmount = 0;
                        let totalProfit = 0;
                        
                        // Populate products table
                        $.each(produits, function(index, produit) {
                            const montant = parseFloat(produit.montant_total);
                            const benefice = parseFloat(produit.benefice);
                            
                            totalAmount += montant;
                            totalProfit += benefice;
                            
                            $('#view-produits').append(`
                                <tr>
                                    <td>${produit.produit_nom} (${produit.produit_code})</td>
                                    <td>${parseFloat(produit.quantite).toLocaleString('fr-FR')} ${produit.unite_mesure}</td>
                                    <td>${parseFloat(produit.prix_unitaire).toLocaleString('fr-FR')} BIF</td>
                                    <td>${montant.toLocaleString('fr-FR')} BIF</td>
                                    <td>${benefice.toLocaleString('fr-FR')} BIF</td>
                                </tr>
                            `);
                        });
                        
                        // Update totals
                        $('#view-total-amount').text(totalAmount.toLocaleString('fr-FR') + ' BIF');
                        $('#view-total-profit').text(totalProfit.toLocaleString('fr-FR') + ' BIF');
                        
                    } else {
                        // Show error
                        $('#view-produits').html(`<tr><td colspan="5" class="text-center text-danger">${response.message || 'Erreur lors du chargement des détails'}</td></tr>`);
                        toastr.error(response.message || 'Erreur lors du chargement des détails');
                    }
                },
                error: function(xhr) {
                    console.error('Error loading sale details:', xhr);
                    $('#view-produits').html('<tr><td colspan="5" class="text-center text-danger">Erreur lors du chargement des détails</td></tr>');
                    toastr.error('Erreur lors du chargement des détails');
                }
            });
        });
    }
    
    // Function to setup cancel buttons
    function setupCancelButtons() {
        $('#ventes-list').on('click', '.cancel-btn', function() {
            const row = $(this).closest('tr');
            const vente_id = $(this).data('id');
            const numero_facture = row.data('facture');
            const client = row.data('client');
            const montant = parseFloat(row.data('montant')).toLocaleString('fr-FR');
            
            // Populate cancel modal
            $('#cancel-vente-id').val(vente_id);
            $('#cancel-numero-facture').text(numero_facture);
            $('#cancel-montant').text(montant);
            $('#cancel-client').text(client);
            $('#cancel-raison').val('');
            
            // Show modal
            $('#cancel-modal').modal('show');
        });
    }
    
    // Cancel form submit
    $('#cancel-form').on('submit', function(e) {
        e.preventDefault();
        
        // Check form validity
        if (!this.checkValidity()) {
            return false;
        }
        
        // Get form data
        const formData = $(this).serialize();
        const button = $(this).find('button[type="submit"]');
        const originalText = button.html();
        
        // Disable button and show loading
        button.html('<i class="fas fa-spinner fa-spin"></i> Traitement...');
        button.attr('disabled', true);
        
        // Send cancellation request
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                // Hide modal
                $('#cancel-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                if (response.success) {
                    // Show success message
                    toastr.success(response.message || 'Vente annulée avec succès');
                    
                    // Reload sales list
                    loadVentes();
                } else {
                    // Show error message
                    toastr.error(response.message || 'Erreur lors de l\'annulation');
                }
            },
            error: function(xhr) {
                // Hide modal
                $('#cancel-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                // Show error message
                let errorMessage = 'Erreur lors de l\'annulation';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing response', e);
                }
                
                toastr.error(errorMessage);
            }
        });
    });
});
</script>

<!-- Toastr JS (must be included after jQuery from footer) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    // Initialize Toastr options
    $(document).ready(function() {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 5000
        };
    });
</script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>