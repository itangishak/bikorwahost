<?php
// Production error settings
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

try {
    // Debt Management page for BIKORWA SHOP
    $page_title = "Gestion des Dettes";
    $active_page = "dettes";

    // Start session if not active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include dependencies
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../../includes/session.php';
    require_once '../../utils/Auth.php';

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize authentication
    $auth = new Auth($conn);

    // Check if user is logged in and has appropriate role
    // Redirect unauthenticated users to the login page rather than the dashboard
    if (!$auth->isLoggedIn()) {
        header('Location: ' . BASE_URL . '/src/views/auth/login.php');
        exit;
    }

    // Allow access to both gestionnaires and receptionnistes with appropriate privileges
    $userRole = strtolower($_SESSION['role'] ?? '');
    if (!$auth->hasAccess('dettes') && $userRole !== 'gestionnaire') {
        header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
        exit;
    }

    // Get current user ID for logging actions
    $current_user_id = $_SESSION['user_id'] ?? 0;

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$statut = $_GET['statut'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Build the base query
$query = "SELECT d.*, c.nom as client_nom, v.numero_facture,
          (SELECT MAX(pd.date_paiement) FROM paiements_dettes pd WHERE pd.dette_id = d.id) as date_dernier_paiement,
          (SELECT COUNT(*) FROM paiements_dettes pd WHERE pd.dette_id = d.id) as nombre_paiements
          FROM dettes d 
          LEFT JOIN clients c ON d.client_id = c.id 
          LEFT JOIN ventes v ON d.vente_id = v.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM dettes d LEFT JOIN clients c ON d.client_id = c.id WHERE 1=1";
$params = [];
$count_params = [];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (c.nom LIKE ? OR c.telephone LIKE ? OR d.note LIKE ? OR v.numero_facture LIKE ?)"; 
    $count_query .= " AND (c.nom LIKE ? OR c.telephone LIKE ? OR d.note LIKE ?)"; 
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    array_push($count_params, $search_param, $search_param, $search_param);
}

// Add client filter if specified
if (!empty($client_id)) {
    $query .= " AND d.client_id = ?";
    $count_query .= " AND d.client_id = ?";
    array_push($params, $client_id);
    $count_params[] = $client_id;
}

// Add status filter if specified
if (!empty($statut)) {
    $query .= " AND d.statut = ?";
    $count_query .= " AND d.statut = ?";
    array_push($params, $statut);
    $count_params[] = $statut;
} else {
    // By default, only show active and partially paid debts (exclude paid and cancelled)
    $query .= " AND d.statut IN ('active', 'partiellement_payee')";
    $count_query .= " AND d.statut IN ('active', 'partiellement_payee')";
}

// Add date range filter if specified
if (!empty($date_debut)) {
    $query .= " AND d.date_creation >= ?";
    $count_query .= " AND d.date_creation >= ?";
    array_push($params, $date_debut);
    $count_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND d.date_creation <= ?";
    $count_query .= " AND d.date_creation <= ?";
    array_push($params, $date_fin);
    $count_params[] = $date_fin;
}

// Add order by and pagination
$query .= " ORDER BY d.date_creation DESC, d.id DESC LIMIT ? OFFSET ?";

// Execute count query for pagination
$count_stmt = $conn->prepare($count_query);

// Bind parameters for count query
for ($i = 0; $i < count($count_params); $i++) {
    $count_stmt->bindParam($i + 1, $count_params[$i]);
}

$count_stmt->execute();
$result = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_rows = $result['total'];
$total_pages = ceil($total_rows / $items_per_page);

// Validate current page
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Recalculate offset
$offset = ($current_page - 1) * $items_per_page;

// Execute the main query
$stmt = $conn->prepare($query);

// Bind parameters
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindParam($i + 1, $params[$i]);
}

// Bind pagination parameters
$param_index = count($params) + 1;
$stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
$stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$dettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get debt statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN statut = 'active' THEN 1 END) as dettes_actives,
    COUNT(CASE WHEN statut = 'partiellement_payee' THEN 1 END) as dettes_partiellement_payees,
    COUNT(CASE WHEN statut = 'payee' THEN 1 END) as dettes_payees,
    COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as dettes_annulees,
    SUM(CASE WHEN statut IN ('active', 'partiellement_payee') THEN montant_restant ELSE 0 END) as montant_total_restant
    FROM dettes";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get clients list for filter
