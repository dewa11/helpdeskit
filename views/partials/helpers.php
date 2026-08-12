<?php

declare(strict_types=1);

/**
 * Shared HTML helpers for badges, status icons, and compact ticket snippets.
 */
function statusLabelMap(): array
{
    return [
        'submitted' => 'Diterima',
        'waiting' => 'Menunggu',
        'assigned' => 'Ditugaskan',
        'in_progress' => 'Sedang Diproses',
        'finished' => 'Selesai',
        'waiting_confirmation' => 'Menunggu Konfirmasi',
        'closed' => 'Ditutup'
    ];
}

function statusIconMap(): array
{
    return [
        'submitted' => 'bi-clock',
        'waiting' => 'bi-hourglass-split',
        'assigned' => 'bi-person-check',
        'in_progress' => 'bi-play-circle',
        'finished' => 'bi-check-circle',
        'waiting_confirmation' => 'bi-question-circle',
        'closed' => 'bi-x-circle'
    ];
}

function statusLabel(string $status): string
{
    $map = statusLabelMap();
    if (isset($map[$status])) {
        return $map[$status];
    }
    $normalized = trim($status);
    if ($normalized === '') return 'Tidak diketahui';
    return ucfirst(str_replace('_', ' ', $normalized));
}

function renderStatusIcon(?string $status): string
{
    $status = (string)$status;
    $statusClass = str_replace(' ', '_', $status);
    $iconMap = statusIconMap();
    $icon = $iconMap[$status] ?? 'bi-info-circle';
    $label = statusLabel($status);
    return '<span class="status-icon status-' . htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"><i class="bi ' . $icon . '"></i></span>';
}

function renderPriorityBadge(?string $priority, string $extraClass = ''): string
{
    $priority = $priority ?: 'Normal';
    $p = strtolower($priority);
    if ($p === 'urgent') {
        $class = 'badge bg-danger';
        $text = 'Urgent';
    } elseif ($p === 'low') {
        $class = 'badge bg-success';
        $text = 'Low';
    } else {
        $class = 'badge bg-secondary';
        $text = 'Normal';
    }
    if ($extraClass !== '') {
        $class .= ' ' . trim($extraClass);
    }
    return '<span class="' . $class . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}

function renderCategoryLabel(?string $category, ?string $subCategory = null): string
{
    $cat = htmlspecialchars((string)$category, ENT_QUOTES, 'UTF-8');
    if ($subCategory !== null && $subCategory !== '') {
        $sub = strtolower((string)$subCategory);
        $catLower = strtolower((string)$category);
        if ($catLower === 'simrs' || $sub !== 'error') {
            $cat .= ' / ' . htmlspecialchars((string)$subCategory, ENT_QUOTES, 'UTF-8');
        }
    }
    return $cat;
}

function renderCompactTimes(array $ticket): string
{
    $reportAt = !empty($ticket['created_at']) ? date('Y-m-d H:i', strtotime((string)$ticket['created_at'])) : '-';
    $assignedAt = !empty($ticket['assigned_at']) ? date('Y-m-d H:i', strtotime((string)$ticket['assigned_at'])) : '-';
    $startedAt = !empty($ticket['started_at']) ? date('Y-m-d H:i', strtotime((string)$ticket['started_at'])) : '-';
    $finishAt = !empty($ticket['finished_at']) ? date('Y-m-d H:i', strtotime((string)$ticket['finished_at'])) : '-';
    return '<div class="compact-times">Lapor: ' . $reportAt . ' &middot; Tugas: ' . $assignedAt . ' &middot; Mulai: ' . $startedAt . ' &middot; Selesai: ' . $finishAt . '</div>';
}

function formatRelativeTime(?string $timestamp): string
{
    if (empty($timestamp)) return '-';
    $t = strtotime((string)$timestamp);
    if (!$t) return date('Y-m-d H:i', strtotime((string)$timestamp));
    $diff = time() - $t;
    $abs = abs($diff);
    if ($abs < 60) $txt = $abs . ' detik';
    elseif ($abs < 3600) $txt = floor($abs/60) . ' menit';
    elseif ($abs < 86400) $txt = floor($abs/3600) . ' jam';
    elseif ($abs < 604800) $txt = floor($abs/86400) . ' hari';
    else $txt = date('Y-m-d', $t);
    return ($diff >= 0) ? $txt . ' lalu' : 'dalam ' . $txt;
}

function renderStatsCards(array $counts): string
{
    $labels = statusLabelMap();
    $icons = statusIconMap();
    $html = '';
    foreach ($counts as $status => $count) {
        $label = htmlspecialchars($labels[$status] ?? $status, ENT_QUOTES, 'UTF-8');
        $icon = $icons[$status] ?? 'bi-info-circle';
        $html .= '<div class="col-auto">';
        $html .= '<div class="card bg-light text-dark" style="min-width:110px">';
        $html .= '<div class="card-body p-2 text-center">';
        $html .= '<div class="h4 mb-0">' . (int)$count . '</div>';
        $html .= '<div class="mt-1"><span class="status-icon status-' . htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $label . '"><i class="bi ' . $icon . '"></i></span></div>';
        $html .= '</div></div></div>';
    }
    return $html;
}

/**
 * Authorization helper: check role
 */
function isRole(string $role): bool
{
    return isset($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === $role);
}

/**
 * Require a role for access. If $forApi is true, return a JSON 403 response; otherwise redirect.
 */
function requireRole(string $role, bool $forApi = false): void
{
    if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== $role)) {
        if ($forApi) {
            if (function_exists('Flight')) {
                Flight::json(['error' => 'unauthorized'], 403);
                Flight::stop();
            }
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'unauthorized']);
            exit;
        }
        if (function_exists('Flight')) {
            Flight::redirect('/dashboard');
            Flight::stop();
        }
        header('Location: /dashboard');
        exit;
    }
}

/**
 * Return a safe URL for an uploaded file by encoding path segments.
 */
function uploadUrl(?string $path): string
{
    if (empty($path)) return '';
    $safePath = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
    return $base . '/uploads/' . $safePath;
}
