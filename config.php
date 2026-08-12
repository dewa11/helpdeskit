<?php

// Ensure application uses Asia/Makassar timezone for all PHP date operations
date_default_timezone_set('Asia/Makassar');

// Database configuration (update for production/cPanel)
define('DB_HOST', 'localhost');
define('DB_NAME', 'rsub7514_helpdeskit');
define('DB_USER', 'rsub7514_rvl');
define('DB_PASS', 'thalia2007');

// Public base URL for the application (include trailing slash)
define('BASE_URL', 'https://helpdesk.rsuthalia.com/');

// Path portion of BASE_URL (used for asset and route prefixes). Empty string if deployed at site root.
$__base_path = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
$__base_path = rtrim($__base_path, '/');
define('APP_BASE_PATH', $__base_path);

// PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure MySQL session timezone matches Asia/Makassar (UTC+08:00)
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // Ignore if server doesn't support setting session time_zone
    }
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Other configs
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_VIDEO_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_VIDEO_DURATION', 15); // seconds

// SLA threshold (hours) used for QA/QC metrics (default 48 hours)
define('SLA_HOURS', 48);

// Telegram bot (used to notify IT group when a client files a report)
// Set these to your bot token and target chat ID (group/channel).
define('TELEGRAM_BOT_TOKEN', '8157566284:AAHrLYnjo1uw2UPdVa1ERsNt7VpMPyYJQ8M');
define('TELEGRAM_CHAT_ID', '-1003653380797');

// UI configuration: number of rows to show in the dashboard 'latest' preview
define('DASHBOARD_PREVIEW_ROWS', 20);

// Prefer absolute system binaries and avoid XAMPP's lib conflicts by
// unsetting LD_LIBRARY_PATH when invoking system ffmpeg/ffprobe.
// You can override by setting environment vars FFMPEG_BIN/FFPROBE_BIN.
define('FFMPEG_BIN', getenv('FFMPEG_BIN') ?: 'env -u LD_LIBRARY_PATH /usr/bin/ffmpeg');
define('FFPROBE_BIN', getenv('FFPROBE_BIN') ?: 'env -u LD_LIBRARY_PATH /usr/bin/ffprobe');

// Ensure upload dir exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}