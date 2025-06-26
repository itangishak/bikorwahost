<?php
class Settings {
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    }

    public function get($name, $default = null) {
        $stmt = $this->conn->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }

    public function set($name, $value) {
        $stmt = $this->conn->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        return $stmt->execute([$name, $value]);
    }

    public function getAll() {
        $stmt = $this->conn->query('SELECT name, value FROM settings');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
