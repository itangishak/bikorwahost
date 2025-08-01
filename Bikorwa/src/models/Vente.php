<?php
/**
 * Modèle Vente pour la gestion des ventes
 * BIKORWA SHOP
 */

class Vente {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "ventes";
    private $table_details = "details_ventes";
    
    // Propriétés de l'objet
    public $id;
    public $numero_facture;
    public $date_vente;
    public $client_id;
    public $utilisateur_id;
    public $montant_total;
    public $montant_paye;
    public $statut_paiement;
    public $note;
    
    // Propriétés liées
    public $client_nom;
    public $utilisateur_nom;
    public $details = [];
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour créer une nouvelle vente
    public function create() {
        // Commencer une transaction
        $this->conn->beginTransaction();
        
        try {
            // Générer un numéro de facture unique
            $this->numero_facture = $this->genererNumeroFacture();
            
            // Requête d'insertion
            $query = "INSERT INTO " . $this->table_name . " 
                      SET numero_facture = :numero_facture, 
                          date_vente = NOW(), 
                          client_id = :client_id, 
                          utilisateur_id = :utilisateur_id, 
                          montant_total = :montant_total, 
                          montant_paye = :montant_paye, 
                          statut_paiement = :statut_paiement, 
                          note = :note";
            
            // Préparation de la requête
            $stmt = $this->conn->prepare($query);
            
            // Nettoyage des données
            $this->numero_facture = htmlspecialchars(strip_tags($this->numero_facture));
            $this->client_id = $this->client_id ? htmlspecialchars(strip_tags($this->client_id)) : null;
            $this->utilisateur_id = htmlspecialchars(strip_tags($this->utilisateur_id));
            $this->montant_total = htmlspecialchars(strip_tags($this->montant_total));
            $this->montant_paye = htmlspecialchars(strip_tags($this->montant_paye));
            $this->statut_paiement = htmlspecialchars(strip_tags($this->statut_paiement));
            $this->note = htmlspecialchars(strip_tags($this->note));
            
            // Liaison des paramètres
            $stmt->bindParam(":numero_facture", $this->numero_facture);
            $stmt->bindParam(":client_id", $this->client_id);
            $stmt->bindParam(":utilisateur_id", $this->utilisateur_id);
            $stmt->bindParam(":montant_total", $this->montant_total);
            $stmt->bindParam(":montant_paye", $this->montant_paye);
            $stmt->bindParam(":statut_paiement", $this->statut_paiement);
            $stmt->bindParam(":note", $this->note);
            
            // Exécution de la requête
            $stmt->execute();
            
            // Récupérer l'ID de la vente
            $this->id = $this->conn->lastInsertId();
            
            // Si la vente est à crédit, créer une dette
            if($this->statut_paiement == 'credit' || $this->statut_paiement == 'partiel') {
                $montant_restant = $this->montant_total - $this->montant_paye;

                if($montant_restant > 0 && $this->client_id) {
                    $query = "INSERT INTO dettes
                              SET client_id = :client_id,
                                  vente_id = :vente_id,
                                  montant_initial = :montant_initial,
                                  montant_restant = :montant_restant,
                                  statut = :statut";

                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(":client_id", $this->client_id);
                    $stmt->bindParam(":vente_id", $this->id);
                    $stmt->bindParam(":montant_initial", $montant_restant);
                    $stmt->bindParam(":montant_restant", $montant_restant);
                    $statut = ($this->statut_paiement == 'partiel') ? 'partiellement_payee' : 'active';
                    $stmt->bindParam(":statut", $statut);
                    $stmt->execute();
                }
            }

            // Traiter les détails de la vente avec la logique FIFO
            foreach ($this->details as $detail) {
                $produit_id = $detail['produit_id'];
                $quantite = $detail['quantite'];
                $prix_vente = $detail['prix_unitaire'];

                if ($quantite <= 0) {
                    throw new Exception('Quantité invalide pour le produit ID: ' . $produit_id);
                }

                // Récupérer les lots de stock disponibles (FIFO)
                $queryStock = "SELECT id, quantity_remaining, prix_unitaire
                               FROM mouvements_stock
                               WHERE produit_id = :produit_id
                                 AND type_mouvement = 'entree'
                                 AND quantity_remaining > 0
                               ORDER BY date_mouvement ASC";
                $stmtStock = $this->conn->prepare($queryStock);
                $stmtStock->bindParam(':produit_id', $produit_id);
                $stmtStock->execute();
                $batches = $stmtStock->fetchAll(PDO::FETCH_ASSOC);

                $totalDisponible = 0;
                foreach ($batches as $batch) {
                    $totalDisponible += $batch['quantity_remaining'];
                }

                if ($totalDisponible < $quantite) {
                    throw new Exception('Stock insuffisant pour le produit ID: ' . $produit_id);
                }

                $quantite_restante = $quantite;
                $cout_total = 0;
                $lots_utilises = [];

                foreach ($batches as $batch) {
                    if ($quantite_restante <= 0) break;

                    $utilise = min($batch['quantity_remaining'], $quantite_restante);
                    $cout_total += $utilise * $batch['prix_unitaire'];
                    $lots_utilises[] = [
                        'id' => $batch['id'],
                        'quantite_restante' => $batch['quantity_remaining'] - $utilise
                    ];
                    $quantite_restante -= $utilise;
                }

                $prix_achat_moyen = $cout_total / $quantite;
                $montant_produit = $quantite * $prix_vente;
                $benefice = $montant_produit - ($quantite * $prix_achat_moyen);

                // Insérer le détail de vente
                $queryDetail = "INSERT INTO " . $this->table_details . "
                               SET vente_id = :vente_id,
                                   produit_id = :produit_id,
                                   quantite = :quantite,
                                   prix_unitaire = :prix_unitaire,
                                   montant_total = :montant_total,
                                   prix_achat_unitaire = :prix_achat_unitaire,
                                   benefice = :benefice";
                $stmtDetail = $this->conn->prepare($queryDetail);
                $stmtDetail->bindParam(':vente_id', $this->id);
                $stmtDetail->bindParam(':produit_id', $produit_id);
                $stmtDetail->bindParam(':quantite', $quantite);
                $stmtDetail->bindParam(':prix_unitaire', $prix_vente);
                $stmtDetail->bindParam(':montant_total', $montant_produit);
                $stmtDetail->bindParam(':prix_achat_unitaire', $prix_achat_moyen);
                $stmtDetail->bindParam(':benefice', $benefice);
                $stmtDetail->execute();

                // Mettre à jour les quantités restantes des lots
                foreach ($lots_utilises as $lot) {
                    $queryUpdate = "UPDATE mouvements_stock
                                    SET quantity_remaining = :quantity_remaining
                                    WHERE id = :id";
                    $stmtUpdate = $this->conn->prepare($queryUpdate);
                    $stmtUpdate->bindParam(':quantity_remaining', $lot['quantite_restante']);
                    $stmtUpdate->bindParam(':id', $lot['id']);
                    $stmtUpdate->execute();
                }

                // Enregistrer le mouvement de sortie
                $reference = "Vente #" . $this->numero_facture;
                $valeur_totale = $montant_produit;
                $querySortie = "INSERT INTO mouvements_stock
                                 (produit_id, type_mouvement, quantite, prix_unitaire, valeur_totale,
                                  reference, utilisateur_id, note, quantity_remaining)
                                 VALUES (:produit_id, 'sortie', :quantite, :prix_unitaire, :valeur_totale,
                                         :reference, :utilisateur_id, :note, 0)";
                $stmtSortie = $this->conn->prepare($querySortie);
                $stmtSortie->bindParam(':produit_id', $produit_id);
                $stmtSortie->bindParam(':quantite', $quantite);
                $stmtSortie->bindParam(':prix_unitaire', $prix_vente);
                $stmtSortie->bindParam(':valeur_totale', $valeur_totale);
                $stmtSortie->bindParam(':reference', $reference);
                $stmtSortie->bindParam(':utilisateur_id', $this->utilisateur_id);
                $stmtSortie->bindParam(':note', $this->note);
                $stmtSortie->execute();

                // Mettre à jour le stock global
                $queryStockUpdate = "UPDATE stock
                                     SET quantite = quantite - :quantite,
                                         date_mise_a_jour = NOW()
                                     WHERE produit_id = :produit_id";
                $stmtStockUpdate = $this->conn->prepare($queryStockUpdate);
                $stmtStockUpdate->bindParam(':quantite', $quantite);
                $stmtStockUpdate->bindParam(':produit_id', $produit_id);
                $stmtStockUpdate->execute();
            }

            // Valider la transaction
            $this->conn->commit();

            return $this->id;
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }
    
    // Méthode pour ajouter un détail de vente
    public function ajouterDetail($produit_id, $quantite, $prix_unitaire, $prix_achat_unitaire) {
        // Calculer le montant total et le bénéfice
        $montant_total = $quantite * $prix_unitaire;
        $benefice = $montant_total - ($quantite * $prix_achat_unitaire);
        
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_details . " 
                  SET vente_id = :vente_id, 
                      produit_id = :produit_id, 
                      quantite = :quantite, 
                      prix_unitaire = :prix_unitaire, 
                      montant_total = :montant_total, 
                      prix_achat_unitaire = :prix_achat_unitaire, 
                      benefice = :benefice";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":vente_id", $this->id);
        $stmt->bindParam(":produit_id", $produit_id);
        $stmt->bindParam(":quantite", $quantite);
        $stmt->bindParam(":prix_unitaire", $prix_unitaire);
        $stmt->bindParam(":montant_total", $montant_total);
        $stmt->bindParam(":prix_achat_unitaire", $prix_achat_unitaire);
        $stmt->bindParam(":benefice", $benefice);
        
        // Exécution de la requête
        if($stmt->execute()) {
            // Mettre à jour le stock
            $stock = new Stock($this->conn);
            $stock->retirerStock(
                $produit_id, 
                $quantite, 
                $prix_achat_unitaire, 
                "Vente #" . $this->numero_facture, 
                $this->utilisateur_id
            );
            
            return true;
        }
        
        return false;
    }
    
    // Méthode pour lire toutes les ventes
    public function readAll($date_debut = null, $date_fin = null, $client_id = null) {
        // Construction de la requête de base
        $query = "SELECT v.*, c.nom as client_nom, u.nom as utilisateur_nom 
                  FROM " . $this->table_name . " v
                  LEFT JOIN clients c ON v.client_id = c.id
                  LEFT JOIN users u ON v.utilisateur_id = u.id
                  WHERE 1=1";
        
        // Ajout des conditions de filtrage
        if($date_debut) {
            $query .= " AND DATE(v.date_vente) >= :date_debut";
        }
        
        if($date_fin) {
            $query .= " AND DATE(v.date_vente) <= :date_fin";
        }
        
        if($client_id) {
            $query .= " AND v.client_id = :client_id";
        }
        
        // Tri
        $query .= " ORDER BY v.date_vente DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($date_debut) {
            $stmt->bindParam(":date_debut", $date_debut);
        }
        
        if($date_fin) {
            $stmt->bindParam(":date_fin", $date_fin);
        }
        
        if($client_id) {
            $stmt->bindParam(":client_id", $client_id);
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour lire une vente
    public function readOne() {
        // Requête de sélection
        $query = "SELECT v.*, c.nom as client_nom, u.nom as utilisateur_nom 
                  FROM " . $this->table_name . " v
                  LEFT JOIN clients c ON v.client_id = c.id
                  LEFT JOIN users u ON v.utilisateur_id = u.id
                  WHERE v.id = :id
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
            $this->numero_facture = $row['numero_facture'];
            $this->date_vente = $row['date_vente'];
            $this->client_id = $row['client_id'];
            $this->utilisateur_id = $row['utilisateur_id'];
            $this->montant_total = $row['montant_total'];
            $this->montant_paye = $row['montant_paye'];
            $this->statut_paiement = $row['statut_paiement'];
            $this->note = $row['note'];
            $this->client_nom = $row['client_nom'];
            $this->utilisateur_nom = $row['utilisateur_nom'];
            
            // Récupérer les détails de la vente
            $this->getDetails();
            
            return true;
        }
        
        return false;
    }
    
    // Méthode pour récupérer les détails d'une vente
    public function getDetails() {
        // Requête de sélection
        $query = "SELECT d.*, p.nom as produit_nom, p.unite_mesure 
                  FROM " . $this->table_details . " d
                  LEFT JOIN produits p ON d.produit_id = p.id
                  WHERE d.vente_id = :vente_id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(":vente_id", $this->id);
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération des résultats
        $this->details = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->details[] = $row;
        }
        
        return $this->details;
    }
    
