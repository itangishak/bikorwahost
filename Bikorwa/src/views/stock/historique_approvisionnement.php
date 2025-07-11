<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique d'approvisionnement - Bikorwa Shop</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body>
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $_SESSION['user_role'] = 'gestionnaire';
}

// Debug: Check if session role is set and its value
echo 'Session Role: ' . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Not Set') . '<br>';
// Temporary: Comment out the redirect to see the debug output
// Check permissions before any output
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gestionnaire') {
//     header('Location: ../auth/login.php');
//     exit;
// }

// Include database connection and config
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../config/config.php';



require_once __DIR__ . '/../layouts/header.php';

// Fetch all supply history (stock entries) with pagination
$itemsPerPage = 10; // Number of items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number
$offset = ($page - 1) * $itemsPerPage; // Calculate offset

// Count total number of entries for pagination
$countSql = "SELECT COUNT(*) as total FROM mouvements_stock WHERE type_mouvement = 'entree'";
try {
    $countStmt = $pdo->query($countSql);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    echo 'Count query error: ' . $e->getMessage() . '<br>';
    $totalItems = 0;
    $totalPages = 1;
}

$sql = "SELECT 
    m.id, 
    p.nom AS produit_nom, 
    m.quantite, 
    m.prix_unitaire, 
    m.valeur_totale, 
    m.date_mouvement,
    u.nom AS utilisateur_nom
FROM mouvements_stock m
JOIN produits p ON m.produit_id = p.id
JOIN users u ON m.utilisateur_id = u.id
WHERE m.type_mouvement = 'entree'
ORDER BY m.date_mouvement DESC
LIMIT :offset, :itemsPerPage";
try {
    $stmt = $pdo->prepare($sql);
    if ($stmt) {
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo 'Query error: ' . $e->getMessage() . '<br>';
    $entries = array();
}
?>

<div class="content">
    <h1>Historique d'approvisionnement</h1>
    
    <?php
    // Get date filter parameters
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';
    
    // Build WHERE clause for date filtering
    $date_where = "";
    $date_params = [];
    if (!empty($date_debut)) {
        $date_where .= " AND date_mouvement >= ?";
        $date_params[] = $date_debut;
    }
    if (!empty($date_fin)) {
        $date_where .= " AND date_mouvement <= ?";
        $date_params[] = $date_fin . ' 23:59:59';
    }
    ?>
    
    <!-- Date Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="date_debut" class="form-label">Date début</label>
                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                           value="<?= htmlspecialchars($date_debut) ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_fin" class="form-label">Date fin</label>
                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                           value="<?= htmlspecialchars($date_fin) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <?php if (!empty($date_debut) || !empty($date_fin)): ?>
                        <a href="?" class="btn btn-outline-secondary ms-2">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <!-- Total Supply Value Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-boxes text-primary fs-4 me-2"></i>
                                <div class="text-uppercase small text-primary fw-bold">
                                    <?= !empty($date_debut) || !empty($date_fin) ? 'Valeur filtrée' : 'Valeur totale' ?>
                                </div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $total_query = "SELECT SUM(valeur_totale) as total FROM mouvements_stock 
                                              WHERE type_mouvement = 'entree' $date_where";
                                $total_stmt = $pdo->prepare($total_query);
                                $total_stmt->execute($date_params);
                                $total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($total_data['total'] ?? 0, 0, ',', ' '); ?> BIF
                            </div>
                            <?php if (!empty($date_debut) || !empty($date_fin)): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-filter text-primary me-1"></i>
                                    Filtre appliqué
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Supplies Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-history text-success fs-4 me-2"></i>
                                <div class="text-uppercase small text-success fw-bold">
                                    <?= !empty($date_debut) || !empty($date_fin) ? 'Appro. filtrés' : 'Approvisionnements' ?>
                                </div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $count_query = "SELECT COUNT(*) as count FROM mouvements_stock 
                                              WHERE type_mouvement = 'entree' $date_where";
                                $count_stmt = $pdo->prepare($count_query);
                                $count_stmt->execute($date_params);
                                $count_data = $count_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($count_data['count'] ?? 0, 0, ',', ' ');
                                ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                <?php
                                $recent_query = "SELECT COUNT(*) as recent FROM mouvements_stock 
                                               WHERE type_mouvement = 'entree' 
                                               AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                               $date_where";
                                $recent_stmt = $pdo->prepare($recent_query);
                                $recent_stmt->execute($date_params);
                                $recent_data = $recent_stmt->fetch(PDO::FETCH_ASSOC);
                                echo $recent_data['recent'] ?? 0; ?> cette semaine
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Value Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-calculator text-info fs-4 me-2"></i>
                                <div class="text-uppercase small text-info fw-bold">Moyenne/approvisionnement</div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $avg_query = "SELECT AVG(valeur_totale) as average FROM mouvements_stock 
                                            WHERE type_mouvement = 'entree' $date_where";
                                $avg_stmt = $pdo->prepare($avg_query);
                                $avg_stmt->execute($date_params);
                                $avg_data = $avg_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($avg_data['average'] ?? 0, 0, ',', ' '); ?> BIF
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-tags text-warning fs-4 me-2"></i>
                                <div class="text-uppercase small text-warning fw-bold">Produits</div>
                            </div>
                            <div class="h4 mb-0">
                                <?php
                                $products_query = "SELECT COUNT(DISTINCT produit_id) as count FROM mouvements_stock 
                                                 WHERE type_mouvement = 'entree' $date_where";
                                $products_stmt = $pdo->prepare($products_query);
                                $products_stmt->execute($date_params);
                                $products_data = $products_stmt->fetch(PDO::FETCH_ASSOC);
                                echo number_format($products_data['count'] ?? 0, 0, ',', ' ');
                                ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-circle text-warning me-1" style="font-size: 0.5rem;"></i>
                                Produits différents
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Historique d'approvisionnement</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped" id="supply-history-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix Unitaire</th>
                                    <th>Valeur Totale</th>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($entries)): ?>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($entry['id']) ?></td>
                                            <td><?= htmlspecialchars($entry['produit_nom']) ?></td>
                                            <td><?= htmlspecialchars($entry['quantite']) ?></td>
                                            <td><?= htmlspecialchars(number_format($entry['prix_unitaire'], 0, ',', ' ')) ?> BIF</td>
                                            <td><?= htmlspecialchars(number_format($entry['valeur_totale'], 0, ',', ' ')) ?> BIF</td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($entry['date_mouvement']))) ?></td>
                                            <td><?= htmlspecialchars($entry['utilisateur_nom']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">Aucun approvisionnement trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                Affichage de <b><?= min(($page - 1) * $itemsPerPage + 1, $totalItems) ?></b> à <b><?= min($page * $itemsPerPage, $totalItems) ?></b> sur <b><?= $totalItems ?></b> entrées
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete button handler
    document.querySelectorAll('.delete-supply').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (confirm('Êtes-vous sûr de vouloir supprimer cet approvisionnement ?')) {
                // Submit form for better reliability
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_supply.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</body>
</html>
