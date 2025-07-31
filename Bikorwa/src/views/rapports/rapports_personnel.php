<?php
// Rapports Personnel - BIKORWA SHOP
$page_title = "Rapports du Personnel";
$active_page = "rapports";

require_once __DIR__.'/../../../src/config/config.php';
require_once __DIR__.'/../../../src/config/database.php';
require_once __DIR__.'/../../../src/utils/Auth.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gestionnaire') {
    header('Location: ' . BASE_URL . '/src/views/auth/login.php');
    exit;
}

// Initialisation de la base de données
$database = new Database();
$pdo = $database->getConnection();

// Récupération des paramètres de filtre
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Validation des dates
if (!DateTime::createFromFormat('Y-m-d', $date_debut) || !DateTime::createFromFormat('Y-m-d', $date_fin)) {
    $date_debut = date('Y-m-01');
    $date_fin = date('Y-m-d');
}

// Fonction pour obtenir les statistiques détaillées du personnel
function getDetailedStaffStats($pdo, $date_debut, $date_fin) {
    try {
        $query = "SELECT 
            u.id,
            u.nom,
            u.username,
            u.role,
            u.email,
            u.telephone,
            u.date_creation,
            u.derniere_connexion,
            COUNT(DISTINCT v.id) as nb_ventes,
            COALESCE(SUM(v.montant_total), 0) as montant_total_ventes,
            COALESCE(SUM(v.montant_paye), 0) as chiffre_affaire,
            COUNT(DISTINCT CASE WHEN v.statut_paiement = 'credit' THEN v.id END) as ventes_credit,
            COUNT(DISTINCT m.id) as nb_approvisionnements,
            COALESCE(SUM(CASE WHEN m.type_mouvement = 'entree' THEN m.valeur_totale END), 0) as valeur_approvisionnements,
            COUNT(DISTINCT DATE(v.date_vente)) as jours_actifs_vente,
            COUNT(DISTINCT DATE(m.date_mouvement)) as jours_actifs_stock
        FROM users u
        LEFT JOIN ventes v ON v.utilisateur_id = u.id 
            AND v.date_vente BETWEEN :date_debut AND :date_fin
        LEFT JOIN mouvements_stock m ON m.utilisateur_id = u.id 
            AND m.date_mouvement BETWEEN :date_debut AND :date_fin
        WHERE u.role IN ('receptionniste', 'gestionnaire') AND u.actif = 1
        GROUP BY u.id, u.nom, u.username, u.role, u.email, u.telephone, u.date_creation, u.derniere_connexion
        ORDER BY chiffre_affaire DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {

        return [];
    }
}


// Fonction pour obtenir les activités récentes - Version corrigée
function getRecentActivities($pdo, $date_debut, $date_fin, $limit = 15) {
    try {
        // Vérification de la connexion PDO
        if (!$pdo) {

            return [];
        }
        
        // Requête simplifiée et testée avec paiements de salaires
        $query = "
        SELECT
            type_activite,
            date_action,
            titre,
            montant,
            username,
            role,
            ref_id,
            reference
        FROM (
            SELECT
                'vente' as type_activite,
                v.date_vente as date_action,
                CONCAT('Vente #', v.numero_facture) as titre,
                CONCAT(FORMAT(v.montant_paye, 0), ' BIF') as montant,
                COALESCE(u.username, 'Système') as username,
                COALESCE(u.role, 'N/A') as role,
                v.id as ref_id,
                v.numero_facture as reference
            FROM ventes v
            LEFT JOIN users u ON v.utilisateur_id = u.id
            WHERE v.date_vente BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT
                CASE
                    WHEN m.type_mouvement = 'entree' THEN 'approvisionnement'
                    ELSE 'sortie'
                END as type_activite,
                m.date_mouvement as date_action,
                CONCAT(
                    CASE WHEN m.type_mouvement = 'entree' THEN 'Entrée: ' ELSE 'Sortie: ' END,
                    COALESCE(p.nom, 'Produit inconnu')
                ) as titre,
                CONCAT(FORMAT(m.quantite, 0), ' ', COALESCE(p.unite_mesure, 'unité')) as montant,
                COALESCE(u.username, 'Système') as username,
                COALESCE(u.role, 'N/A') as role,
                m.id as ref_id,
                m.reference
            FROM mouvements_stock m
            LEFT JOIN users u ON m.utilisateur_id = u.id
            LEFT JOIN produits p ON m.produit_id = p.id
            WHERE m.date_mouvement BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT
                'paiement_salaire' as type_activite,
                s.date_paiement as date_action,
                CONCAT('Salaire: ', COALESCE(e.nom, 'Employé inconnu')) as titre,
                CONCAT(FORMAT(s.montant, 0), ' BIF') as montant,
                COALESCE(u.username, 'Système') as username,
                COALESCE(u.role, 'N/A') as role,
                s.id as ref_id,
                NULL as reference
            FROM salaires s
            LEFT JOIN users u ON s.utilisateur_id = u.id
            LEFT JOIN employes e ON s.employe_id = e.id
            WHERE s.date_paiement BETWEEN ? AND ?
        ) combined_activities
        ORDER BY date_action DESC
        LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        
        $params = [
            $date_debut . ' 00:00:00',
            $date_fin . ' 23:59:59',
            $date_debut . ' 00:00:00',
            $date_fin . ' 23:59:59',
            $date_debut . ' 00:00:00',
            $date_fin . ' 23:59:59',
            $limit
        ];
        
        // Debug logging

        
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $results;
        
    } catch (PDOException $e) {
        return [];
    }
}

// Fonction pour calculer les KPIs globaux
function getGlobalKPIs($pdo, $date_debut, $date_fin) {
    try {
        $query = "SELECT 
            COUNT(DISTINCT u.id) as nb_employes_actifs,
            COUNT(DISTINCT v.id) as total_ventes,
            COALESCE(SUM(v.montant_paye), 0) as chiffre_affaire_total,
            COUNT(DISTINCT m.id) as total_approvisionnements
        FROM users u
        LEFT JOIN ventes v ON v.utilisateur_id = u.id 
            AND v.date_vente BETWEEN :date_debut AND :date_fin
        LEFT JOIN mouvements_stock m ON m.utilisateur_id = u.id 
            AND m.type_mouvement = 'entree'
            AND m.date_mouvement BETWEEN :date_debut AND :date_fin
        WHERE u.role IN ('receptionniste', 'gestionnaire') AND u.actif = 1";

        $stmt = $pdo->prepare($query);
        $stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {

        return [
            'nb_employes_actifs' => 0,
            'total_ventes' => 0,
            'chiffre_affaire_total' => 0,
            'total_approvisionnements' => 0
        ];
    }
}

// Fonction pour obtenir les activités d'aujourd'hui (basée sur journal_activites)
function getTodayActivities($pdo) {
    try {
        $today = date('Y-m-d');
        
        // Vérifier d'abord si le journal a des données aujourd'hui
        $journalCheck = $pdo->prepare("SELECT COUNT(*) as journal_count FROM journal_activites WHERE DATE(date_action) = ?");
        $journalCheck->execute([$today]);
        $journalCount = $journalCheck->fetch()['journal_count'];
        
        if ($journalCount > 0) {
            // Utiliser le journal d'activités avec les entités exactes
            $query = "SELECT 
                COUNT(*) as total_activites,
                COUNT(DISTINCT ja.utilisateur_id) as utilisateurs_actifs,
                SUM(CASE 
                    WHEN ja.entite = 'ventes'
                    THEN 1 ELSE 0 END) as ventes_aujourd_hui,
                SUM(CASE 
                    WHEN ja.entite = 'stock'
                    THEN 1 ELSE 0 END) as mouvements_stock,
                SUM(CASE 
                    WHEN ja.entite = 'salaires'
                    THEN 1 ELSE 0 END) as paiements_salaires
            FROM journal_activites ja
            WHERE DATE(ja.date_action) = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$today]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result;
        } else {
            // Fallback: utiliser les tables directes

            
            $ventesQuery = "SELECT COUNT(*) as ventes_count FROM ventes WHERE DATE(date_vente) = ?";
            $stockQuery = "SELECT COUNT(*) as stock_count FROM mouvements_stock WHERE DATE(date_mouvement) = ?";
            $salairesQuery = "SELECT COUNT(*) as salaires_count FROM salaires WHERE DATE(date_paiement) = ?";
            $usersQuery = "SELECT COUNT(DISTINCT utilisateur_id) as users_count FROM (
                SELECT utilisateur_id FROM ventes WHERE DATE(date_vente) = ? AND utilisateur_id IS NOT NULL
                UNION 
                SELECT utilisateur_id FROM mouvements_stock WHERE DATE(date_mouvement) = ? AND utilisateur_id IS NOT NULL
                UNION
                SELECT utilisateur_id FROM salaires WHERE DATE(date_paiement) = ? AND utilisateur_id IS NOT NULL
            ) as active_users";
            
            $stmt = $pdo->prepare($ventesQuery);
            $stmt->execute([$today]);
            $ventes = $stmt->fetch()['ventes_count'];
            
            $stmt = $pdo->prepare($stockQuery);
            $stmt->execute([$today]);
            $stock = $stmt->fetch()['stock_count'];
            
            $stmt = $pdo->prepare($salairesQuery);
            $stmt->execute([$today]);
            $salaires = $stmt->fetch()['salaires_count'];
            
            $stmt = $pdo->prepare($usersQuery);
            $stmt->execute([$today, $today, $today]);
            $users = $stmt->fetch()['users_count'];
            

            
            return [
                'total_activites' => $ventes + $stock + $salaires,
                'utilisateurs_actifs' => $users,
                'ventes_aujourd_hui' => $ventes,
                'mouvements_stock' => $stock,
                'paiements_salaires' => $salaires
            ];
        }
        
    } catch (PDOException $e) {

        return [
            'total_activites' => 0,
            'utilisateurs_actifs' => 0,
            'ventes_aujourd_hui' => 0,
            'mouvements_stock' => 0,
            'paiements_salaires' => 0
        ];
    }
}

