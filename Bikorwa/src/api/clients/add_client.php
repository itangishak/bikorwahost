<?php
/**
 * BIKORWA SHOP - API for adding a new client
 */
header('Content-Type: application/json');
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize authentication
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Check access permissions
if (!$auth->hasAccess('clients')) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.'
    ]);
    exit;
}

// Get current user ID for logging actions
$current_user_id = $_SESSION['user_id'] ?? 0;

// Process only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit;
}

// Get and validate input data
$nom = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$email = trim($_POST['email'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');
$limite_credit = isset($_POST['limite_credit']) ? (float)$_POST['limite_credit'] : 0;
$note = trim($_POST['note'] ?? '');

// Validate required fields
if (empty($nom)) {
    echo json_encode([
        'success' => false,
        'message' => 'Le nom du client est obligatoire.'
    ]);
    exit;
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'L\'adresse email n\'est pas valide.'
    ]);
    exit;
}

// Validate credit limit
if ($limite_credit < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'La limite de crédit ne peut pas être négative.'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Insert the new client
    $query = "INSERT INTO clients (nom, telephone, email, adresse, limite_credit, note, date_creation) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $nom);
    $stmt->bindParam(2, $telephone);
    $stmt->bindParam(3, $email);
    $stmt->bindParam(4, $adresse);
    $stmt->bindParam(5, $limite_credit);
    $stmt->bindParam(6, $note);
    
    $result = $stmt->execute();
    $client_id = $conn->lastInsertId();
    
    if (!$result) {
        throw new Exception('Erreur lors de l\'ajout du client.');
    }
    
    // Log the action
    $action = "Ajout du client: $nom";
    $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details) 
              VALUES (?, ?, 'client', ?, NOW(), ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $current_user_id);
    $stmt->bindParam(2, $action);
    $stmt->bindParam(3, $client_id);
    $details = "Client ajouté avec les informations: Téléphone: $telephone, Email: $email, Adresse: $adresse, Limite crédit: $limite_credit BIF";
    $stmt->bindParam(4, $details);
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de l\'enregistrement de l\'activité.');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Client ajouté avec succès.',
        'client_id' => $client_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
