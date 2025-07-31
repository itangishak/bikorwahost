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

// Fetch current movement data for comparison
$stmt = $pdo->prepare("SELECT produit_id, quantite, date_mouvement FROM mouvements_stock WHERE id = ?");
$stmt->execute([$id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    echo json_encode(['success' => false, 'message' => 'Approvisionnement introuvable']);
    exit;
}

// Prepare new data
$produit_id = $_POST['produit_id'] ?? $current['produit_id'];
$quantite = (float)($_POST['quantite'] ?? 0);
$prix_unitaire = (float)($_POST['prix_unitaire'] ?? 0);
$date_mouvement = $_POST['date_mouvement'] ?? $current['date_mouvement'];
$reference = $_POST['reference'] ?? null;
$note = $_POST['note'] ?? null;

try {
    $pdo->beginTransaction();

    // Update supply entry
    $query = "UPDATE mouvements_stock SET
        produit_id = ?,
        quantite = ?,
        prix_unitaire = ?,
        valeur_totale = ?,
        date_mouvement = ?,
        reference = ?,
        note = ?
    WHERE id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $produit_id,
        $quantite,
        $prix_unitaire,
        $quantite * $prix_unitaire,
        $date_mouvement,
        $reference,
        $note,
        $id
    ]);

    // Adjust stock if quantity changed
    $oldQuantite = (float)$current['quantite'];
    $diff = $quantite - $oldQuantite;
    if ($diff !== 0.0) {
        $stockQuery = "UPDATE stock SET quantite = quantite + ? WHERE produit_id = ?";
        $stockStmt = $pdo->prepare($stockQuery);
        $stockStmt->execute([$diff, $produit_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur de mise à jour']);
}
