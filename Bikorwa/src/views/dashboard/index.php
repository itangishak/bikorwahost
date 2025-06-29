<?php

// Start session if not already started

    session_start();
// Dashboard Page for BIKORWA SHOP - Gestionnaire Role
$page_title = "Tableau de Bord - Gestionnaire";
$active_page = "dashboard";




require_once __DIR__.'/../../../src/config/config.php';
require_once __DIR__.'/../../../src/config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Check if database connection is successful
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gestionnaire') {
    header('Location: ../auth/login.php');
    exit;
}

// Get current date for filters
$today = date('Y-m-d');
$current_month = date('Y-m-01');
$current_year = date('Y-01-01');

// Initialize variables
$stats = [
    'today_sales' => 0,
    'today_sales_count' => 0,
    'month_sales' => 0,
    'month_sales_count' => 0,
    'year_sales' => 0,
    'total_products' => 0,
    'low_stock_products' => 0,
    'total_clients' => 0,
    'active_debts' => 0,
    'total_debt_amount' => 0,
    'total_employees' => 0,
    'month_expenses' => 0
];

$recent_sales = [];
$low_stock_products = [];
$top_products_month = [];
$monthly_sales_data = [];

try {
    // Today's Sales
    $today_query = "SELECT 
        COALESCE(SUM(montant_paye), 0) as total,
        COUNT(*) as count
        FROM ventes 
        WHERE DATE(date_vente) = :today 
        AND statut_vente != 'annulee'";
    $today_stmt = $pdo->prepare($today_query);
    $today_stmt->execute([':today' => $today]);
    $today_result = $today_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_sales'] = $today_result['total'] ?? 0;
    $stats['today_sales_count'] = $today_result['count'] ?? 0;

    // This Month's Sales
    $month_query = "SELECT 
        COALESCE(SUM(montant_paye), 0) as total,
        COUNT(*) as count
        FROM ventes 
        WHERE DATE(date_vente) >= :current_month 
        AND statut_vente != 'annulee'";
    $month_stmt = $pdo->prepare($month_query);
    $month_stmt->execute([':current_month' => $current_month]);
    $month_result = $month_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['month_sales'] = $month_result['total'] ?? 0;
    $stats['month_sales_count'] = $month_result['count'] ?? 0;

    // This Year's Sales
    $year_query = "SELECT COALESCE(SUM(montant_paye), 0) as total
        FROM ventes 
        WHERE DATE(date_vente) >= :current_year 
        AND statut_vente != 'annulee'";
    $year_stmt = $pdo->prepare($year_query);
    $year_stmt->execute([':current_year' => $current_year]);
    $year_result = $year_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['year_sales'] = $year_result['total'] ?? 0;

    // Total Products
    $products_query = "SELECT COUNT(*) as count FROM produits WHERE actif = 1";
    $products_stmt = $pdo->prepare($products_query);
    $products_stmt->execute();
    $products_result = $products_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_products'] = $products_result['count'] ?? 0;

    // Low Stock Products (less than 10 units)
    $low_stock_query = "SELECT COUNT(*) as count 
        FROM stock s 
        JOIN produits p ON s.produit_id = p.id 
        WHERE s.quantite < 10 AND p.actif = 1";
    $low_stock_stmt = $pdo->prepare($low_stock_query);
    $low_stock_stmt->execute();
    $low_stock_result = $low_stock_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['low_stock_products'] = $low_stock_result['count'] ?? 0;

    // Get Low Stock Products Details
    $low_stock_details_query = "SELECT p.nom, s.quantite, p.unite_mesure
        FROM stock s 
        JOIN produits p ON s.produit_id = p.id 
        WHERE s.quantite < 10 AND p.actif = 1
        ORDER BY s.quantite ASC
        LIMIT 5";
    $low_stock_details_stmt = $pdo->prepare($low_stock_details_query);
    $low_stock_details_stmt->execute();
    $low_stock_products = $low_stock_details_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total Clients
    $clients_query = "SELECT COUNT(*) as count FROM clients";
    $clients_stmt = $pdo->prepare($clients_query);
    $clients_stmt->execute();
    $clients_result = $clients_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_clients'] = $clients_result['count'] ?? 0;

    // Active Debts
    try {
        $debts_query = "SELECT 
            COUNT(*) as count,
            COALESCE(SUM(montant_restant), 0) as total_amount
            FROM dettes 
            WHERE statut IN ('active', 'partiellement_payee')";
        $debts_stmt = $pdo->prepare($debts_query);
        $debts_stmt->execute();
        $debts_result = $debts_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_debts'] = $debts_result['count'] ?? 0;
        $stats['total_debt_amount'] = $debts_result['total_amount'] ?? 0;
    } catch (PDOException $e) {
        // Table dettes might not exist
        $stats['active_debts'] = 0;
        $stats['total_debt_amount'] = 0;
    }

    // Total Employees
    try {
        $employees_query = "SELECT COUNT(*) as count FROM employes WHERE actif = 1";
        $employees_stmt = $pdo->prepare($employees_query);
        $employees_stmt->execute();
        $employees_result = $employees_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_employees'] = $employees_result['count'] ?? 0;
    } catch (PDOException $e) {
        $stats['total_employees'] = 0;
    }

    // This Month's Expenses
    try {
        $expenses_query = "SELECT COALESCE(SUM(montant), 0) as total
            FROM depenses 
            WHERE date_depense >= :current_month";
        $expenses_stmt = $pdo->prepare($expenses_query);
        $expenses_stmt->execute([':current_month' => $current_month]);
        $expenses_result = $expenses_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['month_expenses'] = $expenses_result['total'] ?? 0;
    } catch (PDOException $e) {
        $stats['month_expenses'] = 0;
    }

    // Recent Sales (Last 5)
    $recent_sales_query = "SELECT 
        v.numero_facture,
        v.date_vente,
        COALESCE(c.nom, 'Client Anonyme') as client,
        v.montant_total,
        v.statut_paiement
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        WHERE v.statut_vente != 'annulee'
        ORDER BY v.date_vente DESC
        LIMIT 5";
    $recent_sales_stmt = $pdo->prepare($recent_sales_query);
    $recent_sales_stmt->execute();
    $recent_sales = $recent_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Products This Month
    $top_products_query = "SELECT 
        p.nom,
        SUM(dv.quantite) as quantite_vendue,
        SUM(dv.montant_total) as montant_total
        FROM details_ventes dv
        JOIN produits p ON dv.produit_id = p.id
        JOIN ventes v ON dv.vente_id = v.id
        WHERE DATE(v.date_vente) >= :current_month
        AND v.statut_vente != 'annulee'
        GROUP BY p.id, p.nom
        ORDER BY quantite_vendue DESC
        LIMIT 5";
    $top_products_stmt = $pdo->prepare($top_products_query);
    $top_products_stmt->execute([':current_month' => $current_month]);
    $top_products_month = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des données: " . $e->getMessage();
}

