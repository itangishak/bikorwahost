<?php
/**
 * Classe Rapport pour la génération de rapports et analyses
 * BIKORWA SHOP
 */

class Rapport {
    // Propriétés de la base de données
    private $conn;
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Méthode pour obtenir le tableau de bord général
    public function getDashboard() {
        $result = [];
        
        // Statistiques des ventes du jour
        $query = "SELECT 
                  COUNT(*) as nombre_ventes,
                  SUM(montant_total) as chiffre_affaires,
                  SUM(montant_paye) as montant_encaisse,
                  (SELECT SUM(benefice) FROM details_ventes WHERE vente_id IN 
                    (SELECT id FROM ventes WHERE DATE(date_vente) = CURDATE())
                  ) as benefice_total
                  FROM ventes
                  WHERE DATE(date_vente) = CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['ventes_jour'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Statistiques des ventes du mois
        $query = "SELECT 
                  COUNT(*) as nombre_ventes,
                  SUM(montant_total) as chiffre_affaires,
                  SUM(montant_paye) as montant_encaisse,
                  (SELECT SUM(benefice) FROM details_ventes WHERE vente_id IN 
                    (SELECT id FROM ventes WHERE MONTH(date_vente) = MONTH(CURDATE()) AND YEAR(date_vente) = YEAR(CURDATE()))
                  ) as benefice_total
                  FROM ventes
                  WHERE MONTH(date_vente) = MONTH(CURDATE()) AND YEAR(date_vente) = YEAR(CURDATE())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['ventes_mois'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Produits les plus vendus du mois
        $query = "SELECT p.id, p.nom as produit_nom, p.unite_mesure,
                  SUM(d.quantite) as quantite_totale,
                  SUM(d.montant_total) as montant_total,
                  SUM(d.benefice) as benefice_total
                  FROM details_ventes d
                  LEFT JOIN produits p ON d.produit_id = p.id
                  LEFT JOIN ventes v ON d.vente_id = v.id
                  WHERE MONTH(v.date_vente) = MONTH(CURDATE()) AND YEAR(v.date_vente) = YEAR(CURDATE())
                  GROUP BY p.id
                  ORDER BY montant_total DESC
                  LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['top_produits'] = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['top_produits'][] = $row;
        }
        
        // Valeur du stock
        $query = "SELECT SUM(s.quantite * pp.prix_achat) as valeur_stock
                  FROM stock s
                  LEFT JOIN produits p ON s.produit_id = p.id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['valeur_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['valeur_stock'] ?? 0;
        
        // Produits en stock faible
        $query = "SELECT s.*, p.nom as produit_nom, p.code as produit_code, p.unite_mesure,
                  (SELECT prix_achat FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_achat_actuel,
                  (SELECT prix_vente FROM prix_produits WHERE produit_id = p.id AND date_fin IS NULL LIMIT 1) as prix_vente_actuel
                  FROM stock s
                  LEFT JOIN produits p ON s.produit_id = p.id
                  WHERE s.quantite <= 10 AND p.actif = 1
                  ORDER BY s.quantite ASC
                  LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['stock_faible'] = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['stock_faible'][] = $row;
        }
        
        // Total des dettes
        $query = "SELECT SUM(montant_restant) as total_dettes
                  FROM dettes
                  WHERE statut != 'payee' AND statut != 'annulee'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['total_dettes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_dettes'] ?? 0;
        
        // Clients avec les plus grandes dettes
        $query = "SELECT c.id, c.nom as client_nom, 
                  COUNT(d.id) as nombre_dettes, 
                  SUM(d.montant_restant) as montant_restant_total 
                  FROM clients c
                  LEFT JOIN dettes d ON c.id = d.client_id AND d.statut != 'payee' AND d.statut != 'annulee'
                  GROUP BY c.id
                  HAVING montant_restant_total > 0
                  ORDER BY montant_restant_total DESC
                  LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result['top_dettes'] = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result['top_dettes'][] = $row;
        }
        
        return $result;
    }
    
    // Méthode pour obtenir le rapport des ventes
    public function getRapportVentes($date_debut, $date_fin, $format = 'array') {
        // Statistiques générales
        $query = "SELECT 
                  COUNT(*) as nombre_ventes,
                  SUM(montant_total) as chiffre_affaires,
                  SUM(montant_paye) as montant_encaisse,
                  SUM(montant_total - montant_paye) as montant_credit,
                  (SELECT SUM(benefice) FROM details_ventes WHERE vente_id IN 
                    (SELECT id FROM ventes WHERE date_vente BETWEEN :date_debut_1 AND :date_fin_1)
                  ) as benefice_total
                  FROM ventes
                  WHERE date_vente BETWEEN :date_debut_2 AND :date_fin_2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date_debut_1", $date_debut);
        $stmt->bindParam(":date_fin_1", $date_fin);
        $stmt->bindParam(":date_debut_2", $date_debut);
        $stmt->bindParam(":date_fin_2", $date_fin);
        $stmt->execute();
        $statistiques = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ventes par produit
        $query = "SELECT p.id, p.nom as produit_nom, p.unite_mesure,
                  SUM(d.quantite) as quantite_totale,
                  SUM(d.montant_total) as montant_total,
                  SUM(d.benefice) as benefice_total,
                  AVG(d.prix_unitaire) as prix_moyen
                  FROM details_ventes d
                  LEFT JOIN produits p ON d.produit_id = p.id
                  LEFT JOIN ventes v ON d.vente_id = v.id
                  WHERE v.date_vente BETWEEN :date_debut AND :date_fin
                  GROUP BY p.id
                  ORDER BY montant_total DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date_debut", $date_debut);
        $stmt->bindParam(":date_fin", $date_fin);
        $stmt->execute();
        $ventes_par_produit = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ventes_par_produit[] = $row;
        }
        
        // Ventes par jour
        $query = "SELECT DATE(date_vente) as jour,
                  COUNT(*) as nombre_ventes,
                  SUM(montant_total) as chiffre_affaires,
                  SUM(montant_paye) as montant_encaisse,
                  (SELECT SUM(benefice) FROM details_ventes WHERE vente_id IN 
                    (SELECT id FROM ventes WHERE DATE(date_vente) = jour)
                  ) as benefice_total
                  FROM ventes
                  WHERE date_vente BETWEEN :date_debut AND :date_fin
                  GROUP BY jour
                  ORDER BY jour ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":date_debut", $date_debut);
        $stmt->bindParam(":date_fin", $date_fin);
        $stmt->execute();
        $ventes_par_jour = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ventes_par_jour[] = $row;
        }
        
        // Assembler le rapport
        $rapport = [
            'periode' => [
                'debut' => $date_debut,
                'fin' => $date_fin
            ],
            'statistiques' => $statistiques,
            'ventes_par_produit' => $ventes_par_produit,
            'ventes_par_jour' => $ventes_par_jour
        ];
        
        // Retourner le rapport dans le format demandé
        if($format == 'json') {
            return json_encode($rapport);
        } else {
            return $rapport;
        }
    }
    
    // Méthode pour obtenir le rapport de stock
    public function getRapportStock($format = 'array') {
        // Valeur totale du stock
        $query = "SELECT SUM(s.quantite * pp.prix_achat) as valeur_totale
                  FROM stock s
                  LEFT JOIN produits p ON s.produit_id = p.id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $valeur_totale = $stmt->fetch(PDO::FETCH_ASSOC)['valeur_totale'] ?? 0;
        
        // Détail du stock par produit
        $query = "SELECT p.id, p.code, p.nom as produit_nom, c.nom as categorie_nom, p.unite_mesure,
                  s.quantite, pp.prix_achat, pp.prix_vente,
                  (s.quantite * pp.prix_achat) as valeur_stock
                  FROM produits p
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  LEFT JOIN stock s ON p.id = s.produit_id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1
                  ORDER BY valeur_stock DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stock_par_produit = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock_par_produit[] = $row;
        }
        
        // Stock par catégorie
        $query = "SELECT c.id, c.nom as categorie_nom,
                  COUNT(p.id) as nombre_produits,
                  SUM(s.quantite) as quantite_totale,
                  SUM(s.quantite * pp.prix_achat) as valeur_stock
                  FROM categories c
                  LEFT JOIN produits p ON c.id = p.categorie_id
                  LEFT JOIN stock s ON p.id = s.produit_id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1
                  GROUP BY c.id
                  ORDER BY valeur_stock DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stock_par_categorie = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock_par_categorie[] = $row;
        }
        
        // Produits en stock faible
        $query = "SELECT p.id, p.code, p.nom as produit_nom, c.nom as categorie_nom, p.unite_mesure,
                  s.quantite, pp.prix_achat, pp.prix_vente
                  FROM produits p
                  LEFT JOIN categories c ON p.categorie_id = c.id
                  LEFT JOIN stock s ON p.id = s.produit_id
                  LEFT JOIN prix_produits pp ON p.id = pp.produit_id AND pp.date_fin IS NULL
                  WHERE p.actif = 1 AND s.quantite <= 10
                  ORDER BY s.quantite ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stock_faible = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock_faible[] = $row;
        }
        
        // Assembler le rapport
        $rapport = [
            'date_rapport' => date('Y-m-d H:i:s'),
            'valeur_totale' => $valeur_totale,
            'stock_par_produit' => $stock_par_produit,
            'stock_par_categorie' => $stock_par_categorie,
            'stock_faible' => $stock_faible
        ];
        
        // Retourner le rapport dans le format demandé
        if($format == 'json') {
            return json_encode($rapport);
        } else {
            return $rapport;
        }
    }
    
    // Méthode pour obtenir le rapport des dettes
    public function getRapportDettes($format = 'array') {
        // Total des dettes
        $query = "SELECT 
                  COUNT(*) as nombre_dettes,
                  SUM(montant_initial) as montant_initial_total,
                  SUM(montant_restant) as montant_restant_total
                  FROM dettes
                  WHERE statut != 'payee' AND statut != 'annulee'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $total_dettes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Dettes par client
        $query = "SELECT c.id, c.nom as client_nom, c.telephone,
                  COUNT(d.id) as nombre_dettes,
                  SUM(d.montant_initial) as montant_initial_total,
                  SUM(d.montant_restant) as montant_restant_total
                  FROM clients c
                  LEFT JOIN dettes d ON c.id = d.client_id AND d.statut != 'payee' AND d.statut != 'annulee'
                  GROUP BY c.id
                  HAVING montant_restant_total > 0
                  ORDER BY montant_restant_total DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $dettes_par_client = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dettes_par_client[] = $row;
        }
        
        // Dettes par statut
        $query = "SELECT statut,
                  COUNT(*) as nombre_dettes,
                  SUM(montant_initial) as montant_initial_total,
                  SUM(montant_restant) as montant_restant_total
                  FROM dettes
                  WHERE statut != 'annulee'
                  GROUP BY statut
                  ORDER BY FIELD(statut, 'active', 'partiellement_payee', 'payee')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $dettes_par_statut = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dettes_par_statut[] = $row;
        }
        
        // Paiements récents
        $query = "SELECT p.*, d.montant_initial, c.nom as client_nom, u.nom as utilisateur_nom
                  FROM paiements_dettes p
                  LEFT JOIN dettes d ON p.dette_id = d.id
                  LEFT JOIN clients c ON d.client_id = c.id
                  LEFT JOIN users u ON p.utilisateur_id = u.id
                  ORDER BY p.date_paiement DESC
                  LIMIT 10";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $paiements_recents = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $paiements_recents[] = $row;
        }
        
        // Assembler le rapport
        $rapport = [
            'date_rapport' => date('Y-m-d H:i:s'),
            'total_dettes' => $total_dettes,
            'dettes_par_client' => $dettes_par_client,
            'dettes_par_statut' => $dettes_par_statut,
            'paiements_recents' => $paiements_recents
        ];
        
        // Retourner le rapport dans le format demandé
        if($format == 'json') {
            return json_encode($rapport);
        } else {
            return $rapport;
        }
    }
    
    // Méthode pour obtenir le rapport des employés
    public function getRapportEmployes($date_debut = null, $date_fin = null, $format = 'array') {
        // Conditions de date
        $condition_date = "";
        $params = [];
        
        if($date_debut && $date_fin) {
            $condition_date = "WHERE s.date_paiement BETWEEN :date_debut AND :date_fin";
            $params[':date_debut'] = $date_debut;
            $params[':date_fin'] = $date_fin;
        } else if($date_debut) {
            $condition_date = "WHERE s.date_paiement >= :date_debut";
            $params[':date_debut'] = $date_debut;
        } else if($date_fin) {
            $condition_date = "WHERE s.date_paiement <= :date_fin";
            $params[':date_fin'] = $date_fin;
        }
        
        // Liste des employés avec salaires
        $query = "SELECT e.*, 
                  (SELECT SUM(montant) FROM salaires WHERE employe_id = e.id " . ($condition_date ? str_replace('s.', '', $condition_date) : "") . ") as total_paye
                  FROM employes e
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $employes = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $employes[] = $row;
        }
        
        // Total des salaires payés
        $query = "SELECT SUM(s.montant) as total_salaires
                  FROM salaires s
                  " . $condition_date;
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_salaires = $stmt->fetch(PDO::FETCH_ASSOC)['total_salaires'] ?? 0;
        
        // Paiements récents
        $query = "SELECT s.*, e.nom as employe_nom, u.nom as utilisateur_nom
                  FROM salaires s
                  LEFT JOIN employes e ON s.employe_id = e.id
                  LEFT JOIN users u ON s.utilisateur_id = u.id
                  " . $condition_date . "
                  ORDER BY s.date_paiement DESC
                  LIMIT 10";
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $paiements_recents = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $paiements_recents[] = $row;
        }
        
        // Assembler le rapport
        $rapport = [
            'date_rapport' => date('Y-m-d H:i:s'),
            'periode' => [
                'debut' => $date_debut ?? 'Toutes les dates',
                'fin' => $date_fin ?? 'Toutes les dates'
            ],
            'total_salaires' => $total_salaires,
            'employes' => $employes,
            'paiements_recents' => $paiements_recents
        ];
        
        // Retourner le rapport dans le format demandé
        if($format == 'json') {
            return json_encode($rapport);
        } else {
            return $rapport;
        }
    }
    
    // Méthode pour exporter un rapport au format CSV
    public function exportCSV($data, $filename) {
        if(empty($data)) {
            return false;
        }
        
        // Ouvrir le fichier en écriture
        $fp = fopen($filename, 'w');
        
        // Écrire l'en-tête (noms des colonnes)
        fputcsv($fp, array_keys($data[0]));
        
        // Écrire les données
        foreach($data as $row) {
            fputcsv($fp, $row);
        }
        
        // Fermer le fichier
        fclose($fp);
        
        return true;
    }
}
?>
