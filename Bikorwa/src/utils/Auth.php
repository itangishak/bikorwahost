<?php
/**
 * Classe Auth pour la gestion de l'authentification et des autorisations
 * BIKORWA SHOP
 */

class Auth {
    // Propriétés
    private $conn;
    private $user;
    
    // Constructeur
    public function __construct($db) {
        $this->conn = $db;
        $this->user = new User($db);
    }
    
    // Méthode pour authentifier un utilisateur
    public function login($username, $password) {
        // Définir le nom d'utilisateur
        $this->user->username = $username;
        
        // Vérifier si l'utilisateur existe
        if($this->user->userExists()) {
            // Vérifier si le compte est actif
            if(!$this->user->actif) {
                return [
                    'success' => false,
                    'message' => 'Ce compte est désactivé.'
                ];
            }
            
            // Vérifier le mot de passe
            if(password_verify($password, $this->user->password)) {
                // Mettre à jour la dernière connexion
                $this->user->updateLastLogin();
                
                // Créer les variables de session
                $_SESSION['user_id'] = $this->user->id;
                $_SESSION['user_name'] = $this->user->nom;
                $_SESSION['user_role'] = $this->user->role;
                $_SESSION['logged_in'] = true;
                
                // Journaliser la connexion
                $this->logActivity('Connexion au système', 'auth', $this->user->id);
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $this->user->id,
                        'nom' => $this->user->nom,
                        'role' => $this->user->role
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Mot de passe incorrect.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Utilisateur non trouvé.'
            ];
        }
    }
    
    // Méthode pour déconnecter un utilisateur
    public function logout() {
        // Journaliser la déconnexion si l'utilisateur est connecté
        if(isset($_SESSION['user_id'])) {
            $this->logActivity('Déconnexion du système', 'auth', $_SESSION['user_id']);
        }
        
        // Détruire toutes les variables de session
        $_SESSION = [];
        
        // Détruire la session
        session_destroy();
        
        return true;
    }
    
    // Méthode pour vérifier si l'utilisateur est connecté
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Méthode pour vérifier si l'utilisateur est un gestionnaire
    public function isManager() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'gestionnaire';
    }
    
    // Méthode pour vérifier si l'utilisateur est un réceptionniste
    public function isReceptionist() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'receptionniste';
    }
    
    // Méthode pour vérifier si l'utilisateur a la permission de modifier
    public function canModify() {
        // Seuls les gestionnaires peuvent modifier
        return $this->isManager();
    }
    
    // Méthode pour vérifier si l'utilisateur a la permission de supprimer
    public function canDelete() {
        // Seuls les gestionnaires peuvent supprimer
        return $this->isManager();
    }
    
    // Méthode pour vérifier si l'utilisateur a accès à une fonctionnalité
    public function hasAccess($feature) {
        // Liste des fonctionnalités par rôle
        $permissions = [
            'gestionnaire' => [
                'dashboard', 'ventes', 'stock', 'employes', 'dettes', 'rapports', 
                'utilisateurs', 'parametres', 'clients', 'categories'
            ],
            'receptionniste' => [
                'dashboard', 'ventes', 'stock', 'dettes', 'clients'
            ]
        ];
        
        // Vérifier si l'utilisateur est connecté
        if(!$this->isLoggedIn()) {
            return false;
        }
        
        // Récupérer le rôle de l'utilisateur
        $role = $_SESSION['user_role'];
        
        // Vérifier si la fonctionnalité est accessible pour ce rôle
        return in_array($feature, $permissions[$role]);
    }
    
    // Méthode pour journaliser une activité
    public function logActivity($action, $entite, $entite_id = null, $details = null) {
        // Requête d'insertion
        $query = "INSERT INTO journal_activites 
                  SET utilisateur_id = :utilisateur_id, 
                      action = :action, 
                      entite = :entite, 
                      entite_id = :entite_id, 
                      details = :details";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Récupérer l'ID de l'utilisateur connecté
        $utilisateur_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Liaison des paramètres
        $stmt->bindParam(":utilisateur_id", $utilisateur_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":entite", $entite);
        $stmt->bindParam(":entite_id", $entite_id);
        $stmt->bindParam(":details", $details);
        
        // Exécution de la requête
        $stmt->execute();
    }
}
?>
