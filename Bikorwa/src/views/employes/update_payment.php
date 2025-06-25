<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../../src/config/database.php';

$response = ['success' => false, 'message' => ''];
$logFile = __DIR__.'/../../../payment_updates.log';

try {
    session_start();
    
    // Validation
    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    $requiredFields = ['id', 'employe_id', 'montant', 'date_paiement'];
    foreach($requiredFields as $field) {
        if(empty($_POST[$field])) {
            throw new Exception('Champ manquant: '.$field);
        }
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Vérification permission
    if(!isset($_SESSION['user_id'])) {
        throw new Exception('Non connecté');
    }
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if($stmt->fetchColumn() !== 'gestionnaire') {
        throw new Exception('Permissions insuffisantes');
    }
    
    // Préparation données
    $paymentData = [
        'id' => (int)$_POST['id'],
        'employe_id' => (int)$_POST['employe_id'],
        'montant' => (float)$_POST['montant'],
        'periode_debut' => $_POST['periode_debut'] ?? date('Y-m-d'),
        'periode_fin' => $_POST['periode_fin'] ?? date('Y-m-d'),
        'date_paiement' => $_POST['date_paiement'],
        'utilisateur_id' => (int)$_SESSION['user_id']
    ];
    
    // Mise à jour
    $stmt = $conn->prepare("UPDATE salaires SET 
        employe_id = :employe_id,
        montant = :montant,
        periode_debut = :periode_debut,
        periode_fin = :periode_fin,
        date_paiement = :date_paiement,
        utilisateur_id = :utilisateur_id
        WHERE id = :id");
    
    if($stmt->execute($paymentData)) {
        // Log
        $logMessage = sprintf(
            "[%s] MISE À JOUR - ID: %d | Employé: %d | Nouveau montant: %.2f | Par user_id: %d\n",
            date('Y-m-d H:i:s'),
            $paymentData['id'],
            $paymentData['employe_id'],
            $paymentData['montant'],
            $_SESSION['user_id']
        );
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $response = ['success' => true, 'message' => 'Paiement mis à jour'];
    } else {
        throw new Exception('Échec de la mise à jour');
    }
} catch(Exception $e) {
    $response['message'] = $e->getMessage();
    file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] ERREUR: ".$e->getMessage()."\n", FILE_APPEND);
}

echo json_encode($response);
?>
