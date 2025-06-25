<?php
/**
 * Modèle Employe pour la gestion des employés
 * BIKORWA SHOP
 */

class Employe {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "employes";
    private $table_salaires = "salaires";
    
    // Propriétés de l'objet
    public $id;
    public $nom;
    public $telephone;
    public $adresse;
    public $email;
    public $poste;
    public $date_embauche;
    public $salaire;
    public $actif;
    public $note;
    
    // Propriétés liées
    public $paiements = [];
    
    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Vérifier si un employé existe déjà avec les mêmes informations
     * @param string $nom
     * @param string $telephone
     * @param string $email
     * @param int $exclude_id ID à exclure de la vérification (pour les mises à jour)
     * @return array Résultat de la vérification avec les détails des doublons
     */
    public function checkDuplicates($nom, $telephone = null, $email = null, $exclude_id = null) {
        $duplicates = [];
        $conditions = [];
        $params = [];
        
        // Vérifier le téléphone s'il est fourni (doit être unique)
        if (!empty($telephone)) {
            $conditions[] = "telephone = :telephone";
            $params[':telephone'] = $telephone;
        }
        
        // Vérifier l'email s'il est fourni (doit être unique)
        if (!empty($email)) {
            $conditions[] = "LOWER(TRIM(email)) = LOWER(TRIM(:email))";
            $params[':email'] = $email;
        }
        
        // Si aucune condition (pas de téléphone ni email), pas de doublon possible
        if (empty($conditions)) {
            return ['has_duplicates' => false, 'duplicates' => []];
        }
        
        // Construire la requête - utiliser OR pour vérifier téléphone OU email
        $query = "SELECT id, nom, telephone, email, poste FROM " . $this->table_name . " 
                  WHERE (" . implode(' OR ', $conditions) . ")";
        
        // Exclure l'ID actuel si fourni (pour les mises à jour)
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $exclude_id;
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Analyser les résultats pour identifier les types de doublons
        foreach ($results as $result) {
            $duplicate_types = [];
            
            // Vérifier le type de doublon
            if (!empty($telephone) && $result['telephone'] === $telephone) {
                $duplicate_types[] = 'téléphone';
            }
            
            if (!empty($email) && strtolower(trim($result['email'])) === strtolower(trim($email))) {
                $duplicate_types[] = 'email';
            }
            
            if (!empty($duplicate_types)) {
                $duplicates[] = [
                    'id' => $result['id'],
                    'nom' => $result['nom'],
                    'telephone' => $result['telephone'],
                    'email' => $result['email'],
                    'poste' => $result['poste'],
                    'duplicate_types' => $duplicate_types
                ];
            }
        }
        
        return [
            'has_duplicates' => !empty($duplicates),
            'duplicates' => $duplicates
        ];
    }
    
