<?php
// Basic error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();

try {
    // Simple session start (like working pages)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Basic debug output
    echo '<pre>Session Status: '; var_dump(session_status()); echo '</pre>';
    echo '<pre>Session Data: '; print_r($_SESSION); echo '</pre>';

    // Include dependencies
    require_once('../../config/database.php');
    require_once('../../config/config.php');
    require_once('../../../includes/session.php');

    // Verify login (flexible check)
    if (empty($_SESSION['logged_in'])) {
        header('Location: ../auth/login.php');
        exit;
    }

    // Verify role (flexible check)
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'gestionnaire') {
        header('Location: /dashboard/index.php?error=access_denied');
        exit;
    }

    $page_title = "Gestion des Utilisateurs";
    $active_page = "utilisateurs";

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize auth
    $auth = new Auth($conn);
    $authController = new AuthController();

    // Set default values and get search parameters
    $search = $_GET['search'] ?? '';
    $statut = $_GET['statut'] ?? '';
    $role = $_GET['role'] ?? '';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $items_per_page = 10;
    $offset = ($current_page - 1) * $items_per_page;

    // Build the base query
    $query = "SELECT * FROM users WHERE 1=1";
    $count_query = "SELECT COUNT(*) AS total FROM users WHERE 1=1";
    $params = [];

    // Add search conditions if any
    if (!empty($search)) {
        $query .= " AND (nom LIKE ? OR username LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $count_query .= " AND (nom LIKE ? OR username LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param, $search_param, $search_param);
    }

    // Add role filter if specified
    if (!empty($role)) {
        $query .= " AND role = ?";
        $count_query .= " AND role = ?";
        array_push($params, $role);
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
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user statistics
    $stats_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as users_actifs,
                    SUM(CASE WHEN role = 'receptionniste' THEN 1 ELSE 0 END) as receptionnistes,
                    SUM(CASE WHEN role = 'gestionnaire' THEN 1 ELSE 0 END) as gestionnaires
                    FROM users";
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
    /* Style for role badges */
    .badge-role {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
    }
    /* Loading spinner */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.2em;
    }
    /* Toast container */
    #toastContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
    }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/dashboard/index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Gestion des Utilisateurs</li>
    </ol>

    <!-- Toast Container for Notifications -->
    <div id="toastContainer"></div>

    <div class="row mb-4">
        <div class="col-md-6">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Nouvel utilisateur
            </button>
        </div>
        <div class="col-md-6">
            <form action="./utilisateurs.php" method="get" class="d-flex justify-content-end">
                <div class="input-group">
                    <select name="role" class="form-select" style="max-width: 200px;">
                        <option value="">Tous les rôles</option>
                        <option value="receptionniste" <?php echo ($role == 'receptionniste') ? 'selected' : ''; ?>>Réceptionniste</option>
                        <option value="gestionnaire" <?php echo ($role == 'gestionnaire') ? 'selected' : ''; ?>>Gestionnaire</option>
                    </select>
                    <select name="statut" class="form-select" style="max-width: 200px;">
                        <option value="">Tous les statuts</option>
                        <option value="actif" <?php echo ($statut == 'actif') ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactif" <?php echo ($statut == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                    <input type="text" class="form-control" name="search" placeholder="Rechercher un utilisateur..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Utilisateurs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['total_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Utilisateurs Actifs</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['users_actifs']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Réceptionnistes</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['receptionnistes']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Gestionnaires</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statistiques['gestionnaires']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User List Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-users me-2"></i>Liste des utilisateurs</h6>
            <span class="badge bg-light text-dark">Total: <?php echo $total_rows; ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Nom d'utilisateur</th>
                                <th>Rôle</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Date de création</th>
                                <th>Dernière connexion</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <?php if ($user['role'] == 'receptionniste'): ?>
                                            <span class="badge bg-info badge-role">Réceptionniste</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark badge-role">Gestionnaire</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['telephone'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></td>
                                    <td>
                                        <?php echo $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['actif'] ? 'bg-success' : 'bg-danger'; ?> status-badge">
                                            <?php echo $user['actif'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-primary btn-sm btn-action" 
                                                    onclick="viewUser(<?php echo $user['id']; ?>)" 
                                                    title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-warning btn-sm btn-action" 
                                                    onclick="editUser(<?php echo $user['id']; ?>)" 
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <button type="button" class="btn <?php echo $user['actif'] ? 'btn-secondary' : 'btn-success'; ?> btn-sm btn-action" 
                                                    onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['actif'] ? 'true' : 'false'; ?>)" 
                                                    title="<?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas <?php echo $user['actif'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger btn-sm btn-action" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['nom'])); ?>')" 
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navigation des pages">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&statut=<?php echo urlencode($statut); ?>&role=<?php echo urlencode($role); ?>" aria-label="Précédent">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&statut=<?php echo urlencode($statut); ?>&role=<?php echo urlencode($role); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&statut=<?php echo urlencode($statut); ?>&role=<?php echo urlencode($role); ?>" aria-label="Suivant">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="small text-muted">
                            Affichage de <?php echo min(($current_page - 1) * $items_per_page + 1, $total_rows); ?> à 
                            <?php echo min($current_page * $items_per_page, $total_rows); ?> sur <?php echo $total_rows; ?> utilisateurs
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Aucun utilisateur trouvé.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">Ajouter un nouvel utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_nom" name="nom" required>
                                <div class="invalid-feedback">Veuillez entrer le nom complet.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="add_username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_username" name="username" required>
                                <div class="invalid-feedback">Veuillez entrer un nom d'utilisateur unique.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="add_password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Le mot de passe doit contenir au moins 8 caractères.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="add_confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_role" class="form-label">Rôle <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_role" name="role" required>
                                    <option value="" selected disabled>Sélectionner un rôle</option>
                                    <option value="receptionniste">Réceptionniste</option>
                                    <option value="gestionnaire">Gestionnaire</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un rôle.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="add_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="add_email" name="email">
                                <div class="invalid-feedback">Veuillez entrer une adresse email valide.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="add_telephone" name="telephone">
                            </div>
                            <div class="col-md-6">
                                <label for="add_actif" class="form-label">Statut</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="add_actif" name="actif" checked>
                                    <label class="form-check-label" for="add_actif">Actif</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveNewUserBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewUserModalLabel">Détails de l'utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Nom complet:</h6>
                            <p id="view_nom" class="border-bottom pb-2"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Nom d'utilisateur:</h6>
                            <p id="view_username" class="border-bottom pb-2"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Rôle:</h6>
                            <p id="view_role" class="border-bottom pb-2"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Statut:</h6>
                            <p id="view_actif" class="border-bottom pb-2"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Email:</h6>
                            <p id="view_email" class="border-bottom pb-2"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Téléphone:</h6>
                            <p id="view_telephone" class="border-bottom pb-2"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Date de création:</h6>
                            <p id="view_date_creation" class="border-bottom pb-2"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold">Dernière connexion:</h6>
                            <p id="view_derniere_connexion" class="border-bottom pb-2"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-warning" onclick="editUserFromView()">
                        <i class="fas fa-edit me-2"></i>Modifier
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier l'utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="edit_id" name="id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                <div class="invalid-feedback">Veuillez entrer le nom complet.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                                <div class="invalid-feedback">Veuillez entrer un nom d'utilisateur unique.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_password" class="form-label">Nouveau mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="edit_password" name="password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Laissez vide pour conserver le mot de passe actuel.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_role" class="form-label">Rôle <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <option value="" disabled>Sélectionner un rôle</option>
                                    <option value="receptionniste">Réceptionniste</option>
                                    <option value="gestionnaire">Gestionnaire</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un rôle.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                                <div class="invalid-feedback">Veuillez entrer une adresse email valide.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="edit_telephone" name="telephone">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_actif" class="form-label">Statut</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_actif" name="actif">
                                    <label class="form-check-label" for="edit_actif">Actif</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="updateUserBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        Mettre à jour
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
    
    <script src="./utilisateurs_script.js"></script>
    
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
    error_log('Utilisateurs Error: '.$e->getMessage());
    exit;
}