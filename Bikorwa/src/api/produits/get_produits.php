<?php
// API endpoint to get products with stock information

// Include necessary files using relative paths (consistent with other pages)

require_once './../../config/config.php';
require_once './../../config/database.php';
require_once './../../utils/Auth.php';

// Set header to JSON
header('Content-Type: application/json');

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is authenticated
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action'
    ]);
    exit;
}

try {
    // Get the page and search parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? addslashes($_GET['search']) : '';
    $with_stock = isset($_GET['with_stock']) && $_GET['with_stock'] === 'true';
    
    // Base query for products - Use INNER JOIN with stock for with_stock=true to ensure results have stock
    $baseQuery = "FROM produits p";
    
    // Use INNER JOIN when filtering for products with stock, otherwise LEFT JOIN
    if ($with_stock) {
        $baseQuery .= " INNER JOIN stock s ON p.id = s.produit_id AND s.quantite > 0";
    } else {
        $baseQuery .= " LEFT JOIN stock s ON p.id = s.produit_id";
    }
    
    $baseQuery .= " LEFT JOIN (
                      SELECT produit_id, prix_achat, prix_vente
                      FROM prix_produits
                      WHERE date_fin IS NULL OR date_fin = (
                          SELECT MAX(date_fin)
                          FROM prix_produits pp2
                          WHERE pp2.produit_id = prix_produits.produit_id
                      )
                  ) pp ON p.id = pp.produit_id
                  WHERE p.actif = 1";
    
    // Add search condition if provided
    if (!empty($search)) {
        $baseQuery .= " AND (p.nom LIKE '%$search%' OR p.code LIKE '%$search%' OR p.description LIKE '%$search%')";
    }
    
    // Add stock condition if requested
    if ($with_stock) {
        $baseQuery .= " AND (s.quantite > 0)";
    }
    
    // Count total products
    $countQuery = "SELECT COUNT(*) as total $baseQuery";
    $countStmt = $conn->query($countQuery);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = $countResult['total'];
    
    // Fetch products with pagination
    $query = "SELECT 
                p.id,
                p.code,
                p.nom,
                p.description,
                p.unite_mesure,
                COALESCE(s.quantite, 0) as quantite_stock,
                COALESCE(pp.prix_achat, 0) as prix_achat,
                COALESCE(pp.prix_vente, 0) as prix_vente,
                CASE WHEN s.quantite > 0 THEN true ELSE false END as has_stock
              $baseQuery
              ORDER BY p.nom";
    $query .= " LIMIT $limit OFFSET $offset";
    $stmt = $conn->query($query);
    
    $produits = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get available stock with FIFO batches
        if ($with_stock && $row['quantite_stock'] > 0) {
            // Get the FIFO batches for this product
            $fifoQuery = "SELECT
                            ms.produit_id,
                            ms.quantity_remaining,
                            ms.prix_unitaire,
                            ms.prix_vente,
                            ms.date_mouvement
                           FROM
                            mouvements_stock ms
                           WHERE 
                            ms.produit_id = :id 
                            AND ms.type_mouvement = 'entree'
                            AND ms.quantity_remaining > 0 
                           ORDER BY 
                            ms.date_mouvement ASC";
            
            $stmtFifo = $conn->prepare($fifoQuery);
            $stmtFifo->bindParam(":id", $row['id'], PDO::PARAM_INT);
            $stmtFifo->execute();
            
            $batches = [];
            while ($batch = $stmtFifo->fetch(PDO::FETCH_ASSOC)) {
                $batches[] = [
                    'quantity'   => $batch['quantity_remaining'],
                    'prix_achat' => $batch['prix_unitaire'],
                    'prix_vente' => $batch['prix_vente']
                ];
            }
            
            $row['batches'] = $batches;
        }
        
        $produits[] = $row;
    }
    
    // Return the response
    echo json_encode([
        'success' => true,
        'produits' => $produits,
        'total_count' => $totalCount,
        'page' => $page,
        'limit' => $limit
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
