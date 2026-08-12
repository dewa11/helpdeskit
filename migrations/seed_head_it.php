<?php
// One-time seeder: create or update Head IT user
// Usage: php migrations/seed_head_it.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/User.php';

try {
    $nip = '201908117';
    $name = 'Ismul Aswan';
    $role = 'head_it';
    $rawPassword = 'server';

    // Ensure User helper is available
    $userModel = new User();

    // Try to find existing user by nip, email, or role=head_it
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ? OR email = ? OR role = 'head_it' LIMIT 1");
    $stmt->execute([$nip, $nip]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $id = $existing['id'];
        $hashed = password_hash($rawPassword, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET name = ?, nip = ?, email = ?, password = ?, role = ? WHERE id = ?");
        $upd->execute([$name, $nip, $nip, $hashed, $role, $id]);
        echo "Updated existing user (id={$id}) to nip={$nip}, name={$name}, role={$role}\n";
    } else {
        // Use model create which also hashes password
        $created = $userModel->create($name, $nip, $role);
        if ($created) {
            echo "Created user {$name} (nip={$nip}) with role {$role}\n";
            // Ensure password matches requested raw password (create() hashes nip as password)
            // Reset password to desired raw password
            $lastId = $pdo->lastInsertId();
            $hashed = password_hash($rawPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $lastId]);
            echo "Set password for user id={$lastId}\n";
        } else {
            echo "Failed to create user via model.\n";
        }
    }

    echo "Done.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

return 0;
