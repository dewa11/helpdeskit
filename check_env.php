<?php
// Temporary environment checker - remove after debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=UTF-8');

echo "== HelpdesKit environment check ==\n\n";

echo "PHP SAPI: " . php_sapi_name() . PHP_EOL;
echo "PHP version: " . phpversion() . PHP_EOL;

$phpcli = @shell_exec('php -v 2>&1');
if ($phpcli) {
    echo "CLI PHP: " . trim($phpcli) . PHP_EOL;
} else {
    echo "CLI PHP: not available or shell_exec disabled" . PHP_EOL;
}

echo "CWD: " . __DIR__ . PHP_EOL . PHP_EOL;

$files = [
    'config.php' => __DIR__ . '/config.php',
    'index.php' => __DIR__ . '/index.php',
    '.htaccess' => __DIR__ . '/.htaccess',
    'logo' => __DIR__ . '/public/images/RVL.png',
];

foreach ($files as $name => $path) {
    echo "-- $name --\n";
    echo "path: $path\n";
    echo "exists: " . (file_exists($path) ? 'yes' : 'no') . PHP_EOL;
    echo "readable: " . (is_readable($path) ? 'yes' : 'no') . PHP_EOL;
    if (file_exists($path) && is_readable($path) && is_file($path)) {
        echo "size: " . filesize($path) . " bytes" . PHP_EOL;
        echo "perms: " . sprintf('%o', fileperms($path) & 0777) . PHP_EOL;
    }
    echo PHP_EOL;
}

// Try linting config.php with CLI php -l if available
$configPath = __DIR__ . '/config.php';
if (is_readable($configPath)) {
    $lint = @shell_exec('php -l ' . escapeshellarg($configPath) . ' 2>&1');
    if ($lint) {
        echo "php -l output for config.php:\n" . trim($lint) . PHP_EOL . PHP_EOL;
    } else {
        echo "php -l not available or disabled on this host\n\n";
    }
}

// Read config.php and attempt to extract DB settings (no execution)
echo "-- Parsed config.php values (non-executing) --\n";
$raw = @file_get_contents($configPath);
if ($raw === false) {
    echo "Cannot read config.php to parse DB credentials." . PHP_EOL;
} else {
    $matches = [];
    preg_match("/define\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\)/", $raw, $matches);
    $dbHost = $matches[1] ?? 'localhost';
    preg_match("/define\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\)/", $raw, $matches);
    $dbName = $matches[1] ?? '';
    preg_match("/define\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\)/", $raw, $matches);
    $dbUser = $matches[1] ?? '';
    preg_match("/define\(\s*'DB_PASS'\s*,\s*'([^']+)'\s*\)/", $raw, $matches);
    $dbPass = $matches[1] ?? '';

    echo "DB_HOST: $dbHost" . PHP_EOL;
    echo "DB_NAME: $dbName" . PHP_EOL;
    echo "DB_USER: $dbUser" . PHP_EOL;
    echo "DB_PASS: " . ($dbPass !== '' ? str_repeat('*', 8) : '(empty)') . PHP_EOL . PHP_EOL;

    // Try PDO connect (may fail if remote DB blocked)
    if ($dbName && $dbUser) {
        echo "Attempting PDO connection to {$dbHost}/{$dbName} ...\n";
        try {
            $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            echo "PDO connection: OK\n";
            // run a simple query
            $row = $pdo->query('SELECT 1')->fetchColumn();
            echo "Simple query result: " . var_export($row, true) . PHP_EOL;
        } catch (Throwable $e) {
            echo "PDO connection failed: " . $e->getMessage() . PHP_EOL;
        }
    } else {
        echo "DB credentials incomplete, skipping PDO connect.\n";
    }
}

echo PHP_EOL . "-- END --\n";

// Important: remove this file from your server after debugging
?>
