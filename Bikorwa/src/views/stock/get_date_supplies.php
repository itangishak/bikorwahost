<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check permissions
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gestionnaire') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../../../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if date parameter is provided
    if (!isset($_POST['date']) || empty($_POST['date'])) {
        echo json_encode(['success' => false, 'message' => 'Date non fournie']);
        exit;
    }

    $date = $_POST['date'];
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Format de date invalide']);
        exit;
    }

    // Fetch all supplies for the specified date
    $sql = "SELECT 
        m.id, 
        p.nom AS produit_nom, 
        m.quantite, 
        m.prix_unitaire, 
        m.valeur_totale, 
        TIME(m.date_mouvement) AS heure,
        u.nom AS utilisateur_nom
    FROM mouvements_stock m
    JOIN produits p ON m.produit_id = p.id
    JOIN users u ON m.utilisateur_id = u.id
    WHERE m.type_mouvement = 'entree'
    AND DATE(m.date_mouvement) = ?
    ORDER BY m.date_mouvement ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date]);
    $supplies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics for the date
    $summary_sql = "SELECT 
        COUNT(*) as total_count,
        SUM(valeur_totale) as total_value,
        AVG(valeur_totale) as avg_value,
        COUNT(DISTINCT produit_id) as product_count
    FROM mouvements_stock 
    WHERE type_mouvement = 'entree'
    AND DATE(date_mouvement) = ?";

    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([$date]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    // Format the supplies data
    $formatted_supplies = [];
    foreach ($supplies as $supply) {
        $formatted_supplies[] = [
            'id' => $supply['id'],
            'produit_nom' => $supply['produit_nom'],
            'quantite' => $supply['quantite'],
            'prix_unitaire' => floatval($supply['prix_unitaire']),
            'valeur_totale' => floatval($supply['valeur_totale']),
            'heure' => $supply['heure'],
            'utilisateur_nom' => $supply['utilisateur_nom']
        ];
    }

    // Format summary data
    $formatted_summary = [
        'total_count' => intval($summary['total_count']),
        'total_value' => floatval($summary['total_value'] ?? 0),
        'avg_value' => floatval($summary['avg_value'] ?? 0),
        'product_count' => intval($summary['product_count'])
    ];

    // Return success response
    echo json_encode([
        'success' => true,
        'supplies' => $formatted_supplies,
        'summary' => $formatted_summary,
        'date' => $date
    ]);

} catch (PDOException $e) {
    // Database error
    error_log("Database error in get_date_supplies.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de base de données'
    ]);
} catch (Exception $e) {
    // General error
    error_log("Error in get_date_supplies.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Une erreur s\'est produite'
    ]);
}
?>