    /**
     * Méthode pour créer un nouvel employé avec vérification des doublons
     */
    public function create($data = null) {
        // Si des données sont passées en paramètre, les utiliser
        if ($data) {
            $this->nom = $data['nom'];
            $this->telephone = $data['telephone'];
            $this->adresse = $data['adresse'];
            $this->email = $data['email'];
            $this->poste = $data['poste'];
            $this->date_embauche = $data['date_embauche'];
            $this->salaire = $data['salaire'];
            $this->actif = $data['actif'];
            $this->note = $data['note'];
        }
        
        // Vérifier les doublons avant la création
        $duplicate_check = $this->checkDuplicates($this->nom, $this->telephone, $this->email);
        
        if ($duplicate_check['has_duplicates']) {
            $duplicate_messages = [];
            foreach ($duplicate_check['duplicates'] as $duplicate) {
                $types = implode(' et ', $duplicate['duplicate_types']);
                $duplicate_messages[] = "Un employé avec le même {$types} existe déjà : {$duplicate['nom']} ({$duplicate['poste']})";
            }
            
            return [
                'success' => false,
                'error' => 'duplicate',
                'message' => 'Informations déjà utilisées par un autre employé. ' . implode('; ', $duplicate_messages),
                'duplicates' => $duplicate_check['duplicates']
            ];
        }
        
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom = :nom, 
                      telephone = :telephone, 
                      adresse = :adresse, 
                      email = :email, 
                      poste = :poste, 
                      date_embauche = :date_embauche, 
                      salaire = :salaire, 
                      actif = :actif, 
                      note = :note";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $nom = htmlspecialchars(strip_tags(trim($this->nom)));
        $telephone = $this->telephone ? htmlspecialchars(strip_tags(trim($this->telephone))) : null;
        $adresse = $this->adresse ? htmlspecialchars(strip_tags(trim($this->adresse))) : null;
        $email = $this->email ? htmlspecialchars(strip_tags(trim($this->email))) : null;
        $poste = htmlspecialchars(strip_tags(trim($this->poste)));
        $date_embauche = htmlspecialchars(strip_tags($this->date_embauche));
        $salaire = (float)$this->salaire;
        $actif = $this->actif ? 1 : 0;
        $note = $this->note ? htmlspecialchars(strip_tags(trim($this->note))) : null;
        
        // Liaison des paramètres
        $stmt->bindParam(":nom", $nom);
        $stmt->bindParam(":telephone", $telephone);
        $stmt->bindParam(":adresse", $adresse);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":poste", $poste);
        $stmt->bindParam(":date_embauche", $date_embauche);
        $stmt->bindParam(":salaire", $salaire);
        $stmt->bindParam(":actif", $actif);
        $stmt->bindParam(":note", $note);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Employé ajouté avec succès',
                'id' => $this->conn->lastInsertId()
            ];
        }
        
        return [
            'success' => false,
            'error' => 'database',
            'message' => 'Erreur lors de l\'insertion en base de données'
        ];
    }
    
    /**
     * Méthode pour mettre à jour un employé avec vérification des doublons
     */
    public function update($id = null, $data = null) {
        // Si des données sont passées en paramètre, les utiliser
        if ($id) {
            $this->id = $id;
        }
        if ($data) {
            $this->nom = $data['nom'];
            $this->telephone = $data['telephone'];
            $this->adresse = $data['adresse'];
            $this->email = $data['email'];
            $this->poste = $data['poste'];
            $this->date_embauche = $data['date_embauche'];
            $this->salaire = $data['salaire'];
            $this->actif = $data['actif'];
            $this->note = $data['note'];
        }
        
        // Vérifier les doublons avant la mise à jour (exclure l'ID actuel)
        $duplicate_check = $this->checkDuplicates($this->nom, $this->telephone, $this->email, $this->id);
        
        if ($duplicate_check['has_duplicates']) {
            $duplicate_messages = [];
            foreach ($duplicate_check['duplicates'] as $duplicate) {
                $types = implode(' et ', $duplicate['duplicate_types']);
                $duplicate_messages[] = "Un employé avec le même {$types} existe déjà: {$duplicate['nom']} ({$duplicate['poste']})";
            }
            
            return [
                'success' => false,
                'error' => 'duplicate',
                'message' => 'Informations déjà utilisées par un autre employé',
                'details' => $duplicate_messages,
                'duplicates' => $duplicate_check['duplicates']
            ];
        }
        
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom, 
                      telephone = :telephone, 
                      adresse = :adresse, 
                      email = :email, 
                      poste = :poste, 
                      date_embauche = :date_embauche, 
                      salaire = :salaire, 
                      actif = :actif, 
                      note = :note 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $nom = htmlspecialchars(strip_tags(trim($this->nom)));
        $telephone = $this->telephone ? htmlspecialchars(strip_tags(trim($this->telephone))) : null;
        $adresse = $this->adresse ? htmlspecialchars(strip_tags(trim($this->adresse))) : null;
        $email = $this->email ? htmlspecialchars(strip_tags(trim($this->email))) : null;
        $poste = htmlspecialchars(strip_tags(trim($this->poste)));
        $date_embauche = htmlspecialchars(strip_tags($this->date_embauche));
        $salaire = (float)$this->salaire;
        $actif = $this->actif ? 1 : 0;
        $note = $this->note ? htmlspecialchars(strip_tags(trim($this->note))) : null;
        $id = (int)$this->id;
        
        // Liaison des paramètres
        $stmt->bindParam(":nom", $nom);
        $stmt->bindParam(":telephone", $telephone);
        $stmt->bindParam(":adresse", $adresse);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":poste", $poste);
        $stmt->bindParam(":date_embauche", $date_embauche);
        $stmt->bindParam(":salaire", $salaire);
        $stmt->bindParam(":actif", $actif);
        $stmt->bindParam(":note", $note);
        $stmt->bindParam(":id", $id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Employé mis à jour avec succès'
            ];
        }
        
        return [
            'success' => false,
            'error' => 'database',
            'message' => 'Erreur lors de la mise à jour en base de données'
        ];
    }
    
    // Méthode pour lire tous les employés
    public function readAll($actif_seulement = false) {
        // Construction de la requête
        $query = "SELECT * FROM " . $this->table_name;
        
        if($actif_seulement) {
            $query .= " WHERE actif = 1";
        }
        
        $query .= " ORDER BY nom ASC";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Exécution de la requête
        $stmt->execute();
        
        return $stmt;
    }
    
    // Méthode pour lire un employé
    public function readOne() {
        // Requête de sélection
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = :id 
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
            $this->nom = $row['nom'];
            $this->telephone = $row['telephone'];
            $this->adresse = $row['adresse'];
            $this->email = $row['email'];
            $this->poste = $row['poste'];
            $this->date_embauche = $row['date_embauche'];
            $this->salaire = $row['salaire'];
            $this->actif = $row['actif'];
            $this->note = $row['note'];
            
            // Récupérer les paiements de salaire
            $this->getPaiements();
            
            return true;
        }
        
        return false;
    }
    
    // Méthode pour supprimer un employé
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
    
    // Méthode pour basculer le statut actif/inactif
    public function toggleStatus() {
        // Requête de mise à jour du statut
        $query = "UPDATE " . $this->table_name . " 
                  SET actif = CASE WHEN actif = 1 THEN 0 ELSE 1 END 
                  WHERE id = :id";
        
        // Préparation de la requête
        $stmt = $this->conn->prepare($query);
        
        // Nettoyage des données
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Liaison des paramètres
        $stmt->bindParam(":id", $this->id);
        
        // Exécution de la requête
        if($stmt->execute()) {
            // Récupérer le nouveau statut
            $query_status = "SELECT actif FROM " . $this->table_name . " WHERE id = :id";
            $stmt_status = $this->conn->prepare($query_status);
            $stmt_status->bindParam(":id", $this->id);
            $stmt_status->execute();
            $result = $stmt_status->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'new_status' => $result['actif']
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du statut'
        ];
    }
    
    // Méthode pour récupérer les paiements de salaire
    public function getPaiements() {
        $query = "SELECT * FROM " . $this->table_salaires . " 
                  WHERE employe_id = :employe_id 
                  ORDER BY date_paiement DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":employe_id", $this->id);
        $stmt->execute();
        
        $this->paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->paiements;
    }
    
    // Méthode pour obtenir les statistiques des employés
    public function getStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_employes,
                    SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as employes_actifs,
                    SUM(CASE WHEN actif = 0 THEN 1 ELSE 0 END) as employes_inactifs,
                    SUM(CASE WHEN actif = 1 THEN salaire ELSE 0 END) as masse_salariale,
                    AVG(CASE WHEN actif = 1 THEN salaire ELSE NULL END) as salaire_moyen
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Rechercher des employés avec pagination et filtres
     */
    public function search($search = '', $statut = '', $page = 1, $items_per_page = 10) {
        $offset = ($page - 1) * $items_per_page;
        
        // Construction de la requête de base
        $where_conditions = [];
        $params = [];
        
        // Ajouter les conditions de recherche
        if (!empty($search)) {
            $where_conditions[] = "(nom LIKE :search OR telephone LIKE :search OR email LIKE :search OR poste LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        // Ajouter le filtre de statut
        if ($statut === 'actif') {
            $where_conditions[] = "actif = 1";
        } elseif ($statut === 'inactif') {
            $where_conditions[] = "actif = 0";
        }
        
        // Construire la clause WHERE
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Requête pour compter le total
        $count_query = "SELECT COUNT(*) as total FROM " . $this->table_name . " " . $where_clause;
        $count_stmt = $this->conn->prepare($count_query);
        
        // Lier les paramètres pour le comptage
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        
        $count_stmt->execute();
        $total_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total_rows = $total_result['total'];
        
        // Requête pour récupérer les données
        $data_query = "SELECT * FROM " . $this->table_name . " " . $where_clause . " ORDER BY nom ASC LIMIT :limit OFFSET :offset";
        $data_stmt = $this->conn->prepare($data_query);
        
        // Lier les paramètres pour les données
        foreach ($params as $key => $value) {
            $data_stmt->bindValue($key, $value);
        }
        $data_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $data_stmt->execute();
        $employes = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'employes' => $employes,
            'total_rows' => $total_rows,
            'total_pages' => ceil($total_rows / $items_per_page),
            'current_page' => $page,
            'items_per_page' => $items_per_page
        ];
    }
}
?>