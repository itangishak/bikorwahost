<?php
// Test database connection
require_once __DIR__.'/../../../config/database.php';
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Test simple query
    $stmt = $conn->query("SELECT 1");
    $result = $stmt->fetch();
    
    // Test depenses table access
    $stmt = $conn->query("SELECT COUNT(*) FROM depenses");
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'connection' => 'OK',
        'table_access' => 'OK',
        'row_count' => $count
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
