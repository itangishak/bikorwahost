<?php
/**
 * User Management Process Handler
 * Handles AJAX requests for user management operations (add, update, delete, toggle status)
 * 
 * This file processes all AJAX requests from the utilisateurs.php page
 * Returns JSON responses for all operations
 */

// Include required files
require_once './../../../src/config/config.php';
require_once './../../../src/config/database.php';
require_once './../../../src/utils/Auth.php';
require_once './../../../src/models/User.php';
require_once './../../../src/controllers/AuthController.php';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize auth
$auth = new Auth($conn);
$authController = new AuthController();

// Check if user is logged in and has access to user management
if (!$auth->isLoggedIn() || !$auth->hasAccess('utilisateurs')) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé.'
    ]);
    exit;
}

// Set default response
$response = [
    'success' => false,
    'message' => 'Action non reconnue.'
];

// Process the request based on action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // Add new user
            $response = addUser($conn);
            break;
            
        case 'update':
            // Update existing user
            $response = updateUser($conn);
            break;
            
        case 'delete':
            // Delete user
            $response = deleteUser($conn);
            break;
            
        case 'toggle_status':
            // Toggle user status (active/inactive)
            $response = toggleUserStatus($conn);
            break;
            
        default:
            // Invalid action
            $response = [
                'success' => false,
                'message' => 'Action non reconnue.'
            ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;

/**
 * Add a new user
 * 
 * @param PDO $conn Database connection
 * @return array Response with success status and message
 */
function addUser($conn) {
    // Validate required fields
    $requiredFields = ['nom', 'username', 'password', 'confirm_password', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            return [
                'success' => false,
                'message' => 'Tous les champs obligatoires doivent être remplis.'
            ];
        }
    }
    
    // Validate username format
    $username = trim($_POST['username']);
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return [
            'success' => false,
            'message' => 'Le nom d\'utilisateur doit contenir entre 3 et 20 caractères alphanumériques ou underscore.'
        ];
    }
    
    // Check if passwords match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        return [
            'success' => false,
            'message' => 'Les mots de passe ne correspondent pas.'
        ];
    }
    
    // Validate password strength
    if (strlen($_POST['password']) < 8) {
        return [
            'success' => false,
            'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
        ];
    }
    
    // Validate email if provided
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Format d\'email invalide.'
        ];
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        return [
            'success' => false,
            'message' => 'Ce nom d\'utilisateur existe déjà. Veuillez en choisir un autre.'
        ];
    }
    
    // Prepare data for insertion
    $nom = trim($_POST['nom']);
    $role = $_POST['role'];
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $actif = isset($_POST['actif']) ? (int)$_POST['actif'] : 1;
    
    // Hash password
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, nom, role, email, telephone, actif)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            $password_hash,
            $nom,
            $role,
            $email,
            $telephone,
            $actif
        ]);
        
        $userId = $conn->lastInsertId();
        
        // Log activity
        $currentUserId = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $details = "Ajout d'un nouvel utilisateur: $nom ($username) avec le rôle: $role";
        $stmt->execute([
            $currentUserId,
            'creation',
            'utilisateur',
            $userId,
            $details
        ]);
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Utilisateur ajouté avec succès.',
            'user_id' => $userId
        ];
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        return [
            'success' => false,
            'message' => 'Erreur lors de l\'ajout de l\'utilisateur: ' . $e->getMessage()
        ];
    }
}

/**
 * Update an existing user
 * 
 * @param PDO $conn Database connection
 * @return array Response with success status and message
 */
