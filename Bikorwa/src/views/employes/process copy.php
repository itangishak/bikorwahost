<?php
// Process employee operations (add, edit, toggle status)

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
    $logFile = $logDir . '/process_debug.log';
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . "\n", 3, $logFile);
}

// Start output buffering to catch any unexpected output
ob_start();

require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';
require_once './../../../src/models/Employe.php'; // Fix: French spelling 'Employe' not 'Employee'

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);
$authController = new AuthController();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Vous devez être connecté pour effectuer cette action';
    header('Location: /auth/login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Méthode non autorisée';
    header('Location: /employes/liste.php');
    exit;
}

// Get the action
$action = $_POST['action'] ?? '';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the browser

// Set JSON header when responding via AJAX
header('Content-Type: application/json');

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
// Also treat POST requests to this file as AJAX requests for this implementation
$is_ajax = true;

// Log function for debugging
function logError($message) {
    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/../../../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . ' - Process Error: ' . $message, 3, $logDir . '/employee_errors.log');
}

// Define response array for AJAX requests
$response = ['success' => false, 'message' => ''];

// Log the request for debugging
logError('Request received: ' . print_r($_POST, true));

// Process based on action
switch ($action) {
    case 'add':
        // Check permissions
        if (!$auth->canModify()) {
            $_SESSION['error'] = 'Vous n\'avez pas les permissions nécessaires pour ajouter un employé';
            header('Location: /employes/liste.php');
            exit;
        }
        
        // Validate required fields
        $required_fields = ['nom', 'poste', 'date_embauche', 'salaire'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                header('Location: /employes/liste.php');
                exit;
            }
        }
        
        // Sanitize and prepare data
        $nom = trim($_POST['nom']);
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $poste = trim($_POST['poste']);
        $date_embauche = $_POST['date_embauche'];
        $salaire = (float) $_POST['salaire'];
        $actif = isset($_POST['actif']) ? (int) $_POST['actif'] : 1;
        $note = trim($_POST['note'] ?? '');
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'L\'adresse email fournie n\'est pas valide';
            header('Location: ./liste.php');
            exit;
        }
        
        // Insert employee
        $query = "INSERT INTO employes (nom, telephone, email, adresse, poste, date_embauche, salaire, actif, note) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $nom, PDO::PARAM_STR);
        $stmt->bindParam(2, $telephone, PDO::PARAM_STR);
        $stmt->bindParam(3, $email, PDO::PARAM_STR);
        $stmt->bindParam(4, $adresse, PDO::PARAM_STR);
        $stmt->bindParam(5, $poste, PDO::PARAM_STR);
        $stmt->bindParam(6, $date_embauche, PDO::PARAM_STR);
        $stmt->bindParam(7, $salaire, PDO::PARAM_STR);
        $stmt->bindParam(8, $actif, PDO::PARAM_INT);
        $stmt->bindParam(9, $note, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $employee_id = $conn->lastInsertId();
            
            // Log this activity
            $auth->logActivity('a ajouté', 'employe', $employee_id, "Ajout d'un nouvel employé: $nom");
            
            // Set success message
            $success_message = "L'employé $nom a été ajouté avec succès";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = true;
                $response['message'] = $success_message;
                $response['employee_id'] = $employee_id;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['success'] = $success_message;
                header('Location: ./liste.php');
                exit;
            }
        } else {
            $error_message = "Erreur lors de l'ajout de l'employé";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = false;
                $response['message'] = $error_message;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['error'] = $error_message;
                header('Location: ./liste.php');
                exit;
            }
        }
        break;
        
    case 'edit':
        // Check permissions
        if (!$auth->canModify()) {
            $_SESSION['error'] = 'Vous n\'avez pas les permissions nécessaires pour modifier un employé';
            header('Location: ./liste.php');
            exit;
        }
        
        // Check for employee ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['error'] = 'ID de l\'employé non fourni';
            header('Location: ./liste.php');
            exit;
        }
        
        $id = (int) $_POST['id'];
        
        // Validate required fields
        $required_fields = ['nom', 'poste', 'date_embauche', 'salaire'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $_SESSION['error'] = 'Veuillez remplir tous les champs obligatoires';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        // Sanitize and prepare data
        $nom = trim($_POST['nom']);
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $poste = trim($_POST['poste']);
        $date_embauche = $_POST['date_embauche'];
        $salaire = (float) $_POST['salaire'];
        $actif = isset($_POST['actif']) ? (int) $_POST['actif'] : 1;
        $note = trim($_POST['note'] ?? '');
        
        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'L\'adresse email fournie n\'est pas valide';
            header('Location: ./liste.php');
            exit;
        }
        
        // Update employee
        $query = "UPDATE employes SET 
                nom = ?, 
                telephone = ?, 
                email = ?, 
                adresse = ?, 
                poste = ?, 
                date_embauche = ?, 
                salaire = ?, 
                actif = ?, 
                note = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $nom, PDO::PARAM_STR);
        $stmt->bindParam(2, $telephone, PDO::PARAM_STR);
        $stmt->bindParam(3, $email, PDO::PARAM_STR);
        $stmt->bindParam(4, $adresse, PDO::PARAM_STR);
        $stmt->bindParam(5, $poste, PDO::PARAM_STR);
        $stmt->bindParam(6, $date_embauche, PDO::PARAM_STR);
        $stmt->bindParam(7, $salaire, PDO::PARAM_STR);
        $stmt->bindParam(8, $actif, PDO::PARAM_INT);
        $stmt->bindParam(9, $note, PDO::PARAM_STR);
        $stmt->bindParam(10, $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Log this activity
            $auth->logActivity('a modifié', 'employe', $id, "Modification des informations de l'employé: $nom");
            
            // Set success message
            $success_message = "Les informations de l'employé $nom ont été mises à jour avec succès";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = true;
                $response['message'] = $success_message;
                $response['employee_id'] = $id;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['success'] = $success_message;
                header('Location: ./liste.php');
                exit;
            }
        } else {
            $error_message = "Erreur lors de la modification de l'employé";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = false;
                $response['message'] = $error_message;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['error'] = $error_message;
                header('Location: ./liste.php');
                exit;
            }
        }
        break;
        
    case 'toggle_status':
        // Check if request is AJAX
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Check permissions
        if (!$auth->canDelete()) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas les permissions nécessaires pour modifier le statut d\'un employé']);
                exit;
            } else {
                $_SESSION['error'] = 'Vous n\'avez pas les permissions nécessaires pour modifier le statut d\'un employé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        // Check for employee ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'ID de l\'employé non fourni']);
                exit;
            } else {
                $_SESSION['error'] = 'ID de l\'employé non fourni';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $id = (int) $_POST['id'];
        
        // Get current status
        $query = "SELECT actif, nom FROM employes WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                exit;
            } else {
                $_SESSION['error'] = 'Employé non trouvé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_status = $employee['actif'] ? 0 : 1;
        $status_text = $new_status ? 'activé' : 'désactivé';
        
        // Update status
        $update_query = "UPDATE employes SET actif = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(1, $new_status, PDO::PARAM_INT);
        $update_stmt->bindParam(2, $id, PDO::PARAM_INT);
        
        if ($update_stmt->execute()) {
            // Log this activity
            $auth->logActivity('a ' . $status_text, 'employe', $id, "Modification du statut de l'employé: {$employee['nom']} ($status_text)");
            
            if ($is_ajax) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Le statut de l'employé {$employee['nom']} a été modifié avec succès", 
                    'new_status' => $new_status
                ]);
                exit;
            } else {
                $_SESSION['success'] = "Le statut de l'employé {$employee['nom']} a été modifié avec succès";
            }
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => "Erreur lors de la modification du statut de l'employé: " . $conn->error]);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la modification du statut de l'employé: " . $conn->error;
            }
        }
        
        if (!$is_ajax) {
            header('Location: ./liste.php');
        }
        break;
        
    case 'toggle_status':
        // Check if request is AJAX
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Check permissions
        if (!$auth->canModify()) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas les permissions nécessaires pour modifier le statut d\'un employé']);
                exit;
            } else {
                $_SESSION['error'] = 'Vous n\'avez pas les permissions nécessaires pour modifier le statut d\'un employé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        // Check for employee ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'ID de l\'employé non fourni']);
                exit;
            } else {
                $_SESSION['error'] = 'ID de l\'employé non fourni';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $id = (int) $_POST['id'];
        
        // Get employee details for logging
        $query = "SELECT nom, actif FROM employes WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                exit;
            } else {
                $_SESSION['error'] = 'Employé non trouvé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        $nom_employe = $employee['nom'];
        $currentStatus = (int) $employee['actif'];
        $newStatus = $currentStatus ? 0 : 1;
        $statusText = $newStatus ? 'activé' : 'désactivé';
        
        // If we're activating an employee, check for duplicates first
        if ($newStatus == 1) {
            $check_query = "SELECT COUNT(*) as count FROM employes WHERE nom = ? AND actif = 1 AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(1, $nom_employe, PDO::PARAM_STR);
            $check_stmt->bindParam(2, $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $error_message = "Impossible d'activer cet employé: un employé actif avec le même nom existe déjà";
                
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'message' => $error_message]);
                    exit;
                } else {
                    $_SESSION['error'] = $error_message;
                    header('Location: ./liste.php');
                    exit;
                }
            }
        }
        
        // Update employee status
        $update_query = "UPDATE employes SET actif = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(1, $newStatus, PDO::PARAM_INT);
        $update_stmt->bindParam(2, $id, PDO::PARAM_INT);
        
        if ($update_stmt->execute()) {
            // Log this activity
            $action = $newStatus ? 'a activé' : 'a désactivé';
            $auth->logActivity($action, 'employe', $id, "{$action} l'employé: {$nom_employe}");
            
            // Set success message
            $success_message = "L'employé {$nom_employe} a été {$statusText} avec succès";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = true;
                $response['message'] = $success_message;
                $response['new_status'] = $newStatus;
                $response['employee_id'] = $id;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['success'] = $success_message;
                header('Location: ./liste.php');
                exit;
            }
        } else {
            $error_message = "Erreur lors de la modification du statut de l'employé";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = false;
                $response['message'] = $error_message;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['error'] = $error_message;
                header('Location: ./liste.php');
                exit;
            }
        }
        break;

    case 'delete':
        // Check if request is AJAX
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Check permissions
        if (!$auth->canDelete()) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas les permissions nécessaires pour supprimer un employé']);
                exit;
            } else {
                $_SESSION['error'] = 'Vous n\'avez pas les permissions nécessaires pour supprimer un employé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        // Check for employee ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'ID de l\'employé non fourni']);
                exit;
            } else {
                $_SESSION['error'] = 'ID de l\'employé non fourni';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $id = (int) $_POST['id'];
        
        // Get employee details for logging
        $query = "SELECT nom FROM employes WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                exit;
            } else {
                $_SESSION['error'] = 'Employé non trouvé';
                header('Location: ./liste.php');
                exit;
            }
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        $nom_employe = $employee['nom'];
        
        // Delete employee
        $delete_query = "DELETE FROM employes WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(1, $id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            // Log this activity
            $auth->logActivity('a supprimé', 'employe', $id, "Suppression de l'employé: {$nom_employe}");
            
            // Set success message
            $success_message = "L'employé {$nom_employe} a été supprimé avec succès";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = true;
                $response['message'] = $success_message;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['success'] = $success_message;
                header('Location: ./liste.php');
                exit;
            }
        } else {
            $error_message = "Erreur lors de la suppression de l'employé";
            
            if ($is_ajax) {
                // Return JSON response for AJAX
                $response['success'] = false;
                $response['message'] = $error_message;
                echo json_encode($response);
                exit;
            } else {
                // Redirect for normal form submission
                $_SESSION['error'] = $error_message;
                header('Location: ./liste.php');
                exit;
            }
        }
        break;
        
    default:
        $_SESSION['error'] = 'Action non reconnue';
        header('Location: ./liste.php');
        break;
}

