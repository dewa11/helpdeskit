<?php

date_default_timezone_set('Asia/Makassar');

define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');


define('BASE_URL', 'https://helpdesk.rsuthalia.com/');

$__base_path = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
$__base_path = rtrim($__base_path, '/');
define('APP_BASE_PATH', $__base_path);

// PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        
    }
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Other configs
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_VIDEO_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_VIDEO_DURATION', 15); // seconds

define('SLA_HOURS', 48);

// Telegram bot 
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

define('DASHBOARD_PREVIEW_ROWS', 20);

define('FFMPEG_BIN', getenv('FFMPEG_BIN') ?: 'env -u LD_LIBRARY_PATH /usr/bin/ffmpeg');
define('FFPROBE_BIN', getenv('FFPROBE_BIN') ?: 'env -u LD_LIBRARY_PATH /usr/bin/ffprobe');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
