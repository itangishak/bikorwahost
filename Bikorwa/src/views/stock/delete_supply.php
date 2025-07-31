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

// Delete supply entry
$query = "DELETE FROM mouvements_stock WHERE id = ?";
$stmt = $pdo->prepare($query);
$success = $stmt->execute([$id]);

if ($success) {
    header('Location: historique_approvisionnement.php?status=deleted');
} else {
    header('Location: historique_approvisionnement.php?error=delete');
}