// For AJAX requests, make sure we properly handle any unexpected output
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Get the current buffer content and clean the buffer
    $unexpected_output = ob_get_clean();
    
    // Log unexpected output if any
    if (!empty($unexpected_output)) {
        debugLog('Unexpected output before JSON response: ' . $unexpected_output);
    }
    
    // Set JSON content type header if not already set
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    // If we haven't explicitly output a JSON response yet, do it now
    if (!isset($response) || !is_array($response)) {
        $response = [];
        if (isset($_SESSION['success'])) {
            $response['success'] = true;
            $response['message'] = $_SESSION['success'];
            unset($_SESSION['success']);
        } elseif (isset($_SESSION['error'])) {
            $response['success'] = false;
            $response['message'] = $_SESSION['error'];
            unset($_SESSION['error']);
        } else {
            $response['success'] = false;
            $response['message'] = 'Une erreur inconnue est survenue';
        }
    }
    
    // Add debug info in development
    if (!empty($unexpected_output)) {
        $response['debug'] = [
            'unexpected_output' => $unexpected_output,
            'note' => 'This content was output before the JSON response'
        ];
    }
    
    // Output the JSON response
    echo json_encode($response);
    exit;
} else {
    // For non-AJAX requests, just flush the buffer
    ob_end_flush();
}

// If this is an AJAX request that didn't exit early, return the response
if (isset($is_ajax) && $is_ajax && !headers_sent()) {
    echo json_encode($response);
    exit;
}
