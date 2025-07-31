<?php
// Client Management page for BIKORWA SHOP
$page_title = "Gestion des Clients";
$active_page = "clients";

require_once __DIR__ . '/../../config/config.php';
require_gestionnaire_access();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Settings.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();
$settingsObj = new Settings($conn);

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
    exit;
}

// Check access permissions
if (!$auth->hasAccess('clients')) {
    header('Location: ' . BASE_URL . '/src/views/dashboard/index.php');
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = (int)$settingsObj->get('items_per_page', 10);
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
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get client statistics
$stats_query = "SELECT 
                COUNT(*) as total_clients,
                SUM(limite_credit) as credit_total,
                AVG(limite_credit) as credit_moyen,
                MAX(limite_credit) as credit_max
                FROM clients";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute();
$statistiques = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/../layouts/header.php';

// Set a variable to indicate we'll use a custom footer
$use_custom_footer = true;
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
        <li class="breadcrumb-item"><a href="/dashboard/index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Gestion des Clients</li>
    </ol>
    
    <!-- Toast Container for Notifications -->
    <div id="toastContainer"></div>
    
    <!-- Action Buttons -->
    <div class="row mb-4">
        <div class="col-md-6">
            <button type="button" id="addNewClientBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="fas fa-plus-circle me-2"></i>Nouveau client
            </button>
        </div>
        <div class="col-md-6 text-md-end">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter me-1"></i>Filtres
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-left-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Clients</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['total_clients']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users stat-icon text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-left-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Crédit Total</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['credit_total'], 0, ',', ' '); ?> BIF</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-left-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Crédit Moyen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['credit_moyen'], 0, ',', ' '); ?> BIF</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calculator stat-icon text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-left-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Crédit Maximum</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistiques['credit_max'], 0, ',', ' '); ?> BIF</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Collapse -->
    <div class="collapse mb-4" id="filterCollapse">
        <div class="card filter-form">
            <div class="card-body">
                <form id="searchForm" method="GET">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="searchInput" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="searchInput" name="search" placeholder="Nom, téléphone, email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end mb-3">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Rechercher
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i>Réinitialiser
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client List Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Liste des Clients
        </div>
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo !empty($search) ? 'Aucun client trouvé pour cette recherche.' : 'Aucun client dans la base de données.' ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Limite Crédit</th>
                                <th>Date Création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo $client['id']; ?></td>
                                    <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($client['telephone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($client['email'] ?? '-'); ?></td>
                                    <td><?php echo number_format($client['limite_credit'], 0, ',', ' '); ?> BIF</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($client['date_creation'])); ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info btn-action btn-view-client" data-id="<?php echo $client['id']; ?>" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($userRole === 'gestionnaire'): ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-action btn-edit-client" data-id="<?php echo $client['id']; ?>" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-action btn-delete-client" data-id="<?php echo $client['id']; ?>" data-name="<?php echo htmlspecialchars($client['nom']); ?>" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" data-page="<?php echo $current_page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php 
                                // Show max 5 page links
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                
                                if ($end_page - $start_page < 4 && $total_pages > 5) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" data-page="<?php echo $current_page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addClientModalLabel"><i class="fas fa-plus-circle me-2"></i>Ajouter un client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addClientForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required placeholder="Nom du client">
                            </div>
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="telephone" name="telephone" placeholder="Numéro de téléphone">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Adresse email">
                            </div>
                            <div class="col-md-6">
                                <label for="limite_credit" class="form-label">Limite de crédit (BIF)</label>
                                <input type="number" min="0" step="1" class="form-control" id="limite_credit" name="limite_credit" value="0" placeholder="Limite de crédit en BIF">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2" placeholder="Adresse du client"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="note" class="form-label">Note</label>
                                <textarea class="form-control" id="note" name="note" rows="3" placeholder="Notes ou commentaires additionnels"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveClientBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editClientModalLabel"><i class="fas fa-edit me-2"></i>Modifier le client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editClientForm">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required placeholder="Nom du client">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="edit_telephone" name="telephone" placeholder="Numéro de téléphone">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" placeholder="Adresse email">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_limite_credit" class="form-label">Limite de crédit (BIF)</label>
                                <input type="number" min="0" step="1" class="form-control" id="edit_limite_credit" name="limite_credit" placeholder="Limite de crédit en BIF">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="edit_adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="edit_adresse" name="adresse" rows="2" placeholder="Adresse du client"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="edit_note" class="form-label">Note</label>
                                <textarea class="form-control" id="edit_note" name="note" rows="3" placeholder="Notes ou commentaires additionnels"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="updateClientBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Mettre à jour
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Client Modal -->
    <div class="modal fade" id="viewClientModal" tabindex="-1" aria-labelledby="viewClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewClientModalLabel"><i class="fas fa-eye me-2"></i>Détails du client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center p-4 d-flex justify-content-center" id="viewClientSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="ms-2 mb-0">Chargement des détails...</p>
                    </div>
                    <div id="clientDetails" class="d-none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nom:</label>
                                <div id="view_nom" class="border-bottom pb-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Téléphone:</label>
                                <div id="view_telephone" class="border-bottom pb-2"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email:</label>
                                <div id="view_email" class="border-bottom pb-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Limite de crédit:</label>
                                <div id="view_limite_credit" class="border-bottom pb-2"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Date de création:</label>
                                <div id="view_date_creation" class="border-bottom pb-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Adresse:</label>
                                <div id="view_adresse" class="border-bottom pb-2"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Note:</label>
                                <div id="view_note" class="border-bottom pb-2"></div>
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

<script src="./clients_script.js"></script>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
