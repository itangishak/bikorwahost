<?php
/**
 * API endpoint to cancel a dette
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

// Check if user has permission to cancel dettes (gestionnaire only)
if ($_SESSION['user_role'] !== 'gestionnaire') {
    echo json_encode([
        'success' => false,
        'message' => 'Vous n\'avez pas les droits nécessaires pour annuler une dette'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Validate required fields
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de dette requis'
    ]);
    exit;
}

$dette_id = (int) $_POST['id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if dette exists and can be canceled
    $sql_check = "SELECT statut, client_id, vente_id FROM dettes WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$dette_id]);
    $dette = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Dette non trouvée'
        ]);
        exit;
    }
    
    if ($dette['statut'] === 'annulee') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Cette dette est déjà annulée'
        ]);
        exit;
    }
    
    if ($dette['statut'] === 'payee') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible d\'annuler une dette déjà payée'
        ]);
        exit;
    }
    
    // Update dette to canceled status
    $sql_update = "UPDATE dettes SET statut = 'annulee' WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$dette_id]);
    
    // Log activity
    $action = "Annulation d'une dette";
    $details = "Dette ID: $dette_id, Client ID: {$dette['client_id']}";
    
    $sql_log = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details)
                VALUES (?, ?, ?, ?, NOW(), ?)";
    
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->execute([$_SESSION['user_id'], $action, 'dettes', $dette_id, $details]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dette annulée avec succès'
    ]);
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible d\'annuler la dette'
    ]);
}
