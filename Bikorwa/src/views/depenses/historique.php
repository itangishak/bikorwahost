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

    // Verify authentication and role
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
        !isset($_SESSION['role']) || $_SESSION['role'] !== 'gestionnaire') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            exit(json_encode(['error' => 'Accès non autorisé']));
        } else {
            header('Location: ../auth/login.php');
            exit;
        }
    }

    $page_title = "Historique des Dépenses";
    $active_page = "depenses-historique";

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize authentication
    $auth = new Auth($conn);

    // Get current user ID for logging actions
    $current_user_id = $_SESSION['user_id'] ?? 0;

// Set default values and get search parameters
$search = $_GET['search'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$mode_paiement = $_GET['mode_paiement'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$per_page = $_GET['per_page'] ?? 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = (int)$per_page;
$offset = ($current_page - 1) * $items_per_page;

// Build the base query - Fixed table references and column names
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
    $count_query .= " AND (d.description LIKE ? OR d.reference_paiement LIKE ? OR c.nom LIKE ?)"; 
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    array_push($count_params, $search_param, $search_param, $search_param);
}

// Add category filter if specified
if (!empty($categorie)) {
    $query .= " AND d.categorie_id = ?";
    $count_query .= " AND d.categorie_id = ?";
    array_push($params, $categorie);
    $count_params[] = $categorie;
}

// Add payment mode filter if specified - Fixed enum values
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
array_push($params, (int)$items_per_page, (int)$offset);

// Execute count query for pagination
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($count_params);
$total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $items_per_page);

// Execute main query
$stmt = $conn->prepare($query);

// Bind all parameters with explicit types
for ($i = 0; $i < count($params); $i++) {
    $paramType = PDO::PARAM_STR;
    
    // The last two parameters are for LIMIT and OFFSET - bind as integers
    if ($i >= count($params) - 2) {
        $paramType = PDO::PARAM_INT;
    }
    
    $stmt->bindValue($i + 1, $params[$i], $paramType);
}

