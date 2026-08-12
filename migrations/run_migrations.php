<?php
// Simple migration runner for helpdeskit
// Usage: php migrations/run_migrations.php

require_once __DIR__ . '/../config.php';

$errors = [];
$pdo->beginTransaction();
try {
    // Ensure users table exists
    $t = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$t) throw new Exception('Table `users` not found');

    // Add nip column if missing
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'nip'")->fetch();
    if (!$col) {
        echo "Adding column users.nip...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN nip VARCHAR(128) NULL AFTER email");
        echo "Populating users.nip from email where available...\n";
        $pdo->exec("UPDATE users SET nip = email WHERE (nip IS NULL OR nip = '') AND (email IS NOT NULL AND email != '')");
    } else {
        echo "Column users.nip already exists.\n";
    }

    // Add unique index on nip if not exists
    $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'ux_users_nip'")->fetch();
    if (!$idx) {
        echo "Adding unique index ux_users_nip...\n";
        // Use TRY/CATCH to avoid error if duplicates exist
        try {
            $pdo->exec("CREATE UNIQUE INDEX ux_users_nip ON users(nip)");
        } catch (Throwable $e) {
            echo "Warning: could not create unique index ux_users_nip (possible duplicates).\n";
        }
    } else {
        echo "Index ux_users_nip exists.\n";
    }

    // Tickets table
    $t2 = $pdo->query("SHOW TABLES LIKE 'tickets'")->fetch();
    if (!$t2) throw new Exception('Table `tickets` not found');
    $col2 = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'priority'")->fetch();
    if (!$col2) {
        echo "Adding column tickets.priority...\n";
        $pdo->exec("ALTER TABLE tickets ADD COLUMN priority VARCHAR(32) NULL");
        echo "Setting default 'Normal' for existing tickets...\n";
        $pdo->exec("UPDATE tickets SET priority = 'Normal' WHERE priority IS NULL");
    } else {
        echo "Column tickets.priority already exists.\n";
    }

    // Add lifecycle timestamp columns if missing
    $tsCols = ['assigned_at','started_at','finished_at','closed_at'];
    foreach ($tsCols as $c) {
        $col = $pdo->query("SHOW COLUMNS FROM tickets LIKE '" . $c . "'")->fetch();
        if (!$col) {
            echo "Adding column tickets." . $c . "...\n";
            $pdo->exec("ALTER TABLE tickets ADD COLUMN " . $c . " TIMESTAMP NULL DEFAULT NULL");
        } else {
            echo "Column tickets." . $c . " already exists.\n";
        }
    }

    $pdo->commit();
    echo "Migrations applied successfully.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

return 0;
