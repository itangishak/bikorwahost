<?php
/**
 * Modèle Stock pour la gestion du stock
 * BIKORWA SHOP
 */

class Stock {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "stock";
    private $table_mouvements = "mouvements_stock";
    
    // Propriétés de l'objet
    public $id;
    public $produit_id;
    public $quantite;
    public $date_mise_a_jour;
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour obtenir le stock actuel d'un produit
    public function getStockProduit($produit_id) {
        // Requête de sélection
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE produit_id = :produit_id 
                  LIMIT 0,1";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":produit_id", $produit_id);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération de la ligne
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Assignation des valeurs
        if($row) {
            $this->id = $row['id'];
            $this->produit_id = $row['produit_id'];
            $this->quantite = $row['quantite'];
            $this->date_mise_a_jour = $row['date_mise_a_jour'];
            return true;
        }
        
        return false;
    }
    
    // Méthode pour ajouter du stock (entrée)
    public function ajouterStock($produit_id, $quantite, $prix_unitaire, $reference, $utilisateur_id, $note = null) {
        // Commencer une transaction
        $this->conn->beginTransaction();
        
        try {
            // Vérifier si le produit existe déjà dans le stock
            $this->produit_id = $produit_id;
            $existe = $this->getStockProduit($produit_id);
            
            if($existe) {
                // Mettre à jour le stock existant
                $query = "UPDATE " . $this->table_name . " 
                          SET quantite = quantite + :quantite, 
                              date_mise_a_jour = NOW() 
                          WHERE produit_id = :produit_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":quantite", $quantite);
                $stmt->bindParam(":produit_id", $produit_id);
                $stmt->execute();
            } else {
                // Créer une nouvelle entrée de stock
                $query = "INSERT INTO " . $this->table_name . " 
                          SET produit_id = :produit_id, 
                              quantite = :quantite, 
                              date_mise_a_jour = NOW()";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":produit_id", $produit_id);
                $stmt->bindParam(":quantite", $quantite);
                $stmt->execute();
            }
            
            // Calculer la valeur totale
            $valeur_totale = $quantite * $prix_unitaire;
            
            // Enregistrer le mouvement de stock
            $query = "INSERT INTO " . $this->table_mouvements . " 
                      SET produit_id = :produit_id, 
                          type_mouvement = 'entree', 
                          quantite = :quantite, 
                          prix_unitaire = :prix_unitaire, 
                          valeur_totale = :valeur_totale, 
                          reference = :reference, 
                          utilisateur_id = :utilisateur_id, 
                          note = :note";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":produit_id", $produit_id);
            $stmt->bindParam(":quantite", $quantite);
            $stmt->bindParam(":prix_unitaire", $prix_unitaire);
            $stmt->bindParam(":valeur_totale", $valeur_totale);
            $stmt->bindParam(":reference", $reference);
            $stmt->bindParam(":utilisateur_id", $utilisateur_id);
            $stmt->bindParam(":note", $note);
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
    
    // Méthode pour retirer du stock (sortie)
    public function retirerStock($produit_id, $quantite, $prix_unitaire, $reference, $utilisateur_id, $note = null) {
        // Commencer une transaction
        $this->conn->beginTransaction();
        
        try {
            // Vérifier si le produit existe dans le stock
            $this->produit_id = $produit_id;
            $existe = $this->getStockProduit($produit_id);
            
            if(!$existe || $this->quantite < $quantite) {
                // Stock insuffisant
                return false;
            }
            
            // Mettre à jour le stock
            $query = "UPDATE " . $this->table_name . " 
                      SET quantite = quantite - :quantite, 
                          date_mise_a_jour = NOW() 
                      WHERE produit_id = :produit_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":quantite", $quantite);
            $stmt->bindParam(":produit_id", $produit_id);
            $stmt->execute();
            
            // Calculer la valeur totale
            $valeur_totale = $quantite * $prix_unitaire;
            
            // Enregistrer le mouvement de stock
            $query = "INSERT INTO " . $this->table_mouvements . " 
                      SET produit_id = :produit_id, 
                          type_mouvement = 'sortie', 
                          quantite = :quantite, 
                          prix_unitaire = :prix_unitaire, 
                          valeur_totale = :valeur_totale, 
                          reference = :reference, 
                          utilisateur_id = :utilisateur_id, 
                          note = :note";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":produit_id", $produit_id);
            $stmt->bindParam(":quantite", $quantite);
            $stmt->bindParam(":prix_unitaire", $prix_unitaire);
            $stmt->bindParam(":valeur_totale", $valeur_totale);
            $stmt->bindParam(":reference", $reference);
            $stmt->bindParam(":utilisateur_id", $utilisateur_id);
            $stmt->bindParam(":note", $note);
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
    
    // Méthode pour obtenir tous les mouvements de stock d'un produit
    public function getMouvementsProduit($produit_id) {
        // Requête de sélection
        $query = "SELECT ms.*, u.nom as utilisateur_nom, p.nom as produit_nom 
                  FROM " . $this->table_mouvements . " ms
                  LEFT JOIN users u ON ms.utilisateur_id = u.id
                  LEFT JOIN produits p ON ms.produit_id = p.id
                  WHERE ms.produit_id = :produit_id
                  ORDER BY ms.date_mouvement DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":produit_id", $produit_id);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour obtenir tous les mouvements de stock
    public function getAllMouvements($date_debut = null, $date_fin = null, $type = null) {
        // Construction de la requête de base
        $query = "SELECT ms.*, u.nom as utilisateur_nom, p.nom as produit_nom 
                  FROM " . $this->table_mouvements . " ms
                  LEFT JOIN users u ON ms.utilisateur_id = u.id
                  LEFT JOIN produits p ON ms.produit_id = p.id
                  WHERE 1=1";
        
        // Ajout des conditions de filtrage
        if($date_debut) {
            $query .= " AND ms.date_mouvement >= :date_debut";
        }
        
        if($date_fin) {
            $query .= " AND ms.date_mouvement <= :date_fin";
        }
        
        if($type) {
            $query .= " AND ms.type_mouvement = :type";
        }
        
        // Tri
        $query .= " ORDER BY ms.date_mouvement DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($date_debut) {
            $stmt->bindParam(":date_debut", $date_debut);
        }
        
        if($date_fin) {
            $stmt->bindParam(":date_fin", $date_fin);
        }
        
        if($type) {
            $stmt->bindParam(":type", $type);
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour obtenir la liste des produits en stock faible
    public function getStockFaible($seuil = 10) {
        // Définir le seuil en fonction de la catégorie
        $thresholdCase = "CASE
                WHEN c.nom IN ('Spiritueux','Vins') THEN 1
                WHEN c.nom = 'Sodas' THEN 15
                WHEN c.nom = 'Bières' THEN 10
                ELSE " . intval($seuil) . "
            END";

        // Requête de sélection
        $query = "SELECT s.*, p.nom as produit_nom, p.code as produit_code, p.unite_mesure,
                  c.nom as categorie_nom,
                  (SELECT prix_achat FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_achat_actuel,
                  (SELECT prix_vente FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_vente_actuel
                  FROM " . $this->table_name . " s
                  LEFT JOIN produits p ON s.produit_id = p.id
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  WHERE s.quantite <= $thresholdCase AND p.actif = 1
                  ORDER BY s.quantite ASC";

        // Préparation de la requête
        $stmt = $this->conn->prepare($query);

        // Exécution de la requête
        $stmt->execute();

        return $stmt;
    }
    
    // Méthode pour obtenir la valeur totale du stock
    public function getValeurTotaleStock() {
        // Requête de sélection
        $query = "SELECT SUM(s.quantite * pp.prix_achat) as valeur_totale
                  FROM " . $this->table_name . " s
                  LEFT JOIN produits p ON s.produit_id = p.id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération du résultat
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['valeur_totale'] ?? 0;
    }
}
?>
