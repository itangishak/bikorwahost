<?php
// Include authentication check and database-backed session
require_once __DIR__ . '/../../../includes/auth_check.php';

// Include database connection and config
require_once('../../config/database.php');
require_once('../../config/config.php');
// Check if user role is receptionniste
if ($_SESSION['user_role'] !== 'receptionniste') {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION['user_role'] === 'gestionnaire') {
        header('Location: index.php');
    } else {
        header('Location: ../auth/login.php');
    }
    exit;
}

// Initialize database connection
$db = new Database();
$pdo = $db->getConnection();

// Dashboard page for BIKORWA SHOP
$page_title = "Tableau de bord - Réceptionniste";
$active_page = "dashboard";

// Format current date for date range inputs
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : $first_day_of_month;
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : $today;

// Get user information
$user_name = $_SESSION['user_name'] ?? 'Utilisateur';
$user_role = $_SESSION['user_role'] ?? 'receptionniste';

// Format current date and time
setlocale(LC_TIME, 'fr_FR.UTF-8');
$current_date = date('d/m/Y');
$current_time = date('H:i');

// Initialize database statistics
$total_ventes = 0;
$total_clients = 0;
$total_produits = 0;
$valeur_stock = 0;

// Get dashboard statistics from database
try {
    // Count total sales
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ventes");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_ventes = $result['total'] ?? 0;
    
    // Count total clients
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_clients = $result['total'] ?? 0;
    
    // Count total products in stock
    $stmt = $pdo->prepare("SELECT SUM(quantite) as total FROM stock");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_produits = $result['total'] ?? 0;
    
    // Get total inventory value
    $stmt = $pdo->prepare("SELECT SUM(s.quantite * p.prix_achat) as valeur_stock 
                          FROM stock s 
                          JOIN prix_produits p ON s.produit_id = p.produit_id 
                          WHERE p.date_fin IS NULL");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $valeur_stock = $result['valeur_stock'] ?? 0;
    
} catch (PDOException $e) {
    // Handle database errors
    error_log('Database error: ' . $e->getMessage());
    // Use default values if database query fails
    $valeur_stock = 0;
}
?>

<?php
// Include header layout
require_once __DIR__ . '/../layouts/header.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord - Réceptionniste</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Tableau de Bord</li>
            </ol>
        </nav>
    </div>

    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>Bienvenue, <?php echo htmlspecialchars($user_name); ?>!</strong>
        Voici un aperçu de votre journée.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Date Filter -->
    <form method="get" class="row g-2 align-items-center mb-4">
        <div class="col-auto">
            <label for="date_debut" class="form-label">Date de Début:</label>
        </div>
        <div class="col-auto">
            <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
        </div>
        <div class="col-auto">
            <label for="date_fin" class="form-label">Date de Fin:</label>
        </div>
        <div class="col-auto">
            <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Appliquer</button>
        </div>
    </form>
            
            <!-- Main Stats Cards -->
            <div class="row g-4 mb-5">
                <!-- Total des Ventes -->
                <div class="col-xxl-3 col-md-6">
                    <div class="card dashboard-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-wrapper mb-3 mx-auto">
                                <i class="fas fa-shopping-cart text-primary fa-2x"></i>
                            </div>
                            <h5 class="card-title text-muted mb-3">Total des Ventes</h5>
                            <h2 class="display-5 fw-bold mb-0"><?php echo number_format($total_ventes, 0, ',', ' '); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Total des Clients -->
                <div class="col-xxl-3 col-md-6">
                    <div class="card dashboard-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-wrapper mb-3 mx-auto">
                                <i class="fas fa-users text-success fa-2x"></i>
                            </div>
                            <h5 class="card-title text-muted mb-3">Total des Clients</h5>
                            <h2 class="display-5 fw-bold mb-0"><?php echo number_format($total_clients, 0, ',', ' '); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Total des Produits -->
                <div class="col-xxl-3 col-md-6">
                    <div class="card dashboard-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-wrapper mb-3 mx-auto">
                                <i class="fas fa-box-open text-info fa-2x"></i>
                            </div>
                            <h5 class="card-title text-muted mb-3">Produits en Stock</h5>
                            <h2 class="display-5 fw-bold mb-0"><?php echo number_format($total_produits, 0, ',', ' '); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Valeur du Stock -->
                <div class="col-xxl-3 col-md-6">
                    <div class="card dashboard-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="icon-wrapper mb-3 mx-auto">
                                <i class="fas fa-coins text-warning fa-2x"></i>
                            </div>
                            <h5 class="card-title text-muted mb-3">Valeur du Stock</h5>
                            <h2 class="display-5 fw-bold mb-0"><?php echo number_format($valeur_stock, 0, ',', ' '); ?> BIF</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 p-3">
                            <h5 class="card-title m-0">Actions Rapides</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <a href="../ventes/nouvelle.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center p-4">
                                            <div class="icon-wrapper mb-3 mx-auto">
                                                <i class="fas fa-plus-circle text-primary fa-3x"></i>
                                            </div>
                                            <h5 class="card-title text-dark">Nouvelle Vente</h5>
                                            <p class="text-muted mb-0">Créer une nouvelle vente</p>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="../clients/index.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center p-4">
                                            <div class="icon-wrapper mb-3 mx-auto">
                                                <i class="fas fa-user-plus text-success fa-3x"></i>
                                            </div>
                                            <h5 class="card-title text-dark">Gérer les Clients</h5>
                                            <p class="text-muted mb-0">Ajouter et gérer les clients</p>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="../stock/inventaire.php" class="card border-0 shadow-sm h-100 text-decoration-none">
                                        <div class="card-body text-center p-4">
                                            <div class="icon-wrapper mb-3 mx-auto">
                                                <i class="fas fa-clipboard-list text-info fa-3x"></i>
                                            </div>
                                            <h5 class="card-title text-dark">Inventaire</h5>
                                            <p class="text-muted mb-0">Consulter l'inventaire</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dernières Ventes -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card dashboard-card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white border-0 p-3">
                            <h5 class="card-title m-0">Dernières Ventes</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php 
                                // In production, this would be populated from the database
                                $ventes = [];
                                if (empty($ventes)): ?>
                                    <div class="text-center p-4">
                                        <i class="fas fa-info-circle text-info mb-2" style="font-size: 2rem;"></i>
                                        <p class="mb-0">Aucune vente récente.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($ventes as $vente): ?>
                                    <!-- Vente item -->
                                    <div class="list-group-item p-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong>Facture #<?php echo $vente['numero']; ?></strong>
                                                <p class="text-muted mb-0 small"><?php echo $vente['client']; ?></p>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold"><?php echo number_format($vente['montant'], 0, ',', ' '); ?> BIF</span>
                                                <p class="text-muted mb-0 small"><?php echo $vente['date']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($ventes)): ?>
                        <div class="card-footer bg-white text-center">
                            <a href="../ventes/historique.php" class="btn btn-sm btn-outline-primary">Voir toutes les ventes</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
// Include footer
require_once __DIR__ . '/../layouts/footer.php';
?>
