<?php
/**
 * Get employee details for viewing and editing
 * BIKORWA SHOP
 */

// Set headers for AJAX
header('Content-Type: application/json');

// Start session
session_start();

// Include required files
require_once './../../config/config.php';
require_once './../../config/database.php';
require_once './../../models/Employe.php';
require_once './../../utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

// Get employee ID
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID employé invalide'
    ]);
    exit;
}

try {
    // Initialize employee model
    $employe = new Employe($conn);
    $employe->id = (int)$id;
    
    // Get employee details
    if ($employe->readOne()) {
        echo json_encode([
            'success' => true,
            'employe' => [
                'id' => $employe->id,
                'nom' => $employe->nom,
                'telephone' => $employe->telephone,
                'adresse' => $employe->adresse,
                'email' => $employe->email,
                'poste' => $employe->poste,
                'date_embauche' => $employe->date_embauche,
                'salaire' => $employe->salaire,
                'actif' => $employe->actif,
                'note' => $employe->note,
                'paiements' => $employe->paiements
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employé non trouvé'
        ]);
    }
} catch (Exception $e) {
    error_log("Error getting employee: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des informations de l\'employé'
    ]);
}
?>