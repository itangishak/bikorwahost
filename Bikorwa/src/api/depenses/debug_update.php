<?php
// Ensure no output before headers
error_reporting(0);

// Try to set headers first
try {
    header('Content-Type: application/json');
} catch (Exception $e) {}

try {
    // Load database config from confirmed location
    $configPath = realpath(__DIR__.'/../../../src/config/database.php');
    if (!$configPath) {
        throw new Exception('Database config not found at: '.__DIR__.'/../../../src/config/database.php');
    }
    
    require_once $configPath;
    
    // Test database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Verify we can query the depenses table
    $test = $conn->query("SHOW TABLES LIKE 'depenses'");
    if (!$test || $test->rowCount() === 0) {
        throw new Exception('depenses table not found');
    }
    
    // Get raw input
    $rawInput = @file_get_contents('php://input');
    if ($rawInput === false) {
        throw new Exception('Failed to read input');
    }
    
    // Capture raw input
    $data = json_decode($rawInput, true);
    
    // Prepare response
    $response = [
        'success' => true,
        'debug_info' => [
            'raw_input' => $rawInput,
            'decoded_data' => $data,
            'data_types' => array_map('gettype', $data),
            'db_connection' => 'OK',
            'server_time' => date('Y-m-d H:i:s')
        ]
    ];
    
    // If ID exists, verify record
    if (isset($data['id'])) {
        $stmt = $conn->prepare("SELECT * FROM depenses WHERE id = ?");
        $stmt->execute([$data['id']]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response['debug_info']['record_exists'] = (bool)$record;
        $response['debug_info']['current_record'] = $record;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Ensure clean JSON output even on errors
    die(json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'input_received' => isset($rawInput) ? $rawInput : null,
        'php_errors' => error_get_last()
    ]));
}
