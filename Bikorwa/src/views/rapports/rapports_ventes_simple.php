<?php
// Version simplifiée des rapports de ventes - BIKORWA SHOP
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

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gestionnaire') {
    header('Location: ../auth/login.php');
    exit;
}

// Get date filter parameters with defaults
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // First day of current month
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Today

// Initialize variables to avoid undefined errors
$total_revenue = 0;
$sales_count = 0;
$today_revenue = 0;
$today_count = 0;
$top_products = [];
$error_message = '';

// Version simplifiée - utilise seulement la table ventes avec montant_paye
try {
    // Total Revenue from ventes table using montant_paye
    $revenue_query = "SELECT 
        SUM(montant_paye) as total,
        COUNT(*) as count
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN :date_debut AND :date_fin
        AND statut_vente != 'annulee'";
    
    $revenue_stmt = $pdo->prepare($revenue_query);
    $revenue_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    $revenue_result = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_revenue = $revenue_result['total'] ?? 0;
    $sales_count = $revenue_result['count'] ?? 0;

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

    // Try to get top products if tables exist
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
        // Tables details_ventes ou produits n'existent pas
        $top_products = [];
    }

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : ". $e->getMessage();
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Get filtered sales data using montant_paye
        $sales_query = "SELECT 
            numero_facture,
            date_vente,
            montant_paye,
            statut_paiement
        FROM ventes 
        WHERE DATE(date_vente) BETWEEN :date_debut AND :date_fin
        AND statut_vente != 'annulee'";
        
        $sales_stmt = $pdo->prepare($sales_query);
        $sales_stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        $sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventes_'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, array('Numéro', 'Date', 'Montant Payé', 'Statut'));
        
        foreach ($sales_data as $row) {
            fputcsv($output, [
                $row['numero_facture'],
                date('d/m/Y H:i', strtotime($row['date_vente'])),
                number_format($row['montant_paye'], 0, ',', ' ') . ' BIF',
                $row['statut_paiement']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de l'export : " . $e->getMessage();
    }
}

// Include header
require_once __DIR__.'/../layouts/header.php';
?>

<main id="main" class="main">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="pagetitle">
        <h1><i class="fas fa-chart-line"></i> Rapports de Ventes (Version Simplifiée)</h1>
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
    </div>

    <?php if (!empty($top_products)): ?>
    <!-- Top Products -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-trophy text-warning"></i> Top 5 Produits Vendus
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['nom']) ?></td>
                                        <td><?= number_format($product['quantite'], 2) ?></td>
                                        <td><?= number_format($product['montant'], 0, ',', ' ') ?> BIF</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Cette version simplifiée utilise uniquement la table des ventes. 
                Pour des rapports plus détaillés, assurez-vous que toutes les tables nécessaires existent dans votre base de données.
            </div>
        </div>
    </div>

</main>

<?php
// Include footer
require_once __DIR__.'/../layouts/footer.php';
?>