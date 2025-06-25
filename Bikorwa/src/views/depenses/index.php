<?php
// Expense Management page for BIKORWA SHOP
$page_title = "Gestion des Dépenses";
$active_page = "depenses";

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in and has appropriate role
if (!$auth->isLoggedIn()) {
    header('Location: /dashboard/index.php');
    exit;
}

// For now, allow access to both gestionnaires and receptionnistes with appropriate privileges
// We can check role directly as fallback if hasAccess isn't working
$userRole = $_SESSION['user_role'] ?? '';
if (!$auth->hasAccess('depenses') && $userRole !== 'gestionnaire') {
    header('Location: /dashboard/index.php');
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$mode_paiement = $_GET['mode_paiement'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Build the base query
$query = "SELECT d.*, c.nom as categorie_nom, u.nom as utilisateur_nom 
          FROM depenses d 
          LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
          LEFT JOIN users u ON d.utilisateur_id = u.id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM depenses d WHERE 1=1";
$params = [];
$count_params = [];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (d.description LIKE ? OR d.reference_paiement LIKE ? OR c.nom LIKE ?)"; 
    $count_query .= " AND (d.description LIKE ? OR d.reference_paiement LIKE ?)"; 
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    // For count query - fewer joins
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// Add category filter if specified
if (!empty($categorie)) {
    $query .= " AND d.categorie_id = ?";
    $count_query .= " AND d.categorie_id = ?";
    array_push($params, $categorie);
    $count_params[] = $categorie;
}

// Add payment mode filter if specified
if (!empty($mode_paiement)) {
    $query .= " AND d.mode_paiement = ?";
    $count_query .= " AND d.mode_paiement = ?";
    array_push($params, $mode_paiement);
    $count_params[] = $mode_paiement;
}

// Add date range filter if specified
if (!empty($date_debut)) {
    $query .= " AND d.date_depense >= ?";
    $count_query .= " AND d.date_depense >= ?";
    array_push($params, $date_debut);
    $count_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND d.date_depense <= ?";
    $count_query .= " AND d.date_depense <= ?";
    array_push($params, $date_fin);
    $count_params[] = $date_fin;
}

// Add order by and pagination
$query .= " ORDER BY d.date_depense DESC, d.id DESC LIMIT ? OFFSET ?";

// Execute count query for pagination
$count_stmt = $conn->prepare($count_query);

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
$stmt = $conn->prepare($query);

// Bind parameters if any
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindParam($i + 1, $params[$i]);
}

// Bind pagination parameters
$param_index = count($params) + 1;
$stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
$stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$depenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expense statistics
$stats_query = "SELECT 
                COUNT(*) as total_depenses,
                SUM(montant) as montant_total,
                AVG(montant) as montant_moyen,
                MAX(montant) as montant_max,
                MIN(montant) as montant_min,
                COUNT(DISTINCT categorie_id) as nb_categories
                FROM depenses";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$statistiques = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get expense categories for dropdown
$categories_query = "SELECT id, nom FROM categories_depenses ORDER BY nom ASC";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* CSS Custom Properties for consistent styling */
    :root {
        --primary: #4e73df;
        --primary-light: #f8f9fc; /* Changed to match common style */
        --secondary: #858796;
        --success: #1cc88a;
        --info: #36b9cc;
        --warning: #f6c23e;
        --danger: #e74a3b;
        --light: #f8f9fc;
        --dark: #5a5c69;
        --border-radius: 0.35rem;
        --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        --transition: all 0.2s ease-in-out;
    }

    /* Responsive card styles */
    .stat-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card .card-body {
        padding: 1.25rem;
    }
    
    .stat-card .stat-icon {
        font-size: 2rem;
        opacity: 0.85;
    }
    
    /* Filter form styles */
    .filter-form {
        background-color: var(--light);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--box-shadow);
    }
    
    /* Button styling */
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
    
    /* Sidebar hover styles to match other pages */
    .sidebar-item:hover, .sidebar-item.active {
        color: var(--primary);
        background-color: var(--primary-light);
        border-left: 4px solid var(--primary);
        padding-left: calc(1.5rem - 4px);
    }
    
    .sidebar-subitem:hover {
        color: var(--primary);
        background-color: var(--primary-light);
    }
    
    /* Mobile-friendly adjustments */
    @media (max-width: 768px) {
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
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Gestion des Dépenses</li>
    </ol>
    
    <!-- Toast Container for Notifications -->
    <div id="toastContainer"></div>
    
    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-md-6">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus-circle me-2"></i>Nouvelle dépense
            </button>
            <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-tags me-2"></i>Nouvelle catégorie
            </button>
        </div>
        <div class="col-md-6 text-md-end">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
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
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Description, référence...">
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <select name="categorie" id="categorie" class="form-select">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($categorie == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="mode_paiement" class="form-label">Mode de paiement</label>
                        <select name="mode_paiement" id="mode_paiement" class="form-select">
                            <option value="">Tous les modes</option>
                            <option value="especes" <?php echo ($mode_paiement == 'especes') ? 'selected' : ''; ?>>Espèces</option>
                            <option value="cheque" <?php echo ($mode_paiement == 'cheque') ? 'selected' : ''; ?>>Chèque</option>
                            <option value="virement" <?php echo ($mode_paiement == 'virement') ? 'selected' : ''; ?>>Virement</option>
                            <option value="carte" <?php echo ($mode_paiement == 'carte') ? 'selected' : ''; ?>>Carte</option>
                            <option value="mobile_money" <?php echo ($mode_paiement == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
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
                        <i class="fas fa-search me-1"></i>Rechercher
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
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Dépenses</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($statistiques['montant_total'] ?? 0, 0, ',', ' '); ?> BIF
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Nombre de dépenses</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($statistiques['total_depenses'] ?? 0, 0, ',', ' '); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card bg-info text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Montant moyen</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($statistiques['montant_moyen'] ?? 0, 0, ',', ' '); ?> BIF
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Catégories utilisées</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?php echo number_format($statistiques['nb_categories'] ?? 0, 0, ',', ' '); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags stat-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expense Listing Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Liste des dépenses</h6>
            <span class="badge bg-light text-dark">Total: <span id="total-count">0</span></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Catégorie</th>
                            <th>Montant</th>
                            <th>Description</th>
                            <th>Mode paiement</th>
                            <th>Référence</th>
                            <th>Utilisateur</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expenses-list">
                        <!-- Expenses will be loaded here via JavaScript -->
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center" id="expenses-pagination">
                    <!-- Pagination will be loaded here via JavaScript -->
                </ul>
            </nav>
            
            <div id="no-expenses" class="alert alert-info d-none">
                <i class="fas fa-info-circle me-2"></i>Aucune dépense trouvée.
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addExpenseModalLabel"><i class="fas fa-plus-circle me-2"></i>Ajouter une dépense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addExpenseForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date_depense" class="form-label">Date de la dépense <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_depense" name="date_depense" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant (BIF) <span class="text-danger">*</span></label>
                                <input type="number" min="1" step="1" class="form-control" id="montant" name="montant" required placeholder="Montant en BIF">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="categorie_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select" id="categorie_id" name="categorie_id" required>
                                    <option value="">Choisir une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mode_paiement" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                    <option value="">Choisir un mode de paiement</option>
                                    <option value="especes">Espèces</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="virement">Virement</option>
                                    <option value="carte">Carte</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="description" name="description" required placeholder="Description courte de la dépense">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="reference_paiement" class="form-label">Référence de paiement</label>
                                <input type="text" class="form-control" id="reference_paiement" name="reference_paiement" placeholder="Numéro de chèque, transaction, etc.">
                            </div>
                            <div class="col-md-6">
                                <label for="note" class="form-label">Note additionnelle</label>
                                <textarea class="form-control" id="note" name="note" rows="2" placeholder="Note ou commentaire (optionnel)"></textarea>
                            </div>
                        </div>
                        <input type="hidden" name="utilisateur_id" value="<?php echo $current_user_id; ?>">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveExpenseBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editExpenseModalLabel"><i class="fas fa-edit me-2"></i>Modifier la dépense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editExpenseForm">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_date_depense" class="form-label">Date de la dépense <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_date_depense" name="date_depense" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_montant" class="form-label">Montant (BIF) <span class="text-danger">*</span></label>
                                <input type="number" min="1" step="1" class="form-control" id="edit_montant" name="montant" required placeholder="Montant en BIF">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_categorie_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_categorie_id" name="categorie_id" required>
                                    <option value="">Choisir une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mode_paiement" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_mode_paiement" name="mode_paiement" required>
                                    <option value="">Choisir un mode de paiement</option>
                                    <option value="especes">Espèces</option>
                                    <option value="cheque">Chèque</option>
                                    <option value="virement">Virement</option>
                                    <option value="carte">Carte</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_description" name="description" required placeholder="Description courte de la dépense">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_reference_paiement" class="form-label">Référence de paiement</label>
                                <input type="text" class="form-control" id="edit_reference_paiement" name="reference_paiement" placeholder="Numéro de chèque, transaction, etc.">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_note" class="form-label">Note additionnelle</label>
                                <textarea class="form-control" id="edit_note" name="note" rows="2" placeholder="Note ou commentaire (optionnel)"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="updateExpenseBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Mettre à jour
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Expense Modal -->
    <div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewExpenseModalLabel"><i class="fas fa-eye me-2"></i>Détails de la dépense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p>Chargement des détails...</p>
                    </div>
                    <div id="expenseDetails" class="d-none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Montant</h6>
                                <p class="h4 text-primary" id="view_montant">-</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Date</h6>
                                <p id="view_date_depense">-</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Catégorie</h6>
                                <p id="view_categorie">-</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Mode de paiement</h6>
                                <p id="view_mode_paiement">-</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h6 class="text-muted">Description</h6>
                                <p id="view_description">-</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Référence</h6>
                                <p id="view_reference_paiement">-</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Utilisateur</h6>
                                <p id="view_utilisateur">-</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <h6 class="text-muted">Note</h6>
                                <p id="view_note">-</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">ID</h6>
                                <p id="view_id">-</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Date d'enregistrement</h6>
                                <p id="view_date_enregistrement">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addCategoryModalLabel"><i class="fas fa-tags me-2"></i>Ajouter une catégorie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm">
                        <div class="mb-3">
                            <label for="nom_categorie" class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom_categorie" name="nom" required placeholder="Nom de la catégorie">
                        </div>
                        <div class="mb-3">
                            <label for="description_categorie" class="form-label">Description</label>
                            <textarea class="form-control" id="description_categorie" name="description" rows="3" placeholder="Description de la catégorie"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" id="saveCategoryBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="confirmationMessage"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="./depenses_script.js"></script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>