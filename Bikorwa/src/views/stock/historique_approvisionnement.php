<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enhanced debug logging
error_log("=== STOCK HISTORY ACCESS ===");
error_log("Session ID: " . session_id());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request Data: " . print_r($_REQUEST, true));

// Include config first to get BASE_URL
require_once __DIR__ . '/../../config/config.php';

// Enhanced role check
$allowedRoles = ['gestionnaire', 'admin'];
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), $allowedRoles)) {
    error_log("Access Denied - Role: " . ($_SESSION['role'] ?? 'Not Set'));
    header('Location: ' . BASE_URL . '/src/views/auth/login.php?reason=unauthorized');
    exit;
}

// Include database connection
require_once __DIR__ . '/../../../includes/db.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique d'approvisionnement - Bikorwa Shop</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <!-- Toastr CSS for toast notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" integrity="sha512-vKM35js9h5fDGXkGSp1XCMVvjJAnYXhYzu3/n6PvJEa3TBUnIFV969GryuVKy7jH9MuNoMyl2I6rNq5rW4vYg==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <!-- jQuery (required by Toastr) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ+Y3VCRppShypQOWcZH/5LiO8Z8a9kaI0s+8=" crossorigin="anonymous"></script>
    <!-- Toastr JS for toast notifications -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" integrity="sha512-5/uhZywS4cxMTKOouUMHjUo8eRP0upZgg/KwZq/953IBwYswcA6RAjHgNsx3Rp3XIanFkFJxuxMxDPZbkfK6g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Configure default Toastr options
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: 5000
            };
        }
    </script>
</head>
<body>
<?php

require_once __DIR__ . '/../layouts/header.php';

// Get date filter parameters first
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Build WHERE clause for date filtering
$date_where = "";
$date_params = [];
if (!empty($date_debut)) {
    $date_where .= " AND date_mouvement >= ?";
    $date_params[] = $date_debut;
}
if (!empty($date_fin)) {
    $date_where .= " AND date_mouvement <= ?";
    $date_params[] = $date_fin . ' 23:59:59';
}

// Fetch all supply history (stock entries) with pagination
$itemsPerPage = 10; // Number of items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number
$offset = ($page - 1) * $itemsPerPage; // Calculate offset

