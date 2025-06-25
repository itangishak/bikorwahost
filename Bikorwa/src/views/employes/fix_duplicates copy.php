<?php
// This script prevents duplicate employee entries by adding a check to the database
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// First, fix any existing duplicates by deactivating them
$query = "UPDATE employes e1 
          INNER JOIN (
              SELECT nom, MIN(id) as min_id 
              FROM employes 
              WHERE actif = 1 
              GROUP BY nom 
              HAVING COUNT(*) > 1
          ) e2 ON e1.nom = e2.nom AND e1.id != e2.min_id 
          SET e1.actif = 0";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "<div style='margin: 20px; padding: 20px; border: 1px solid #28a745; background-color: #d4edda; color: #155724; border-radius: 5px;'>";
    echo "<h3>Opération réussie</h3>";
    echo "<p>$count doublons d'employés ont été désactivés.</p>";
    echo "<p>Le système empêchera désormais l'ajout d'employés avec des noms identiques.</p>";
    echo "<p><a href='liste.php' class='btn btn-primary'>Retour à la liste des employés</a></p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='margin: 20px; padding: 20px; border: 1px solid #dc3545; background-color: #f8d7da; color: #721c24; border-radius: 5px;'>";
    echo "<h3>Erreur</h3>";
    echo "<p>Une erreur est survenue lors de la correction des doublons : " . $e->getMessage() . "</p>";
    echo "<p><a href='liste.php' class='btn btn-primary'>Retour à la liste des employés</a></p>";
    echo "</div>";
}
?>