// Récupération des données
$staffStats = getDetailedStaffStats($pdo, $date_debut, $date_fin);

// Récupération des activités récentes avec debug amélioré
$recentActivities = getRecentActivities($pdo, $date_debut, $date_fin);

// Si aucune activité trouvée avec la fonction principale, utiliser une approche directe
if (empty($recentActivities)) {
    try {
        // Requête directe pour les ventes dans la période
        $ventesQuery = "
        SELECT 
            'ventes' as type_activite,
            v.date_vente as date_action,
            CONCAT('Vente #', v.numero_facture) as titre,
            CONCAT(FORMAT(v.montant_paye, 0), ' BIF') as montant,
            COALESCE(u.username, 'Système') as username,
            COALESCE(u.role, 'N/A') as role,
            v.id as ref_id
        FROM ventes v
        LEFT JOIN users u ON v.utilisateur_id = u.id
        WHERE v.date_vente BETWEEN ? AND ?
        ORDER BY v.date_vente DESC";
        
        $stmt = $pdo->prepare($ventesQuery);
        $stmt->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
        $ventesActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Requête directe pour les mouvements de stock dans la période
        $stockQuery = "
        SELECT 
            'stock' as type_activite,
            m.date_mouvement as date_action,
            CONCAT(
                CASE WHEN m.type_mouvement = 'entree' THEN 'Entrée: ' ELSE 'Sortie: ' END,
                COALESCE(p.nom, 'Produit')
            ) as titre,
            CONCAT(FORMAT(m.quantite, 0), ' ', COALESCE(p.unite_mesure, 'unité')) as montant,
            COALESCE(u.username, 'Système') as username,
            COALESCE(u.role, 'N/A') as role,
            m.id as ref_id
        FROM mouvements_stock m
        LEFT JOIN users u ON m.utilisateur_id = u.id
        LEFT JOIN produits p ON m.produit_id = p.id
        WHERE m.date_mouvement BETWEEN ? AND ?
        ORDER BY m.date_mouvement DESC";
        
        $stmt = $pdo->prepare($stockQuery);
        $stmt->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
        $stockActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combiner toutes les activités
        $recentActivities = array_merge($ventesActivities, $stockActivities);
        
        // Trier par date décroissante
        if (!empty($recentActivities)) {
            usort($recentActivities, function($a, $b) {
                return strtotime($b['date_action']) - strtotime($a['date_action']);
            });
            
            // Limiter à 15 résultats
            $recentActivities = array_slice($recentActivities, 0, 15);
        }
        
    } catch (PDOException $e) {

        $recentActivities = [];
    }
}

