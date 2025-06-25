<?php
/**
 * BIKORWA SHOP - Add Expense Category AJAX Handler
 * This file processes the AJAX request to add a new expense category.
 */

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Une erreur est survenue.'
];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée.');
    }

    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Initialize auth
    $auth = new Auth($conn);

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        throw new Exception('Accès non autorisé. Veuillez vous connecter.');
    }
    
    // Allow access to both gestionnaires and users with depenses permission
    $userRole = $_SESSION['user_role'] ?? '';
    if (!$auth->hasAccess('depenses') && $userRole !== 'gestionnaire') {
        throw new Exception('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.');
    }

    // Get current user ID for logging actions
    $current_user_id = $_SESSION['user_id'] ?? 0;

    // Validate required fields
    if (empty($_POST['nom'])) {
        throw new Exception("Le nom de la catégorie est obligatoire.");
    }

    $nom = trim($_POST['nom']);
    $description = !empty($_POST['description']) ? trim($_POST['description']) : null;

    // Check if category with same name already exists
    $check_stmt = $conn->prepare("SELECT id FROM categories_depenses WHERE nom = ?");
    $check_stmt->execute([$nom]);
    if ($check_stmt->rowCount() > 0) {
        throw new Exception("Une catégorie avec ce nom existe déjà.");
    }

    // Insert new category into database
    $query = "INSERT INTO categories_depenses (nom, description) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([$nom, $description]);

    if (!$result) {
        throw new Exception("Échec de l'ajout de la catégorie dans la base de données.");
    }

    $category_id = $conn->lastInsertId();

    // Log the action in journal_activites
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details) 
                  VALUES (?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_details = "Ajout d'une nouvelle catégorie de dépense: $nom";
    $log_stmt->execute([$current_user_id, 'ajout', 'categories_depenses', $category_id, $log_details]);

    // Set success response
    $response['success'] = true;
    $response['message'] = "La catégorie a été ajoutée avec succès.";
    $response['category'] = [
        'id' => $category_id,
        'nom' => $nom,
        'description' => $description
    ];

} catch (Exception $e) {
    // Set error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