function updateUser($conn) {
    // Validate required fields
    $requiredFields = ['id', 'nom', 'username', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            return [
                'success' => false,
                'message' => 'Tous les champs obligatoires doivent être remplis.'
            ];
        }
    }
    
    $id = (int)$_POST['id'];
    $username = trim($_POST['username']);
    $nom = trim($_POST['nom']);
    $role = $_POST['role'];
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $actif = isset($_POST['actif']) ? (int)$_POST['actif'] : 0;
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return [
            'success' => false,
            'message' => 'Le nom d\'utilisateur doit contenir entre 3 et 20 caractères alphanumériques ou underscore.'
        ];
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Format d\'email invalide.'
        ];
    }
    
    // Check if username already exists for another user
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetchColumn() > 0) {
        return [
            'success' => false,
            'message' => 'Ce nom d\'utilisateur existe déjà. Veuillez en choisir un autre.'
        ];
    }
    
    // Fetch current user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Utilisateur introuvable.'
        ];
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Build update query
        $query = "UPDATE users SET 
                  username = ?, 
                  nom = ?, 
                  role = ?, 
                  email = ?, 
                  telephone = ?, 
                  actif = ?";
        
        $params = [
            $username, 
            $nom, 
            $role, 
            $email, 
            $telephone, 
            $actif
        ];
        
        // Update password if provided
        if (!empty($password)) {
            // Validate password strength
            if (strlen($password) < 8) {
                return [
                    'success' => false,
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
                ];
            }
            
            // Check if passwords match
            if ($password !== $_POST['confirm_password']) {
                return [
                    'success' => false,
                    'message' => 'Les mots de passe ne correspondent pas.'
                ];
            }
            
            // Hash new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query .= ", password = ?";
            $params[] = $password_hash;
        }
        
        // Complete query
        $query .= " WHERE id = ?";
        $params[] = $id;
        
        // Execute update
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        // Log activity
        $currentUserId = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $changes = [];
        if ($user['nom'] !== $nom) $changes[] = "Nom: {$user['nom']} → $nom";
        if ($user['username'] !== $username) $changes[] = "Nom d'utilisateur: {$user['username']} → $username";
        if ($user['role'] !== $role) $changes[] = "Rôle: {$user['role']} → $role";
        if ($user['email'] !== $email) $changes[] = "Email: {$user['email']} → $email";
        if ($user['telephone'] !== $telephone) $changes[] = "Téléphone: {$user['telephone']} → $telephone";
        if ($user['actif'] != $actif) $changes[] = "Statut: " . ($user['actif'] ? 'Actif' : 'Inactif') . " → " . ($actif ? 'Actif' : 'Inactif');
        if (!empty($password)) $changes[] = "Mot de passe modifié";
        
        $details = "Modification de l'utilisateur: $nom ($username). Changements: " . implode(", ", $changes);
        $stmt->execute([
            $currentUserId,
            'modification',
            'utilisateur',
            $id,
            $details
        ]);
        
        // Commit transaction
        $conn->commit();
        
        // Prepare user data for response
        $updatedUser = [
            'id' => $id,
            'nom' => $nom,
            'username' => $username,
            'role' => $role,
            'email' => $email,
            'telephone' => $telephone,
            'actif' => (string)$actif
        ];
        
        return [
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès.',
            'user' => $updatedUser
        ];
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        return [
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete a user
 * 
 * @param PDO $conn Database connection
 * @return array Response with success status and message
 */
function deleteUser($conn) {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        return [
            'success' => false,
            'message' => 'ID utilisateur manquant.'
        ];
    }
    
    $id = (int)$_POST['id'];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT nom, username FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Utilisateur introuvable.'
        ];
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Log activity before deletion
        $currentUserId = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $details = "Suppression de l'utilisateur: {$user['nom']} ({$user['username']})";
        $stmt->execute([
            $currentUserId,
            'suppression',
            'utilisateur',
            $id,
            $details
        ]);
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès.'
        ];
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        return [
            'success' => false,
            'message' => 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage()
        ];
    }
}

/**
 * Toggle user status (active/inactive)
 * 
 * @param PDO $conn Database connection
 * @return array Response with success status and message
 */
function toggleUserStatus($conn) {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        return [
            'success' => false,
            'message' => 'ID utilisateur manquant.'
        ];
    }
    
    if (!isset($_POST['actif'])) {
        return [
            'success' => false,
            'message' => 'Statut manquant.'
        ];
    }
    
    $id = (int)$_POST['id'];
    $actif = (int)$_POST['actif'];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT nom, username, actif FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Utilisateur introuvable.'
        ];
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Update user status
        $stmt = $conn->prepare("UPDATE users SET actif = ? WHERE id = ?");
        $stmt->execute([$actif, $id]);
        
        // Log activity
        $currentUserId = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO journal_activites (utilisateur_id, action, entite, entite_id, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $action = $actif ? 'activation' : 'désactivation';
        $details = ucfirst($action) . " de l'utilisateur: {$user['nom']} ({$user['username']})";
        $stmt->execute([
            $currentUserId,
            $action,
            'utilisateur',
            $id,
            $details
        ]);
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Statut de l\'utilisateur modifié avec succès.',
            'user' => [
                'id' => $id,
                'actif' => (string)$actif
            ]
        ];
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        
        return [
            'success' => false,
            'message' => 'Erreur lors de la modification du statut: ' . $e->getMessage()
        ];
    }
}
?>