$globalKPIs = getGlobalKPIs($pdo, $date_debut, $date_fin);
$todayStats = getTodayActivities($pdo);

// Fonction pour calculer la performance améliorée
function calculateAdvancedPerformance($staff) {
    $score = 0;
    $maxScore = 100;
    
    if ($staff['role'] === 'receptionniste') {
        // Pour les réceptionnistes : focus sur les ventes
        $ventesScore = min(($staff['nb_ventes'] / 10) * 30, 30); // Max 30 points pour 10+ ventes
        $caScore = min(($staff['chiffre_affaire'] / 500000) * 40, 40); // Max 40 points pour 500k+ BIF
        $regulariteScore = min(($staff['jours_actifs_vente'] / 20) * 20, 20); // Max 20 points pour 20+ jours actifs
        $creditScore = $staff['nb_ventes'] > 0 ? max(0, 10 - (($staff['ventes_credit'] / $staff['nb_ventes']) * 10)) : 10;
        
        $score = $ventesScore + $caScore + $regulariteScore + $creditScore;
    } else {
        // Pour les gestionnaires : focus sur la gestion globale
        $approScore = min(($staff['nb_approvisionnements'] / 5) * 40, 40); // Max 40 points pour 5+ appros
        $valeurScore = min(($staff['valeur_approvisionnements'] / 1000000) * 30, 30); // Max 30 points pour 1M+ BIF
        $ventesScore = min(($staff['nb_ventes'] / 5) * 20, 20); // Max 20 points pour 5+ ventes
        $regulariteScore = min((($staff['jours_actifs_stock'] + $staff['jours_actifs_vente']) / 15) * 10, 10);
        
        $score = $approScore + $valeurScore + $ventesScore + $regulariteScore;
    }
    
    return min(round($score, 1), $maxScore);
}

