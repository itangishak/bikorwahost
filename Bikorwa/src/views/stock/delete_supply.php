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

// Delete supply entry
$query = "DELETE FROM mouvements_stock WHERE id = ?";
$stmt = $conn->prepare($query);
$success = $stmt->execute([$id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur de suppression']);
}
