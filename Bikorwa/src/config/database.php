<?php
/**
 * Configuration de la base de données
 * BIKORWA SHOP
 */

class Database {
    // Paramètres de connexion à la base de données
    private $host = "localhost";
    private $port = 3306;
    private $db_name = "bumadste_bikorwa_shop";
    private $username = "bumadste_bikorwa";
    private $password = "Bikorwa2024!";
    private $conn;

    // Méthode pour obtenir la connexion à la base de données
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                $options
            );
            
            // Test the connection
            $this->conn->query('SELECT 1');
            
        } catch(PDOException $exception) {
            // Log detailed error information
            error_log('Database connection error: ' . $exception->getMessage());
            error_log('DSN: mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->db_name);
            error_log('Username: ' . $this->username);
            
            // Return null to indicate connection failure
            $this->conn = null;
        }
        return $this->conn;
    }
}
