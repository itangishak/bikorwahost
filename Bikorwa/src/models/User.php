<?php
/**
 * Modèle User pour la gestion des utilisateurs
 * BIKORWA SHOP
 */

class User {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "users";
    
    // Propriétés de l'objet
    public $id;
    public $username;
    public $password;
    public $nom;
    public $role;
    public $email;
    public $telephone;
    public $date_creation;
    public $derniere_connexion;
    public $actif;
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour créer un nouvel utilisateur
    public function create() {
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                  SET username = :username, 
                      password = :password, 
                      nom = :nom, 
                      role = :role, 
                      email = :email, 
                      telephone = :telephone, 
                      actif = :actif";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telephone = htmlspecialchars(strip_tags($this->telephone));
        $this->actif = $this->actif ? 1 : 0;
        
        // Liaison des paramètres
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":telephone", $this->telephone);
        $stmt->bindParam(":actif", $this->actif);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Méthode pour vérifier si un utilisateur existe
    public function userExists() {
        // Requête pour vérifier si l'utilisateur existe
        $query = "SELECT id, username, password, nom, role, actif 
                  FROM " . $this->table_name . " 
                  WHERE username = :username 
                  LIMIT 0,1";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->username = htmlspecialchars(strip_tags($this->username));
        
        // Liaison des paramètres
        $stmt->bindParam(":username", $this->username);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération du nombre de lignes
        $num = $stmt->rowCount();
        
        // Si l'utilisateur existe
        if($num > 0) {
            // Récupération des détails
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Assignation des valeurs
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->password = $row['password']; // Mot de passe hashé
            $this->nom = $row['nom'];
            $this->role = $row['role'];
            $this->actif = $row['actif'];
            
            return true;
        }
        
        return false;
    }
    
    // Méthode pour mettre à jour la dernière connexion
    public function updateLastLogin() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                  SET derniere_connexion = NOW() 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Méthode pour lire tous les utilisateurs
    public function readAll() {
        // Requête de sélection
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nom ASC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour lire un utilisateur
    public function readOne() {
        // Requête de sélection
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération de la ligne
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Assignation des valeurs
        if($row) {
            $this->username = $row['username'];
            $this->nom = $row['nom'];
            $this->role = $row['role'];
            $this->email = $row['email'];
            $this->telephone = $row['telephone'];
            $this->date_creation = $row['date_creation'];
            $this->derniere_connexion = $row['derniere_connexion'];
            $this->actif = $row['actif'];
            return true;
        }
        
        return false;
    }
    
    // Méthode pour mettre à jour un utilisateur
    public function update() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom, 
                      role = :role, 
                      email = :email, 
                      telephone = :telephone, 
                      actif = :actif 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telephone = htmlspecialchars(strip_tags($this->telephone));
        $this->actif = $this->actif ? 1 : 0;
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Liaison des paramètres
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":telephone", $this->telephone);
        $stmt->bindParam(":actif", $this->actif);
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Méthode pour mettre à jour le mot de passe
    public function updatePassword() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                  SET password = :password 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Hashage du mot de passe
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Liaison des paramètres
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Méthode pour supprimer un utilisateur
    public function delete() {
        // Requête de suppression
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Liaison des paramètres
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>
