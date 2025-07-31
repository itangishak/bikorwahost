<?php
// Sales Reports Page for BIKORWA SHOP
$page_title = "Rapports de Ventes";
$active_page = "rapports";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../../../src/config/config.php';
require_once __DIR__.'/../../../src/config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Check if database connection is successful
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

// Check if user is logged in and has gestionnaire role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'gestionnaire') {
    header('Location: ../../auth/login.php');
    exit;
}

// Get date filter parameters with defaults
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // First day of current month
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Today

// Initialize variables to avoid undefined errors
$total_revenue = 0;
$sales_count = 0;
$product_revenue = 0;
$debt_revenue = 0;
$avg_sale = 0;

// Get sales summary data
try {
    // Product Revenue (using montant_paye from ventes table) - corrected per user requirement
    $product_revenue_query = "SELECT 
        SUM(v.montant_paye) as total
        FROM ventes v
        WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
        AND v.statut_vente != 'annulee'";
    $product_revenue_stmt = $pdo->prepare($product_revenue_query);
    $product_revenue_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    $product_revenue_result = $product_revenue_stmt->fetch(PDO::FETCH_ASSOC);
    $product_revenue = $product_revenue_result['total'] ?? 0;

    // Debt Payments Revenue - only from sales (check if tables exist)
    $debt_revenue = 0;
    try {
        $debt_revenue_query = "SELECT 
            SUM(pd.montant) as total 
            FROM paiements_dettes pd
            JOIN dettes d ON pd.dette_id = d.id
            JOIN ventes v ON d.vente_id = v.id
            WHERE DATE(pd.date_paiement) BETWEEN :date_debut AND :date_fin
            AND v.statut_vente != 'annulee'";
        $debt_revenue_stmt = $pdo->prepare($debt_revenue_query);
        $debt_revenue_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        $debt_revenue_result = $debt_revenue_stmt->fetch(PDO::FETCH_ASSOC);
        $debt_revenue = $debt_revenue_result['total'] ?? 0;
    } catch (PDOException $e) {
        // Tables dettes/paiements_dettes n'existent pas ou erreur
        $debt_revenue = 0;
    }

    // Total Revenue
    $total_revenue = $product_revenue + $debt_revenue;

    // Number of sales in date range - excluding canceled sales
    $sales_count_query = "SELECT COUNT(*) as count FROM ventes 
        WHERE DATE(date_vente) BETWEEN :date_debut AND :date_fin
        AND statut_vente != 'annulee'";
    $sales_count_stmt = $pdo->prepare($sales_count_query);
    $sales_count_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    $sales_count_result = $sales_count_stmt->fetch(PDO::FETCH_ASSOC);
    $sales_count = $sales_count_result['count'] ?? 0;

    // Sales today - useful for daily monitoring (exclude zero payments marked as paid)
    $today_sales_query = "SELECT 
        SUM(montant_paye) as total_today,
        COUNT(*) as count_today
        FROM ventes 
        WHERE DATE(date_vente) = CURDATE()
        AND statut_vente != 'annulee'
        AND NOT (montant_paye = 0 AND statut_paiement = 'paye')";
    $today_sales_stmt = $pdo->prepare($today_sales_query);
    $today_sales_stmt->execute();
    $today_sales_result = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);
    $today_revenue = $today_sales_result['total_today'] ?? 0;
    $today_count = $today_sales_result['count_today'] ?? 0;

    // Top products sold - excluding canceled sales
    $top_products = [];
    try {
        $top_products_query = "SELECT p.nom, SUM(dv.quantite) as quantite, 
            SUM(dv.montant_total) as montant
            FROM details_ventes dv
            JOIN produits p ON dv.produit_id = p.id
            JOIN ventes v ON dv.vente_id = v.id
            WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
            AND v.statut_vente != 'annulee'
            GROUP BY p.id, p.nom
            ORDER BY quantite DESC
            LIMIT 5";
        $top_products_stmt = $pdo->prepare($top_products_query);
        $top_products_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Erreur dans la requête des top produits
        $top_products = [];
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : ". $e->getMessage();
    // Initialize default values
    $product_revenue = 0;
    $debt_revenue = 0;
    $total_revenue = 0;
    $sales_count = 0;
    $today_revenue = 0;
    $today_count = 0;
    $top_products = [];
}

