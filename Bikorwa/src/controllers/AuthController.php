<?php
/**
 * Contrôleur d'authentification
 * BIKORWA SHOP
 */

class AuthController {
    // Propriétés
    private $db;
    private $auth;
    
    // Constructeur
    public function __construct() {
        // Vérifier que la classe Database est bien chargée
        if (!class_exists('Database')) {
            $databaseFile = __DIR__ . '/../config/database.php';
            if (file_exists($databaseFile)) {
                require_once $databaseFile;
            } else {
                throw new RuntimeException('Fichier de configuration de la base de données introuvable : ' . $databaseFile);
            }
        }

        // Connexion à la base de données
        $database = new Database();
        $this->db = $database->getConnection();

        // Initialisation de l'authentification
        $this->auth = new Auth($this->db);
    }
    
    // Méthode pour traiter la connexion
    public function login($username = null, $password = null) {
        // Vérifier si on a des paramètres passés directement
        if($username !== null && $password !== null) {
            // Les paramètres sont passés directement via la méthode
            $username = trim($username);
            $password = trim($password);
        } 
        // Sinon, vérifier si le formulaire a été soumis via POST
        else if($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Récupérer les données du formulaire
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        } else {
            return [
                'success' => false,
                'message' => 'Méthode non autorisée ou paramètres manquants.'
            ];
        }
        
        // Vérifier que les champs ne sont pas vides
        if(empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Veuillez remplir tous les champs.'
            ];
        }
        
        // Tenter la connexion
        $result = $this->auth->login($username, $password);
        
        return $result;
    }
    
    // Méthode pour traiter la déconnexion
    public function logout() {
        $this->auth->logout();
        
        // Rediriger vers la page de connexion
        header('Location: ' . BASE_URL . '/src/views/auth/login.php');
        exit;
    }
    
    // Méthode pour vérifier si l'utilisateur est connecté
    public function isLoggedIn() {
        return $this->auth->isLoggedIn();
    }
    
    // Méthode pour vérifier si l'utilisateur est un gestionnaire
    public function isManager() {
        return $this->auth->isManager();
    }
    
    // Méthode pour vérifier si l'utilisateur est un réceptionniste
    public function isReceptionist() {
        return $this->auth->isReceptionist();
    }
    
    // Méthode pour vérifier si l'utilisateur a la permission de modifier
    public function canModify() {
        return $this->auth->canModify();
    }
    
    // Méthode pour vérifier si l'utilisateur a la permission de supprimer
    public function canDelete() {
        return $this->auth->canDelete();
    }
    
    // Méthode pour vérifier si l'utilisateur a accès à une fonctionnalité
    public function hasAccess($feature) {
        return $this->auth->hasAccess($feature);
    }
    
    // Méthode pour journaliser une activité
    public function logActivity($action, $entite, $entite_id = null, $details = null) {
        return $this->auth->logActivity($action, $entite, $entite_id, $details);
    }
}
?>
