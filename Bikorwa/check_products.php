<?php
// Check if there are products in the database
require_once './src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Database Connection Test</h2>";
    if ($conn) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
        exit;
    }
    
    // Check products table
    echo "<h3>Products Table</h3>";
    $query = "SELECT COUNT(*) as total FROM produits WHERE actif = 1";
    $stmt = $conn->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total active products: " . $result['total'] . "</p>";
    
    if ($result['total'] > 0) {
        echo "<h4>Sample Products:</h4>";
        $query = "SELECT p.id, p.nom, p.code, COALESCE(s.quantite, 0) as stock 
                  FROM produits p 
                  LEFT JOIN stock s ON p.id = s.produit_id 
                  WHERE p.actif = 1 
                  LIMIT 5";
        $stmt = $conn->query($query);
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Code</th><th>Stock</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['nom'] . "</td>";
            echo "<td>" . $row['code'] . "</td>";
            echo "<td>" . $row['stock'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check stock table
    echo "<h3>Stock Table</h3>";
    $query = "SELECT COUNT(*) as total FROM stock WHERE quantite > 0";
    $stmt = $conn->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Products with stock > 0: " . $result['total'] . "</p>";
    
    // Test the API query directly
    echo "<h3>API Query Test</h3>";
    $baseQuery = "FROM produits p INNER JOIN stock s ON p.id = s.produit_id AND s.quantite > 0
                  LEFT JOIN (
                      SELECT produit_id, prix_achat, prix_vente
                      FROM prix_produits
                      WHERE date_fin IS NULL OR date_fin = (
                          SELECT MAX(date_fin)
                          FROM prix_produits pp2
                          WHERE pp2.produit_id = prix_produits.produit_id
                      )
                  ) pp ON p.id = pp.produit_id
                  WHERE p.actif = 1 AND (s.quantite > 0)";
    
    $countQuery = "SELECT COUNT(*) as total $baseQuery";
    $stmt = $conn->query($countQuery);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Products matching API criteria: " . $result['total'] . "</p>";
    
    if ($result['total'] > 0) {
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
                  ORDER BY p.nom
                  LIMIT 3";
        $stmt = $conn->query($query);
        echo "<h4>Sample API Results:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Code</th><th>Stock</th><th>Prix Vente</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['nom'] . "</td>";
            echo "<td>" . $row['code'] . "</td>";
            echo "<td>" . $row['quantite_stock'] . "</td>";
            echo "<td>" . $row['prix_vente'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
