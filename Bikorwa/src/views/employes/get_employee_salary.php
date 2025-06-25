<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../../src/config/database.php';

$response = ['success' => false, 'message' => ''];

try {
    if(!isset($_POST['employe_id'])) {
        throw new Exception('ID employé manquant');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT salaire FROM employes WHERE id = ?");
    $stmt->execute([(int)$_POST['employe_id']]);
    
    if($row = $stmt->fetch()) {
        $response = [
            'success' => true,
            'salaire' => $row['salaire']
        ];
    } else {
        throw new Exception('Employé non trouvé');
    }
} catch(Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
