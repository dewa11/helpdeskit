<?php

declare(strict_types=1);

class ReportController
{
    public static function charts(): void
    {
        requireRole('head_it');
        $today = date('Y-m-d');
        // fetch units and users for filter controls
        $unitModel = new Unit();
        $usersModel = new User();
        try { $units = $unitModel->getAll(); } catch (Throwable $e) { $units = []; }
        try { $users = $usersModel->getAll(); } catch (Throwable $e) { $users = []; }

        // Render view fragment from views/reports.php for maintainability
        ob_start();
        $today = $today; // available to included view
        $units = $units;
        $users = $users;
        include __DIR__ . '/../views/reports.php';
        $content = ob_get_clean();
        Flight::render('layout', ['content' => $content]);
    }

    public static function data(): void
    {
        requireRole('head_it', true);

        $filters = [];
        $filters['from'] = $_GET['from'] ?? null;
        $filters['to'] = $_GET['to'] ?? null;
        $filters['status'] = $_GET['status'] ?? 'all';
        $filters['q'] = isset($_GET['q']) ? substr(trim($_GET['q']), 0, 200) : null;
        $filters['unit'] = $_GET['unit'] ?? null;
        $filters['assignee'] = $_GET['assignee'] ?? null;
        $filters['priority'] = $_GET['priority'] ?? null;
        $filters['reopened'] = $_GET['reopened'] ?? null;
        $filters['sla'] = $_GET['sla'] ?? null;
        $filters['unit'] = $_GET['unit'] ?? null;
        $filters['assignee'] = $_GET['assignee'] ?? null;
        $filters['priority'] = $_GET['priority'] ?? null;
        $filters['reopened'] = $_GET['reopened'] ?? null;
        $filters['sla'] = $_GET['sla'] ?? null;

        // Validate date inputs (YYYY-MM-DD)
        foreach (['from', 'to'] as $k) {
            if (!empty($filters[$k])) {
                $d = DateTime::createFromFormat('Y-m-d', $filters[$k]);
                if (!($d && $d->format('Y-m-d') === $filters[$k])) {
                    $filters[$k] = null;
                }
            }
        }

        // By default (no filters provided) restrict to today's data to keep payload small
        $isEmptyFilter = empty($filters['from']) && empty($filters['to']) && empty($filters['q']) && ($filters['status'] === 'all');
        if ($isEmptyFilter) {
            $today = date('Y-m-d');
            $filters['from'] = $today;
            $filters['to'] = $today;
            $listLimit = 50;
        } else {
            $listLimit = 200;
        }

        // Enforce a reasonable maximum page size
        $maxListLimit = 500;
        // allow client to request a page size (bounded)
        if (isset($_GET['page_size'])) {
            $requested = max(1, (int)$_GET['page_size']);
            $listLimit = min($requested, $maxListLimit);
        }
        // if client requested full list (e.g., view all), allow up to maxListLimit
        if (isset($_GET['full']) && ($_GET['full'] == '1' || $_GET['full'] === true)) {
            $listLimit = max($listLimit, $maxListLimit);
        } else {
            $listLimit = min($listLimit, $maxListLimit);
        }

        // Paging support: page number (1-based) -> offset
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $listLimit;

        $ticket = new Ticket();
        $data = $ticket->getReportsFiltered($filters);
        $metrics = $ticket->getMetricsFiltered($filters);
        $trends = $ticket->getTrendsFiltered($filters);
        $rows = $ticket->getReportsList($filters, $listLimit, $offset);
        $hasMore = count($rows) >= $listLimit;

        $rowsHtml = '';
        foreach ($rows as $r) {
            $code = htmlspecialchars($r['ticket_code']);
            $time = htmlspecialchars($r['created_at']);
            $statusKey = $r['status'] ?? '';
            $name = htmlspecialchars($r['nama']);
            $unit = htmlspecialchars($r['unit_dept'] ?? '');
            $desc = htmlspecialchars(substr($r['description'] ?? '', 0, 120));
            $attach = $r['attachment_path'] ?? '';
            $attachHtml = '';
            if (!empty($attach)) {
                // Safely encode path segments to avoid unsafe characters
                $safePath = implode('/', array_map('rawurlencode', explode('/', ltrim($attach, '/'))));
                $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
                $url = $base . '/uploads/' . $safePath;
                $attachHtml = "<a href=\"{$url}\" data-url=\"{$url}\" class=\"preview-attachment\">📎</a>";
            }
            // map status to badge class and Indonesian label
            $badgeClass = 'bg-secondary';
            $statusLabel = htmlspecialchars($statusKey);
            switch ($statusKey) {
                case 'waiting': $badgeClass = 'bg-warning text-dark'; $statusLabel = 'Menunggu'; break;
                case 'assigned': $badgeClass = 'bg-primary'; $statusLabel = 'Ditugaskan'; break;
                case 'in_progress': $badgeClass = 'bg-info text-dark'; $statusLabel = 'Sedang Dikerjakan'; break;
                case 'waiting_confirmation': $badgeClass = 'bg-secondary'; $statusLabel = 'Menunggu Konfirmasi'; break;
                case 'closed': $badgeClass = 'bg-success'; $statusLabel = 'Tertutup'; break;
            }
            $rowsHtml .= "<div class=\"report-row mb-2 p-2 border rounded\"><div class=\"d-flex justify-content-between\"><div><strong>{$code}</strong> — {$desc}<br><small>{$name} • {$unit}</small></div><div><span class=\"badge {$badgeClass}\">{$statusLabel}</span> {$attachHtml}<br><small>{$time}</small></div></div></div>";
        }

        Flight::json(['data' => $data, 'metrics' => $metrics, 'trends' => $trends, 'rows_html' => $rowsHtml, 'page' => $page, 'has_more' => $hasMore, 'page_size' => $listLimit]);
    }

