<?php
// API endpoint to get employee details by ID

// Disable error display for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../../../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Function to log debug information
function debugLog($message) {
    global $logDir;
    $logFile = $logDir . '/get_employe_debug.log';
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . "\n", 3, $logFile);
}

// Start output buffering to catch any unexpected output
ob_start();

debugLog('Starting get_employe.php script');

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';
require_once './../../../src/models/Employe.php';

// Set header to return JSON
header('Content-Type: application/json');

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);
$authController = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'employé non fourni']);
    exit;
}

$id = (int)$_GET['id'];

// Prepare query to get employee details
$query = "SELECT * FROM employes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bindParam(1, $id, PDO::PARAM_INT);
$stmt->execute();

// Check if employee exists
if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
    exit;
}

// Get employee data
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

// Log this activity
$auth->logActivity('consulté', 'employe', $id, "Consultation des détails de l'employé: " . $employe['nom']);

// Prepare the response data
$response = ['success' => true, 'employe' => $employe];

// Get any unexpected output from the buffer
$unexpected_output = ob_get_clean();

// Log any unexpected output
if (!empty($unexpected_output)) {
    debugLog('Unexpected output before JSON response: ' . $unexpected_output);
    $response['debug'] = [
        'unexpected_output' => $unexpected_output,
        'note' => 'This content was output before the JSON response'
    ];
}

// Make sure the content type is set
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Return success response with employee data
echo json_encode($response);
debugLog('Response sent: ' . json_encode($response));
