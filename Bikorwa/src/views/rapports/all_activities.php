<?php
// Page listing all activities with pagination
$page_title = "Toutes les Activités";
$active_page = "rapports";

require_once __DIR__.'/../../../src/config/config.php';
require_once __DIR__.'/../../../src/config/database.php';
require_once __DIR__.'/../../../src/utils/Auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gestionnaire') {
    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$date_debut = $_GET['date_debut'] ?? '1970-01-01';
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$items_per_page = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Count total activities
$count_query = "SELECT COUNT(*) FROM (
    SELECT v.id FROM ventes v WHERE v.date_vente BETWEEN ? AND ?
    UNION ALL
    SELECT m.id FROM mouvements_stock m WHERE m.date_mouvement BETWEEN ? AND ?
    UNION ALL
    SELECT s.id FROM salaires s WHERE s.date_paiement BETWEEN ? AND ?
) as all_acts";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute([
    $date_debut.' 00:00:00', $date_fin.' 23:59:59',
    $date_debut.' 00:00:00', $date_fin.' 23:59:59',
    $date_debut.' 00:00:00', $date_fin.' 23:59:59'
]);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $items_per_page);

// Fetch paginated activities (same structure as getRecentActivities)
$activities_query = "
SELECT
    type_activite,
    date_action,
    titre,
    montant,
    username,
    role,
    reference
FROM (
    SELECT
        'vente' as type_activite,
        v.date_vente as date_action,
        CONCAT('Vente #', v.numero_facture) as titre,
        CONCAT(FORMAT(v.montant_paye, 0), ' BIF') as montant,
        COALESCE(u.username, 'Système') as username,
        COALESCE(u.role, 'N/A') as role,
        v.numero_facture as reference
    FROM ventes v
    LEFT JOIN users u ON v.utilisateur_id = u.id
    WHERE v.date_vente BETWEEN ? AND ?

    UNION ALL

    SELECT
        CASE WHEN m.type_mouvement = 'entree' THEN 'approvisionnement' ELSE 'sortie' END,
        m.date_mouvement,
        CONCAT(CASE WHEN m.type_mouvement = 'entree' THEN 'Entrée: ' ELSE 'Sortie: ' END,
               COALESCE(p.nom, 'Produit inconnu')),
        CONCAT(FORMAT(m.quantite, 0), ' ', COALESCE(p.unite_mesure, 'unité')),
        COALESCE(u.username, 'Système') as username,
        COALESCE(u.role, 'N/A') as role,
        m.reference
    FROM mouvements_stock m
    LEFT JOIN users u ON m.utilisateur_id = u.id
    LEFT JOIN produits p ON m.produit_id = p.id
    WHERE m.date_mouvement BETWEEN ? AND ?

    UNION ALL

    SELECT
        'paiement_salaire',
        s.date_paiement,
        CONCAT('Salaire: ', COALESCE(e.nom, 'Employé inconnu')),
        CONCAT(FORMAT(s.montant, 0), ' BIF'),
        COALESCE(u.username, 'Système') as username,
        COALESCE(u.role, 'N/A') as role,
        NULL
    FROM salaires s
    LEFT JOIN users u ON s.utilisateur_id = u.id
    LEFT JOIN employes e ON s.employe_id = e.id
    WHERE s.date_paiement BETWEEN ? AND ?
) acts
ORDER BY date_action DESC
LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($activities_query);
$stmt->bindValue(1, $date_debut.' 00:00:00');
$stmt->bindValue(2, $date_fin.' 23:59:59');
$stmt->bindValue(3, $date_debut.' 00:00:00');
$stmt->bindValue(4, $date_fin.' 23:59:59');
$stmt->bindValue(5, $date_debut.' 00:00:00');
$stmt->bindValue(6, $date_fin.' 23:59:59');
$stmt->bindValue(7, (int)$items_per_page, PDO::PARAM_INT);
$stmt->bindValue(8, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__.'/../layouts/header.php';
?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="fas fa-list"></i> Toutes les Activités</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Activités</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Historique des Activités</h5>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Détails</th>
                            <th>Montant</th>
                            <th>Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $act): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($act['date_action'])) ?></td>
                            <td>
                                <?php
                                if ($act['type_activite'] === 'sortie') {
                                    $detail = 'Vente';
                                    $ref = $act['reference'] ?? '';
                                    if (stripos($ref, 'ADJ-OUT') === 0 || stripos($ref, 'RETRAIT') === 0) {
                                        $detail = 'Ajustement';
                                    }
                                    echo 'Sortie (' . $detail . ')';
                                } else {
                                    echo ucfirst($act['type_activite']);
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($act['titre']) ?></td>
                            <td><?= htmlspecialchars($act['montant']) ?></td>
                            <td><?= htmlspecialchars($act['username']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Pagination" class="mt-3">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo;</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
