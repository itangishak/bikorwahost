<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../../src/config/database.php';

$response = ['success' => false, 'data' => null];

try {
    session_start();
    
    // Vérification permission
    if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID invalide');
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Vérification rôle gestionnaire
    if(!isset($_SESSION['user_id'])) {
        throw new Exception('Non connecté');
    }
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if($stmt->fetchColumn() !== 'gestionnaire') {
        throw new Exception('Permissions insuffisantes');
    }
    
    // Récupération données paiement
    $stmt = $conn->prepare("SELECT 
        s.id, s.employe_id, s.montant, 
        DATE_FORMAT(s.periode_debut, '%Y-%m-%d') as periode_debut,
        DATE_FORMAT(s.periode_fin, '%Y-%m-%d') as periode_fin,
        DATE_FORMAT(s.date_paiement, '%Y-%m-%d') as date_paiement,
        e.nom as employe_nom
        FROM salaires s
        JOIN employes e ON s.employe_id = e.id
        WHERE s.id = ?");
    $stmt->execute([$_GET['id']]);
    
    $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($paymentData) {
        $response = ['success' => true, 'data' => $paymentData];
    } else {
        throw new Exception('Paiement non trouvé');
    }
} catch(Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
