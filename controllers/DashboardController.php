<?php

declare(strict_types=1);

class DashboardController
{
    public static function showDashboard(): void
    {
        global $pdo;
        $ticket = new Ticket();
        if (($_SESSION['user']['role'] ?? '') == 'head_it') {
            $tickets = $ticket->getAll();
        } elseif (($_SESSION['user']['role'] ?? '') == 'it_staff') {
            $assigned = $ticket->getByAssigned($_SESSION['user']['id']);
            $waiting = $ticket->getWaitingUnassigned();
            $map = [];
            $merged = [];
            foreach (array_merge($assigned, $waiting) as $t) {
                if (isset($map[$t['id']])) continue;
                $map[$t['id']] = true; $merged[] = $t;
            }
            $tickets = $merged;
        } else {
            $tickets = $ticket->getByAssigned($_SESSION['user']['id']);
        }

        $filter = $_SESSION['dashboard_filter'] ?? 'all';
        if ($filter !== 'all') {
            $tickets = array_values(array_filter($tickets, function($tt) use ($filter) {
                return isset($tt['status']) && $tt['status'] === $filter;
            }));
        }

        $today = date('Y-m-d');
        // Keep dashboard focused to today's reports for non-head users, but show all to Head IT.
        // Allow IT staff to also see tickets assigned to them regardless of creation date.
        $currentUserId = $_SESSION['user']['id'] ?? null;
        $isStaff = (($_SESSION['user']['role'] ?? '') === 'it_staff');
        if (($_SESSION['user']['role'] ?? '') !== 'head_it') {
            $tickets = array_values(array_filter($tickets, function($tt) use ($today, $currentUserId, $isStaff) {
                if ($isStaff && !empty($tt['assigned_to']) && $tt['assigned_to'] == $currentUserId) return true;
                return !empty($tt['created_at']) && strpos($tt['created_at'], $today) === 0;
            }));
        }

        // Exclude already-closed tickets from the dashboard view
        $tickets = array_values(array_filter($tickets, function($tt) {
            return (($tt['status'] ?? '') !== 'closed');
        }));

        $usersStmt = $pdo->query("SELECT id, name FROM users");
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u['name'];

        $counts = ['submitted'=>0,'waiting'=>0,'assigned'=>0,'in_progress'=>0,'finished'=>0,'waiting_confirmation'=>0,'closed'=>0];
        foreach ($tickets as $t) { if (isset($counts[$t['status']])) $counts[$t['status']]++; }

        $filter = $_SESSION['dashboard_filter'] ?? 'all';

        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="d-flex align-items-center justify-content-between mb-3">';
        $content .= '<h2 class="mb-0">Dashboard</h2>';
        $content .= '<div class="d-flex gap-2 align-items-center">';
        $content .= '<select id="dashboardFilterSelect" class="form-select form-select-sm" style="width:auto">';
        $statuses = ['all'=>'Semua','submitted'=>'Diterima','waiting'=>'Menunggu','assigned'=>'Ditugaskan','in_progress'=>'Sedang Diproses','finished'=>'Selesai','waiting_confirmation'=>'Menunggu Konfirmasi','closed'=>'Ditutup'];
        foreach ($statuses as $k => $lbl) { $sel = ($filter === $k) ? ' selected' : ''; $content .= '<option value="' . $k . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>'; }
        $content .= '</select>';
        $content .= '<a class="btn btn-sm btn-outline-light" href="' . $base . '/report">Buat Tiket</a>';
        $content .= '</div>';
        $content .= '</div>';

        $content .= '<div id="dashboard-stats" class="row g-2 mb-3">';
        $content .= renderStatsCards($counts);
        $content .= '</div>';

        $content .= '<div class="table-responsive">';
        $content .= '<table class="table table-striped table-hover align-middle tickets-table dashboard-table"><thead><tr><th scope="col">#</th><th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">Unit</th><th scope="col">Kategori</th><th scope="col">Prioritas</th><th scope="col">Status</th><th scope="col">Petugas</th><th scope="col">Aksi</th></tr></thead><tbody id="dashboard-tbody">';
        foreach ($tickets as $t) {
            $assigned = $t['assigned_to'] ? ($userMap[$t['assigned_to']] ?? 'N/A') : '-';
            $content .= '<tr data-ticket-id="' . $t['id'] . '">';
            $content .= '<td>' . $t['id'] . '</td>';
            $content .= '<td><a class="ticket-code-link" href="' . $base . '/ticket/' . $t['id'] . '"><small>' . htmlspecialchars($t['ticket_code']) . '</small></a></td>';
            $content .= '<td>' . htmlspecialchars($t['nama']) . '<br><small>' . htmlspecialchars($t['nip']) . '</small>';
            $content .= '<div class="compact-times-wrap">' . renderCompactTimes($t) . '</div>';
            $content .= '</td>';
            $content .= '<td>' . htmlspecialchars($t['unit_dept']) . '</td>';
            $catLabel = renderCategoryLabel($t['category'] ?? '', $t['sub_category'] ?? null);
            $content .= '<td>' . $catLabel . '</td>';
            $priority = $t['priority'] ?? 'Normal';
            $pBadge = renderPriorityBadge($priority);
            $statusHtml = renderStatusIcon($t['status'] ?? '');
            $content .= '<td class="priority-cell">' . $pBadge . '</td>';
            $content .= '<td class="status-cell">' . $statusHtml . '</td>';
            $content .= '<td class="assigned-cell">' . htmlspecialchars($assigned) . '</td>';
            $content .= '<td>';
            $content .= '<a class="action-btn" href="' . $base . '/ticket/' . $t['id'] . '" role="button" data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat Photo/Video"><i class="bi bi-eye"></i></a>';
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
                $content .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/priority" class="priority-form d-inline ms-2">';
                $content .= '<select name="priority" class="form-select form-select-sm d-inline-block w-auto me-1">';
                $opts = ['Low'=>'Low','Normal'=>'Normal','Urgent'=>'Urgent'];
                foreach ($opts as $k => $v) {
                    $sel = ($v == $priority) ? ' selected' : '';
                    $content .= '<option value="' . $v . '"' . $sel . '>' . $k . '</option>';
                }
                $content .= '</select>';
                $content .= '<button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Set Prioritas" data-bs-toggle="tooltip" data-bs-placement="top" title="Set Prioritas"><i class="bi bi-check-lg"></i></button>';
                $content .= '</form>';
            
                // Add delete button for Head IT in tickets list
                $content .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/delete" class="delete-form d-inline ms-2" style="display:inline;">';
                $content .= '<button class="btn btn-sm btn-outline-danger" type="submit" data-ticket-id="' . $t['id'] . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Hapus Tiket"><i class="bi bi-trash"></i></button>';
                $content .= '</form>';
            }
            if ($_SESSION['user']['role'] == 'head_it' && ($t['status'] == 'submitted' || $t['status'] == 'waiting')) {
                $hasPriority = !empty($t['priority']);
                $disabled = $hasPriority ? '' : ' disabled';
                $content .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/assign" method="post" class="assign-form d-inline ms-2">';
                $content .= '<select name="assignee_id" class="form-select form-select-sm d-inline-block w-auto me-1"' . $disabled . '>';
                foreach ($users as $usr) {
                    $content .= '<option value="' . $usr['id'] . '">' . htmlspecialchars($usr['name']) . '</option>';
                }
                $content .= '</select>';
                $content .= '<button type="submit" class="btn btn-sm btn-outline-primary"' . $disabled . ' aria-label="Assign" data-bs-toggle="tooltip" data-bs-placement="top" title="Assign"><i class="bi bi-person-plus"></i></button>';
                $content .= '</form>';
            }
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'it_staff' && ($t['status'] == 'waiting') && empty($t['assigned_to'])) {
                $content .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/assign" method="post" class="assign-form d-inline ms-2">';
                $content .= '<button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Ambil Tiket" data-bs-toggle="tooltip" data-bs-placement="top" title="Ambil Tiket">Ambil</button>';
                $content .= '</form>';
            }
            if ($_SESSION['user']['role'] == 'it_staff' && $t['status'] == 'assigned' && $t['assigned_to'] == $_SESSION['user']['id']) {
                $content .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/start" method="post"><button type="submit" class="btn btn-sm btn-outline-primary">Start</button></form>';
            }
            if ($_SESSION['user']['role'] == 'it_staff' && $t['status'] == 'in_progress' && $t['assigned_to'] == $_SESSION['user']['id']) {
                $content .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/finish" method="post"><button type="submit" class="btn btn-sm btn-outline-success">Finish</button></form>';
            }
            $content .= '</td>';
            $content .= '</tr>';
        }
        $content .= '</tbody></table>';

