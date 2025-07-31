<?php
// Start or resume session using provided PHPSESSID if available
if (isset($_POST['PHPSESSID'])) {
    session_id($_POST['PHPSESSID']);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return JSON response
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

// Delete supply entry
$query = "DELETE FROM mouvements_stock WHERE id = ?";
$stmt = $pdo->prepare($query);
$success = $stmt->execute([$id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur de suppression']);
}
