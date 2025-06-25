<?php
/**
 * Modèle Produit pour la gestion des produits
 * BIKORWA SHOP
 */

class Produit {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "produits";
    
    // Propriétés de l'objet
    public $id;
    public $code;
    public $nom;
    public $description;
    public $categorie_id;
    public $unite_mesure;
    public $date_creation;
    public $actif;
    
    // Propriétés liées
    public $categorie_nom;
    public $prix_achat_actuel;
    public $prix_vente_actuel;
    public $quantite_stock;
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour créer un nouveau produit
    public function create() {
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                  SET code = :code, 
                      nom = :nom, 
                      description = :description, 
                      categorie_id = :categorie_id, 
                      unite_mesure = :unite_mesure, 
                      actif = :actif";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->code = htmlspecialchars(strip_tags($this->code));
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->categorie_id = htmlspecialchars(strip_tags($this->categorie_id));
        $this->unite_mesure = htmlspecialchars(strip_tags($this->unite_mesure));
        $this->actif = $this->actif ? 1 : 0;
        
        // Liaison des paramètres
        $stmt->bindParam(":code", $this->code);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":categorie_id", $this->categorie_id);
        $stmt->bindParam(":unite_mesure", $this->unite_mesure);
        $stmt->bindParam(":actif", $this->actif);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Méthode pour lire tous les produits
    public function readAll() {
        // Requête de sélection avec jointure
        $query = "SELECT p.*, c.nom as categorie_nom, 
                  (SELECT prix_achat FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_achat_actuel,
                  (SELECT prix_vente FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_vente_actuel,
                  (SELECT quantite FROM stock WHERE produit_id = p.id LIMIT 1) as quantite_stock
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  ORDER BY p.nom ASC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour lire un produit
    public function readOne() {
        // Requête de sélection avec jointure
        $query = "SELECT p.*, c.nom as categorie_nom, 
                  (SELECT prix_achat FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_achat_actuel,
                  (SELECT prix_vente FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_vente_actuel,
                  (SELECT quantite FROM stock WHERE produit_id = p.id LIMIT 1) as quantite_stock
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  WHERE p.id = :id
                  LIMIT 0,1";
        
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
            $this->code = $row['code'];
            $this->nom = $row['nom'];
            $this->description = $row['description'];
            $this->categorie_id = $row['categorie_id'];
            $this->unite_mesure = $row['unite_mesure'];
            $this->date_creation = $row['date_creation'];
            $this->actif = $row['actif'];
            $this->categorie_nom = $row['categorie_nom'];
            $this->prix_achat_actuel = $row['prix_achat_actuel'];
            $this->prix_vente_actuel = $row['prix_vente_actuel'];
            $this->quantite_stock = $row['quantite_stock'];
            return true;
        }
        
        return false;
    }
    
    // Méthode pour mettre à jour un produit
    public function update() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                  SET code = :code, 
                      nom = :nom, 
                      description = :description, 
                      categorie_id = :categorie_id, 
                      unite_mesure = :unite_mesure, 
                      actif = :actif 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->code = htmlspecialchars(strip_tags($this->code));
        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->categorie_id = htmlspecialchars(strip_tags($this->categorie_id));
        $this->unite_mesure = htmlspecialchars(strip_tags($this->unite_mesure));
        $this->actif = $this->actif ? 1 : 0;
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Liaison des paramètres
        $stmt->bindParam(":code", $this->code);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":categorie_id", $this->categorie_id);
        $stmt->bindParam(":unite_mesure", $this->unite_mesure);
        $stmt->bindParam(":actif", $this->actif);
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Méthode pour supprimer un produit
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
    
    // Méthode pour rechercher des produits
    public function search($keywords) {
        // Requête de recherche
        $query = "SELECT p.*, c.nom as categorie_nom, 
                  (SELECT prix_achat FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_achat_actuel,
                  (SELECT prix_vente FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_vente_actuel,
                  (SELECT quantite FROM stock WHERE produit_id = p.id LIMIT 1) as quantite_stock
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  WHERE p.nom LIKE :keywords OR p.code LIKE :keywords OR p.description LIKE :keywords
                  ORDER BY p.nom ASC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Liaison des paramètres
        $stmt->bindParam(":keywords", $keywords);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour définir le prix d'un produit
    public function setPrix($prix_achat, $prix_vente, $user_id) {
        // Commencer une transaction
        $this->conn->beginTransaction();
        
        try {
            // Mettre fin au prix actuel s'il existe
            $query = "UPDATE prix_produits 
                      SET date_fin = NOW() 
                      WHERE produit_id = :produit_id AND date_fin IS NULL";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":produit_id", $this->id);
            $stmt->execute();
            
            // Insérer le nouveau prix
            $query = "INSERT INTO prix_produits 
                      SET produit_id = :produit_id, 
                          prix_achat = :prix_achat, 
                          prix_vente = :prix_vente, 
                          cree_par = :cree_par";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":produit_id", $this->id);
            $stmt->bindParam(":prix_achat", $prix_achat);
            $stmt->bindParam(":prix_vente", $prix_vente);
            $stmt->bindParam(":cree_par", $user_id);
            $stmt->execute();
            
            // Valider la transaction
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }
    
    // Méthode pour obtenir l'historique des prix d'un produit
    public function getPrixHistory() {
        // Requête de sélection
        $query = "SELECT pp.*, u.nom as utilisateur_nom 
                  FROM prix_produits pp
                  LEFT JOIN users u ON pp.cree_par = u.id
                  WHERE pp.produit_id = :produit_id
                  ORDER BY pp.date_debut DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":produit_id", $this->id);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
}
?>
