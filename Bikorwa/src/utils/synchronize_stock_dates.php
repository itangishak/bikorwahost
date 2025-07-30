<?php
/**
 * Script de synchronisation des dates de mouvements de stock
 * Ce script met à jour les dates des mouvements de stock pour les synchroniser avec:
 * - date_vente pour les mouvements de type "sortie" liés aux ventes
 * - date_creation du produit pour les mouvements de stock initial
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Initialiser la connexion à la base de données
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    die("Erreur de connexion à la base de données\n");
}

echo "=== Script de Synchronisation des Dates de Mouvements de Stock ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Synchroniser les mouvements de sortie avec les dates de vente
    echo "1. Synchronisation des mouvements de sortie avec les dates de vente...\n";
    
    $query = "UPDATE mouvements_stock ms 
              JOIN ventes v ON ms.reference LIKE CONCAT('Vente #', v.numero_facture)
              SET ms.date_mouvement = v.date_vente
              WHERE ms.type_mouvement = 'sortie' 
              AND ms.reference LIKE 'Vente #%'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $affected_sales = $stmt->rowCount();
    
    echo "   → {$affected_sales} mouvements de sortie synchronisés avec les dates de vente\n";
    
    // 2. Synchroniser les mouvements de stock initial avec les dates de création des produits
    echo "\n2. Synchronisation des mouvements de stock initial avec les dates de création des produits...\n";
    
    // Identifier les premiers mouvements d'entrée pour chaque produit (stock initial)
    $query = "UPDATE mouvements_stock ms1
              JOIN produits p ON ms1.produit_id = p.id
              SET ms1.date_mouvement = p.date_creation
              WHERE ms1.type_mouvement = 'entree'
              AND ms1.id = (
                  SELECT MIN(ms2.id) 
                  FROM mouvements_stock ms2 
                  WHERE ms2.produit_id = ms1.produit_id 
                  AND ms2.type_mouvement = 'entree'
              )";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $affected_initial = $stmt->rowCount();
    
    echo "   → {$affected_initial} mouvements de stock initial synchronisés avec les dates de création\n";
    
    // 3. Rapport des mouvements traités
    echo "\n3. Génération du rapport...\n";
    
    // Compter les mouvements par type
    $query = "SELECT 
                type_mouvement,
                COUNT(*) as total,
                MIN(date_mouvement) as date_min,
                MAX(date_mouvement) as date_max
              FROM mouvements_stock 
              GROUP BY type_mouvement";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   Statistiques des mouvements de stock:\n";
    foreach ($stats as $stat) {
        echo "   - {$stat['type_mouvement']}: {$stat['total']} mouvements ";
        echo "(du {$stat['date_min']} au {$stat['date_max']})\n";
    }
    
    // 4. Vérifier les mouvements non synchronisés
    echo "\n4. Vérification des mouvements non synchronisés...\n";
    
    // Mouvements de sortie sans référence de vente
    $query = "SELECT COUNT(*) as count 
              FROM mouvements_stock 
              WHERE type_mouvement = 'sortie' 
              AND (reference NOT LIKE 'Vente #%' OR reference IS NULL)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $non_sync_sorties = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($non_sync_sorties > 0) {
        echo "   ⚠️  {$non_sync_sorties} mouvements de sortie ne sont pas liés à des ventes\n";
    }
    
    // Mouvements d'entrée sans date de création
    $query = "SELECT COUNT(*) as count 
              FROM mouvements_stock ms
              LEFT JOIN produits p ON ms.produit_id = p.id
              WHERE ms.type_mouvement = 'entree' 
              AND p.date_creation IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $non_sync_entrees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($non_sync_entrees > 0) {
        echo "   ⚠️  {$non_sync_entrees} mouvements d'entrée liés à des produits sans date de création\n";
    }
    
    if ($non_sync_sorties == 0 && $non_sync_entrees == 0) {
        echo "   ✅ Tous les mouvements sont correctement synchronisés\n";
    }
    
    // Valider la transaction
    $pdo->commit();
    
    echo "\n=== Synchronisation terminée avec succès ===\n";
    echo "Total des mouvements mis à jour: " . ($affected_sales + $affected_initial) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Erreur lors de la synchronisation: " . $e->getMessage() . "\n";
    exit(1);
}

// Fonction pour afficher l'aide
function showHelp() {
    echo "Usage: php synchronize_stock_dates.php [options]\n";
    echo "\nOptions:\n";
    echo "  --help, -h    Afficher cette aide\n";
    echo "  --dry-run     Simuler les changements sans les appliquer\n";
    echo "\nDescription:\n";
    echo "Ce script synchronise les dates des mouvements de stock avec:\n";
    echo "- Les dates de vente pour les sorties de stock\n";
    echo "- Les dates de création des produits pour le stock initial\n";
}

// Traitement des arguments de ligne de commande
if (isset($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            showHelp();
            exit(0);
        }
    }
}
?>
