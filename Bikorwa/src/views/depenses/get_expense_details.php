<?php
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

// Get ID from URL parameter
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    // Prepare SQL query - Updated to use categories_depenses table
    $query = "SELECT 
                d.*, 
                cd.nom as categorie_nom, 
                u.nom as user_nom 
              FROM depenses d 
              LEFT JOIN categories_depenses cd ON d.categorie_id = cd.id 
              LEFT JOIN users u ON d.utilisateur_id = u.id 
              WHERE d.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($expense) {
        // Format date for display
        $expense['date_depense'] = date('d/m/Y', strtotime($expense['date_depense']));
        
        echo json_encode([
            'success' => true,
            'expense' => $expense
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dépense non trouvée']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
