<?php
// Test the get_produits API with authentication
session_start();

// Set up session for testing
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'gestionnaire';
$_SESSION['user_id'] = 1;

echo "<h2>API Authentication Test</h2>";
echo "<p>Session setup:</p>";
echo "<ul>";
echo "<li>logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "</li>";
echo "<li>role: " . ($_SESSION['role'] ?? 'not set') . "</li>";
echo "<li>user_id: " . ($_SESSION['user_id'] ?? 'not set') . "</li>";
echo "</ul>";

// Test the API
echo "<h3>API Response:</h3>";

// Capture output
ob_start();

// Set GET parameters
$_GET['with_stock'] = 'true';
$_GET['page'] = '1';

// Include the API file
try {
    include './src/api/produits/get_produits.php';
} catch (Exception $e) {
    echo "<p style='color: red;'>Error including API: " . $e->getMessage() . "</p>";
}

$output = ob_get_clean();

echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($output);
echo "</pre>";

// Also test with direct database query
echo "<h3>Direct Database Test:</h3>";
require_once './src/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $query = "SELECT p.id, p.nom, p.code, COALESCE(s.quantite, 0) as quantite_stock
              FROM produits p 
              INNER JOIN stock s ON p.id = s.produit_id AND s.quantite > 0
              WHERE p.actif = 1 
              LIMIT 5";
    $stmt = $conn->query($query);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($products) . " products with stock</p>";
    if (count($products) > 0) {
        echo "<pre>";
        print_r($products);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>
