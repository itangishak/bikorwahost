<?php
/**
 * API endpoint to update an existing dette
 */
require_once 'D:\MyApp\app\Bikorwa\includes\db.php';
require_once 'D:\MyApp\app\Bikorwa\includes\functions.php';
require_once 'D:\MyApp\app\Bikorwa\includes\auth_check.php';

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Validate required fields
if (!isset($_POST['dette_id']) || empty($_POST['dette_id']) ||
    !isset($_POST['client_id']) || empty($_POST['client_id']) ||
    !isset($_POST['montant_initial']) || empty($_POST['montant_initial']) ||
    !isset($_POST['montant_restant']) || empty($_POST['montant_restant'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'Tous les champs obligatoires doivent être remplis'
    ]);
    exit;
}

// Get and sanitize data
$dette_id = (int) $_POST['dette_id'];
$client_id = (int) $_POST['client_id'];
$vente_id = !empty($_POST['vente_id']) ? (int) $_POST['vente_id'] : null;
$montant_initial = (float) $_POST['montant_initial'];
$montant_restant = (float) $_POST['montant_restant'];
$date_creation = !empty($_POST['date_creation']) ? $_POST['date_creation'] : date('Y-m-d H:i:s');
$date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
$note = trim($_POST['note'] ?? '');

// Validate amounts
if ($montant_initial <= 0 || $montant_restant < 0 || $montant_restant > $montant_initial) {
    echo json_encode([
        'success' => false,
        'message' => 'Les montants sont invalides'
    ]);
    exit;
}

// Determine status based on remaining amount
$statut = 'active';
if ($montant_restant == 0) {
    $statut = 'payee';
} elseif ($montant_restant < $montant_initial) {
    $statut = 'partiellement_payee';
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get the current dette to check if it's allowed to be updated
    $sql_check = "SELECT statut FROM dettes WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$dette_id]);
    $current_dette = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_dette) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Dette non trouvée'
        ]);
        exit;
    }
    
    if ($current_dette['statut'] == 'annulee' || $current_dette['statut'] == 'payee') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de modifier une dette ' . ($current_dette['statut'] == 'annulee' ? 'annulée' : 'déjà payée')
        ]);
        exit;
    }
    
    // Update dette
    $sql = "UPDATE dettes 
            SET client_id = ?, vente_id = ?, montant_initial = ?, montant_restant = ?, 
                date_creation = ?, date_echeance = ?, statut = ?, note = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $client_id, $vente_id, $montant_initial, $montant_restant, 
        $date_creation, $date_echeance, $statut, $note, $dette_id
    ]);
    
    // Log activity
    $action = "Mise à jour d'une dette";
    $details = "Montant initial: $montant_initial BIF, Montant restant: $montant_restant BIF";
    
    $sql_log = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details)
                VALUES (?, ?, ?, ?, NOW(), ?)";
    
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->execute([$_SESSION['user_id'], $action, 'dettes', $dette_id, $details]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dette mise à jour avec succès'
    ]);
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible de mettre à jour la dette'
    ]);
}