// Calculs des Métriques Financières
try {
    // Initialize financial metrics
    $cogs = 0;
    $expenses = 0;
    $labor = 0;
    $gross_profit = 0;
    $net_profit = 0;
    
    // 1. Coût des Marchandises Vendues - calculé à partir du prix d'achat des produits vendus
    $cogs_query = "SELECT
            SUM(dv.prix_achat_unitaire * dv.quantite) AS total_cogs
        FROM details_ventes dv
        JOIN ventes v ON dv.vente_id = v.id
        WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
        AND v.statut_vente != 'annulee'";
    $cogs_stmt = $pdo->prepare($cogs_query);
    $cogs_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    $cogs_result = $cogs_stmt->fetch(PDO::FETCH_ASSOC);
    $cogs = $cogs_result['total_cogs'] ?? 0;
    
    // 2. Frais d'Exploitation - vérifier si la table existe
    try {
        $expenses_query = "SELECT SUM(montant) as total FROM depenses
            WHERE date_depense BETWEEN :date_debut AND :date_fin";
        $expenses_stmt = $pdo->prepare($expenses_query);
        $expenses_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        $expenses_result = $expenses_stmt->fetch(PDO::FETCH_ASSOC);
        $expenses = $expenses_result['total'] ?? 0;
    } catch (PDOException $e) {
        // Table depenses n'existe pas ou erreur de colonne
        $expenses = 0;
    }
    
    // 3. Bénéfice Brut
    $gross_profit = $total_revenue - $cogs;
    
    // 4. Coûts de Personnel - utiliser la table salaires existante
    try {
        $labor_query = "SELECT SUM(montant) as total FROM salaires
            WHERE date_paiement BETWEEN :date_debut AND :date_fin";
        $labor_stmt = $pdo->prepare($labor_query);
        $labor_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        $labor_result = $labor_stmt->fetch(PDO::FETCH_ASSOC);
        $labor = $labor_result['total'] ?? 0;
    } catch (PDOException $e) {
        // Table salaires n'existe pas ou erreur
        $labor = 0;
    }
    
    // 5. Bénéfice Net
    $net_profit = $gross_profit - $expenses - $labor;
    
    // Set color for net profit display
    $health_color = ($net_profit > 0) ? 'success' : 'danger';
} catch (PDOException $e) {
    $error_message = "Erreur métriques financières : ". $e->getMessage();
    // Initialize default values in case of error
    $cogs = 0;
    $expenses = 0;
    $labor = 0;
    $gross_profit = $total_revenue;
    $net_profit = $total_revenue;
    $health_color = 'warning';
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    // Get filtered sales data
    $sales_query = "SELECT 
        v.numero_facture,
        v.date_vente,
        COALESCE(c.nom, 'Client Anonyme') as client,
        v.montant_total,
        v.statut_paiement
    FROM ventes v
    LEFT JOIN clients c ON v.client_id = c.id
    WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
    AND v.statut_vente != 'annulee'";
    
    $sales_stmt = $pdo->prepare($sales_query);
    $sales_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    $sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($export_type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventes_'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, array('Numéro', 'Date', 'Client', 'Montant', 'Statut'));
        
        foreach ($sales_data as $row) {
            fputcsv($output, [
                $row['numero_facture'],
                date('d/m/Y H:i', strtotime($row['date_vente'])),
                $row['client'],
                number_format($row['montant_total'], 0, ',', ' ') . ' BIF',
                $row['statut_paiement']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// Include header
require_once __DIR__.'/../layouts/header.php';
?>

<main id="main" class="main">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="pagetitle">
        <h1><i class="fas fa-chart-line"></i> Rapports de Ventes</h1>
        <nav>
            <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Rapports de Ventes</li>
            </ol>
        </nav>
    </div>
    
    <!-- Export Buttons -->
    <div class="float-end mb-3">
        <div class="btn-group mb-3">
            <a href="?export=csv&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Excel (CSV)
            </a>
            <button onclick="window.print()" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>
    
    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
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
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                    <a href="?" class="btn btn-outline-secondary ms-2">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-money-bill-wave text-primary fs-4 me-2"></i>
                                <div class="text-uppercase small text-primary fw-bold">Revenu Total</div>
                            </div>
                            <div class="h4 mb-0">
                                <?= number_format($total_revenue, 0, ',', ' ') ?> BIF
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-primary me-1" style="font-size: 0.5rem;"></i>
                                <?= $sales_count ?> ventes
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-calendar-day text-success fs-4 me-2"></i>
                                <div class="text-uppercase small text-success fw-bold">Ventes du Jour</div>
                            </div>
                            <div class="h4 mb-0">
                                <?= number_format($today_revenue ?? 0, 0, ',', ' ') ?> BIF
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                <?= $today_count ?> ventes aujourd'hui
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-calendar text-info fs-4 me-2"></i>
                                <div class="text-uppercase small text-info fw-bold">Période</div>
                            </div>
                            <div class="h4 mb-0">
                                <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-boxes text-warning fs-4 me-2"></i>
                                <div class="text-uppercase small text-warning fw-bold">Revenu des Produits Vendus</div>
                            </div>
                            <div class="h4 mb-0">
                                <?= number_format($product_revenue, 0, ',', ' ') ?> BIF
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-file-invoice-dollar text-danger fs-4 me-2"></i>
                                <div class="text-uppercase small text-danger fw-bold">Revenu des Dettes</div>
                            </div>
                            <div class="h4 mb-0">
                                <?= number_format($debt_revenue, 0, ',', ' ') ?> BIF
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Résumé Financier -->
    <div class="row mt-4">
        <div class="col-md-12">
            <h4 class="mb-3">Résumé Financier</h4>
        </div>
        
        <!-- Coût des Marchandises Vendues -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Coût des Marchandises</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($cogs, 0, ',', ' ') ?> BIF</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Frais d'Exploitation -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Frais d'Exploitation</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($expenses, 0, ',', ' ') ?> BIF</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bénéfice Brut -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Bénéfice Brut</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($gross_profit, 0, ',', ' ') ?> BIF</h6>
                            <span class="text-<?= ($gross_profit > 0) ? 'success' : 'danger' ?> small pt-1 fw-bold">
                                <?= $total_revenue > 0 ? round(($gross_profit/$total_revenue)*100, 2) : 0 ?>% Marge
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coûts de Personnel -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Masse Salariale</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($labor, 0, ',', ' ') ?> BIF</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bénéfice Net -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Bénéfice Net</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?= number_format($net_profit, 0, ',', ' ') ?> BIF</h6>
                            <span class="text-<?= ($net_profit > 0) ? 'success' : 'danger' ?> small pt-1 fw-bold">
                                <?= $total_revenue > 0 ? round(($net_profit/$total_revenue)*100, 2) : 0 ?>% Marge Nette
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Santé Financière -->
        <div class="col-md-4">
            <div class="card info-card revenue-card">
                <div class="card-body">
                    <h5 class="card-title">Santé de l'Entreprise</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-<?= $health_color ?>">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="ps-3">
                            <h6 class="text-<?= $health_color ?>"><?= ($net_profit > 0) ? 'Saine' : 'Attention Requise' ?></h6>
                            <span class="text-<?= $health_color ?> small pt-1 fw-bold">
                                <?= ($net_profit > 0) ? 'Rentable' : 'Ajustements Nécessaires' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-trophy"></i> Produits les Plus Vendus</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité Vendue</th>
                                    <th>Montant Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (empty($top_products)) {
                                    echo '<tr><td colspan="3" class="text-center">Aucun produit vendu pour cette période</td></tr>';
                                } else {
                                    foreach ($top_products as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['nom']) ?></td>
                                            <td><?= number_format($product['quantite'], 2) ?></td>
                                            <td><?= number_format($product['montant'], 0, ',', ' ') ?> BIF</td>
                                        </tr>
                                    <?php endforeach;
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Clients Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Top Clients</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Total Achats</th>
                                    <th>Nombre de Commandes</th>
                                    <th>Dernière Commande</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $top_clients_query = "SELECT 
                                        c.nom as client,
                                        SUM(v.montant_total) as total_achats,
                                        COUNT(v.id) as nombre_commandes,
                                        MAX(v.date_vente) as derniere_commande
                                    FROM ventes v
                                    JOIN clients c ON v.client_id = c.id
                                    WHERE DATE(v.date_vente) BETWEEN :date_debut AND :date_fin
                                    AND v.statut_vente != 'annulee'
                                    GROUP BY c.id, c.nom
                                    ORDER BY total_achats DESC
                                    LIMIT 5";
                                    
                                    $top_clients_stmt = $pdo->prepare($top_clients_query);
                                    $top_clients_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
                                    $top_clients = $top_clients_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($top_clients)) {
                                        echo '<tr><td colspan="4" class="text-center">Aucun client trouvé pour cette période</td></tr>';
                                    } else {
                                        foreach ($top_clients as $client): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($client['client']) ?></td>
                                                <td><?= number_format($client['total_achats'], 0, ',', ' ') ?> BIF</td>
                                                <td><?= $client['nombre_commandes'] ?></td>
                                                <td><?= date('d/m/Y', strtotime($client['derniere_commande'])) ?></td>
                                            </tr>
                                        <?php endforeach;
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="4" class="text-center text-danger">Erreur lors du chargement des données clients</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Export functions
function exportToPDF() {
    // TODO: Implement PDF export
    alert('PDF export functionality will be implemented');
}

function exportToExcel() {
    // TODO: Implement Excel export
    alert('Excel export functionality will be implemented');
}

function printReport() {
    window.print();
}
</script>

<?php
// Include footer
require_once __DIR__.'/../layouts/footer.php';
?>
