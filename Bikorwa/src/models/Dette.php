<?php
/**
 * Modèle Dette pour la gestion des dettes
 * BIKORWA SHOP
 */

class Dette {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "dettes";
    private $table_paiements = "paiements_dettes";
    
    // Propriétés de l'objet
    public $id;
    public $client_id;
    public $vente_id;
    public $montant_initial;
    public $montant_restant;
    public $date_creation;
    public $date_echeance;
    public $statut;
    public $note;
    
    // Propriétés liées
    public $client_nom;
    public $numero_facture;
    public $paiements = [];
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour créer une nouvelle dette
    public function create() {
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                  SET client_id = :client_id, 
                      vente_id = :vente_id, 
                      montant_initial = :montant_initial, 
                      montant_restant = :montant_restant, 
                      date_echeance = :date_echeance, 
                      statut = :statut, 
                      note = :note";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->vente_id = $this->vente_id ? htmlspecialchars(strip_tags($this->vente_id)) : null;
        $this->montant_initial = htmlspecialchars(strip_tags($this->montant_initial));
        $this->montant_restant = htmlspecialchars(strip_tags($this->montant_restant));
        $this->date_echeance = $this->date_echeance ? htmlspecialchars(strip_tags($this->date_echeance)) : null;
        $this->statut = htmlspecialchars(strip_tags($this->statut));
        $this->note = htmlspecialchars(strip_tags($this->note));
        
        // Liaison des paramètres
        $stmt->bindParam(":client_id", $this->client_id);
        $stmt->bindParam(":vente_id", $this->vente_id);
        $stmt->bindParam(":montant_initial", $this->montant_initial);
        $stmt->bindParam(":montant_restant", $this->montant_restant);
        $stmt->bindParam(":date_echeance", $this->date_echeance);
        $stmt->bindParam(":statut", $this->statut);
        $stmt->bindParam(":note", $this->note);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    // Méthode pour enregistrer un paiement
    public function enregistrerPaiement($montant, $utilisateur_id, $methode_paiement, $reference = null, $note = null) {
        // Commencer une transaction
        $this->conn->beginTransaction();
        
        try {
            // Vérifier si la dette existe
            $this->readOne();
            
            if(!$this->id) {
                return false;
            }
            
            // Vérifier si le montant est valide
            if($montant <= 0 || $montant > $this->montant_restant) {
                return false;
            }
            
            // Insérer le paiement
            $query = "INSERT INTO " . $this->table_paiements . " 
                      SET dette_id = :dette_id, 
                          montant = :montant, 
                          utilisateur_id = :utilisateur_id, 
                          methode_paiement = :methode_paiement, 
                          reference = :reference, 
                          note = :note";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":dette_id", $this->id);
            $stmt->bindParam(":montant", $montant);
            $stmt->bindParam(":utilisateur_id", $utilisateur_id);
            $stmt->bindParam(":methode_paiement", $methode_paiement);
            $stmt->bindParam(":reference", $reference);
            $stmt->bindParam(":note", $note);
            $stmt->execute();
            
            // Mettre à jour le montant restant et le statut de la dette
            $nouveau_montant_restant = $this->montant_restant - $montant;
            $nouveau_statut = 'active';
            
            if($nouveau_montant_restant <= 0) {
                $nouveau_statut = 'payee';
                $nouveau_montant_restant = 0;
            } else {
                $nouveau_statut = 'partiellement_payee';
            }
            
            $query = "UPDATE " . $this->table_name . " 
                      SET montant_restant = :montant_restant, 
                          statut = :statut 
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":montant_restant", $nouveau_montant_restant);
            $stmt->bindParam(":statut", $nouveau_statut);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();
            
            // Si la dette est liée à une vente, mettre à jour le statut de paiement de la vente
            if($this->vente_id) {
                $query = "SELECT montant_total FROM ventes WHERE id = :vente_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":vente_id", $this->vente_id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $montant_total = $row['montant_total'];
                
                $query = "SELECT SUM(montant) as total_paye FROM " . $this->table_paiements . " 
                          WHERE dette_id IN (SELECT id FROM " . $this->table_name . " WHERE vente_id = :vente_id)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":vente_id", $this->vente_id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_paye = $row['total_paye'] ?? 0;
                
                $statut_paiement = 'credit';
                if($total_paye >= $montant_total) {
                    $statut_paiement = 'paye';
                } else if($total_paye > 0) {
                    $statut_paiement = 'partiel';
                }
                
                $query = "UPDATE ventes 
                          SET montant_paye = :montant_paye, 
                              statut_paiement = :statut_paiement 
                          WHERE id = :vente_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":montant_paye", $total_paye);
                $stmt->bindParam(":statut_paiement", $statut_paiement);
                $stmt->bindParam(":vente_id", $this->vente_id);
                $stmt->execute();
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }
    
    // Méthode pour lire toutes les dettes
    public function readAll($client_id = null, $statut = null) {
        // Construction de la requête de base
        $query = "SELECT d.*, c.nom as client_nom, v.numero_facture 
                  FROM " . $this->table_name . " d
                  LEFT JOIN clients c ON d.client_id = c.id
                  LEFT JOIN ventes v ON d.vente_id = v.id
                  WHERE 1=1";
        
        // Ajout des conditions de filtrage
        if($client_id) {
            $query .= " AND d.client_id = :client_id";
        }
        
        if($statut) {
            $query .= " AND d.statut = :statut";
        }
        
        // Tri
        $query .= " ORDER BY d.date_creation DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($client_id) {
            $stmt->bindParam(":client_id", $client_id);
        }
        
        if($statut) {
            $stmt->bindParam(":statut", $statut);
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour lire une dette
    public function readOne() {
        // Requête de sélection
        $query = "SELECT d.*, c.nom as client_nom, v.numero_facture 
                  FROM " . $this->table_name . " d
                  LEFT JOIN clients c ON d.client_id = c.id
                  LEFT JOIN ventes v ON d.vente_id = v.id
                  WHERE d.id = :id
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
            $this->client_id = $row['client_id'];
            $this->vente_id = $row['vente_id'];
            $this->montant_initial = $row['montant_initial'];
            $this->montant_restant = $row['montant_restant'];
            $this->date_creation = $row['date_creation'];
            $this->date_echeance = $row['date_echeance'];
            $this->statut = $row['statut'];
            $this->note = $row['note'];
            $this->client_nom = $row['client_nom'];
            $this->numero_facture = $row['numero_facture'];
            
            // Récupérer les paiements
            $this->getPaiements();
            
            return true;
        }
        
        return false;
    }
    
    // Méthode pour récupérer les paiements d'une dette
    public function getPaiements() {
        // Requête de sélection
        $query = "SELECT p.*, u.nom as utilisateur_nom 
                  FROM " . $this->table_paiements . " p
                  LEFT JOIN users u ON p.utilisateur_id = u.id
                  WHERE p.dette_id = :dette_id
                  ORDER BY p.date_paiement DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":dette_id", $this->id);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération des résultats
        $this->paiements = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->paiements[] = $row;
        }
        
