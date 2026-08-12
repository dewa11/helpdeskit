<?php

declare(strict_types=1);

require_once __DIR__ . '/flight/Flight.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Ticket.php';
require_once __DIR__ . '/models/Unit.php';
require_once __DIR__ . '/views/partials/helpers.php';

// Harden session cookie parameters before starting session
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Ensure Flight uses a fixed base URL (useful when behind proxies or deployed to root)
if (defined('BASE_URL')) {
    Flight::set('flight.base_url', BASE_URL);
}

// Normalize REQUEST_URI when app is hosted in a subfolder (APP_BASE_PATH)
$basePrefix = (defined('APP_BASE_PATH') ? APP_BASE_PATH : '');
if ($basePrefix !== '' && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $basePrefix) === 0) {
    $new = substr($_SERVER['REQUEST_URI'], strlen($basePrefix));
    if ($new === '') $new = '/';
    $_SERVER['REQUEST_URI'] = $new;
    // also adjust PATH_INFO if present
    if (isset($_SERVER['PATH_INFO']) && strpos($_SERVER['PATH_INFO'], $basePrefix) === 0) {
        $_SERVER['PATH_INFO'] = substr($_SERVER['PATH_INFO'], strlen($basePrefix));
    }
}

// Collapse multiple slashes (//) to single slash to avoid routing issues
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = preg_replace('#/+#', '/', $_SERVER['REQUEST_URI']);
}
if (isset($_SERVER['PATH_INFO'])) {
    $_SERVER['PATH_INFO'] = preg_replace('#/+#', '/', $_SERVER['PATH_INFO']);
}

// Auth middleware
Flight::before('start', function() {
    $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
    $publicRoutes = [$base . '/', $base . '/report', $base . '/login', $base . '/captcha.php'];
    $url = Flight::request()->url;
    if (!in_array($url, $publicRoutes) && !isset($_SESSION['user'])) {
        Flight::redirect($base . '/login');
    }
});
