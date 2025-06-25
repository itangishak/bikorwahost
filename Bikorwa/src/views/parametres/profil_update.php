<?php
// Ensure no output before headers
error_reporting(0);

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Non autorisé']));
}

// Include database connection
require_once(__DIR__ . '/../../config/database.php');

// Get POST data
$nom = $_POST['nom'] ?? null;
$email = $_POST['email'] ?? null;

// Validate input
if (empty($nom)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Le nom est requis']));
}

// Initialize database
$database = new Database();
$pdo = $database->getConnection();

try {
    // Update profile
    $stmt = $pdo->prepare("UPDATE users SET nom = ?, email = ? WHERE id = ?");
    $stmt->execute([$nom, $email, $_SESSION['user_id']]);
    
    echo json_encode(['status' => 'success', 'message' => 'Profil mis à jour avec succès']);
    exit;
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données']);
    exit;
}
