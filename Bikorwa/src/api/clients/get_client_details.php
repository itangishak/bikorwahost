<?php
/**
 * BIKORWA SHOP - API for retrieving client details
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

// Validate client ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID client invalide.'
    ]);
    exit;
}

$client_id = (int)$_GET['id'];

try {
    // Get client details
    $query = "SELECT * FROM clients WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id);
    $stmt->execute();
    
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode([
            'success' => false,
            'message' => 'Client non trouvé.'
        ]);
        exit;
    }
    
    // Log the action
    $action = "Consultation des détails du client ID: $client_id";
    $query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details) 
              VALUES (?, ?, 'client', ?, NOW(), ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $current_user_id);
    $stmt->bindParam(2, $action);
    $stmt->bindParam(3, $client_id);
    $details = "Consultation des détails du client: {$client['nom']}";
    $stmt->bindParam(4, $details);
    
    $stmt->execute();
    
    // Return client details
    echo json_encode([
        'success' => true,
        'client' => $client
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