        Flight::render('layout', ['content' => $content]);
    }

    public static function dashboardUpdates(): void
    {
        global $pdo;
        $ticket = new Ticket();
        $tickets = ($_SESSION['user']['role'] == 'head_it') ? $ticket->getAll() : $ticket->getByAssigned($_SESSION['user']['id']);
        $filter = $_SESSION['dashboard_filter'] ?? 'all';
        if ($filter !== 'all') {
            $tickets = array_values(array_filter($tickets, function($tt) use ($filter) {
                return isset($tt['status']) && $tt['status'] === $filter;
            }));
        }

        // Restrict to today for non-head users; Head IT should see all recent tickets
        $today = date('Y-m-d');
        $currentUserId = $_SESSION['user']['id'] ?? null;
        $isStaff = (($_SESSION['user']['role'] ?? '') === 'it_staff');
        if (($_SESSION['user']['role'] ?? '') !== 'head_it') {
            $tickets = array_values(array_filter($tickets, function($tt) use ($today, $currentUserId, $isStaff) {
                if ($isStaff && !empty($tt['assigned_to']) && $tt['assigned_to'] == $currentUserId) return true;
                return !empty($tt['created_at']) && strpos($tt['created_at'], $today) === 0;
            }));
        }

        // Exclude already-closed tickets from dashboard updates
        $tickets = array_values(array_filter($tickets, function($tt) {
            return (($tt['status'] ?? '') !== 'closed');
        }));

        $usersStmt = $pdo->query("SELECT id, name FROM users");
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u['name'];

        $counts = ['submitted'=>0,'waiting'=>0,'assigned'=>0,'in_progress'=>0,'finished'=>0,'waiting_confirmation'=>0,'closed'=>0];
        foreach ($tickets as $t) { if (isset($counts[$t['status']])) $counts[$t['status']]++; }

        $statsHtml = renderStatsCards($counts);

        $rowsHtml = '';
        $latest = $tickets;
        foreach ($latest as $t) {
            $assigned = $t['assigned_to'] ? ($userMap[$t['assigned_to']] ?? 'N/A') : '-';
            $priority = $t['priority'] ?? 'Normal';
            $pBadge = renderPriorityBadge($priority);
            $statusHtml = renderStatusIcon($t['status'] ?? '');
            $rowsHtml .= '<tr>';
            $rowsHtml .= '<td>' . $t['id'] . '</td>';
            $rowsHtml .= '<td><a class="ticket-code-link" href="' . $base . '/ticket/' . $t['id'] . '"><small>' . htmlspecialchars($t['ticket_code']) . '</small></a></td>';
            $rowsHtml .= '<td>' . htmlspecialchars($t['nama']) . '<br><small>' . htmlspecialchars($t['nip']) . '</small>';
            $rowsHtml .= '<div class="compact-times-wrap">' . renderCompactTimes($t) . '</div>';
            $rowsHtml .= '</td>';
            $rowsHtml .= '<td>' . htmlspecialchars($t['unit_dept']) . '</td>';
            $catLabel = renderCategoryLabel($t['category'] ?? '', $t['sub_category'] ?? null);
            $rowsHtml .= '<td>' . $catLabel . '</td>';
            $rowsHtml .= '<td class="priority-cell">' . $pBadge . '</td>';
            $rowsHtml .= '<td class="status-cell">' . $statusHtml . '</td>';
            $rowsHtml .= '<td class="assigned-cell">' . htmlspecialchars($assigned) . '</td>';
            $rowsHtml .= '<td>';
            $rowsHtml .= '<a class="action-btn" href="' . $base . '/ticket/' . $t['id'] . '" role="button" data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat"><i class="bi bi-eye"></i></a>';
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
                $rowsHtml .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/priority" class="priority-form d-inline ms-2">';
                $rowsHtml .= '<select name="priority" class="form-select form-select-sm d-inline-block w-auto me-1">';
                $opts = ['Low'=>'Low','Normal'=>'Normal','Urgent'=>'Urgent'];
                foreach ($opts as $k => $v) {
                    $sel = ($v == $priority) ? ' selected' : '';
                    $rowsHtml .= '<option value="' . $v . '"' . $sel . '>' . $k . '</option>';
                }
                $rowsHtml .= '</select>';
                $rowsHtml .= '<button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Set Prioritas" data-bs-toggle="tooltip" data-bs-placement="top" title="Set Prioritas"><i class="bi bi-check-lg"></i></button>';
                $rowsHtml .= '</form>';
                // Add delete button for Head IT
                $rowsHtml .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/delete" class="delete-form d-inline ms-2" style="display:inline;">';
                $rowsHtml .= '<button class="btn btn-sm btn-outline-danger" type="submit" data-ticket-id="' . $t['id'] . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Hapus Tiket"><i class="bi bi-trash"></i></button>';
                $rowsHtml .= '</form>';
            }
            if ($_SESSION['user']['role'] == 'head_it' && ($t['status'] == 'submitted' || $t['status'] == 'waiting')) {
                $hasPriority = !empty($t['priority']);
                $disabled = $hasPriority ? '' : ' disabled';
                $rowsHtml .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/assign" method="post" class="assign-form d-inline ms-2">';
                $rowsHtml .= '<select name="assignee_id" class="form-select form-select-sm d-inline-block w-auto me-1"' . $disabled . '>';
                foreach ($users as $usr) {
                    $rowsHtml .= '<option value="' . $usr['id'] . '">' . htmlspecialchars($usr['name']) . '</option>';
                }
                $rowsHtml .= '</select>';
                $rowsHtml .= '<button class="btn btn-sm btn-outline-primary"' . $disabled . ' aria-label="Assign" data-bs-toggle="tooltip" data-bs-placement="top" title="Assign"><i class="bi bi-person-plus"></i></button>';
                $rowsHtml .= '</form>';
            }
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'it_staff' && ($t['status'] == 'waiting') && empty($t['assigned_to'])) {
                $rowsHtml .= '<form style="display:inline;" action="' . $base . '/ticket/' . $t['id'] . '/assign" method="post" class="assign-form d-inline ms-2">';
                $rowsHtml .= '<button class="btn btn-sm btn-outline-primary" aria-label="Ambil Tiket" data-bs-toggle="tooltip" data-bs-placement="top" title="Ambil Tiket">Ambil</button>';
                $rowsHtml .= '</form>';
            }
            $rowsHtml .= '</td>';
            $rowsHtml .= '</tr>';
        }

        header('Content-Type: application/json');
        echo json_encode(['stats_html' => $statsHtml, 'rows_html' => $rowsHtml]);
        exit;
    }

    public static function setFilter(): void
    {
        $status = $_POST['status'] ?? 'all';
        $allowed = ['all','submitted','waiting','assigned','in_progress','waiting_confirmation','closed'];
        if (!in_array($status, $allowed)) $status = 'all';
        $_SESSION['dashboard_filter'] = $status;
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'filter' => $status]);
        exit;
    }

    public static function listTickets(): void
    {
        global $pdo;
        $ticket = new Ticket();
        $tickets = ($_SESSION['user']['role'] == 'head_it') ? $ticket->getAll() : $ticket->getByAssigned($_SESSION['user']['id']);

        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');

        $statusFilter = $_GET['status'] ?? 'all';
        $dateFrom = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
        $dateTo = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if ($statusFilter !== 'all') {
            $tickets = array_values(array_filter($tickets, function($tt) use ($statusFilter) { return isset($tt['status']) && $tt['status'] === $statusFilter; }));
        }
        if (!empty($dateFrom)) {
            $tickets = array_values(array_filter($tickets, function($tt) use ($dateFrom) {
                return !empty($tt['created_at']) && substr($tt['created_at'],0,10) >= $dateFrom;
            }));
        }
        if (!empty($dateTo)) {
            $tickets = array_values(array_filter($tickets, function($tt) use ($dateTo) {
                return !empty($tt['created_at']) && substr($tt['created_at'],0,10) <= $dateTo;
            }));
        }
        if (!empty($q)) {
            $qlow = strtolower($q);
            $tickets = array_values(array_filter($tickets, function($tt) use ($qlow) {
                $hay = strtolower((string)($tt['nama'] ?? '') . ' ' . (string)($tt['nip'] ?? ''));
                return strpos($hay, $qlow) !== false;
            }));
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 10;
        $total = count($tickets);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;
        $ticketsPage = array_slice($tickets, $offset, $perPage);
        $pageUrl = function(int $pageNum) use ($statusFilter, $dateFrom, $dateTo, $q, $base) {
            return $base . '/tickets?' . http_build_query([
                'status' => $statusFilter,
                'from' => $dateFrom,
                'to' => $dateTo,
                'q' => $q,
                'page' => $pageNum
            ]);
        };

        $usersStmt = $pdo->query("SELECT id, name FROM users");
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u['name'];

        $content = '<div class="d-flex align-items-center justify-content-between mb-3">';
        $content .= '<h2 class="mb-0">Daftar Tiket</h2>';
        $content .= '<div class="d-flex gap-2 align-items-center">';
        $content .= '<form method="get" action="' . $base . '/tickets" class="d-flex gap-2 align-items-center">';
        $statuses = ['all'=>'Semua','submitted'=>'Diterima','waiting'=>'Menunggu','assigned'=>'Ditugaskan','in_progress'=>'Sedang Diproses','finished'=>'Selesai','waiting_confirmation'=>'Menunggu Konfirmasi','closed'=>'Ditutup'];
        $content .= '<input type="search" name="q" class="form-control form-control-sm" style="width:220px" value="' . htmlspecialchars($q ?? '') . '" placeholder="Cari Nama atau NIP">';
        $content .= '<select name="status" class="form-select form-select-sm" style="width:auto">';
        foreach ($statuses as $k => $lbl) { $sel = ($statusFilter === $k) ? ' selected' : ''; $content .= '<option value="' . $k . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>'; }
        $content .= '</select>';
        $content .= '<input type="date" name="from" class="form-control form-control-sm" style="width:auto" value="' . htmlspecialchars($dateFrom) . '" placeholder="From">';
        $content .= '<input type="date" name="to" class="form-control form-control-sm" style="width:auto" value="' . htmlspecialchars($dateTo) . '" placeholder="To">';
        $today = date('Y-m-d');
        $content .= '<button class="btn btn-sm btn-primary" type="submit" aria-label="Filter"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button>';
        $content .= '<a class="btn btn-sm btn-outline-secondary" href="' . $base . '/tickets?from=' . $today . '&to=' . $today . '&status=all" role="button" aria-label="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i><span class="visually-hidden">Reset Filters</span></a>';
        $content .= '<a class="btn btn-sm btn-outline-primary" href="' . $base . '/dashboard">Kembali ke Dashboard</a>';
        $content .= '</form>';
        $content .= '</div>';
        $content .= '</div>';

        $content .= '<div class="table-responsive">';
        $content .= '<table class="table table-striped table-hover align-middle tickets-table"><thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Unit</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Petugas</th><th>Aksi</th></tr></thead><tbody>';
        foreach ($ticketsPage as $t) {
            $assigned = $t['assigned_to'] ? ($userMap[$t['assigned_to']] ?? 'N/A') : '-';
            $priority = $t['priority'] ?? 'Normal';
            $pBadge = renderPriorityBadge($priority);
            $statusHtml = renderStatusIcon($t['status'] ?? '');
            $content .= '<tr>';
            $content .= '<td>' . $t['id'] . '</td>';
            $content .= '<td><a class="ticket-code-link" href="' . $base . '/ticket/' . $t['id'] . '"><small>' . htmlspecialchars($t['ticket_code']) . '</small></a></td>';
            $content .= '<td>' . htmlspecialchars($t['nama']) . '<br><small>' . htmlspecialchars($t['nip']) . '</small>';
            $content .= renderCompactTimes($t) . '</td>';
            $content .= '<td>' . htmlspecialchars($t['unit_dept']) . '</td>';
            $catLabel = renderCategoryLabel($t['category'] ?? '', $t['sub_category'] ?? null);
            $content .= '<td>' . $catLabel . '</td>';
            $content .= '<td>' . $pBadge . '</td>';
            $content .= '<td>' . $statusHtml . '</td>';
            $content .= '<td>' . htmlspecialchars($assigned) . '</td>';
            $content .= '<td>';
            $content .= '<a class="action-btn" href="' . $base . '/ticket/' . $t['id'] . '" role="button" data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat"><i class="bi bi-eye"></i></a>';
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
                $content .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/priority" class="priority-form d-inline ms-2">';
                $content .= '<select name="priority" class="form-select form-select-sm d-inline-block w-auto me-1">';
                $opts = ['Low'=>'Low','Normal'=>'Normal','Urgent'=>'Urgent'];
                foreach ($opts as $k => $v) {
                    $sel = ($v == $priority) ? ' selected' : '';
                    $content .= '<option value="' . $v . '"' . $sel . '>' . $k . '</option>';
                }
                $content .= '</select>';
                $content .= '<button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Set Prioritas" data-bs-toggle="tooltip" data-bs-placement="top" title="Set Prioritas"><i class="bi bi-check-lg"></i></button>';
                $content .= '</form>';
                // Delete button for Head IT in ticket list
                $content .= '<form method="post" action="' . $base . '/ticket/' . $t['id'] . '/delete" class="delete-form d-inline ms-2" style="display:inline;">';
                $content .= '<button class="btn btn-sm btn-outline-danger" type="submit" data-ticket-id="' . $t['id'] . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Hapus Tiket"><i class="bi bi-trash"></i></button>';
                $content .= '</form>';
            }
            $content .= '</td>';
            $content .= '</tr>';
        }
        $content .= '</tbody></table>';
        $content .= '</div>';

        $start = $total ? ($offset + 1) : 0;
        $end = $total ? min($total, $offset + count($ticketsPage)) : 0;
        $content .= '<div class="d-flex justify-content-between align-items-center mt-2">';
        $content .= '<div class="small text-muted">Menampilkan ' . $start . ' - ' . $end . ' dari ' . $total . ' tiket</div>';
        $content .= '<nav aria-label="Pagination">';
        $content .= '<ul class="pagination pagination-sm mb-0">';
        if ($page > 1) {
            $content .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($page - 1)) . '">&laquo; Sebelumnya</a></li>';
        } else {
            $content .= '<li class="page-item disabled"><span class="page-link">&laquo; Sebelumnya</span></li>';
        }
        $content .= '<li class="page-item disabled"><span class="page-link">Hal ' . $page . ' / ' . $totalPages . '</span></li>';
        if ($page < $totalPages) {
            $content .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($page + 1)) . '">Berikutnya &raquo;</a></li>';
        } else {
            $content .= '<li class="page-item disabled"><span class="page-link">Berikutnya &raquo;</span></li>';
        }
        $content .= '</ul></nav></div>';

        Flight::render('layout', ['content' => $content]);
    }
}
