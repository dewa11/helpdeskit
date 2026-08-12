<?php

require_once 'config.php';

class Unit {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAll() {
        try {
            $stmt = $this->pdo->query("SELECT id, name FROM units ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                $this->ensureTable();
                $stmt = $this->pdo->query("SELECT id, name FROM units ORDER BY name ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            throw $e;
        }
    }

    public function create($name) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO units (name) VALUES (?)");
            return $stmt->execute([$name]);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') {
                $this->ensureTable();
                $stmt = $this->pdo->prepare("INSERT INTO units (name) VALUES (?)");
                return $stmt->execute([$name]);
            }
            throw $e;
        }
    }

    private function ensureTable() {
        $sql = "CREATE TABLE IF NOT EXISTS units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo->exec($sql);
    }
}
