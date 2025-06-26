<?php
/**
 * Configuration de la base de données
 * BIKORWA SHOP
 */

class Database {
    // Paramètres de connexion à la base de données
    private $host = "localhost:3306";
    private $db_name = "bumadste_bikorwa_shop";
    private $username = "bumadste_bikorwa";
    private $password = "Bikorwa2024!";
    private $conn;

    // Méthode pour obtenir la connexion à la base de données
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Avoid sending output before headers are sent
            error_log('Database connection error: ' . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>
