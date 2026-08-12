<?php

declare(strict_types=1);

class UnitController
{
    public static function list(): void
    {
        requireRole('head_it');
        $unit = new Unit();
        $units = $unit->getAll();
        $content = '';
        $content .= '<div class="card shadow-sm mb-3 bg-white"><div class="card-body d-flex justify-content-between align-items-center text-dark">';
        $content .= '<div><h4 class="mb-1">Unit / Departemen</h4><p class="text-muted mb-0">Kelola daftar unit/departemen</p></div>';
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content .= '<a class="btn btn-primary btn-sm" href="' . $base . '/unit/create" role="button" aria-label="Tambah Unit" data-bs-toggle="tooltip" data-bs-placement="top" title="Tambah Unit"><i class="bi bi-plus-lg"></i></a>';
        $content .= '</div></div>';

        $content .= '<div class="card shadow-sm bg-white"><div class="card-body text-dark">';
        if (count($units) === 0) {
            $content .= '<div class="alert alert-light border d-flex align-items-center mb-0"><i class="bi bi-info-circle me-2"></i><span>Belum ada unit terdaftar.</span></div>';
        } else {
            $content .= '<div class="table-responsive"><table class="table align-middle mb-0">';
            $content .= '<thead><tr><th class="text-uppercase text-muted small">Nama Unit</th></tr></thead><tbody>';
            foreach ($units as $u) {
                $content .= '<tr><td class="fw-semibold">' . htmlspecialchars($u['name']) . '</td></tr>';
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
            $content = '<div class="card shadow-sm bg-white"><div class="card-body text-dark">';
        $content .= '<h4 class="mb-3">Tambah Unit</h4>';
        $content .= '<form action="' . $base . '/unit/create" method="post" class="row g-3">';
        $content .= '<div class="col-12"><label class="form-label text-white">Nama Unit</label><input type="text" name="name" class="form-control" placeholder="Contoh: Radiologi" required></div>';
        $content .= '<div class="col-12 d-flex gap-2">';
        $content .= '<a class="btn btn-light border" href="' . $base . '/units"><i class="bi bi-arrow-left"></i> Kembali</a>';
        $content .= '<button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan</button>';
        $content .= '</div></form></div></div>';
        Flight::render('layout', ['content' => $content]);
    }

    public static function create(): void
    {
        requireRole('head_it');
        $unit = new Unit();
        $unit->create($_POST['name']);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/units');
    }
}
