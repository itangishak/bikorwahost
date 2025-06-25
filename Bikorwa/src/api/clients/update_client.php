<?php
/**
 * BIKORWA SHOP - API for updating client information
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

// Only managers can modify clients
if (!$auth->isManager()) {
    echo json_encode([
        'success' => false,
        'message' => 'Seuls les gestionnaires peuvent modifier un client.'
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
$client_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nom = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$email = trim($_POST['email'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');
$limite_credit = isset($_POST['limite_credit']) ? (float)$_POST['limite_credit'] : 0;
$note = trim($_POST['note'] ?? '');

// Validate required fields
if (empty($client_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID client invalide.'
    ]);
    exit;
}

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
    // Check if client exists
    $check_query = "SELECT nom FROM clients WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(1, $client_id);
    $check_stmt->execute();
    
    $client = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode([
            'success' => false,
            'message' => 'Client non trouvé.'
        ]);
        exit;
    }
    
    $old_nom = $client['nom'];
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update client information
    $query = "UPDATE clients SET 
              nom = ?, 
              telephone = ?, 
              email = ?, 
              adresse = ?, 
              limite_credit = ?, 
              note = ? 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $nom);
    $stmt->bindParam(2, $telephone);
    $stmt->bindParam(3, $email);
    $stmt->bindParam(4, $adresse);
    $stmt->bindParam(5, $limite_credit);
    $stmt->bindParam(6, $note);
    $stmt->bindParam(7, $client_id);
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour du client.');
    }
    
    // Log the action
    $action = "Mise à jour du client: $old_nom (ID: $client_id)";
    $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details) 
              VALUES (?, ?, 'client', ?, NOW(), ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $current_user_id);
    $stmt->bindParam(2, $action);
    $stmt->bindParam(3, $client_id);
    $details = "Client mis à jour avec les informations: Nom: $nom, Téléphone: $telephone, Email: $email, Adresse: $adresse, Limite crédit: $limite_credit BIF";
    $stmt->bindParam(4, $details);
    
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception('Erreur lors de l\'enregistrement de l\'activité.');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Client mis à jour avec succès.'
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