    // Méthode pour générer un numéro de facture unique
    private function genererNumeroFacture() {
        $prefix = "FAC-";
        $date = date("Ymd");
        $random = mt_rand(1000, 9999);
        
        return $prefix . $date . "-" . $random;
    }
    
    // Méthode pour obtenir les statistiques de vente
    public function getStatistiques($date_debut = null, $date_fin = null) {
        // Construction de la requête de base
        $query = "SELECT 
                  COUNT(*) as nombre_ventes,
                  SUM(montant_total) as chiffre_affaires,
                  SUM(montant_paye) as montant_encaisse,
                  SUM(montant_total - montant_paye) as montant_credit,
                  (SELECT SUM(benefice) FROM " . $this->table_details . " WHERE vente_id IN (SELECT id FROM " . $this->table_name;
        
        // Conditions pour les détails
        $conditions = " WHERE 1=1";
        if($date_debut) {
            $conditions .= " AND DATE(date_vente) >= :date_debut";
        }
        
        if($date_fin) {
            $conditions .= " AND DATE(date_vente) <= :date_fin";
        }
        
        $query .= $conditions . ")) as benefice_total
                  FROM " . $this->table_name;
        
        // Conditions pour les ventes
        $query .= $conditions;
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($date_debut) {
            $stmt->bindParam(":date_debut", $date_debut);
            if($date_fin) {
                $stmt->bindParam(":date_debut", $date_debut, PDO::PARAM_STR, 10);
            }
        }
        
        if($date_fin) {
            $stmt->bindParam(":date_fin", $date_fin);
            if($date_debut) {
                $stmt->bindParam(":date_fin", $date_fin, PDO::PARAM_STR, 10);
            }
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        // Récupération du résultat
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Méthode pour obtenir les ventes par produit
    public function getVentesParProduit($date_debut = null, $date_fin = null) {
        // Construction de la requête
        $query = "SELECT p.id, p.nom as produit_nom, p.unite_mesure,
                  SUM(d.quantite) as quantite_totale,
                  SUM(d.montant_total) as montant_total,
                  SUM(d.benefice) as benefice_total
                  FROM " . $this->table_details . " d
                  LEFT JOIN produits p ON d.produit_id = p.id
                  LEFT JOIN " . $this->table_name . " v ON d.vente_id = v.id
                  WHERE 1=1";
        
        // Ajout des conditions de filtrage
        if($date_debut) {
            $query .= " AND DATE(v.date_vente) >= :date_debut";
        }
        
        if($date_fin) {
            $query .= " AND DATE(v.date_vente) <= :date_fin";
        }
        
        // Groupement et tri
        $query .= " GROUP BY p.id
                   ORDER BY montant_total DESC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        if($date_debut) {
            $stmt->bindParam(":date_debut", $date_debut);
        }
        
        if($date_fin) {
            $stmt->bindParam(":date_fin", $date_fin);
        }
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
}
?>