// Export CSV si demandé
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_personnel_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // En-têtes
    fputcsv($output, [
        'Nom', 'Rôle', 'Email', 'Téléphone', 'Nb Ventes', 'Chiffre d\'affaires (BIF)', 
        'Ventes à Crédit', 'Approvisionnements', 
        'Valeur Appros (BIF)', 'Jours Actifs Vente', 'Performance (%)'
    ], ';');
    
    // Données
    foreach ($staffStats as $staff) {
        fputcsv($output, [
            $staff['username'],
            ucfirst($staff['role']),
            $staff['email'] ?? '',
            $staff['telephone'] ?? '',
            $staff['nb_ventes'],
            number_format($staff['chiffre_affaire'], 0, ',', ' '),
            $staff['ventes_credit'],
            $staff['nb_approvisionnements'],
            number_format($staff['valeur_approvisionnements'], 0, ',', ' '),
            $staff['jours_actifs_vente'],
            calculateAdvancedPerformance($staff)
        ], ';');
    }
    
    fclose($output);
    exit;
}

require_once __DIR__.'/../../../src/views/layouts/header.php';
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main" id="main">
    <div class="pagetitle">
        <h1><?= $page_title ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/index.php">Accueil</a></li>
                <li class="breadcrumb-item">Rapports</li>
                <li class="breadcrumb-item active">Personnel</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- KPIs Cards -->
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Employés Actifs</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center me-2">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $globalKPIs['nb_employes_actifs'] ?></h6>
                                <span class="text-muted small pt-1 fw-bold">Membres de l'équipe</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Chiffre d'Affaires</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($globalKPIs['chiffre_affaire_total'], 0, ',', ' ') ?> BIF</h6>
                                <span class="text-success small pt-1 fw-bold">Total période</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Ventes</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $globalKPIs['total_ventes'] ?></h6>
                                <span class="text-muted small pt-1 fw-bold">Transactions</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Approvisionnements</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $globalKPIs['total_approvisionnements'] ?></h6>
                                <span class="text-info small pt-1 fw-bold">Total période</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres et Actions -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres et Actions</h5>
                        <form id="filter-form" class="row g-3" method="GET">
                            <div class="col-md-3">
                                <label for="date_debut" class="form-label">Date début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $date_debut ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_fin" class="form-label">Date fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $date_fin ?>">
                            </div>
                            <div class="col-md-3 align-self-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Appliquer
                                </button>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                                   class="btn btn-success">
                                    <i class="fas fa-download"></i> Exporter CSV
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Performances du personnel -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Performances du Personnel</h5>
                        
                        <!-- Onglets -->
                        <ul class="nav nav-tabs" id="staffTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="table-tab" data-bs-toggle="tab" 
                                        data-bs-target="#table-view" type="button" role="tab">
                                    <i class="fas fa-table"></i> Tableau Détaillé
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="charts-tab" data-bs-toggle="tab" 
                                        data-bs-target="#charts-view" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Graphiques
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Contenu des onglets -->
                        <div class="tab-content pt-3" id="staffTabContent">
                            <div class="tab-pane fade show active" id="table-view" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped" id="staff-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Employé</th>
                                                <th>Rôle</th>
                                                <th>Ventes</th>
                                                <th>CA (BIF)</th>
                                                <th>Crédit</th>
                                                <th>Appros</th>
                                                <th>Performance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($staffStats as $staff): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($staff['username']) ?></strong>
                                                            <?php if ($staff['email']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($staff['email']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $staff['role'] === 'gestionnaire' ? 'primary' : 'info' ?>">
                                                        <?= htmlspecialchars(ucfirst($staff['role'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= $staff['nb_ventes'] ?></strong>
                                                    <?php if ($staff['jours_actifs_vente'] > 0): ?>
                                                        <br><small class="text-muted"><?= $staff['jours_actifs_vente'] ?> jours actifs</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= number_format($staff['chiffre_affaire'], 0, ',', ' ') ?></strong>
                                                    <?php if ($staff['montant_total_ventes'] != $staff['chiffre_affaire']): ?>
                                                        <br><small class="text-warning">
                                                            <?= number_format($staff['montant_total_ventes'] - $staff['chiffre_affaire'], 0, ',', ' ') ?> impayé
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($staff['ventes_credit'] > 0): ?>
                                                        <span class="badge bg-warning"><?= $staff['ventes_credit'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= $staff['nb_approvisionnements'] ?></strong>
                                                    <?php if ($staff['valeur_approvisionnements'] > 0): ?>
                                                        <br><small class="text-muted">
                                                            <?= number_format($staff['valeur_approvisionnements'], 0, ',', ' ') ?> BIF
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $performance = calculateAdvancedPerformance($staff);
                                                    $color = $performance >= 80 ? 'success' : ($performance >= 60 ? 'warning' : ($performance >= 40 ? 'info' : 'danger'));
                                                    ?>
                                                    <div class="progress mb-1" style="height: 20px;">
                                                        <div class="progress-bar bg-<?= $color ?>" role="progressbar" 
                                                             style="width: <?= $performance ?>%" 
                                                             aria-valuenow="<?= $performance ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?= $performance ?>%
                                                        </div>
                                                    </div>
                                                    <small class="text-<?= $color ?>">
                                                        <?php
                                                        if ($performance >= 80) echo "Excellent";
                                                        elseif ($performance >= 60) echo "Bon";
                                                        elseif ($performance >= 40) echo "Moyen";
                                                        else echo "À améliorer";
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="showStaffDetails(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['username']) ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="charts-view" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">Chiffre d'affaires par employé</h6>
                                                <canvas id="revenueChart" height="300"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">Répartition des performances</h6>
                                                <canvas id="performanceChart" height="300"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">Activité comparative</h6>
                                                <canvas id="activityChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activités récentes -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Activités Récentes 
                            <span class="badge bg-primary"><?= count($recentActivities) ?></span>
                        </h5>
                        <div class="activity">
                            <?php if (empty($recentActivities)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <p>Aucune activité récente trouvée</p>
                                    <small>Période: <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?></small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item d-flex">
                                    <div class="activite-label">
                                        <?= date('H:i', strtotime($activity['date_action'])) ?>
                                    </div>
                                    <i class="fas fa-<?php
                                        switch($activity['type_activite']) {
                                            case 'vente':
                                                echo 'shopping-cart text-success';
                                                break;
                                            case 'approvisionnement':
                                                echo 'truck text-primary';
                                                break;
                                            case 'sortie':
                                                echo 'box-open text-warning';
                                                break;
                                            case 'paiement_salaire':
                                                echo 'money-bill-wave text-info';
                                                break;
                                            default:
                                                echo 'circle text-secondary';
                                        }
                                    ?> activity-badge align-self-start"></i>
                                    <div class="activity-content">
                                        <p class="mb-1">
                                            <strong><?= htmlspecialchars($activity['titre']) ?></strong>
                                            <?php if ($activity['type_activite'] === 'sortie'): ?>
                                                <?php
                                                $detail = 'Vente';
                                                if (isset($activity['reference']) && strpos($activity['reference'], 'ADJ-OUT') === 0) {
                                                    $detail = 'Ajustement';
                                                }
                                                ?>
                                                <span class="badge bg-secondary ms-1"><?= $detail ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($activity['montant'])): ?>
                                                <span class="text-muted">- <?= htmlspecialchars($activity['montant']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            Par <?= htmlspecialchars($activity['username'] ?? 'Système') ?>
                                            <?php if (!empty($activity['role'])): ?>
                                                <span class="badge bg-light text-dark"><?= ucfirst($activity['role']) ?></span>
                                            <?php endif; ?>
                                            <br>
                                            <?= date('d/m/Y', strtotime($activity['date_action'])) ?>
                                            <?php if (date('Y-m-d') === date('Y-m-d', strtotime($activity['date_action']))): ?>
                                                <span class="badge bg-success">Aujourd'hui</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (count($recentActivities) >= 15): ?>
                        <div class="text-center mt-3">
                            <small class="text-muted">Affichage limité aux 15 dernières activités</small>
                        </div>
                        <?php endif; ?>
                        <div class="text-center mt-2">
                            <a href="all_activities.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list"></i> Tous les activités
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Top Performers</h5>
                        <?php 
                        $topPerformers = $staffStats;
                        usort($topPerformers, function($a, $b) {
                            return calculateAdvancedPerformance($b) <=> calculateAdvancedPerformance($a);
                        });
                        $topPerformers = array_slice($topPerformers, 0, 3);
                        ?>
                        
                        <?php foreach ($topPerformers as $index => $performer): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?= $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'dark') ?> rounded-pill">
                                    #<?= $index + 1 ?>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?= htmlspecialchars($performer['username']) ?></h6>
                                <small class="text-muted">
                                    <?= calculateAdvancedPerformance($performer) ?>% - 
                                    <?= number_format($performer['chiffre_affaire'], 0, ',', ' ') ?> BIF
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Activité d'aujourd'hui -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Activité d'Aujourd'hui 
                            <span class="badge bg-info"><?= date('d/m/Y') ?></span>
                        </h5>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h4 class="text-primary"><?= $todayStats['total_activites'] ?></h4>
                                    <small class="text-muted">Actions totales</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?= $todayStats['utilisateurs_actifs'] ?></h4>
                                <small class="text-muted">Utilisateurs actifs</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="text-warning"><?= $todayStats['ventes_aujourd_hui'] ?></h5>
                                <small class="text-muted">Ventes</small>
                            </div>
                            <div class="col-4">
                                <div class="border-start border-end">
                                    <h5 class="text-info"><?= $todayStats['mouvements_stock'] ?></h5>
                                    <small class="text-muted">Mouvements stock</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h5 class="text-success"><?= $todayStats['paiements_salaires'] ?? 0 ?></h5>
                                <small class="text-muted">Paiements salaires</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour les détails d'un employé -->
<div class="modal fade" id="staffDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de l'employé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffDetailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Données pour les graphiques
    const staffData = <?= json_encode($staffStats) ?>;
    
    // Graphique des revenus
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: staffData.map(staff => staff.username),
            datasets: [{
                label: 'Chiffre d\'affaires (BIF)',
                data: staffData.map(staff => staff.chiffre_affaire || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fr-FR').format(value) + ' BIF';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + 
                                   new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' BIF';
                        }
                    }
                }
            }
        }
    });
    
    // Graphique des performances (donut)
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    const performanceData = staffData.map(staff => {
        const perf = calculatePerformanceJS(staff);
        if (perf >= 80) return 'Excellent';
        if (perf >= 60) return 'Bon';
        if (perf >= 40) return 'Moyen';
        return 'À améliorer';
    });
    
    const performanceCounts = performanceData.reduce((acc, level) => {
        acc[level] = (acc[level] || 0) + 1;
        return acc;
    }, {});
    
    new Chart(performanceCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(performanceCounts),
            datasets: [{
                data: Object.values(performanceCounts),
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',   // Excellent - vert
                    'rgba(255, 193, 7, 0.8)',   // Bon - jaune
                    'rgba(23, 162, 184, 0.8)',  // Moyen - bleu
                    'rgba(220, 53, 69, 0.8)'    // À améliorer - rouge
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Graphique d'activité comparative
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'radar',
        data: {
            labels: ['Ventes', 'Approvisionnements', 'Jours Actifs', 'Performance'],
            datasets: staffData.slice(0, 4).map((staff, index) => {
                const colors = [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)'
                ];
                return {
                    label: staff.username,
                    data: [
                        Math.min(staff.nb_ventes / 5 * 100, 100),
                        Math.min(staff.nb_approvisionnements / 3 * 100, 100),
                        Math.min((staff.jours_actifs_vente + staff.jours_actifs_stock) / 20 * 100, 100),
                        calculatePerformanceJS(staff)
                    ],
                    backgroundColor: colors[index],
                    borderColor: colors[index].replace('0.6', '1'),
                    borderWidth: 2
                };
            })
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // Fonction pour calculer la performance côté client
    function calculatePerformanceJS(staff) {
        let score = 0;
        
        if (staff.role === 'receptionniste') {
            const ventesScore = Math.min((staff.nb_ventes / 10) * 30, 30);
            const caScore = Math.min((staff.chiffre_affaire / 500000) * 40, 40);
            const regulariteScore = Math.min((staff.jours_actifs_vente / 20) * 20, 20);
            const creditScore = staff.nb_ventes > 0 ? Math.max(0, 10 - ((staff.ventes_credit / staff.nb_ventes) * 10)) : 10;
            
            score = ventesScore + caScore + regulariteScore + creditScore;
        } else {
            const approScore = Math.min((staff.nb_approvisionnements / 5) * 40, 40);
            const valeurScore = Math.min((staff.valeur_approvisionnements / 1000000) * 30, 30);
            const ventesScore = Math.min((staff.nb_ventes / 5) * 20, 20);
            const regulariteScore = Math.min(((staff.jours_actifs_stock + staff.jours_actifs_vente) / 15) * 10, 10);
            
            score = approScore + valeurScore + ventesScore + regulariteScore;
        }
        
        return Math.min(Math.round(score * 10) / 10, 100);
    }
});

// Fonction pour afficher les détails d'un employé
function showStaffDetails(staffId, staffName) {
    $('#staffDetailsContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
    $('#staffDetailsModal').modal('show');
    
    // Simuler le chargement des détails (à implémenter avec AJAX)
    setTimeout(() => {
        const staff = <?= json_encode($staffStats) ?>.find(s => s.id == staffId);
        if (staff) {
            const performance = calculatePerformanceJS(staff);
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informations Générales</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Nom:</strong></td><td>${staff.username}</td></tr>
                            <tr><td><strong>Rôle:</strong></td><td>${staff.role}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${staff.email || 'N/A'}</td></tr>
                            <tr><td><strong>Téléphone:</strong></td><td>${staff.telephone || 'N/A'}</td></tr>
                            <tr><td><strong>Membre depuis:</strong></td><td>${new Date(staff.date_creation).toLocaleDateString('fr-FR')}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Statistiques de Performance</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Nombre de ventes:</strong></td><td>${staff.nb_ventes}</td></tr>
                            <tr><td><strong>Chiffre d'affaires:</strong></td><td>${new Intl.NumberFormat('fr-FR').format(staff.chiffre_affaire)} BIF</td></tr>
                            <tr><td><strong>Ventes à crédit:</strong></td><td>${staff.ventes_credit}</td></tr>
                            <tr><td><strong>Approvisionnements:</strong></td><td>${staff.nb_approvisionnements}</td></tr>
                            <tr><td><strong>Performance globale:</strong></td><td><span class="badge bg-${performance >= 80 ? 'success' : performance >= 60 ? 'warning' : 'danger'}">${performance}%</span></td></tr>
                        </table>
                    </div>
                </div>
            `;
            $('#staffDetailsContent').html(content);
        }
    }, 500);
}

// Fonction helper pour calculer la performance (dupliquée pour JS)
function calculatePerformanceJS(staff) {
    let score = 0;
    
    if (staff.role === 'receptionniste') {
        const ventesScore = Math.min((staff.nb_ventes / 10) * 30, 30);
        const caScore = Math.min((staff.chiffre_affaire / 500000) * 40, 40);
        const regulariteScore = Math.min((staff.jours_actifs_vente / 20) * 20, 20);
        const creditScore = staff.nb_ventes > 0 ? Math.max(0, 10 - ((staff.ventes_credit / staff.nb_ventes) * 10)) : 10;
        
        score = ventesScore + caScore + regulariteScore + regulariteScore;
    } else {
        const approScore = Math.min((staff.nb_approvisionnements / 5) * 40, 40);
        const valeurScore = Math.min((staff.valeur_approvisionnements / 1000000) * 30, 30);
        const ventesScore = Math.min((staff.nb_ventes / 5) * 20, 20);
        const regulariteScore = Math.min(((staff.jours_actifs_stock + staff.jours_actifs_vente) / 15) * 10, 10);
        
        score = approScore + valeurScore + ventesScore + regulariteScore;
    }
    
    return Math.min(Math.round(score * 10) / 10, 100);
}
</script>

<?php require_once __DIR__.'/../../../src/views/layouts/footer.php'; ?>