// Calculate profit margin (estimation)
$estimated_profit_margin = 0;
if ($stats['month_sales'] > 0) {
    $estimated_costs = ($stats['month_sales'] * 0.6) + $stats['month_expenses']; // 60% COGS estimation
    $estimated_profit_margin = (($stats['month_sales'] - $estimated_costs) / $stats['month_sales']) * 100;
}

// Include header
require_once __DIR__.'/../layouts/header.php';
?>

<style>
/* Existing styles */
{{ ... }}

/* New spacing rules */
.card {
    margin-bottom: 1.5rem;
}

.row > [class^="col-"] {
    padding-bottom: 1rem;
}

@media (min-width: 992px) {
    .row > [class^="col-"] {
        padding-right: 0.75rem;
        padding-left: 0.75rem;
    }
}

/* Existing animations and hover effects */
{{ ... }}
</style>

<main id="main" class="main">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="pagetitle">
        <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord - Gestionnaire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Tableau de Bord</li>
            </ol>
        </nav>
    </div>

    <!-- Welcome Message -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle"></i> 
        <strong>Bienvenue, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Gestionnaire') ?>!</strong> 
        Voici un aperçu de votre entreprise pour aujourd'hui.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Key Performance Indicators -->
    <div class="row">
        <!-- Today's Sales -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card sales-card">
                <div class="card-body">
                    <h5 class="card-title">Ventes Aujourd'hui</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($stats['today_sales'], 0, ',', ' ') ?> BIF</h6>
                            <span class="text-success small pt-1 fw-bold"><?= $stats['today_sales_count'] ?></span> 
                            <span class="text-muted small pt-2 ps-1">ventes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- This Month's Sales -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Ventes du Mois</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($stats['month_sales'], 0, ',', ' ') ?> BIF</h6>
                            <span class="text-primary small pt-1 fw-bold"><?= $stats['month_sales_count'] ?></span> 
                            <span class="text-muted small pt-2 ps-1">ventes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Products -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card customers-card">
                <div class="card-body">
                    <h5 class="card-title">Produits Actifs</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($stats['total_products']) ?></h6>
                            <?php if ($stats['low_stock_products'] > 0): ?>
                                <span class="text-danger small pt-1 fw-bold"><?= $stats['low_stock_products'] ?></span> 
                                <span class="text-muted small pt-2 ps-1">stock faible</span>
                            <?php else: ?>
                                <span class="text-success small pt-1 fw-bold">Stock OK</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Debts -->
        <div class="col-xxl-3 col-md-6">
            <div class="card info-card customers-card">
                <div class="card-body">
                    <h5 class="card-title">Créances Actives</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($stats['total_debt_amount'], 0, ',', ' ') ?> BIF</h6>
                            <span class="text-warning small pt-1 fw-bold"><?= $stats['active_debts'] ?></span> 
                            <span class="text-muted small pt-2 ps-1">dettes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
                    <h5>Ventes Annuelles</h5>
                    <h4 class="text-success"><?= number_format($stats['year_sales'], 0, ',', ' ') ?> BIF</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-2x text-primary mb-3"></i>
                    <h5>Clients</h5>
                    <h4 class="text-primary"><?= number_format($stats['total_clients']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-tie fa-2x text-info mb-3"></i>
                    <h5>Employés</h5>
                    <h4 class="text-info"><?= number_format($stats['total_employees']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-receipt fa-2x text-danger mb-3"></i>
                    <h5>Dépenses Mois</h5>
                    <h4 class="text-danger"><?= number_format($stats['month_expenses'], 0, ',', ' ') ?> BIF</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Profit Margin Alert -->
    <?php if ($estimated_profit_margin > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-<?= $estimated_profit_margin > 20 ? 'success' : ($estimated_profit_margin > 10 ? 'warning' : 'danger') ?>" role="alert">
                <i class="fas fa-chart-pie"></i> 
                <strong>Marge Bénéficiaire Estimée ce Mois:</strong> 
                <?= number_format($estimated_profit_margin, 1) ?>%
                <?php if ($estimated_profit_margin > 20): ?>
                    - Excellente performance!
                <?php elseif ($estimated_profit_margin > 10): ?>
                    - Performance correcte, améliorations possibles.
                <?php else: ?>
                    - Attention: Marge faible, révision nécessaire.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Recent Sales -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Ventes Récentes</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_sales)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Aucune vente récente</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_sales as $sale): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sale['numero_facture']) ?></td>
                                            <td><?= htmlspecialchars($sale['client']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($sale['date_vente'])) ?></td>
                                            <td><?= number_format($sale['montant_total'], 0, ',', ' ') ?> BIF</td>
                                            <td>
                                                <span class="badge bg-<?= $sale['statut_paiement'] == 'paye' ? 'success' : ($sale['statut_paiement'] == 'partiel' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($sale['statut_paiement']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="../ventes/index.php" class="btn btn-outline-primary">Voir Toutes les Ventes</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock & Top Products -->
        <div class="col-lg-6">
            <!-- Low Stock Alert -->
            <?php if (!empty($low_stock_products)): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title text-danger">⚠️ Produits en Stock Faible</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Stock</th>
                                    <th>Unité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['nom']) ?></td>
                                        <td class="text-danger fw-bold"><?= number_format($product['quantite'], 2) ?></td>
                                        <td><?= htmlspecialchars($product['unite_mesure']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="../stock/index.php" class="btn btn-outline-warning btn-sm">Gérer Stock</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Products -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">🏆 Top Produits du Mois</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products_month)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Aucune vente ce mois</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_products_month as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['nom']) ?></td>
                                            <td><?= number_format($product['quantite_vendue'], 2) ?></td>
                                            <td><?= number_format($product['montant_total'], 0, ',', ' ') ?> BIF</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Include footer
require_once __DIR__.'/../layouts/footer.php';
?>