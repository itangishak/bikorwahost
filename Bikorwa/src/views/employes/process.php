<?php
/**
 * Process employee CRUD operations with duplicate checking
 * BIKORWA SHOP
 */

// Start session and set headers for AJAX
session_start();
header('Content-Type: application/json');

// Include required files
require_once './../../config/config.php';
require_once './../../config/database.php';
require_once './../../models/Employe.php';
require_once './../../utils/Auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

// Initialize employee model
$employe = new Employe($conn);

// Get the action
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            handleAdd($employe, $auth);
            break;
            
        case 'edit':
            handleEdit($employe, $auth);
            break;
            
        case 'delete':
            handleDelete($employe, $auth);
            break;
            
        case 'toggle_status':
            handleToggleStatus($employe, $auth);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error in employee process: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors du traitement'
    ]);
}

/**
 * Handle adding a new employee
 */
function handleAdd($employe, $auth) {
    // Check permissions
    if (!$auth->canModify()) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas les permissions pour ajouter un employé'
        ]);
        return;
    }
    
    // Validate required fields
    $required_fields = ['nom', 'poste', 'date_embauche', 'salaire'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode([
                'success' => false,
                'message' => 'Le champ ' . $field . ' est obligatoire'
            ]);
            return;
        }
    }
    
    // Set employee properties
    $employe->nom = trim($_POST['nom']);
    $employe->telephone = trim($_POST['telephone'] ?? '');
    $employe->email = trim($_POST['email'] ?? '');
    $employe->adresse = trim($_POST['adresse'] ?? '');
    $employe->poste = trim($_POST['poste']);
    $employe->date_embauche = $_POST['date_embauche'];
    $employe->salaire = floatval($_POST['salaire']);
    $employe->actif = isset($_POST['actif']) ? intval($_POST['actif']) : 1;
    $employe->note = trim($_POST['note'] ?? '');
    
    // Validate email format if provided
    if (!empty($employe->email) && !filter_var($employe->email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format d\'email invalide'
        ]);
        return;
    }
    
    // Validate salary
    if ($employe->salaire < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Le salaire ne peut pas être négatif'
        ]);
        return;
    }
    
    // Attempt to create the employee
    $result = $employe->create();
    
    if ($result['success']) {
        $_SESSION['success'] = 'Employé ajouté avec succès';
        echo json_encode([
            'success' => true,
            'message' => 'Employé ajouté avec succès',
            'id' => $result['id']
        ]);
    } else {
        if ($result['error'] === 'duplicate') {
            // Handle duplicate error
            $duplicate_messages = [];
            foreach ($result['duplicates'] as $duplicate) {
                $types = implode(', ', $duplicate['duplicate_types']);
                $duplicate_messages[] = "Un employé avec le même {$types} existe déjà : {$duplicate['nom']} ({$duplicate['poste']})";
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Doublon détecté',
                'details' => $duplicate_messages
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Erreur lors de l\'ajout de l\'employé'
            ]);
        }
    }
}

/**
 * Handle editing an employee
 */
function handleEdit($employe, $auth) {
    // Check permissions
    if (!$auth->canModify()) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas les permissions pour modifier un employé'
        ]);
        return;
    }
    
    // Validate required fields
    $required_fields = ['id', 'nom', 'poste', 'date_embauche', 'salaire'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode([
                'success' => false,
                'message' => 'Le champ ' . $field . ' est obligatoire'
            ]);
            return;
        }
    }
    
    // Set employee properties
    $employe->id = intval($_POST['id']);
    $employe->nom = trim($_POST['nom']);
    $employe->telephone = trim($_POST['telephone'] ?? '');
    $employe->email = trim($_POST['email'] ?? '');
    $employe->adresse = trim($_POST['adresse'] ?? '');
    $employe->poste = trim($_POST['poste']);
    $employe->date_embauche = $_POST['date_embauche'];
    $employe->salaire = floatval($_POST['salaire']);
    $employe->actif = isset($_POST['actif']) ? intval($_POST['actif']) : 1;
    $employe->note = trim($_POST['note'] ?? '');
    
    // Validate email format if provided
    if (!empty($employe->email) && !filter_var($employe->email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format d\'email invalide'
        ]);
        return;
    }
    
    // Validate salary
    if ($employe->salaire < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Le salaire ne peut pas être négatif'
        ]);
        return;
    }
    
    // Attempt to update the employee
    $result = $employe->update();
    
    if ($result['success']) {
        $_SESSION['success'] = 'Informations de l\'employé mises à jour avec succès';
        echo json_encode([
            'success' => true,
            'message' => 'Informations de l\'employé mises à jour avec succès'
        ]);
    } else {
        if ($result['error'] === 'duplicate') {
            // Handle duplicate error
            $duplicate_messages = [];
            foreach ($result['duplicates'] as $duplicate) {
                $types = implode(', ', $duplicate['duplicate_types']);
                $duplicate_messages[] = "Un employé avec le même {$types} existe déjà : {$duplicate['nom']} ({$duplicate['poste']})";
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Doublon détecté',
                'details' => $duplicate_messages
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Erreur lors de la mise à jour de l\'employé'
            ]);
        }
    }
}

/**
 * Handle deleting an employee
 */
function handleDelete($employe, $auth) {
    // Check permissions
    if (!$auth->canDelete()) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas les permissions pour supprimer un employé'
        ]);
        return;
    }
    
    // Validate ID
    if (empty($_POST['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de l\'employé manquant'
        ]);
        return;
    }
    
    $employe->id = intval($_POST['id']);
    
    // Attempt to delete the employee
    if ($employe->delete()) {
        $_SESSION['success'] = 'Employé supprimé avec succès';
        echo json_encode([
            'success' => true,
            'message' => 'Employé supprimé avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la suppression de l\'employé'
        ]);
    }
}

/**
 * Handle toggling employee status
 */
function handleToggleStatus($employe, $auth) {
    // Check permissions
    if (!$auth->canModify()) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'avez pas les permissions pour modifier le statut d\'un employé'
        ]);
        return;
    }
    
    // Validate ID
    if (empty($_POST['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de l\'employé manquant'
        ]);
        return;
    }
    
    $employe->id = intval($_POST['id']);
    
    // Attempt to toggle status
    $result = $employe->toggleStatus();
    
    if ($result['success']) {
        $status_text = $result['new_status'] ? 'activé' : 'désactivé';
        $_SESSION['success'] = "Employé {$status_text} avec succès";
        echo json_encode([
            'success' => true,
            'message' => "Employé {$status_text} avec succès",
            'new_status' => $result['new_status']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Erreur lors de la mise à jour du statut'
        ]);
    }
}
?>