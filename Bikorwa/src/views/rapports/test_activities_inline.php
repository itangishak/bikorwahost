<?php
// Test inline des activités pour le rapport personnel
require_once __DIR__.'/../../../src/config/database.php';

$database = new Database();
$pdo = $database->getConnection();

// Même période que le rapport
$date_debut = '2025-06-01';
$date_fin = '2025-06-12';

echo "<h2>Test Inline des Activités</h2>";
echo "<p>Période: $date_debut à $date_fin</p>";

// Test de la fonction exacte du rapport
function getRecentActivities($pdo, $date_debut, $date_fin, $limit = 15) {
    try {
        // Requête simplifiée sans LIMIT dans les sous-requêtes UNION
        $query = "
        SELECT 
            type_activite,
            date_action,
            titre,
            montant,
            username,
            role,
            ref_id
        FROM (
            SELECT 
                'vente' as type_activite,
                v.date_vente as date_action,
                CONCAT('Vente #', v.numero_facture) as titre,
                CONCAT(FORMAT(v.montant_paye, 0), ' BIF') as montant,
                u.username,
                u.role,
                v.id as ref_id
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
                    p.nom
                ) as titre,
                CONCAT(FORMAT(m.quantite, 0), ' ', p.unite_mesure) as montant,
                u.username,
                u.role,
                m.id as ref_id
            FROM mouvements_stock m
            LEFT JOIN users u ON m.utilisateur_id = u.id
            LEFT JOIN produits p ON m.produit_id = p.id
            WHERE m.date_mouvement BETWEEN ? AND ?
        ) combined_activities
        ORDER BY date_action DESC
        LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $date_debut . ' 00:00:00',
            $date_fin . ' 23:59:59',
            $date_debut . ' 00:00:00',
            $date_fin . ' 23:59:59',
            $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
        return [];
    }
}

$activities = getRecentActivities($pdo, $date_debut, $date_fin);

echo "<h3>Résultats: " . count($activities) . " activités trouvées</h3>";

if (!empty($activities)) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Type</th><th>Date/Heure</th><th>Titre</th><th>Montant</th><th>Utilisateur</th></tr>";
    
    foreach ($activities as $activity) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($activity['type_activite']) . "</td>";
        echo "<td>" . htmlspecialchars($activity['date_action']) . "</td>";
        echo "<td>" . htmlspecialchars($activity['titre']) . "</td>";
        echo "<td>" . htmlspecialchars($activity['montant']) . "</td>";
        echo "<td>" . htmlspecialchars($activity['username'] ?? 'N/A') . " (" . htmlspecialchars($activity['role'] ?? 'N/A') . ")</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: orange;'>Aucune activité trouvée!</p>";
    
    // Test simple des ventes
    echo "<h4>Test ventes uniquement:</h4>";
    $ventesQuery = "SELECT COUNT(*) as count FROM ventes WHERE date_vente BETWEEN ? AND ?";
    $stmt = $pdo->prepare($ventesQuery);
    $stmt->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
    $ventesCount = $stmt->fetch()['count'];
    echo "<p>Nombre de ventes: $ventesCount</p>";
    
    // Test simple des mouvements de stock
    echo "<h4>Test mouvements de stock uniquement:</h4>";
    $stockQuery = "SELECT COUNT(*) as count FROM mouvements_stock WHERE date_mouvement BETWEEN ? AND ?";
    $stmt = $pdo->prepare($stockQuery);
    $stmt->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
    $stockCount = $stmt->fetch()['count'];
    echo "<p>Nombre de mouvements de stock: $stockCount</p>";
}
?>