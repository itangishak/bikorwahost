<?php
// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';

// Check authentication and permissions
$allowedRoles = ['gestionnaire', 'admin'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) ||
    !in_array(strtolower($_SESSION['role']), $allowedRoles)) {
    header('Location: ' . BASE_URL . '/src/views/auth/login.php?reason=unauthorized');
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

$id = $_POST['id'] ?? null;

if (!$id) {
    header('Location: historique_approvisionnement.php?error=invalid');
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
    header('Location: historique_approvisionnement.php?status=updated');
} else {
    header('Location: historique_approvisionnement.php?error=update');
}
