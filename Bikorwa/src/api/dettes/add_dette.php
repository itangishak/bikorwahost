<?php
// API endpoint to add a new debt
// Enhanced error handling and debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

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
        echo json_encode([
            "success" => false, 
            "message" => "Non autorisé - Utilisateur non connecté",
            "debug" => [
                "session_status" => session_status(),
                "logged_in" => $_SESSION['logged_in'] ?? 'not_set',
                "user_id" => $_SESSION['user_id'] ?? 'not_set',
                "role" => $_SESSION['role'] ?? 'not_set'
            ]
        ]);
        exit;
    }

// Get user ID for logging
$user_id = $_SESSION['user_id'] ?? 0;

// Get POST data
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$vente_id = !empty($_POST['vente_id']) ? intval($_POST['vente_id']) : null;
$montant_initial = isset($_POST['montant_initial']) ? floatval($_POST['montant_initial']) : 0;
$date_creation = !empty($_POST['date_creation']) ? $_POST['date_creation'] : date('Y-m-d');
$date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
$note = $_POST['note'] ?? '';

// Validate required fields
if (!$client_id || $montant_initial <= 0) {
    echo json_encode([
        "success" => false, 
        "message" => "Veuillez remplir tous les champs obligatoires",
        "debug" => [
            "client_id" => $client_id,
            "montant_initial" => $montant_initial,
            "post_data" => $_POST
        ]
    ]);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Insert new debt
    $query = "INSERT INTO dettes (client_id, vente_id, montant_initial, montant_restant, date_creation, date_echeance, note)
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $client_id, PDO::PARAM_INT);

    // Vente ID may be null. bindValue handles null values correctly while
    // bindParam with PARAM_NULL can lead to unexpected behaviour on some
    // drivers.  Use bindValue when the value is null.
    if ($vente_id !== null) {
        $stmt->bindParam(2, $vente_id, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(2, null, PDO::PARAM_NULL);
    }

    $stmt->bindParam(3, $montant_initial, PDO::PARAM_STR);
    $stmt->bindParam(4, $montant_initial, PDO::PARAM_STR); // montant_restant = montant_initial for new debt
    $stmt->bindParam(5, $date_creation, PDO::PARAM_STR);

    // date_echeance is optional and may be null as well
    if ($date_echeance !== null) {
        $stmt->bindParam(6, $date_echeance, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(6, null, PDO::PARAM_NULL);
    }

    $stmt->bindParam(7, $note, PDO::PARAM_STR);
    
    $stmt->execute();
    $dette_id = $conn->lastInsertId();
    
    // Log activity
    $log_query = "INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
                  VALUES (?, 'ajout', 'dette', ?, ?)";
    
    $details = "Ajout d'une nouvelle dette pour le client #$client_id d'un montant de $montant_initial BIF";
    if ($vente_id) {
        $details .= " liée à la vente #$vente_id";
    }
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $dette_id, PDO::PARAM_INT);
    $log_stmt->bindParam(3, $details, PDO::PARAM_STR);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true, 
        "message" => "Dette ajoutée avec succès",
        "dette_id" => $dette_id
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database error in add_dette.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur de base de données: " . $e->getMessage(),
        "debug" => [
            "error_code" => $e->getCode(),
            "error_info" => $e->errorInfo ?? null
        ]
    ]);
} catch (Exception $e) {
    error_log("General error in add_dette.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur générale: " . $e->getMessage(),
        "debug" => [
            "trace" => $e->getTraceAsString()
        ]
    ]);
}

} catch (Exception $e) {
    error_log("Fatal error in add_dette.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Erreur fatale lors de l'initialisation: " . $e->getMessage()
    ]);
}
