<?php
// Production error settings
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

try {
    // Start session if not active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Include dependencies
    require_once('../../config/database.php');
    require_once('../../config/config.php');
    require_once('../../../includes/session.php');

    // Verify authentication
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: ../auth/login.php');
        exit;
    }

    // Page settings
    $page_title = "Liste des Employés";
    $active_page = "employes";

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    $settingsObj = new Settings($conn);

    // Initialize auth
    $auth = new Auth($conn);
    $authController = new AuthController();

    // Set default values and get search parameters
    $search = $_GET['search'] ?? '';
    $statut = $_GET['statut'] ?? '';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = (int)$settingsObj->get('items_per_page', 10);
    $offset = ($current_page - 1) * $items_per_page;

// Build the base query
$query = "SELECT * FROM employes WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM employes WHERE 1=1";
$params = [];

// Add search conditions if any
if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR poste LIKE ?)";
    $count_query .= " AND (nom LIKE ? OR telephone LIKE ? OR email LIKE ? OR poste LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

// Add status filter if specified
if ($statut === 'actif') {
    $query .= " AND actif = 1";
    $count_query .= " AND actif = 1";
} elseif ($statut === 'inactif') {
    $query .= " AND actif = 0";
    $count_query .= " AND actif = 0";
}

// Add order by and pagination
$query .= " ORDER BY nom ASC LIMIT ? OFFSET ?";

// Execute count query for pagination
$count_stmt = $conn->prepare($count_query);

// Bind parameters for count query if any
for ($i = 0; $i < count($params); $i++) {
    $count_stmt->bindParam($i + 1, $params[$i], PDO::PARAM_STR);
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
    $stmt->bindParam($i + 1, $params[$i], PDO::PARAM_STR);
}

