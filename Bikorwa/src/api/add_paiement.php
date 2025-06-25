<?php
/**
 * API endpoint to add a payment for a dette
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
    !isset($_POST['montant']) || empty($_POST['montant']) ||
    !isset($_POST['methode_paiement']) || empty($_POST['methode_paiement']) ||
    !isset($_POST['utilisateur_id']) || empty($_POST['utilisateur_id'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'Tous les champs obligatoires doivent être remplis'
    ]);
    exit;
}

// Get and sanitize data
$dette_id = (int) $_POST['dette_id'];
$montant = (float) $_POST['montant'];
$date_paiement = !empty($_POST['date_paiement']) ? $_POST['date_paiement'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');
$utilisateur_id = (int) $_POST['utilisateur_id'];
$methode_paiement = trim($_POST['methode_paiement']);
$reference = trim($_POST['reference'] ?? '');
$note = trim($_POST['note'] ?? '');

// Validate payment amount
if ($montant <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Le montant du paiement doit être supérieur à zéro'
    ]);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get current dette info
    $sql_dette = "SELECT montant_initial, montant_restant, statut FROM dettes WHERE id = ?";
    $stmt_dette = $pdo->prepare($sql_dette);
    $stmt_dette->execute([$dette_id]);
    $dette = $stmt_dette->fetch(PDO::FETCH_ASSOC);
    
    if (!$dette) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Dette non trouvée'
        ]);
        exit;
    }
    
    // Check if dette is annulee
    if ($dette['statut'] == 'annulee') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Impossible d\'ajouter un paiement à une dette annulée'
        ]);
        exit;
    }
    
    // Check if payment is greater than remaining amount
    if ($montant > $dette['montant_restant']) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Le montant du paiement ne peut pas être supérieur au montant restant'
        ]);
        exit;
    }
    
    // Calculate new remaining amount
    $new_montant_restant = $dette['montant_restant'] - $montant;
    
    // Determine new status
    $new_statut = 'active';
    if ($new_montant_restant == 0) {
        $new_statut = 'payee';
    } elseif ($new_montant_restant < $dette['montant_initial']) {
        $new_statut = 'partiellement_payee';
    }
    
    // Add payment
    $sql_paiement = "INSERT INTO paiements_dettes (dette_id, montant, date_paiement, utilisateur_id, methode_paiement, reference, note)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_paiement = $pdo->prepare($sql_paiement);
    $stmt_paiement->execute([
        $dette_id, $montant, $date_paiement, $utilisateur_id, $methode_paiement, $reference, $note
    ]);
    
    $paiement_id = $pdo->lastInsertId();
    
    // Update dette with new remaining amount and status
    $sql_update = "UPDATE dettes SET montant_restant = ?, statut = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$new_montant_restant, $new_statut, $dette_id]);
    
    // Log activity
    $action = "Ajout d'un paiement de dette";
    $details = "Montant: $montant BIF, Méthode: $methode_paiement, Dette ID: $dette_id";
    
    $sql_log = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, date_action, details)
                VALUES (?, ?, ?, ?, NOW(), ?)";
    
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->execute([$utilisateur_id, $action, 'paiements_dettes', $paiement_id, $details]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Paiement enregistré avec succès',
        'payment_id' => $paiement_id,
        'new_status' => $new_statut
    ]);
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error and return error response
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: impossible d\'enregistrer le paiement'
    ]);
}