        return $this->paiements;
    }
    
    // Méthode pour annuler une dette
    public function annuler($utilisateur_id, $note = null) {
        // Vérifier si la dette existe
        $this->readOne();
        
        if(!$this->id || $this->statut == 'payee' || $this->statut == 'annulee') {
            return false;
        }
        
        // Mettre à jour le statut de la dette
        $query = "UPDATE " . $this->table_name . " 
                  SET statut = 'annulee', 
                      note = CONCAT(note, ' | Annulée le ', NOW(), ' par utilisateur #', :utilisateur_id, '. Raison: ', :note)
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":utilisateur_id", $utilisateur_id);
        $stmt->bindParam(":note", $note);
        $stmt->bindParam(":id", $this->id);
        
        return $stmt->execute();
    }
    
    // Méthode pour obtenir le total des dettes
    public function getTotalDettes($statut = null) {
        // Construction de la requête
        $query = "SELECT SUM(montant_restant) as total 
                  FROM " . $this->table_name . " 
                  WHERE statut != 'payee' AND statut != 'annulee'";
        
        if($statut) {
            $query .= " AND statut = :statut";
        }
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($statut) {
            $stmt->bindParam(":statut", $statut);
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération du résultat
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ?? 0;
    }
    
    // Méthode pour obtenir les dettes par client
    public function getDettesParClient() {
        // Requête de sélection
        $query = "SELECT c.id, c.nom as client_nom, 
                  COUNT(d.id) as nombre_dettes, 
                  SUM(d.montant_initial) as montant_initial_total, 
                  SUM(d.montant_restant) as montant_restant_total 
                  FROM clients c
                  LEFT JOIN " . $this->table_name . " d ON c.id = d.client_id AND d.statut != 'payee' AND d.statut != 'annulee'
                  GROUP BY c.id
                  HAVING montant_restant_total > 0
                  ORDER BY montant_restant_total DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
}
?>
