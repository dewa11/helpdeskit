<?php

require_once 'config.php';

class User {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->ensureNipColumnExists();
    }

    // Ensure `nip` column exists. If missing, attempt to add it and populate from `email` if present.
    private function ensureNipColumnExists() {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE 'nip'");
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($col) return true;
            // Add the column (nullable to avoid breaking inserts), then try to populate from email
            $this->pdo->exec("ALTER TABLE users ADD COLUMN nip VARCHAR(128) NULL AFTER email");
            // If email column exists, copy it to nip for existing rows
            try {
                $this->pdo->exec("UPDATE users SET nip = email WHERE nip IS NULL OR nip = ''");
            } catch (Throwable $e) {
                // ignore possible issues copying values
            }
            return true;
        } catch (Throwable $e) {
            // If something goes wrong (e.g., table doesn't exist), don't break the request flow
            return false;
        }
    }

    public function authenticate($username, $password) {
        // The `nip` column is used to store the login username in this app.
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE nip = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getAll() {
        try {
            $stmt = $this->pdo->query("SELECT id, name, nip, role FROM users");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // If column missing, try to ensure it exists and retry once
            $this->ensureNipColumnExists();
            $stmt = $this->pdo->query("SELECT id, name, nip, role FROM users");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Create a new user. Username and initial password are both the provided NIP by default.
    // Also populate the legacy `email` column with the NIP value so older schema constraints are satisfied.
    public function create($name, $nip, $role) {
        $hashed = password_hash($nip, PASSWORD_DEFAULT);
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (name, email, nip, password, role) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$name, $nip, $nip, $hashed, $role]);
        } catch (Throwable $e) {
            // Fallback: if email column does not exist, insert without it
            try {
                $stmt = $this->pdo->prepare("INSERT INTO users (name, nip, password, role) VALUES (?, ?, ?, ?)");
                return $stmt->execute([$name, $nip, $hashed, $role]);
            } catch (Throwable $e2) {
                throw $e2;
            }
        }
    }

    public function update($id, $name, $nip, $role) {
        $stmt = $this->pdo->prepare("UPDATE users SET name = ?, nip = ?, role = ? WHERE id = ?");
        return $stmt->execute([$name, $nip, $role, $id]);
    }

    // Reset user's password to their NIP (hashed)
    public function resetPasswordToNip($id) {
        $u = $this->getById($id);
        if (!$u) return false;
        $nip = $u['nip'];
        $hashed = password_hash($nip, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed, $id]);
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, nip, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->ensureNipColumnExists();
            $stmt = $this->pdo->prepare("SELECT id, name, nip, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}