    public static function sanity(): void
    {
        requireRole('head_it', true);

        $filters = [];
        $filters['from'] = $_GET['from'] ?? null;
        $filters['to'] = $_GET['to'] ?? null;
        $filters['status'] = $_GET['status'] ?? 'all';
        $filters['q'] = isset($_GET['q']) ? substr(trim($_GET['q']), 0, 200) : null;
        $filters['unit'] = $_GET['unit'] ?? null;
        $filters['assignee'] = $_GET['assignee'] ?? null;

        // Validate dates
        foreach (['from', 'to'] as $k) {
            if (!empty($filters[$k])) {
                $d = DateTime::createFromFormat('Y-m-d', $filters[$k]);
                if (!($d && $d->format('Y-m-d') === $filters[$k])) {
                    $filters[$k] = null;
                }
            }
        }

        $ticket = new Ticket();
        $report = $ticket->getSanityReport($filters);
        Flight::json(['sanity' => $report]);
    }

    public static function export(): void
    {
        requireRole('head_it');

        $filters = [];
        $filters['from'] = $_GET['from'] ?? null;
        $filters['to'] = $_GET['to'] ?? null;
        $filters['status'] = $_GET['status'] ?? 'all';
        $filters['q'] = isset($_GET['q']) ? substr(trim($_GET['q']), 0, 200) : null;
        $format = $_GET['format'] ?? 'csv';

        // Validate dates
        foreach (['from', 'to'] as $k) {
            if (!empty($filters[$k])) {
                $d = DateTime::createFromFormat('Y-m-d', $filters[$k]);
                if (!($d && $d->format('Y-m-d') === $filters[$k])) {
                    $filters[$k] = null;
                }
            }
        }

        $ticket = new Ticket();
        // Cap export size to avoid heavy memory/DB usage
        $maxExport = 2000;
        $rows = $ticket->getReportsList($filters, $maxExport + 1);
        if (count($rows) > $maxExport) {
            // Ask user to narrow filters
            $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
            Flight::redirect($base . '/reports?error=export_too_large');
            return;
        }

        $date = date('Ymd_His');
        if ($format === 'pdf') {
            // Render a simple HTML report (open in new tab)
            header('Content-Type: text/html; charset=utf-8');
            // Use Bootstrap for nicer presentation and a small print stylesheet
            echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Reports {$date}</title>";
            echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
            echo "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css\" rel=\"stylesheet\">";
            echo "<style>body{padding:20px;font-family:Helvetica,Arial,sans-serif} .report-header{display:flex;align-items:center;gap:16px;margin-bottom:16px} .report-logo{height:64px} .report-title{flex:1} .table-wrap{overflow:auto} @media print{ .no-print{display:none} }</style>";
            echo "</head><body>";
            // header with logo and localized labels
            $logoUrl = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/images/RVL.png';
            $periodFrom = !empty($filters['from']) ? $filters['from'] : '—';
            $periodTo = !empty($filters['to']) ? $filters['to'] : '—';
            echo '<div class="report-header">';
            echo '<img src="' . htmlspecialchars($logoUrl) . '" alt="RVL" class="report-logo"/>';
            echo '<div class="report-title"><h2 class="mb-0">Laporan Helpdesk IT</h2><div class="text-muted small">Periode: ' . htmlspecialchars((string)$periodFrom) . ' &mdash; ' . htmlspecialchars((string)$periodTo) . '</div></div>';
            echo '<div class="no-print"><a class="btn btn-sm btn-outline-secondary" href="#" onclick="window.print();return false;">Cetak</a></div>';
            echo '</div>';
            echo '<div class="table-wrap"><table class="table table-sm table-striped table-bordered">';
            echo '<thead class="table-light"><tr><th>Tiket</th><th>Dibuat</th><th>Selesai</th><th>Status</th><th>Prioritas</th><th>Nama Pelapor</th><th>Unit</th><th>Petugas</th><th>Alasan Penutupan</th><th>Deskripsi</th></tr></thead><tbody>';
            // prepare user resolver
            $userModel = new User();
            foreach ($rows as $r) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string)($r['ticket_code'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['created_at'] ?? '')) . '</td>';
                // show finished_at (staff finished) or fallback to closed_at
                $done = $r['finished_at'] ?? $r['closed_at'] ?? '';
                echo '<td>' . htmlspecialchars((string)$done) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['status'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['priority'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['nama'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['unit_dept'] ?? '')) . '</td>';
                // resolve assigned_to to staff name when possible
                $assignedLabel = '';
                if (!empty($r['assigned_to'])) {
                    if (is_numeric($r['assigned_to'])) {
                        $u = $userModel->getById((int)$r['assigned_to']);
                        if ($u && !empty($u['name'])) $assignedLabel = $u['name']; else $assignedLabel = (string)$r['assigned_to'];
                    } else {
                        $assignedLabel = (string)$r['assigned_to'];
                    }
                }
                echo '<td>' . htmlspecialchars($assignedLabel) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['closure_reason'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string)($r['description'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></body></html>';
            if (function_exists('Flight')) Flight::stop();
            return;
        }

        $filename = "reports_{$date}.csv";
        $contentType = 'text/csv';
        if ($format === 'xls') {
            $filename = "reports_{$date}.xls";
            $contentType = 'application/vnd.ms-excel';
        }

        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ticket_code','created_at','status','priority','nama','unit_dept','assigned_to','closure_reason','description','attachment_url']);
        foreach ($rows as $r) {
            $url = '';
            if (!empty($r['attachment_path'])) {
                $safePath = implode('/', array_map('rawurlencode', explode('/', ltrim($r['attachment_path'], '/'))));
                $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
                $url = $base . '/uploads/' . $safePath;
            }
            fputcsv($out, [$r['ticket_code'],$r['created_at'],$r['status'],$r['priority'],$r['nama'],$r['unit_dept'],$r['assigned_to'],$r['closure_reason'],$r['description'],$url]);
        }
        fclose($out);
        if (function_exists('Flight')) {
            Flight::stop();
        }
        return;
    }
}