// Count total number of entries for pagination (with date filter)
$countSql = "SELECT COUNT(*) as total FROM mouvements_stock WHERE type_mouvement = 'entree'" . $date_where;
try {
    $countStmt = $pdo->prepare($countSql);
    if (!empty($date_params)) {
        foreach ($date_params as $index => $param) {
            $countStmt->bindValue($index + 1, $param);
        }
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    echo 'Count query error: ' . $e->getMessage() . '<br>';
    $totalItems = 0;
    $totalPages = 1;
}

// Main query with date filter
$sql = "SELECT 
    m.id, 
    p.nom AS produit_nom, 
    m.quantite, 
    m.prix_unitaire, 
    m.valeur_totale, 
    m.date_mouvement,
    u.nom AS utilisateur_nom
FROM mouvements_stock m
JOIN produits p ON m.produit_id = p.id
JOIN users u ON m.utilisateur_id = u.id
WHERE m.type_mouvement = 'entree'" . $date_where . "
ORDER BY m.date_mouvement DESC
LIMIT ?, ?";
try {
    $stmt = $pdo->prepare($sql);
    if ($stmt) {
        $paramIndex = 1;
        // Bind date parameters first
        if (!empty($date_params)) {
            foreach ($date_params as $param) {
                $stmt->bindValue($paramIndex++, $param);
            }
        }
        // Bind pagination parameters
        $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex, $itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo 'Query error: ' . $e->getMessage() . '<br>';
    $entries = array();
}

?>

<div class="content">
    <h1>Historique d'approvisionnement</h1>
    
    <!-- Date Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <!-- Existing date range filter -->
                <div class="col-md-8">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="date_debut" class="form-label">Date début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_fin" class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                            <?php if (!empty($date_debut) || !empty($date_fin)): ?>
                                <a href="?" class="btn btn-outline-secondary">Réinitialiser</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Date search functionality -->
                <div class="col-md-4">
                    <div class="border-start ps-3">
                        <label for="search_date" class="form-label">Rechercher par date spécifique</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="search_date" placeholder="Sélectionner une date">
                            <button class="btn btn-success" type="button" id="searchDateBtn">
                                <i class="fas fa-search me-1"></i>
                                Rechercher
                            </button>
                        </div>
                        <small class="text-muted">Voir tous les approvisionnements d'une date</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <!-- Total Supply Value Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-boxes text-primary fs-4 me-2"></i>
                                <div class="text-uppercase small text-primary fw-bold">
                                    <?= !empty($date_debut) || !empty($date_fin) ? 'Valeur filtrée' : 'Valeur totale' ?>
                                </div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $total_query = "SELECT SUM(valeur_totale) as total FROM mouvements_stock 
                                              WHERE type_mouvement = 'entree' $date_where";
                                $total_stmt = $pdo->prepare($total_query);
                                $total_stmt->execute($date_params);
                                $total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($total_data['total'] ?? 0, 0, ',', ' '); ?> BIF
                            </div>
                            <?php if (!empty($date_debut) || !empty($date_fin)): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-filter text-primary me-1"></i>
                                    Filtre appliqué
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Supplies Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-history text-success fs-4 me-2"></i>
                                <div class="text-uppercase small text-success fw-bold">
                                    <?= !empty($date_debut) || !empty($date_fin) ? 'Appro. filtrés' : 'Approvisionnements' ?>
                                </div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $count_query = "SELECT COUNT(*) as count FROM mouvements_stock 
                                              WHERE type_mouvement = 'entree' $date_where";
                                $count_stmt = $pdo->prepare($count_query);
                                $count_stmt->execute($date_params);
                                $count_data = $count_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($count_data['count'] ?? 0, 0, ',', ' ');
                                ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                <?php
                                $recent_query = "SELECT COUNT(*) as recent FROM mouvements_stock 
                                               WHERE type_mouvement = 'entree' 
                                               AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                               $date_where";
                                $recent_stmt = $pdo->prepare($recent_query);
                                $recent_stmt->execute($date_params);
                                $recent_data = $recent_stmt->fetch(PDO::FETCH_ASSOC);
                                echo $recent_data['recent'] ?? 0; ?> cette semaine
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Products Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-tags text-warning fs-4 me-2"></i>
                                <div class="text-uppercase small text-warning fw-bold">Produits</div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $products_query = "SELECT COUNT(DISTINCT produit_id) as count FROM mouvements_stock 
                                                 WHERE type_mouvement = 'entree' $date_where";
                                $products_stmt = $pdo->prepare($products_query);
                                $products_stmt->execute($date_params);
                                $products_data = $products_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($products_data['count'] ?? 0, 0, ',', ' ');
                                ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-warning me-1" style="font-size: 0.5rem;"></i>
                                Produits différents
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Historique d'approvisionnement</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped" id="supply-history-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix Unitaire</th>
                                    <th>Valeur Totale</th>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($entries)): ?>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($entry['id']) ?></td>
                                            <td><?= htmlspecialchars($entry['produit_nom']) ?></td>
                                            <td><?= htmlspecialchars($entry['quantite']) ?></td>
                                            <td><?= htmlspecialchars(number_format($entry['prix_unitaire'], 0, ',', ' ')) ?> BIF</td>
                                            <td><?= htmlspecialchars(number_format($entry['valeur_totale'], 0, ',', ' ')) ?> BIF</td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($entry['date_mouvement']))) ?></td>
                                            <td><?= htmlspecialchars($entry['utilisateur_nom']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">Aucun approvisionnement trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                Affichage de <b><?= min(($page - 1) * $itemsPerPage + 1, $totalItems) ?></b> à <b><?= min($page * $itemsPerPage, $totalItems) ?></b> sur <b><?= $totalItems ?></b> entrées
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for viewing supplies by date -->
<div class="modal fade" id="dateSuppliesModal" tabindex="-1" aria-labelledby="dateSuppliesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dateSuppliesModalLabel">
                    <i class="fas fa-calendar-day text-primary me-2"></i>
                    Approvisionnements du <span id="modalDateTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalLoadingSpinner" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2 text-muted">Chargement des approvisionnements...</p>
                </div>
                <div id="modalContent" style="display: none;">
                    <!-- Summary cards for the selected date -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-boxes fa-2x mb-2"></i>
                                    <h4 id="modalTotalValue">0 BIF</h4>
                                    <small>Valeur totale</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ol fa-2x mb-2"></i>
                                    <h4 id="modalTotalCount">0</h4>
                                    <small>Nombre d'entrées</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-tags fa-2x mb-2"></i>
                                    <h4 id="modalProductCount">0</h4>
                                    <small>Produits différents</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix Unitaire</th>
                                    <th>Valeur Totale</th>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="modalTableBody">
                                <!-- Content will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="modalError" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="modalErrorMessage">Une erreur s'est produite lors du chargement des données.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete button handler
    document.querySelectorAll('.delete-supply').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (confirm('Êtes-vous sûr de vouloir supprimer cet approvisionnement ?')) {
                // Submit form for better reliability
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= BASE_URL ?>/src/views/stock/delete_supply.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;

                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'PHPSESSID';
                sessionInput.value = '<?= session_id() ?>';

                form.appendChild(idInput);
                form.appendChild(sessionInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Search date button handler
    document.getElementById('searchDateBtn').addEventListener('click', function() {
        const dateInput = document.getElementById('search_date');
        const date = dateInput.value;

        if (!date) {
            showToast('Veuillez sélectionner une date', 'warning');
            return;
        }
        
        // Format date for display
        const dateObj = new Date(date);
        const dateFormatted = dateObj.toLocaleDateString('fr-FR');
        
        // Update modal title
        document.getElementById('modalDateTitle').textContent = dateFormatted;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('dateSuppliesModal'));
        modal.show();
        
        // Reset modal content
        document.getElementById('modalLoadingSpinner').style.display = 'block';
        document.getElementById('modalContent').style.display = 'none';
        document.getElementById('modalError').style.display = 'none';
        
        // Fetch data for the selected date
        fetchDateSupplies(date);
    });
    
    // Allow Enter key to trigger search
    document.getElementById('search_date').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchDateBtn').click();
        }
    });
    
    function fetchDateSupplies(date) {
        // Create form data
        const formData = new FormData();
        formData.append('date', date);
        formData.append('PHPSESSID', '<?= session_id() ?>');
        
        fetch('<?= BASE_URL ?>/src/views/stock/get_date_supplies.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDateSupplies(data.supplies, data.summary);
            } else {
                showModalError(data.message || 'Erreur lors du chargement des données');
                showToast(data.message || 'Erreur lors du chargement des données', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showModalError('Erreur de connexion au serveur');
            showToast('Erreur de connexion au serveur', 'error');
        });
    }
    
    function displayDateSupplies(supplies, summary) {
        // Hide loading spinner
        document.getElementById('modalLoadingSpinner').style.display = 'none';
        
        // Update summary cards
        document.getElementById('modalTotalValue').textContent = formatNumber(summary.total_value) + ' BIF';
        document.getElementById('modalTotalCount').textContent = summary.total_count;
        document.getElementById('modalProductCount').textContent = summary.product_count;
        
        // Update table
        const tbody = document.getElementById('modalTableBody');
        tbody.innerHTML = '';
        
        if (supplies.length > 0) {
            supplies.forEach(supply => {
                const row = document.createElement('tr');
                row.dataset.id = supply.id;
                row.dataset.produitId = supply.produit_id;
                const dateValue = supply.date_mouvement.replace(' ', 'T').slice(0,16);
                row.innerHTML = `
                    <td>${escapeHtml(supply.id)}</td>
                    <td>${escapeHtml(supply.produit_nom)}</td>
                    <td><input type="number" class="form-control form-control-sm quantite-input" value="${escapeHtml(supply.quantite)}"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm prix-input" value="${escapeHtml(supply.prix_unitaire)}"></td>
                    <td class="valeur-cell">${formatNumber(supply.valeur_totale)} BIF</td>
                    <td><input type="datetime-local" class="form-control form-control-sm date-input" value="${dateValue}"></td>
                    <td>${escapeHtml(supply.utilisateur_nom)}</td>
                    <td><button class="btn btn-sm btn-primary save-row"><i class="fas fa-save"></i></button></td>
                `;
                tbody.appendChild(row);
            });

            // Update total value when quantity or price changes
            tbody.querySelectorAll('.quantite-input, .prix-input').forEach(input => {
                input.addEventListener('input', function() {
                    const row = this.closest('tr');
                    const q = parseFloat(row.querySelector('.quantite-input').value) || 0;
                    const p = parseFloat(row.querySelector('.prix-input').value) || 0;
                    row.querySelector('.valeur-cell').textContent = formatNumber(q * p) + ' BIF';
                });
            });

            // Save button handler for each row
            tbody.querySelectorAll('.save-row').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const id = row.dataset.id;
                    const produitId = row.dataset.produitId;
                    const quantite = row.querySelector('.quantite-input').value;
                    const prix = row.querySelector('.prix-input').value;
                    const date = row.querySelector('.date-input').value;

                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('produit_id', produitId);
                    formData.append('quantite', quantite);
                    formData.append('prix_unitaire', prix);
                    formData.append('date_mouvement', date.replace('T', ' ') + ':00');
                    formData.append('PHPSESSID', '<?= session_id() ?>');

                    fetch('<?= BASE_URL ?>/src/views/stock/update_supply.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Mise à jour réussie');
                        } else {
                            showToast(data.message || 'Erreur lors de la mise à jour', 'error');
                        }
                    })
                    .catch(() => {
                        showToast('Erreur de connexion au serveur', 'error');
                    });
                });
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="8" class="text-center text-muted">Aucun approvisionnement trouvé pour cette date.</td>';
            tbody.appendChild(row);
        }
        
        // Show content
        document.getElementById('modalContent').style.display = 'block';
    }
    
    function showModalError(message) {
        document.getElementById('modalLoadingSpinner').style.display = 'none';
        document.getElementById('modalContent').style.display = 'none';
        document.getElementById('modalErrorMessage').textContent = message;
        document.getElementById('modalError').style.display = 'block';
    }
    
    function formatNumber(number) {
        return new Intl.NumberFormat('fr-FR').format(number || 0);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'success') {
        if (typeof toastr !== 'undefined') {
            toastr[type](message);
        } else {
            alert(message);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</body>
</html>
