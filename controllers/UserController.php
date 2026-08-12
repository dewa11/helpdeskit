<?php

declare(strict_types=1);

class UserController
{
    public static function list(): void
    {
        requireRole('head_it');
        $user = new User();
        $users = $user->getAll();
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="d-flex align-items-center justify-content-between mb-3"><h2 class="mb-0">Pengguna</h2><a class="btn btn-sm btn-primary" href="' . $base . '/user/create" role="button" aria-label="Add User" title="Add User"><i class="bi bi-plus-lg"></i></a></div>';
        $content .= '<div class="card"><div class="card-body">';
        if (count($users) === 0) {
            $content .= '<div class="alert alert-light">Belum ada pengguna terdaftar.</div>';
        } else {
            $content .= '<div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Nama</th><th>NIP</th><th>Role</th><th>Aksi</th></tr></thead><tbody>';
            foreach ($users as $u) {
                $content .= '<tr>';
                $content .= '<td class="fw-semibold">' . htmlspecialchars($u['name']) . '</td>';
                $content .= '<td>' . htmlspecialchars($u['nip']) . '</td>';
                $content .= '<td>' . htmlspecialchars($u['role']) . '</td>';
                $content .= '<td>';
                $content .= '<a class="btn btn-sm btn-outline-secondary me-1" href="' . $base . '/user/' . $u['id'] . '/edit" role="button" aria-label="Edit user" title="Edit"><i class="bi bi-pencil"></i></a>';
                $content .= '<form style="display:inline;" action="' . $base . '/user/' . $u['id'] . '/reset" method="post" onsubmit="return confirm(\'Reset password ke NIP untuk pengguna ini?\')">';
                $content .= '<button class="btn btn-sm btn-outline-warning" type="submit" aria-label="Reset password" title="Reset Password"><i class="bi bi-key"></i></button>';
                $content .= '</form>';
                $content .= '</td>';
                $content .= '</tr>';
            }
            $content .= '</tbody></table></div>';
        }
        $content .= '</div></div>';
        Flight::render('layout', ['content' => $content]);
    }

    public static function showCreate(): void
    {
        requireRole('head_it');
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="card mx-auto" style="max-width:600px"><div class="card-body">';
        $content .= '<h3 class="card-title mb-3">Tambah Pengguna</h3>';
        $content .= '<form action="' . $base . '/user/create" method="post">';
        $content .= '<div class="mb-3"><label class="form-label">Nama</label><input class="form-control" type="text" name="name" required></div>';
        $content .= '<div class="mb-3"><label class="form-label">NIP</label><input class="form-control" type="text" name="nip" required></div>';
        $content .= '<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role"><option value="it_staff">IT Staff</option><option value="head_it">Head IT</option></select></div>';
        $content .= '<div class="d-flex gap-2">';
        $content .= '<a class="btn btn-outline-secondary" href="' . $base . '/users" role="button" aria-label="Cancel" title="Cancel"><i class="bi bi-x-circle"></i></a>';
        $content .= '<button class="btn btn-primary" type="submit" aria-label="Save user" title="Save"><i class="bi bi-save"></i></button>';
        $content .= '</div>';
        $content .= '</form>';
        $content .= '</div></div>';
        Flight::render('layout', ['content' => $content]);
    }

    public static function create(): void
    {
        requireRole('head_it');
        $user = new User();
        $user->create($_POST['name'], $_POST['nip'], $_POST['role']);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/users');
    }

    public static function showEdit($id): void
    {
        requireRole('head_it');
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $user = new User();
        $u = $user->getById($id);
        if (!$u) Flight::notFound();
        $content = '<div class="card mx-auto" style="max-width:600px"><div class="card-body">';
        $content .= '<h3 class="card-title mb-3">Edit Pengguna</h3>';
        $content .= '<form action="' . $base . '/user/' . $u['id'] . '/edit" method="post">';
        $content .= '<div class="mb-3"><label class="form-label">Nama</label><input class="form-control" type="text" name="name" value="' . htmlspecialchars($u['name']) . '" required></div>';
        $content .= '<div class="mb-3"><label class="form-label">NIP</label><input class="form-control" type="text" name="nip" value="' . htmlspecialchars($u['nip']) . '" required></div>';
        $content .= '<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role">';
        $selHead = ($u['role'] == 'head_it') ? ' selected' : '';
        $selStaff = ($u['role'] == 'it_staff') ? ' selected' : '';
        $content .= '<option value="it_staff"' . $selStaff . '>IT Staff</option><option value="head_it"' . $selHead . '>Head IT</option>';
        $content .= '</select></div>';
        $content .= '<div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="' . $base . '/users">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>';
        $content .= '</form>';
        $content .= '</div></div>';
        Flight::render('layout', ['content' => $content]);
    }

    public static function update($id): void
    {
        requireRole('head_it');
        $user = new User();
        $user->update($id, $_POST['name'], $_POST['nip'], $_POST['role']);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/users');
    }

    public static function resetPassword($id): void
    {
        requireRole('head_it');
        $user = new User();
        $user->resetPasswordToNip($id);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/users');
    }

    public static function showChangePassword(): void
    {
        if (!isset($_SESSION['user'])) {
            $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
            Flight::redirect($base . '/login');
        }
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="card mx-auto" style="max-width:420px"><div class="card-body">';
        $content .= '<h3 class="card-title mb-3">Ganti Password</h3>';
        $content .= '<form action="' . $base . '/user/change-password" method="post">';
        $content .= '<div class="mb-2"><label class="form-label">Password Saat Ini</label><input class="form-control" type="password" name="current_password" required></div>';
        $content .= '<div class="mb-2"><label class="form-label">Password Baru</label><input class="form-control" type="password" name="new_password" required></div>';
        $content .= '<div class="mb-2"><label class="form-label">Konfirmasi Password Baru</label><input class="form-control" type="password" name="confirm_password" required></div>';
        $content .= '<div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="' . $base . '/dashboard">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>';
        $content .= '</form></div></div>';
        Flight::render('layout', ['content' => $content]);
    }

    public static function changePassword(): void
    {
        if (!isset($_SESSION['user'])) {
            $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
            Flight::redirect($base . '/login');
        }
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        if ($new === '' || $new !== $confirm) {
            Flight::redirect($base . '/user/change-password?error=nomatch');
        }
        $userId = $_SESSION['user']['id'];
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($current, $row['password'])) {
                Flight::redirect($base . '/user/change-password?error=wrong');
            }
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hashed, $userId]);
        } catch (Throwable $e) {
            Flight::redirect($base . '/user/change-password?error=server');
        }
        // Optionally refresh session user without password
        try {
            $stmt = $pdo->prepare("SELECT id, name, nip, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fresh) $_SESSION['user'] = $fresh;
        } catch (Throwable $e) {
            // ignore
        }
        Flight::redirect($base . '/dashboard?changed=1');
    }
}
