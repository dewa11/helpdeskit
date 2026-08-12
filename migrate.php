<?php
// Temporary web runner to execute migrations and optional seed.
// USAGE:
// 1) Upload this file to your site root (public_html/helpdesk.rsuthalia.com/)
// 2) Visit: https://helpdesk.rsuthalia.com/migrate.php?token=migrate_once_20260205
// 3) After successful run, DELETE this file immediately.

// Simple token guard to avoid accidental public execution
if (!isset($_GET['token']) || $_GET['token'] !== 'migrate_once_20260205') {
    http_response_code(403);
    echo "Forbidden. Provide correct token to run migrations.";
    exit;
}

echo "<pre>";
try {
    require_once __DIR__ . '/migrations/run_migrations.php';
    if (file_exists(__DIR__ . '/migrations/seed_head_it.php')) {
        echo "\nRunning head IT seeder...\n";
        require_once __DIR__ . '/migrations/seed_head_it.php';
    }
    echo "\nMigrations (and seed) finished.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
echo "<p><strong>IMPORTANT:</strong> Delete this file now.</p>";
exit;