$clients_query = "SELECT id, nom FROM clients ORDER BY nom ASC";
$clients_stmt = $conn->prepare($clients_query);
$clients_stmt->execute();
$clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
    /* CSS Custom Properties for consistent styling */
    :root {
        --primary: #4e73df;
        --primary-light: #f8f9fc;
        --secondary: #858796;
        --success: #1cc88a;
        --info: #36b9cc;
        --warning: #f6c23e;
        --danger: #e74a3b;
        --light: #f8f9fc;
        --dark: #5a5c69;
        --border-radius: 0.35rem;
        --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }
    
    .stat-card {
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card .card-body {
        padding: 1.25rem;
    }
    
    .stat-card .stat-icon {
        font-size: 2rem;
        opacity: 0.3;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    
    .stat-card .stat-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        margin-bottom: 0;
    }
    
    .filter-form {
        padding: 1rem;
        background-color: var(--primary-light);
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
    }
    
    .btn-action {
        margin-right: 3px;
        padding: 0.2rem 0.4rem;
    }
    
    .btn-action .fas {
        font-size: 0.75rem;
    }
    
    /* Toast container for notifications */
    #toastContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
    }
    
    /* Loading spinner */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.2em;
    }
    
    /* Responsive design adjustments */
    @media (max-width: 768px) {
        .stat-card .stat-value {
            font-size: 1.2rem;
        }
        
        .stat-card .stat-icon {
            font-size: 1.5rem;
        }
        
        .card-title {
            font-size: 0.9rem;
        }
        
        .filter-form .form-group {
            margin-bottom: 0.5rem;
        }
    }
 </style>
 <div class="container-fluid px-4">
    <!-- Page Header -->
    <header class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2 text-gray-800"><?php echo $page_title; ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard/index.php" class="text-decoration-none">Tableau de bord</a></li>
                    <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detteModal">
            <i class="fas fa-plus-circle me-2"></i>Nouvelle dette
        </button>
    </header>

     <!-- Action buttons and filters -->
     <div class="row mb-4">
         <div class="col-md-6">
             <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                 <i class="fas fa-filter me-1"></i>Filtres avancés
             </button>
         </div>
     </div>
    
    <!-- Collapsible Filter Form -->
    <div class="collapse mb-4" id="filterCollapse">
        <div class="filter-form">
            <form action="./index.php" method="get" class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Nom, téléphone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo ($client_id == $client['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?php echo ($statut == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="partiellement_payee" <?php echo ($statut == 'partiellement_payee') ? 'selected' : ''; ?>>Partiellement payée</option>
                            <option value="payee" <?php echo ($statut == 'payee') ? 'selected' : ''; ?>>Payée</option>
                            <option value="annulee" <?php echo ($statut == 'annulee') ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="date_debut" class="form-label">Date début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="date_fin" class="form-label">Date fin</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                    </div>
                </div>
                
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filtrer
                    </button>
                    <a href="./index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-redo me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Active Debts -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['dettes_actives'] ?? 0, 0, ',', ' '); ?></div>
                            <div class="stat-label">Dettes actives</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Partially Paid Debts -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['dettes_partiellement_payees'] ?? 0, 0, ',', ' '); ?></div>
                            <div class="stat-label">Partiellement payées</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paid Debts -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['dettes_payees'] ?? 0, 0, ',', ' '); ?></div>
                            <div class="stat-label">Dettes payées</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Remaining -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['montant_total_restant'] ?? 0, 0, ',', ' '); ?> F</div>
                            <div class="stat-label">Montant restant</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- Debts Table Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-table me-1"></i>Liste des dettes</h6>
                <div>
                    <?php if ($auth->hasAccess('dettes') || $userRole === 'gestionnaire'): ?>
                        <button class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#detteModal">
                            <i class="fas fa-plus me-1"></i>Ajouter une dette
                        </button>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark">Total: <?php echo number_format($total_rows, 0, ',', ' '); ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($dettes)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Client</th>
                                    <th>Montant initial</th>
                                    <th>Montant restant</th>
                                    <th>Date création</th>
                                    <th>Date échéance</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dettes as $dette): ?>
                                    <tr data-id="<?php echo $dette['id']; ?>">
                                        <td><?php echo htmlspecialchars($dette['client_nom']); ?></td>
                                        <td><?php echo number_format($dette['montant_initial'], 0, ',', ' '); ?> F</td>
                                        <td><?php echo number_format($dette['montant_restant'], 0, ',', ' '); ?> F</td>
                                        <td><?php echo date('d/m/Y', strtotime($dette['date_creation'])); ?></td>
                                        <td>
                                            <?php if (!empty($dette['date_echeance'])): ?>
                                                <?php echo date('d/m/Y', strtotime($dette['date_echeance'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($dette['statut'] == 'active'): ?>
                                                <span class="badge bg-warning">Active</span>
                                            <?php elseif ($dette['statut'] == 'partiellement_payee'): ?>
                                                <span class="badge bg-info">Partiellement payée</span>
                                            <?php elseif ($dette['statut'] == 'payee'): ?>
                                                <span class="badge bg-success">Payée</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Annulée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info btn-action viewBtn" data-id="<?php echo $dette['id']; ?>" title="Détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (in_array($dette['statut'], ['active', 'partiellement_payee'])): ?>
                                                <button class="btn btn-sm btn-success btn-action payBtn" data-id="<?php echo $dette['id']; ?>" title="Paiement">
                                                    <i class="fas fa-money-bill"></i>
                                                </button>
                                                
                                                <?php if ($userRole === 'gestionnaire'): ?>
                                                <button class="btn btn-sm btn-primary btn-action editBtn" data-id="<?php echo $dette['id']; ?>" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($userRole === 'gestionnaire'): ?>
                                                <button class="btn btn-sm btn-danger btn-action deleteBtn" data-id="<?php echo $dette['id']; ?>" title="Annuler">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Pagination des dettes">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&client_id=<?php echo urlencode($client_id); ?>&statut=<?php echo urlencode($statut); ?>&date_debut=<?php echo urlencode($date_debut); ?>&date_fin=<?php echo urlencode($date_fin); ?>">Précédent</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&client_id=<?php echo urlencode($client_id); ?>&statut=<?php echo urlencode($statut); ?>&date_debut=<?php echo urlencode($date_debut); ?>&date_fin=<?php echo urlencode($date_fin); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&client_id=<?php echo urlencode($client_id); ?>&statut=<?php echo urlencode($statut); ?>&date_debut=<?php echo urlencode($date_debut); ?>&date_fin=<?php echo urlencode($date_fin); ?>">Suivant</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Aucune dette trouvée
                    </div>
                <?php endif; ?>
            </div>
        </div>
            <!-- Toast Container -->
            <div id="toastContainer"></div>
            
            <!-- View Debt Modal -->
            <div class="modal fade" id="viewDetteModal" tabindex="-1" aria-labelledby="viewDetteModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title" id="viewDetteModalLabel"><i class="fas fa-eye me-2"></i>Détails de la dette</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-3 d-none" id="detailsSpinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                            <div id="detteDetails">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Client</h6>
                                        <p id="view_client_nom" class="fs-5 fw-bold">-</p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <h6 class="text-muted">Statut</h6>
                                        <p id="view_statut">-</p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Montant initial</h6>
                                        <p id="view_montant_initial">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Montant restant</h6>
                                        <p id="view_montant_restant">-</p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Date de création</h6>
                                        <p id="view_date_creation">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Date d'échéance</h6>
                                        <p id="view_date_echeance">-</p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Numéro de facture</h6>
                                        <p id="view_facture">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">ID</h6>
                                        <p id="view_id">-</p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-muted">Note</h6>
                                        <p id="view_note">-</p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h5 class="mb-3">Historique des paiements</h5>
                                <div id="paiementsTableContainer">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="paiementsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Montant</th>
                                                    <th>Méthode</th>
                                                    <th>Référence</th>
                                                    <th>Utilisateur</th>
                                                </tr>
                                            </thead>
                                            <tbody id="paiementsTableBody">
                                                <!-- Populated dynamically -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="noPaiements" class="alert alert-info d-none">
                                    <i class="fas fa-info-circle me-2"></i>Aucun paiement enregistré
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add/Edit Debt Modal -->
            <div class="modal fade" id="detteModal" tabindex="-1" aria-labelledby="detteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="detteModalLabel"><i class="fas fa-money-bill-wave me-2"></i>Gestion dette</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-3 d-none" id="detteSpinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                            <form id="detteForm">
                                <input type="hidden" id="dette_id" name="dette_id">
                                
                                <div class="mb-3">
                                    <label for="client_id_form" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select class="form-select" id="client_id_form" name="client_id" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="montant_initial" class="form-label">Montant initial <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="montant_initial" name="montant_initial" required min="1" step="1">
                                        <span class="input-group-text">F</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="date_creation" class="form-label">Date de la dette <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_creation" name="date_creation" required value="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="date_echeance" class="form-label">Date d'échéance</label>
                                    <input type="date" class="form-control" id="date_echeance" name="date_echeance">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="note" class="form-label">Note</label>
                                    <textarea class="form-control" id="note" name="note" rows="3" placeholder="Détails additionnels sur la dette..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-primary" id="saveDetteBtn">
                                <span class="spinner-border spinner-border-sm d-none" id="saveDetteSpinner" role="status" aria-hidden="true"></span>
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Modal -->
            <div class="modal fade" id="paiementModal" tabindex="-1" aria-labelledby="paiementModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="paiementModalLabel"><i class="fas fa-money-bill-wave me-2"></i>Enregistrer un paiement</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="me-3"><i class="fas fa-info-circle fa-2x"></i></div>
                                    <div>
                                        <p class="mb-1"><strong>Client:</strong> <span id="paiement-client-nom">-</span></p>
                                        <p class="mb-0"><strong>Montant restant:</strong> <span id="paiement-montant-restant">-</span> F</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form id="paiementForm">
                                <input type="hidden" id="paiement_dette_id" name="dette_id">
                                
                                <div class="mb-3">
                                    <label for="montant" class="form-label">Montant du paiement <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="montant" name="montant" required min="1" step="1">
                                        <span class="input-group-text">F</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="methode_paiement" class="form-label">Méthode de paiement <span class="text-danger">*</span></label>
                                    <select class="form-select" id="methode_paiement" name="methode_paiement" required>
                                        <option value="">Sélectionner une méthode</option>
                                        <option value="espèces">Espèces</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="carte_bancaire">Carte bancaire</option>
                                        <option value="virement">Virement bancaire</option>
                                        <option value="chèque">Chèque</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reference" class="form-label">Référence</label>
                                    <input type="text" class="form-control" id="reference" name="reference" placeholder="N° de transaction, chèque...">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="note_paiement" class="form-label">Note</label>
                                    <textarea class="form-control" id="note_paiement" name="note" rows="2" placeholder="Commentaire sur le paiement..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-success" id="savePaiementBtn">
                                <span class="spinner-border spinner-border-sm d-none" id="savePaiementSpinner" role="status" aria-hidden="true"></span>
                                Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Êtes-vous sûr de vouloir annuler cette dette?</p>
                            <p class="text-muted">Cette action ne peut pas être annulée. La dette sera marquée comme annulée.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn" data-id="">
                                <span class="spinner-border spinner-border-sm d-none" id="deleteSpinner" role="status" aria-hidden="true"></span>
                                Confirmer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pass user role information to JavaScript -->
            <script>
                // Store user role for permission checks
                const userRole = '<?php echo $userRole; ?>';
                const isManager = <?php echo ($userRole === 'gestionnaire') ? 'true' : 'false'; ?>;
                const currentUserId = <?php echo (int)($current_user_id ?? 0); ?>;
                const baseUrl = '<?php echo BASE_URL; ?>';
            </script>
            
            <!-- External JavaScript for debt management functionality -->
            <script src="<?php echo BASE_URL; ?>/src/views/dettes/fixed_script.js"></script>
            <!-- All JavaScript code has been moved to the external file for better organization and maintenance -->

            </div>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
<?php } catch (Exception $e) {
    echo '<div style="background:#ffeeee;padding:20px;border:2px solid red;margin:20px">';
    echo '<h3>Error Debug Information</h3>';
    echo '<p><strong>Error:</strong> '.htmlspecialchars($e->getMessage()).'</p>';
    echo '<pre>Stack Trace: '.htmlspecialchars($e->getTraceAsString()).'</pre>';
    echo '</div>';
    error_log('Dettes Error: '.$e->getMessage());
    exit;
}