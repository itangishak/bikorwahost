<?php
/**
 * API endpoint to get all clients for select dropdown
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

try {
    // Get all active clients
    $sql = "SELECT id, nom FROM clients ORDER BY nom ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'clients' => $clients
    ]);
} catch (PDOException $e) {
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible de récupérer les clients'
    ]);
}
