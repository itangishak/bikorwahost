<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../../src/config/database.php';

$response = ['success' => false, 'message' => ''];
$logFile = __DIR__.'/../../../payment_deletions.log';

try {
    // Initialisation connexion DB
    $database = new Database();
    $conn = $database->getConnection();
    
    // Log des tentatives
    file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Tentative de suppression par user_id: ".($_SESSION['user_id'] ?? 'inconnu')."\n", FILE_APPEND);

    // Vérification session
    session_start();
    if(!isset($_SESSION['user_id'])) {
        throw new Exception('Non connecté');
    }
    
    // Vérification rôle directement dans users
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userRole = $stmt->fetchColumn();
    
    if($userRole !== 'gestionnaire') {
        throw new Exception('Permissions insuffisantes');
    }

    // Validation de l'ID
    if(!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('ID invalide: '.($_POST['id'] ?? 'null'));
    }

    $paymentId = (int)$_POST['id'];
    
    // Récupération avant suppression (pour log)
    $stmt = $conn->prepare("SELECT * FROM salaires WHERE id = ?");
    $stmt->execute([$paymentId]);
    $paymentData = $stmt->fetch();
    
    if(!$paymentData) {
        throw new Exception('Paiement introuvable');
    }

    // Suppression
    $stmt = $conn->prepare("DELETE FROM salaires WHERE id = ?");
    $stmt->execute([$paymentId]);
    
    if($stmt->rowCount() > 0) {
        // Log de succès
        $logMessage = sprintf(
            "[%s] SUPPRESSION - ID: %d | Employé: %d | Montant: %.2f | Par user_id: %d\n",
            date('Y-m-d H:i:s'),
            $paymentId,
            $paymentData['employe_id'],
            $paymentData['montant'],
            $_SESSION['user_id']
        );
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        $response = ['success' => true, 'message' => 'Paiement #'.$paymentId.' supprimé avec succès'];
    } else {
        throw new Exception('Échec de suppression');
    }
} catch(Exception $e) {
    $errorMsg = "[".date('Y-m-d H:i:s')."] ERREUR: ".$e->getMessage()."\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
    $response['message'] = 'Erreur: '.$e->getMessage();
}

echo json_encode($response);
?>
