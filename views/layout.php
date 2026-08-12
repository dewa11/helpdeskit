<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Helpdesk IT</title>
    <link rel="icon" type="image/png" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/images/RVL.png'; ?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/css/style.css'; ?>">
</head>
<body>
    <div class="sidebar bg-dark text-light p-3">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="sidebar-title text-uppercase mb-0"><span class="menu-text">MENU</span></h2>
            <button id="sidebarToggle" class="btn btn-outline-light btn-sm"><i class="bi bi-list"></i></button>
        </div>
        <hr class="border-secondary mt-3 mb-2">
        <ul class="nav flex-column mt-3">
            <li class="nav-item"><a class="nav-link text-light" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/dashboard'; ?>"><i class="bi bi-speedometer2 me-2"></i><span class="nav-text">Dashboard</span></a></li>
                <li class="nav-item"><a class="nav-link text-light" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/tickets'; ?>"><i class="bi bi-card-list me-2"></i><span class="nav-text">Tiket</span></a></li>
            <?php if (isset($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') == 'head_it')): ?>
                <li class="nav-item"><a class="nav-link text-light" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/users'; ?>"><i class="bi bi-people me-2"></i><span class="nav-text">User/Staff</span></a></li>
                <li class="nav-item"><a class="nav-link text-light" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/units'; ?>"><i class="bi bi-building me-2"></i><span class="nav-text">Unit</span></a></li>
                <li class="nav-item"><a class="nav-link text-light" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/reports'; ?>"><i class="bi bi-bar-chart-line me-2"></i><span class="nav-text">Laporan</span></a></li>
            <?php endif; ?>
        </ul>
            <div class="logout mt-4 d-flex flex-column gap-2">
            <?php if (isset($_SESSION['user'])): ?>
                <div class="user-name text-white fw-semibold mb-1"><?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?></div>
                <div class="d-flex gap-2 align-items-center">
                    <a class="btn btn-outline-light btn-sm" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/user/change-password'; ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Ganti Password"><i class="bi bi-key"></i></a>
                    <a class="btn btn-outline-light btn-sm" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/logout'; ?>"><i class="bi bi-box-arrow-right me-1"></i><span class="nav-text">Logout</span></a>
                </div>
            <?php endif; ?>
            <?php include __DIR__ . '/view_loader.php'; ?>
        </div>
    </div>
    <div class="content">
        <div class="main-panel container-fluid p-4">
            <?php echo $content; ?>
        </div>
    </div>
    <!-- Attachment lightbox modal -->
    <div class="modal fade" id="attachmentLightbox" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2 text-light" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div id="attachmentLightboxContent" class="w-100"></div>
                </div>
            </div>
        </div>
    </div>
    <script>var APP_BASE_PATH = '<?php echo APP_BASE_PATH; ?>';</script>
    <script src="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/js/script.js'; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <!-- Floating Action Button for mobile -->
    <a href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/tickets/create'; ?>" id="createTicketFab" class="fab d-none" aria-label="Buat Tiket" title="Buat Tiket">
        <i class="bi bi-plus-lg" style="font-size:20px"></i>
    </a>

    <!-- Bottom action bar shown on small screens -->
    <div class="bottom-action-bar d-none" id="mobileBottomBar" role="toolbar" aria-hidden="true">
        <button id="openFiltersBtn" class="btn btn-outline-secondary touch-target"><i class="bi bi-funnel-fill me-2"></i>Filter</button>
        <button id="applyFiltersBottom" class="btn btn-primary touch-target"><i class="bi bi-search me-2"></i>Terapkan</button>
        <div class="dropdown">
            <button class="btn btn-light touch-target dropdown-toggle" id="exportDropMobile" data-bs-toggle="dropdown" aria-expanded="false">Export</button>
            <ul class="dropdown-menu" aria-labelledby="exportDropMobile">
                <li><a class="dropdown-item export-option" href="#" data-format="csv">CSV</a></li>
                <li><a class="dropdown-item export-option" href="#" data-format="xlsx">XLSX</a></li>
            </ul>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) { new bootstrap.Tooltip(el); });
    });
    </script>
</body>
</html>