// Bind pagination parameters
$param_index = count($params) + 1;
$stmt->bindParam($param_index++, $items_per_page, PDO::PARAM_INT);
$stmt->bindParam($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee statistics
$stats_query = "SELECT 
                COUNT(*) as total_employes,
                SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as employes_actifs,
                SUM(CASE WHEN actif = 1 THEN salaire ELSE 0 END) as masse_salariale
                FROM employes";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$statistiques = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* Fix for smaller icons in action buttons */
    .btn-action {
        margin-right: 3px;
        padding: 0.2rem 0.4rem;
    }
    .btn-action .fas {
        font-size: 0.75rem;
    }
    /* Improve number formatting for salary */
    .salary-cell {
        text-align: right;
        font-family: monospace;
    }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../dashboard/index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Liste des Employés</li>
    </ol>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <?php if ($auth->canModify()): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fas fa-user-plus me-2"></i>Nouvel employé
                </button>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <form action="./liste.php" method="get" class="d-flex justify-content-end">
                <div class="input-group">
                    <select name="statut" class="form-select" style="max-width: 200px;">
                        <option value="">Tous les statuts</option>
                        <option value="actif" <?php echo ($statut == 'actif') ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactif" <?php echo ($statut == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                    <input type="text" class="form-control" name="search" placeholder="Rechercher un employé..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Statistics -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-left-primary h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Nombre total d'employés</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['total_employes'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-left-success h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Employés actifs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['employes_actifs'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-left-warning h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Masse salariale mensuelle</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['masse_salariale'] ?? 0, 0, ',', ' '); ?> BIF</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-alt fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee List Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-users me-2"></i>Liste des employés</h6>
            <span class="badge bg-light text-dark">Total: <?php echo $total_rows; ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($employes)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Poste</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Date d'embauche</th>
                                <th>Salaire</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employes as $employe): ?>
                                <tr data-employee-id="<?php echo $employe['id']; ?>">
                                    <td><?php echo $employe['id']; ?></td>
                                    <td><?php echo htmlspecialchars($employe['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['poste']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['telephone']); ?></td>
                                    <td><?php echo htmlspecialchars($employe['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($employe['date_embauche'])); ?></td>
                                    <td class="salary-cell"><?php echo number_format((float)$employe['salaire'], 0, ',', ' '); ?> BIF</td>
                                    <td class="text-center status-cell">
                                        <?php if ($employe['actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- View Button -->
                                        <button type="button" class="btn btn-sm btn-info btn-action" title="Détails" 
                                                onclick="viewEmployee(<?php echo $employe['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($auth->canModify()): ?>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-warning btn-action" title="Modifier"
                                                    onclick="editEmployee(<?php echo $employe['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($auth->canDelete()): ?>
                                                <!-- Toggle Status Button -->
                                                <button type="button" class="btn btn-sm <?php echo $employe['actif'] ? 'btn-danger' : 'btn-success'; ?> btn-action toggle-status-btn" 
                                                    title="<?php echo $employe['actif'] ? 'Désactiver' : 'Activer'; ?>" 
                                                    onclick="toggleStatus(<?php echo $employe['id']; ?>, <?php echo $employe['actif'] ? 'true' : 'false'; ?>)">
                                                    <i class="fas <?php echo $employe['actif'] ? 'fa-times' : 'fa-check'; ?>"></i>
                                                </button>
                                                
                                                <!-- Delete Button -->
                                                <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" title="Supprimer"
                                                    onclick="deleteEmployee(<?php echo $employe['id']; ?>, '<?php echo htmlspecialchars($employe['nom'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="small text-muted">
                            Affichage de <?php echo min(($current_page - 1) * $items_per_page + 1, $total_rows); ?> à 
                            <?php echo min($current_page * $items_per_page, $total_rows); ?> sur <?php echo $total_rows; ?> employés
                        </div>
                        <nav aria-label="Pagination des employés">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- First Page -->
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statut) ? '&statut=' . urlencode($statut) : ''; ?>" aria-label="Première page">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Previous Page -->
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statut) ? '&statut=' . urlencode($statut) : ''; ?>" aria-label="Page précédente">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php 
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                // Always show at least 5 pages if available
                                if ($end_page - $start_page + 1 < 5) {
                                    if ($start_page == 1) {
                                        $end_page = min($total_pages, $start_page + 4);
                                    } else {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statut) ? '&statut=' . urlencode($statut) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page -->
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statut) ? '&statut=' . urlencode($statut) : ''; ?>" aria-label="Page suivante">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                
                                <!-- Last Page -->
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($statut) ? '&statut=' . urlencode($statut) : ''; ?>" aria-label="Dernière page">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Aucun employé trouvé.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addEmployeeModalLabel">Ajouter un nouvel employé</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEmployeeForm" method="post" action="#">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="poste" class="form-label">Poste <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="poste" name="poste" required>
                        </div>
                        <div class="col-md-6">
                            <label for="date_embauche" class="form-label">Date d'embauche <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_embauche" name="date_embauche" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="salaire" class="form-label">Salaire mensuel <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="salaire" name="salaire" min="0" required>
                                <span class="input-group-text">BIF</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="actif" class="form-label">Statut</label>
                            <select class="form-select" id="actif" name="actif">
                                <option value="1" selected>Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label">Note</label>
                        <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Employee Modal -->
<div class="modal fade" id="viewEmployeeModal" tabindex="-1" aria-labelledby="viewEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewEmployeeModalLabel">Détails de l'employé</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="employeeDetails">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
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

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editEmployeeModalLabel">Modifier l'employé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEmployeeForm" method="post" action="./process.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_id" name="id" value="">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_telephone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="edit_telephone" name="telephone">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="edit_adresse" name="adresse" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_poste" class="form-label">Poste <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_poste" name="poste" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date_embauche" class="form-label">Date d'embauche <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_date_embauche" name="date_embauche" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_salaire" class="form-label">Salaire mensuel <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_salaire" name="salaire" min="0" required>
                                <span class="input-group-text">BIF</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_actif" class="form-label">Statut</label>
                            <select class="form-select" id="edit_actif" name="actif">
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_note" class="form-label">Note</label>
                        <textarea class="form-control" id="edit_note" name="note" rows="3"></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning">Mettre à jour</button>
                    </div>
                </form>
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
            <div class="modal-body" id="confirmationMessage">
                Êtes-vous sûr de vouloir effectuer cette action ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmAction">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize tooltips and setup AJAX forms
    document.addEventListener('DOMContentLoaded', function() {
        // Enable tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Set today's date as default for date fields
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date_embauche').value = today;
        
        // Add employee form AJAX submission
        document.getElementById('addEmployeeForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            
            // Validate the form
            if (!form.checkValidity()) {
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            
            // Show spinner or loading message
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...';
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Hide the modal
                bootstrap.Modal.getInstance(document.getElementById('addEmployeeModal')).hide();
                
                if (data.success) {
                    // Show success toast/alert
                    showToast('Succès', data.message || "L'employé a été ajouté avec succès", 'success');
                    
                    // Reload employee data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Handle duplicate error specifically
                    if (data.error === 'duplicate' || data.details) {
                        let errorMessage = data.message || 'Doublon détecté';
                        if (data.details && Array.isArray(data.details)) {
                            errorMessage += ':\n' + data.details.join('\n');
                        }
                        showToast('Doublon détecté', errorMessage, 'warning', 8000);
                    } else {
                        // Show general error toast/alert
                        showToast('Erreur', data.message || "Erreur lors de l'ajout de l'employé", 'danger');
                    }
                    
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', "Une erreur est survenue lors de la communication avec le serveur", 'danger');
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
            
            form.classList.add('was-validated');
        });
        
        // Edit employee form AJAX submission
        document.getElementById('editEmployeeForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            
            // Validate the form
            if (!form.checkValidity()) {
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            
            // Show spinner or loading message
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Mise à jour...';
            
            // Submit form via AJAX
            const formData = new FormData(form);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the modal
                    bootstrap.Modal.getInstance(document.getElementById('editEmployeeModal')).hide();
                    
                    // Show success toast/alert
                    showToast('Succès', data.message || "Les informations de l'employé ont été mises à jour", 'success');
                    
                    // Reload employee data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Reset button first
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    
                    // Handle duplicate error specifically
                    if (data.message && data.message.includes('Doublon détecté')) {
                        let errorMessage = 'Un employé avec des informations similaires existe déjà:';
                        if (data.details && Array.isArray(data.details)) {
                            errorMessage += '<ul class="mt-2 mb-0">';
                            data.details.forEach(detail => {
                                errorMessage += `<li>${detail}</li>`;
                            });
                            errorMessage += '</ul>';
                        }
                        
                        // Show detailed duplicate error
                        showToast('Doublon détecté', errorMessage, 'warning', 8000);
                    } else {
                        // Show general error
                        showToast('Erreur', data.message || "Erreur lors de la mise à jour de l'employé", 'danger');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', "Une erreur est survenue lors de la communication avec le serveur", 'danger');
                
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
            
            form.classList.add('was-validated');
        });
        
        // Reset forms when modals are closed
        document.getElementById('addEmployeeModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addEmployeeForm').reset();
            document.getElementById('addEmployeeForm').classList.remove('was-validated');
            document.getElementById('date_embauche').value = today;
        });
        
        document.getElementById('editEmployeeModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('editEmployeeForm').classList.remove('was-validated');
        });
    });
    
    // Function to view employee details
    function viewEmployee(id) {
        // Show modal
        const viewModal = new bootstrap.Modal(document.getElementById('viewEmployeeModal'));
        viewModal.show();
        
        // Show loading spinner
        document.getElementById('employeeDetails').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;
        
        // Fetch employee details
        fetch(`./get_employe.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const employe = data.employe;
                    const statusBadge = employe.actif ? 
                        '<span class="badge bg-success">Actif</span>' : 
                        '<span class="badge bg-danger">Inactif</span>';
                    
                    // Format date
                    const dateEmbauche = new Date(employe.date_embauche).toLocaleDateString('fr-FR');
                    
                    // Build HTML for employee details
                    let html = `
                        <div class="card border-info mb-3">
                            <div class="card-header bg-info text-white">
                                <h5 class="m-0"><i class="fas fa-user me-2"></i>${employe.nom}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>ID:</strong> ${employe.id}</p>
                                        <p><strong>Poste:</strong> ${employe.poste}</p>
                                        <p><strong>Date d'embauche:</strong> ${dateEmbauche}</p>
                                        <p><strong>Statut:</strong> ${statusBadge}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Téléphone:</strong> ${employe.telephone || 'Non spécifié'}</p>
                                        <p><strong>Email:</strong> ${employe.email || 'Non spécifié'}</p>
                                        <p><strong>Salaire mensuel:</strong> ${new Intl.NumberFormat('fr-FR').format(employe.salaire)} F</p>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p><strong>Adresse:</strong></p>
                                    <p>${employe.adresse || 'Non spécifiée'}</p>
                                </div>
                                <div>
                                    <p><strong>Note:</strong></p>
                                    <p>${employe.note || 'Aucune note'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('employeeDetails').innerHTML = html;
                } else {
                    document.getElementById('employeeDetails').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>${data.message || "Erreur lors du chargement des détails de l'employé"}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('employeeDetails').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Erreur lors du chargement des détails de l'employé
                    </div>
                `;
                console.error('Error:', error);
            });
    }
    
    // Function to load employee data for editing
    function editEmployee(id) {
        // Fetch employee details
        fetch(`./get_employe.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const employe = data.employe;
                    
                    // Fill the form with employee data
                    document.getElementById('edit_id').value = employe.id;
                    document.getElementById('edit_nom').value = employe.nom;
                    document.getElementById('edit_telephone').value = employe.telephone || '';
                    document.getElementById('edit_email').value = employe.email || '';
                    document.getElementById('edit_adresse').value = employe.adresse || '';
                    document.getElementById('edit_poste').value = employe.poste;
                    
                    // Format date to YYYY-MM-DD for input type="date"
                    const dateObj = new Date(employe.date_embauche);
                    const formattedDate = dateObj.toISOString().split('T')[0];
                    document.getElementById('edit_date_embauche').value = formattedDate;
                    
                    document.getElementById('edit_salaire').value = employe.salaire;
                    document.getElementById('edit_actif').value = employe.actif ? '1' : '0';
                    document.getElementById('edit_note').value = employe.note || '';
                    
                    // Show the modal
                    const editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
                    editModal.show();
                } else {
                    showToast('Erreur', data.message || "Erreur lors du chargement des informations de l'employé", 'danger');
                }
            })
            .catch(error => {
                showToast('Erreur', "Erreur lors du chargement des informations de l'employé", 'danger');
                console.error('Error:', error);
            });
    }
    
    // Function to delete an employee
    function deleteEmployee(id, nom) {
        document.getElementById('confirmationMessage').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Êtes-vous sûr de vouloir supprimer définitivement l'employé <strong>${nom}</strong> ?
                <br><small class="text-danger">Cette action est irréversible.</small>
            </div>
        `;
        
        // Set up confirmation action
        document.getElementById('confirmAction').onclick = function() {
            // Hide confirmation modal
            bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
            
            // Show loading toast
            showToast('Traitement', 'Suppression en cours...', 'info', 2000);
            
            // Send request to delete employee
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e, text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Show success toast
                    showToast('Succès', data.message || "L'employé a été supprimé avec succès", 'success');
                    
                    // Remove the row from the table without reloading the page
                    const row = document.querySelector(`tr[data-employee-id="${id}"]`);
                    if (row) {
                        // Add fade-out animation
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        
                        // Remove the row after animation
                        setTimeout(() => {
                            row.remove();
                            
                            // Update employee count if applicable
                            const countBadge = document.querySelector('.card-header .badge');
                            if (countBadge) {
                                let count = parseInt(countBadge.textContent.split(':')[1].trim()) - 1;
                                countBadge.textContent = `Total: ${count}`;
                            }
                        }, 500);
                    } else {
                        // If we can't find the row, reload the page
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    // Show error toast
                    showToast('Erreur', data.message || "Erreur lors de la suppression de l'employé", 'danger');
                }
            })
            .catch(error => {
                showToast('Erreur', "Erreur lors de la communication avec le serveur", 'danger');
                console.error('Error:', error);
            });
        };
        
        // Show confirmation modal
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmModal.show();
    }
    
    // Function to toggle employee status (active/inactive)
    function toggleStatus(id, isActive) {
        const statusText = isActive ? "désactiver" : "activer";
        document.getElementById('confirmationMessage').innerHTML = `
            Êtes-vous sûr de vouloir ${statusText} cet employé ?
        `;
        
        // Setup confirmation action
        document.getElementById('confirmAction').onclick = function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            
            // Show a loading toast
            showToast('Traitement', 'Mise à jour du statut en cours...', 'info', 2000);
            
            // Send the request
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', id);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e, text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showToast('Succès', data.message, 'success');
                    
                    // Update the UI without reloading
                    const row = document.querySelector(`tr[data-employee-id="${id}"]`);
                    if (row) {
                        // Get the new status from the response
                        const newStatus = data.new_status === 1 || data.new_status === true;
                        
                        // Find the status cell and update it
                        const statusCell = row.querySelector('.status-cell');
                        if (statusCell) {
                            statusCell.innerHTML = newStatus ? 
                                '<span class="badge bg-success">Actif</span>' : 
                                '<span class="badge bg-danger">Inactif</span>';
                        }
                        
                        // Find the salary cell to ensure it displays correctly
                        const salaryCell = row.querySelector('.salary-cell');
                        if (salaryCell && !newStatus) {
                            // Ensure the salary is correctly formatted even when inactive
                            const salaryText = salaryCell.textContent;
                            const salaryValue = parseFloat(salaryText.replace(/[^0-9,.]/g, '').replace(',', '.'));
                            if (!isNaN(salaryValue)) {
                                salaryCell.textContent = `${salaryValue.toLocaleString('fr-FR').replace(/,/g, ' ')} FC`;
                            }
                        }
                        
                        // Find the toggle button and update it
                        const btn = row.querySelector('.toggle-status-btn');
                        if (btn) {
                            // Update button appearance - keep existing classes except change the color class
                            btn.classList.remove('btn-danger', 'btn-success');
                            btn.classList.add(newStatus ? 'btn-danger' : 'btn-success');
                            btn.setAttribute('title', newStatus ? 'Désactiver' : 'Activer');
                            btn.setAttribute('onclick', `toggleStatus(${id}, ${newStatus})`);
                            
                            // Update icon
                            const icon = btn.querySelector('i');
                            if (icon) {
                                // Keep fas class but change the icon
                                icon.classList.remove('fa-times', 'fa-check');
                                icon.classList.add(newStatus ? 'fa-times' : 'fa-check');
                            }
                        }
                    } else {
                        // If we can't find the row, reload the page
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showToast('Erreur', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Erreur lors de la communication avec le serveur', 'danger');
            });
        };
        
        // Show the confirmation modal
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmModal.show();
    }
    
    // Toast notification function
    function showToast(title, message, type = 'info', duration = 5000) {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create a unique ID for this toast
        const toastId = 'toast-' + Date.now();
        
        // Create toast HTML
        const toastHtml = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        // Add toast to container
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        // Initialize and show the toast
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            delay: duration
        });
        
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
</script>

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
    error_log('Employes Liste Error: '.$e->getMessage());
    exit;
}