$stmt->execute();
$depenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for the form
$categories_query = "SELECT id, nom FROM categories_depenses ORDER BY nom";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the header
require_once __DIR__ . '/../layouts/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIKORWA SHOP - Historique des Dépenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- ... existing wrapper content ... -->

        <!-- Toast Container -->
        <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

        <div class="container-fluid px-4">
            <h1 class="mt-4"><?php echo $page_title; ?></h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="../dashboard/index.php">Tableau de bord</a></li>
                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
            </ol>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <!-- Total Expenses Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-money-bill-wave text-primary fs-4 me-2"></i>
                                        <div class="text-uppercase small text-primary fw-bold">Total des dépenses</div>
                                    </div>
                                    <div class="h4 mb-0">
                                        <?php
                                        $total_query = "SELECT SUM(montant) as total, COUNT(*) as count FROM depenses";
                                        $total_stmt = $conn->prepare($total_query);
                                        $total_stmt->execute();
                                        $total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
                                        $total = $total_data['total'] ?? 0;
                                        $count = $total_data['count'] ?? 0;
                                        echo number_format($total, 0, ',', ' '); ?> FBU
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-circle text-primary me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo $count; ?> dépenses au total
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Expenses Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-calendar text-success fs-4 me-2"></i>
                                        <div class="text-uppercase small text-success fw-bold">Dépenses mensuelles</div>
                                    </div>
                                    <div class="h4 mb-0">
                                        <?php
                                        $monthly_query = "SELECT SUM(montant) as total, COUNT(*) as count 
                                                FROM depenses 
                                                WHERE MONTH(date_depense) = MONTH(CURRENT_DATE())
                                                AND YEAR(date_depense) = YEAR(CURRENT_DATE())";
                                        $monthly_stmt = $conn->prepare($monthly_query);
                                        $monthly_stmt->execute();
                                        $monthly_data = $monthly_stmt->fetch(PDO::FETCH_ASSOC);
                                        $monthly = $monthly_data['total'] ?? 0;
                                        $monthly_count = $monthly_data['count'] ?? 0;
                                        echo number_format($monthly, 0, ',', ' '); ?> FBU
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo $monthly_count; ?> dépenses ce mois
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Annual Expenses Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-chart-line text-warning fs-4 me-2"></i>
                                        <div class="text-uppercase small text-warning fw-bold">Dépenses annuelles</div>
                                    </div>
                                    <div class="h4 mb-0">
                                        <?php
                                        $yearly_query = "SELECT SUM(montant) as total, COUNT(*) as count 
                                                FROM depenses 
                                                WHERE YEAR(date_depense) = YEAR(CURRENT_DATE())";
                                        $yearly_stmt = $conn->prepare($yearly_query);
                                        $yearly_stmt->execute();
                                        $yearly_data = $yearly_stmt->fetch(PDO::FETCH_ASSOC);
                                        $yearly = $yearly_data['total'] ?? 0;
                                        $yearly_count = $yearly_data['count'] ?? 0;
                                        echo number_format($yearly, 0, ',', ' '); ?> FBU
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-circle text-warning me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo $yearly_count; ?> dépenses cette année
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Most Used Category Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-tags text-danger fs-4 me-2"></i>
                                        <div class="text-uppercase small text-danger fw-bold">Catégorie la plus utilisée</div>
                                    </div>
                                    <div class="h4 mb-0">
                                        <?php
                                        $category_query = "SELECT c.nom, SUM(d.montant) as total, COUNT(*) as count 
                                                FROM depenses d 
                                                LEFT JOIN categories_depenses c ON d.categorie_id = c.id 
                                                GROUP BY c.id 
                                                ORDER BY total DESC 
                                                LIMIT 1";
                                        $category_stmt = $conn->prepare($category_query);
                                        $category_stmt->execute();
                                        $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
                                        echo htmlspecialchars($category['nom'] ?? 'Aucune');
                                        ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-circle text-danger me-1" style="font-size: 0.5rem;"></i>
                                        <?php 
                                        echo number_format($category['total'] ?? 0, 0, ',', ' '); ?> FBU
                                        <br>
                                        <?php echo $category['count'] ?? 0; ?> dépenses
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="categorie" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie" name="categorie">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $categorie == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['nom']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="mode_paiement" class="form-label">Mode de Paiement</label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement">
                                <option value="">Tous les modes</option>
                                <option value="Espèces" <?php echo $mode_paiement == 'Espèces' ? 'selected' : ''; ?>>Espèces</option>
                                <option value="Cheque" <?php echo $mode_paiement == 'Cheque' ? 'selected' : ''; ?>>Chèque</option>
                                <option value="Virement" <?php echo $mode_paiement == 'Virement' ? 'selected' : ''; ?>>Virement</option>
                                <option value="Carte" <?php echo $mode_paiement == 'Carte' ? 'selected' : ''; ?>>Carte</option>
                                <option value="Mobile Money" <?php echo $mode_paiement == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_range" class="form-label">Période</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                                <span class="input-group-text">à</span>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Expenses List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Liste des dépenses</h6>
                    <span class="badge bg-light text-dark">Total: <?php echo $total_rows; ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($depenses)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Aucune dépense trouvée.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Catégorie</th>
                                        <th>Mode de Paiement</th>
                                        <th>Utilisateur</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($depenses as $depense): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($depense['date_depense'])); ?></td>
                                        <td><?php echo htmlspecialchars($depense['description']); ?></td>
                                        <td class="fw-bold"><?php echo number_format($depense['montant'], 0, ',', ' '); ?> FBU</td>
                                        <td>
                                            <span class="badge bg-info text-white"><?php echo htmlspecialchars($depense['categorie_nom'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPaymentModeClass($depense['mode_paiement']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $depense['mode_paiement'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($depense['utilisateur_nom'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewExpense(<?php echo $depense['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editExpense(<?php echo $depense['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Controls -->
                        <div class="row mt-4">
                            <div class="col-md-6 d-flex align-items-center">
                                <span class="me-2">Éléments par page:</span>
                                <select class="form-select form-select-sm w-auto" id="itemsPerPage" onchange="updateItemsPerPage(this.value)">
                                    <option value="10" <?php echo $items_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                                <span class="ms-3">Page <?php echo $current_page; ?> sur <?php echo $total_pages; ?></span>
                            </div>
                            
                            <div class="col-md-6">
                                <nav aria-label="Pagination des dépenses">
                                    <ul class="pagination pagination-sm justify-content-end mb-0">
                                        <!-- Previous Page -->
                                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" aria-label="Précédent">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <!-- First Page -->
                                        <?php if ($current_page > 3): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            $active = $i == $current_page ? 'active' : '';
                                            echo '<li class="page-item ' . $active . '">';
                                            echo '<a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                        ?>
                                        
                                        <!-- Last Page -->
                                        <?php if ($end_page < $total_pages): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <!-- Next Page -->
                                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" aria-label="Suivant">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <script>
                        function updateItemsPerPage(value) {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('per_page', value);
                            urlParams.set('page', 1);
                            window.location.search = urlParams.toString();
                        }
                        </script>
                    <?php endif; ?>
                </div>
            </div>

            <!-- View Expense Modal -->
            <div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-labelledby="viewExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="viewExpenseModalLabel"><i class="fas fa-eye me-2"></i>Détails de la dépense</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="expenseDetails"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Expense Modal -->
            <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-white">
                            <h5 class="modal-title" id="editExpenseModalLabel"><i class="fas fa-edit me-2"></i>Modifier la dépense</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="editExpenseFormContainer"></div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // Function to get payment mode class
            function getPaymentModeClass(mode) {
                const classes = {
                    'especes': 'bg-success',
                    'mobile_money': 'bg-info',
                    'carte': 'bg-secondary',
                    'virement': 'bg-primary',
                    'cheque': 'bg-warning'
                };
                return classes[mode] || 'bg-dark';
            }

            // Enhanced viewExpense with error handling
            function viewExpense(id) {
                fetch(`get_expense_details.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            document.getElementById('expenseDetails').innerHTML = `
                                <div class="row">
                                    <div class="col-md-6"><strong>Date:</strong> ${data.expense.date_depense}</div>
                                    <div class="col-md-6"><strong>Montant:</strong> ${data.expense.montant} FBU</div>
                                    <div class="col-md-6"><strong>Catégorie:</strong> ${data.expense.categorie_nom}</div>
                                    <div class="col-md-6"><strong>Mode de paiement:</strong> ${data.expense.mode_paiement}</div>
                                    <div class="col-12"><strong>Description:</strong> ${data.expense.description}</div>
                                    ${data.expense.reference_paiement ? `<div class="col-12"><strong>Référence:</strong> ${data.expense.reference_paiement}</div>` : ''}
                                    ${data.expense.note ? `<div class="col-12"><strong>Note:</strong> ${data.expense.note}</div>` : ''}
                                </div>
                            `;
                            new bootstrap.Modal(document.getElementById('viewExpenseModal')).show();
                        } else {
                            showToast('error', 'Erreur', data.message || 'Impossible de charger les détails');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('error', 'Erreur', 'Une erreur est survenue lors du chargement');
                    });
            }

            // New editExpense function with modal
            function editExpense(id) {
                fetch(`get_expense_edit_form.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(html => {
                        document.getElementById('editExpenseFormContainer').innerHTML = html;
                        
                        // Initialize form submission handler
                        const form = document.getElementById('editExpenseForm');
                        if (form) {
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                fetch(form.action, {
                                    method: 'POST',
                                    body: new FormData(form)
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        toastr.success(data.message);
                                        setTimeout(() => {
                                            $('#editExpenseModal').modal('hide');
                                            window.location.reload(); // Temporary solution - replace with your data refresh logic
                                        }, 1500);
                                    } else {
                                        toastr.error(data.message);
                                    }
                                })
                                .catch(error => {
                                    toastr.error('Erreur de réseau');
                                    console.error(error);
                                });
                            });
                        }
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('editExpenseModal')).show();
                    })
                    .catch(error => {
                        toastr.error('Erreur de chargement du formulaire');
                        console.error(error);
                    });
            }

            // Helper function to show toast notifications
            function showToast(type, title, message) {
                // Implementation depends on your toast library (Toastr, Bootstrap Toasts, etc.)
                console.log(`[${type}] ${title}: ${message}`);
                // Example with Toastr:
                // toastr[type](message, title);
            }
            
            // Initialize toastr after jQuery is loaded
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        closeButton: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        timeOut: 5000
                    };
                } else {
                    console.error('Toastr still not loaded');
                }
            });
            </script>

            <?php
            // Helper function for payment mode classes
            function getPaymentModeClass($mode) {
                $classes = [
                    'especes' => 'bg-success',
                    'mobile_money' => 'bg-info',
                    'carte' => 'bg-secondary',
                    'virement' => 'bg-primary',
                    'cheque' => 'bg-warning'
                ];
                return $classes[$mode] ?? 'bg-dark';
            }

            // Include the footer
            require_once __DIR__ . '/../layouts/footer.php';
            ?>
            <?php if (!isset($toastr_included)) { ?>
            <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" />
            <?php $toastr_included = true; } ?>
            <script>
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js" crossorigin><\/script>');
            </script>
            <script>
            if (window.toastr) {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 5000,
                    newestOnTop: true
                };
            }
            </script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>
            <script src="depenses_script.js?v=<?php echo time(); ?>"></script>
<?php } catch (Exception $e) {
    echo '<div style="background:#ffeeee;padding:20px;border:2px solid red;margin:20px">';
    echo '<h3>Error Debug Information</h3>';
    echo '<p><strong>Error:</strong> '.htmlspecialchars($e->getMessage()).'</p>';
    echo '<pre>Stack Trace: '.htmlspecialchars($e->getTraceAsString()).'</pre>';
    echo '</div>';
    error_log('Depenses Historique Error: '.$e->getMessage());
    exit;
}?>
