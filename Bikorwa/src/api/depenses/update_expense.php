<?php

// Enable error reporting but do not display errors in response
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log the start of the script execution for debugging
error_log('['.date('Y-m-d H:i:s').'] update_expense.php script started');

// Database configuration
require_once __DIR__.'/../../../config/database.php';

// Set response header
header('Content-Type: application/json');

try {
    // Get and validate input
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);
    
    if ($data === null) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate ID is numeric
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        throw new Exception('ID must be a numeric value');
    }
    
    // Convert ID to integer
    $data['id'] = (int)$data['id'];
    
    // Log received data
    file_put_contents('update_debug.log', print_r($data, true), FILE_APPEND);
    
    // Required fields
    $requiredFields = ['date_depense', 'montant', 'categorie_id', 'mode_paiement'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Champ requis manquant: $field");
        }
    }
    
    // Database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    // Set MySQL error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Verify record exists first
        $checkStmt = $conn->prepare("SELECT * FROM depenses WHERE id = ?");
        $checkStmt->execute([$data['id']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            throw new Exception("Record with ID {$data['id']} not found");
        }
        
        // Prepare update query with MySQL-specific syntax
        $query = "UPDATE depenses SET 
            date_depense = :date_depense, 
            montant = :montant, 
            categorie_id = :categorie_id, 
            mode_paiement = :mode_paiement, 
            description = :description, 
            reference_paiement = :reference_paiement, 
            note = :note,
            updated_at = NOW()
            WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters with MySQL data types
        $stmt->bindValue(':date_depense', $data['date_depense'], PDO::PARAM_STR);
        $stmt->bindValue(':montant', $data['montant'], PDO::PARAM_STR);
        $stmt->bindValue(':categorie_id', $data['categorie_id'], PDO::PARAM_INT);
        $stmt->bindValue(':mode_paiement', $data['mode_paiement'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':reference_paiement', $data['reference_paiement'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':note', $data['note'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':id', $data['id'], PDO::PARAM_INT);
        
        // Execute the update
        $success = $stmt->execute();
        
        if (!$success) {
            throw new Exception('Update failed: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $rowCount = $stmt->rowCount();
        
        if ($rowCount === 0) {
            throw new Exception('No rows updated - values may be identical');
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Dépense mise à jour avec succès',
            'affected_rows' => $rowCount
        ]);
        
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();
        
        // MySQL-specific error handling
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Erreur MySQL [{$errorInfo[1]}]: {$errorInfo[2]}");
    }
    
} catch (Exception $e) {
    // Log detailed error
    error_log('['.date('Y-m-d H:i:s').'] Update Error: '.$e->getMessage()."\nInput: ".print_r($data, true)."\n");
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'input_data' => $data,
            'required_fields' => ['date_depense', 'montant', 'categorie_id', 'mode_paiement']
        ]
    ]);
} catch (Throwable $t) {
    // Catch any other unexpected errors
    error_log('['.date('Y-m-d H:i:s').'] Unexpected Error in update_expense.php: '.$t->getMessage()." at ".$t->getFile().":".$t->getLine()."\nStack trace: ". $t->getTraceAsString()."\n");
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected server error occurred',
        'error_details' => [
            'type' => get_class($t),
            'error' => $t->getMessage(),
            'file' => $t->getFile(),
            'line' => $t->getLine()
        ]
    ]);
}
