<?php

declare(strict_types=1);

class AuthController
{
    public static function showHome(): void
    {
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="hero">';
        $content .= '<img class="hero-logo" src="' . $base . '/public/images/RVL.png" alt="RVL Logo">';
        $content .= '<div class="hero-title">Helpdesk IT</div>';
        $content .= '<p class="hero-sub">Layanan bantuan TI internal.</p>';
        $content .= '<div class="icon-menu">';
        $content .= '<a href="' . $base . '/report" class="icon-btn" aria-label="Laporkan Masalah" data-label="Laporkan Masalah">';
        $content .= '<span class="icon">🛰</span>';
        $content .= '</a>';
        $content .= '<a href="' . $base . '/login" class="icon-btn" aria-label="Login sebagai IT" data-label="Login sebagai IT">';
        $content .= '<span class="icon">🔐</span>';
        $content .= '</a>';
        $content .= '</div>';
        $content .= '</div>';
        Flight::render('public_layout', ['content' => $content]);
    }

    public static function showLogin(): void
    {
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="card login-card mx-auto" style="max-width:420px">';
        $content .= '<div class="card-body">';
        $content .= '<h2 class="card-title mb-3">Login IT</h2>';
        $content .= '<form action="" method="post">';
        $content .= '<div class="mb-2"><label class="form-label">NIP</label><input class="form-control" type="text" name="username" required placeholder="NIP"></div>';
        $content .= '<div class="mb-2"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>';
        $content .= '<div class="captcha-row d-flex align-items-center mb-2">';
        $content .= '<img id="loginCaptcha" src="' . $base . '/captcha.php?generate=1" alt="captcha" class="me-2">';
        $content .= '<button type="button" id="refreshLoginCaptcha" class="btn btn-outline-light btn-sm">⟳</button>';
        $content .= '</div>';
        $content .= '<div class="mb-3"><label class="form-label">Captcha</label><input class="form-control" type="text" name="captcha" required maxlength="3" pattern="\\d{3}"></div>';
        $content .= '<div class="d-flex justify-content-between align-items-center">';
        $content .= '<button type="submit" class="btn btn-primary">Login</button>';
        $content .= '<a href="' . ($base !== '' ? $base . '/' : '/') . '" class="btn btn-outline-light">Kembali</a></div>';
        $err = $_SESSION['login_error'] ?? (Flight::request()->query['error'] ?? null);
        if (isset($_SESSION['login_error'])) unset($_SESSION['login_error']);
        if ($err) {
            $msg = '';
            if ($err === 'captcha') $msg = 'Captcha salah. Silakan ulangi.';
            elseif ($err === '1') $msg = 'Username atau password salah.';
            elseif ($err === 'missing') $msg = 'Lengkapi semua field yang diperlukan.';
            else $msg = 'Terjadi kesalahan. Silakan coba lagi.';
            $content .= '<div class="error">' . htmlspecialchars($msg) . '</div>';
        }
        $content .= '</form>';
        $content .= '</div>';
        Flight::render('public_layout', ['content' => $content]);
    }

    public static function postLogin(): void
    {
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        error_log('Login POST: user=' . ($_POST['username'] ?? '[none]') . ' posted_captcha=' . ($_POST['captcha'] ?? '[none]') . ' session_captcha=' . ($_SESSION['captcha'] ?? '[none]') . ' SID=' . session_id());

        $logfile = __DIR__ . '/../debug_login.log';
        $now = date('c');
        $postedUser = trim($_POST['username'] ?? '');
        $postedPw = $_POST['password'] ?? '';
        $found = 'no';
        $pw_verify = 'na';
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT id, nip, password FROM users WHERE nip = ?");
            $stmt->execute([$postedUser]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $found = 'yes';
                $pw_verify = password_verify($postedPw, $row['password']) ? 'match' : 'nomatch';
            }
        } catch (Throwable $e) {
            $row = null;
            $found = 'err';
        }
        $line = sprintf("[%s] login attempt user='%s' found=%s pw_verify=%s SID=%s\n", $now, $postedUser, $found, $pw_verify, session_id());
        @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);

        if (!isset($_POST['captcha']) || $_POST['captcha'] != $_SESSION['captcha']) {
            error_log('Login failed: captcha mismatch');
            $_SESSION['login_error'] = 'captcha';
            Flight::redirect($base . '/login');
        }

        $user = new User();
        $auth = $user->authenticate($_POST['username'], $_POST['password']);
        if ($auth) {
            // Prevent session fixation: regenerate session id on successful login
            try { session_regenerate_id(true); } catch (Throwable $e) { /* ignore if not supported */ }
            $_SESSION['user'] = $auth;
            error_log('Login success for user=' . ($_POST['username'] ?? '[none]'));
            Flight::redirect($base . '/dashboard');
        } else {
            error_log('Login failed: wrong credentials for user=' . ($_POST['username'] ?? '[none]'));
            $_SESSION['login_error'] = '1';
            Flight::redirect($base . '/login');
        }
    }

    public static function logout(): void
    {
        session_destroy();
        Flight::redirect('/');
    }
}
