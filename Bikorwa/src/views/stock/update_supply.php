<?php
require_once __DIR__ . '/../../includes/config.php';

// Check permissions
if ($_SESSION['role'] !== 'gestionnaire') {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$conn = require __DIR__ . '/../../includes/db.php';

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

$stmt = $conn->prepare($query);
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
