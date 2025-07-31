<?php
// Start or resume session using provided PHPSESSID if available
if (isset($_POST['PHPSESSID'])) {
    session_id($_POST['PHPSESSID']);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON responses
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';

// Check permissions (allow gestionnaire and admin)
$allowedRoles = ['gestionnaire', 'admin'];
if (!isset($_SESSION['role']) || !in_array(strtolower($_SESSION['role']), $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

// Prepare data
$produit_id = $_POST['produit_id'];
$quantite = $_POST['quantite'];
$prix_unitaire = $_POST['prix_unitaire'];
$date_mouvement = $_POST['date_mouvement'];
$reference = $_POST['reference'] ?? null;
$note = $_POST['note'] ?? null;

// Update supply entry
$query = "UPDATE mouvements_stock SET
    produit_id = ?,
    quantite = ?,
    prix_unitaire = ?,
    date_mouvement = ?,
    reference = ?,
    note = ?
WHERE id = ?";

$stmt = $pdo->prepare($query);
$success = $stmt->execute([
    $produit_id,
    $quantite,
    $prix_unitaire,
    $date_mouvement,
    $reference,
    $note,
    $id
]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour']);
}
