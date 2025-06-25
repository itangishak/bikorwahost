<?php
require_once __DIR__.'/../../../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Test data - replace with actual ID from your database
    $testData = [
        'id' => 1, // CHANGE THIS TO EXISTING ID
        'date_depense' => date('Y-m-d'),
        'montant' => 1000,
        'categorie_id' => 1,
        'mode_paiement' => 'Test',
        'description' => 'Test direct update'
    ];
    
    $query = "UPDATE depenses SET 
        date_depense = :date_depense, 
        montant = :montant, 
        categorie_id = :categorie_id, 
        mode_paiement = :mode_paiement, 
        description = :description,
        updated_at = CURRENT_TIMESTAMP
        WHERE id = :id";
    
    $stmt = $conn->prepare($query);
    
    foreach ($testData as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    
    $conn->beginTransaction();
    
    if ($stmt->execute()) {
        $rowCount = $stmt->rowCount();
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Direct update test successful. Affected rows: $rowCount",
            'test_data' => $testData
        ]);
    } else {
        $conn->rollBack();
        throw new Exception('Direct update failed');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_info' => $conn->errorInfo() ?? null
    ]);
}
