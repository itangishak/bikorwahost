<?php
// API endpoint to update debt details
// Enhanced error handling and session management
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conditional session manager loading
if (!isset($_SESSION['SESSION_MANAGER_LOADED'])) {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Auth.php';
    $_SESSION['SESSION_MANAGER_LOADED'] = true;
} else {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/Auth.php';
}

$response = ['success' => false, 'message' => ''];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Initialize authentication
    $auth = new Auth($conn);

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        $response['message'] = "Session expirée. Veuillez vous reconnecter.";
        echo json_encode($response);
        exit;
    }

    // Check access permissions: allow users with dettes access or managers
    $user_role = strtolower($_SESSION['role'] ?? '');
    $has_dettes_access = $auth->hasAccess('dettes');

    if (!$has_dettes_access && $user_role !== 'gestionnaire') {
        http_response_code(403);
        $response['message'] = "Permission refusée: vous n'avez pas l'autorisation de modifier des dettes";
        $response['debug'] = [
            'session_role' => $user_role,
            'has_access' => $has_dettes_access,
            'session_data' => $_SESSION ?? null,
            'user_id' => $_SESSION['user_id'] ?? 'NOT_SET'
        ];
        echo json_encode($response);
        exit;
    }

    // Validate required fields
    if (empty($_POST['dette_id']) || empty($_POST['client_id']) || empty($_POST['montant_initial'])) {
        http_response_code(400);
        $response['message'] = "Données manquantes: ID dette, client et montant requis";
        echo json_encode($response);
        exit;
    }

    $conn->beginTransaction();

    try {
        // Update debt
        $stmt = $conn->prepare("UPDATE dettes SET 
            client_id = :client_id, 
            montant_initial = :montant_initial,
            note = :note,
            date_echeance = :date_echeance
            WHERE id = :dette_id");

        $stmt->execute([
            ':client_id' => $_POST['client_id'],
            ':montant_initial' => $_POST['montant_initial'],
            ':note' => $_POST['note'] ?? null,
            ':date_echeance' => !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null,
            ':dette_id' => $_POST['dette_id']
        ]);

        if ($stmt->rowCount() > 0) {
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Dette mise à jour avec succès';
        } else {
            $conn->rollBack();
            $response['message'] = 'Aucune modification effectuée ou dette non trouvée';
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Database error in update_dette.php: " . $e->getMessage());
        $response['message'] = 'Erreur de base de données: ' . $e->getMessage();
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
    $response['debug'] = [
        'session' => $_SESSION ?? null,
        'post_data' => $_POST
    ];
}

echo json_encode($response);
