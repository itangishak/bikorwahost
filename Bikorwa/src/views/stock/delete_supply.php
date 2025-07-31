<?php
require_once __DIR__ . '/../../../includes/init.php';
require_once __DIR__ . '/../../../src/config/database.php';

// Only gestionnaires can perform this action
requireManager();

$database = new Database();
$pdo = $database->getConnection();

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

// Delete supply entry
$query = "DELETE FROM mouvements_stock WHERE id = ?";
$stmt = $pdo->prepare($query);
$success = $stmt->execute([$id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur de suppression']);
}
