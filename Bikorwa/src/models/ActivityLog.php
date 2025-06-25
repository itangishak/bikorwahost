<?php

class ActivityLog {
    private $conn;
    private $table_name = 'journal_activites';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Logs an activity.
     *
     * @param int $user_id The ID of the user performing the action.
     * @param string $action The type of action (e.g., 'creation', 'update', 'delete').
     * @param string $entite The entity type (e.g., 'vente', 'produit', 'client').
     * @param int|null $entity_id The ID of the entity affected (e.g., client ID, product ID). Optional.
     * @param string|null $details Additional details about the activity. Optional.
     * @return bool True on success, false on failure.
     */
    public function logActivity($user_id, $action, $entite, $entity_id = null, $details = null) {
        try {
            $query = "INSERT INTO " . $this->table_name . " (utilisateur_id, action, entite, entite_id, details, date_action) 
                      VALUES (:utilisateur_id, :action, :entite, :entite_id, :details, NOW())";

            $stmt = $this->conn->prepare($query);

            // Sanitize string inputs
            $action = htmlspecialchars(strip_tags($action));
            $entite = htmlspecialchars(strip_tags($entite));

            $stmt->bindParam(':utilisateur_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':entite', $entite);
            
            if ($entity_id === null) {
                $stmt->bindValue(':entite_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':entite_id', $entity_id, PDO::PARAM_INT);
            }
            
            if ($details === null) {
                $stmt->bindValue(':details', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':details', $details);
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("ActivityLog PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a sale activity
     */
    public function logSale($user_id, $sale_id, $invoice_number, $amount) {
        $details = "Vente #$invoice_number - Montant: " . number_format($amount, 0) . " BIF";
        return $this->logActivity($user_id, 'creation', 'vente', $sale_id, $details);
    }

    /**
     * Log a stock movement activity
     */
    public function logStockMovement($user_id, $movement_id, $product_name, $quantity, $unit, $type) {
        $action = $type === 'entree' ? 'entree' : 'sortie';
        $details = ($type === 'entree' ? 'Approvisionnement: ' : 'Sortie: ') . 
                   "$product_name ($quantity $unit)";
        return $this->logActivity($user_id, $action, 'stock', $movement_id, $details);
    }

    /**
     * Log a client activity
     */
    public function logClient($user_id, $client_id, $client_name, $action = 'creation') {
        $details = "Client: $client_name";
        return $this->logActivity($user_id, $action, 'client', $client_id, $details);
    }

    /**
     * Log a product activity
     */
    public function logProduct($user_id, $product_id, $product_name, $action = 'creation') {
        $details = "Produit: $product_name";
        return $this->logActivity($user_id, $action, 'produit', $product_id, $details);
    }

    /**
     * Log a salary payment activity
     */
    public function logSalaryPayment($user_id, $payment_id, $employee_name, $amount, $period_start, $period_end) {
        $details = sprintf(
            "Paiement de salaire de %s BIF pour %s (Période: %s au %s)",
            number_format($amount, 0, ',', ' '),
            $employee_name,
            date('d/m/Y', strtotime($period_start)),
            date('d/m/Y', strtotime($period_end))
        );
        return $this->logActivity($user_id, 'paiement', 'salaires', $payment_id, $details);
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities($limit = 10, $date_from = null, $date_to = null) {
        try {
            $query = "SELECT ja.*, u.username, u.role 
                      FROM " . $this->table_name . " ja
                      LEFT JOIN users u ON ja.utilisateur_id = u.id";
            
            $conditions = [];
            $params = [];
            
            if ($date_from && $date_to) {
                $conditions[] = "ja.date_action BETWEEN :date_from AND :date_to";
                $params[':date_from'] = $date_from . ' 00:00:00';
                $params[':date_to'] = $date_to . ' 23:59:59';
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $query .= " ORDER BY ja.date_action DESC LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ActivityLog getRecentActivities PDOException: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity count for today
     */
    public function getTodayActivityCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                      WHERE DATE(date_action) = CURDATE()";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
        } catch (PDOException $e) {
            error_log("ActivityLog getTodayActivityCount PDOException: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if journal_activites table exists and has data
     */
    public function isJournalActive() {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
