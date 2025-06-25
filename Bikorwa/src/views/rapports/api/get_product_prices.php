<?php
try {
    // Correction du chemin (niveau 3 au lieu de 2)
    $appRoot = dirname(__DIR__, 3);
    
    // Charger la configuration
    require $appRoot . '/config/config.php';
    require $appRoot . '/config/database.php';
    
    // Configurer les headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        exit;
    }
    
    // Récupérer le terme de recherche
    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
    
    // Connexion à la base de données
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Requête SQL sécurisée
    $query = "SELECT 
        p.id, 
        p.nom as produit,
        m.prix_unitaire,
        SUM(m.quantite) as quantite_totale,
        MIN(m.date_mouvement) as premiere_date,
        MAX(m.date_mouvement) as derniere_date,
        COUNT(*) as nb_achats
    FROM mouvements_stock m
    JOIN produits p ON m.produit_id = p.id
    WHERE m.type_mouvement = 'entree'
    AND p.nom LIKE :search
    GROUP BY p.id, p.nom, m.prix_unitaire
    ORDER BY p.nom, m.prix_unitaire";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->execute();
    
    // Préparer les résultats
    $results = [];
    $currentProduct = null;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['produit'] !== $currentProduct) {
            if ($currentProduct !== null) {
                $results[] = ['type' => 'end-product'];
            }
            $results[] = [
                'type' => 'start-product',
                'product' => htmlspecialchars($row['produit'], ENT_QUOTES, 'UTF-8')
            ];
            $currentProduct = $row['produit'];
        }
        
        $results[] = [
            'type' => 'price',
            'product' => htmlspecialchars($row['produit'], ENT_QUOTES, 'UTF-8'),
            'price' => number_format($row['prix_unitaire'], 0, ',', ' '),
            'quantity' => number_format($row['quantite_totale'], 2, ',', ' '),
            'period' => formatPeriod($row['premiere_date'], $row['derniere_date']),
            'purchases' => $row['nb_achats'],
            'total' => number_format($row['quantite_totale'] * $row['prix_unitaire'], 0, ',', ' ')
        ];
    }
    
    if ($currentProduct !== null) {
        $results[] = ['type' => 'end-product'];
    }
    
    // Envoyer la réponse
    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur',
        'details' => $e->getMessage()
    ]);
}

function formatPeriod($start, $end) {
    $startDate = (new DateTime($start))->format('d/m/Y');
    if ($start === $end) {
        return $startDate;
    }
    return $startDate . ' - ' . (new DateTime($end))->format('d/m/Y');
}
?>
