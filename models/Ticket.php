<?php

require_once 'config.php';

class Ticket {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    private function cacheGet(string $key) {
        $file = sys_get_temp_dir() . '/helpdeskit_cache_' . md5($key);
        if (!file_exists($file)) return null;
        $data = @file_get_contents($file);
        if (!$data) return null;
        $payload = @unserialize($data);
        if (!is_array($payload) || !isset($payload['expire'])) return null;
        if ($payload['expire'] < time()) { @unlink($file); return null; }
        return $payload['value'];
    }

    private function cacheSet(string $key, $value, int $ttl = 300) {
        $file = sys_get_temp_dir() . '/helpdeskit_cache_' . md5($key);
        $payload = ['expire' => time() + $ttl, 'value' => $value];
        @file_put_contents($file, serialize($payload), LOCK_EX);
    }

    public function create($data) {
        $code = $this->generateCode();
        $stmt = $this->pdo->prepare("INSERT INTO tickets (nip, nama, no_wa, unit_dept, category, sub_category, description, attachment_path, ticket_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['nip'], $data['nama'], $data['no_wa'], $data['unit_dept'], $data['category'], $data['sub_category'], $data['description'], $data['attachment_path'], $code
        ]);
        return $this->pdo->lastInsertId();
    }

    private function generateCode() {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM tickets ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function assign($id, $userId) {
        // record assigned_at timestamp when assigning
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = 'assigned', assigned_to = ?, assigned_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$userId, $id]);
    }

    // Reassign without changing current status (used by Head IT even after closed)
    public function reassignKeepStatus($id, $userId) {
        $stmt = $this->pdo->prepare("UPDATE tickets SET assigned_to = ?, assigned_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$userId, $id]);
    }

    public function startProgress($id) {
        // record started_at when staff begins work
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = 'in_progress', started_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function finish($id) {
        // When staff finishes work, move ticket to waiting_confirmation so Head IT can verify/close
        // record finished_at when staff marks finished
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = 'waiting_confirmation', finished_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Reopen a ticket back to in_progress (used by Head IT to send back to staff)
    public function reopenToInProgress($id) {
        // reopen: set status back to in_progress and update started_at to now
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = 'in_progress', started_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function close($id, $reason) {
        // close and record closed_at and closure reason
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = 'closed', closure_reason = ?, closed_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$reason, $id]);
    }

    public function setPriority($id, $priority) {
        try {
            // Ensure priority column exists (attempt update; if fails, try ALTER and continue)
            // Fetch current ticket to decide status transition
            $t = $this->getById($id);
            $assigned = $t['assigned_to'] ?? null;
            $currStatus = $t['status'] ?? null;

            // If ticket is not yet assigned and not in a terminal/active state, mark as 'waiting'
            $noAssignee = empty($assigned);
            $activeStates = ['assigned','in_progress','finished','closed','waiting_confirmation'];
            $shouldSetWaiting = $noAssignee && !in_array($currStatus, $activeStates, true);

            if ($shouldSetWaiting) {
                $stmt = $this->pdo->prepare("UPDATE tickets SET priority = ?, status = 'waiting' WHERE id = ?");
                return $stmt->execute([$priority, $id]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE tickets SET priority = ? WHERE id = ?");
                return $stmt->execute([$priority, $id]);
            }
        } catch (PDOException $e) {
            // If the column doesn't exist, try adding it and retry the same logic
            try {
                $this->pdo->exec("ALTER TABLE tickets ADD COLUMN priority VARCHAR(20) DEFAULT 'Normal'");
            } catch (Exception $inner) {
                // ignore - maybe concurrent or unsupported
            }
            // Retry after ensuring column exists
            $t = $this->getById($id);
            $assigned = $t['assigned_to'] ?? null;
            $currStatus = $t['status'] ?? null;
            $noAssignee = empty($assigned);
            $activeStates = ['assigned','in_progress','finished','closed','waiting_confirmation'];
            $shouldSetWaiting = $noAssignee && !in_array($currStatus, $activeStates, true);
            if ($shouldSetWaiting) {
                $stmt = $this->pdo->prepare("UPDATE tickets SET priority = ?, status = 'waiting' WHERE id = ?");
                return $stmt->execute([$priority, $id]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE tickets SET priority = ? WHERE id = ?");
                return $stmt->execute([$priority, $id]);
            }
        }
    }

    public function getByAssigned($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE assigned_to = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWaitingUnassigned() {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE status = 'waiting' AND (assigned_to IS NULL OR assigned_to = '') ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReports() {
        // For charts: count by status, category, closure_reason
        $status = $this->pdo->query("SELECT status, COUNT(*) as count FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $category = $this->pdo->query("SELECT category, COUNT(*) as count FROM tickets GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
        $reasons = $this->pdo->query("SELECT closure_reason, COUNT(*) as count FROM tickets WHERE closure_reason IS NOT NULL GROUP BY closure_reason")->fetchAll(PDO::FETCH_ASSOC);
        return ['status' => $status, 'category' => $category, 'reasons' => $reasons];
    }

    public function getReportsFiltered(array $filters = []): array {
        // Build WHERE clauses based on filters: from, to, status, q (search)
        $where = [];
        $params = [];

        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        // Unit / department filter
        if (!empty($filters['unit'])) {
            $where[] = 'unit_dept = ?';
            $params[] = $filters['unit'];
        }
        // Assignee filter: numeric id or special 'unassigned'
        if (isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== null) {
            if ($filters['assignee'] === 'unassigned') {
                $where[] = '(assigned_to IS NULL OR assigned_to = \'\')';
            } elseif ($filters['assignee'] !== 'all') {
                $where[] = 'assigned_to = ?';
                $params[] = (int)$filters['assignee'];
            }
        }
        // Priority filter
        if (!empty($filters['priority'])) {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        // Reopened filter (heuristic)
        if (!empty($filters['reopened']) && $filters['reopened'] == '1') {
            $where[] = 'started_at IS NOT NULL AND finished_at IS NOT NULL AND started_at > finished_at';
        }
        // SLA filter: breached|open_breached|within
        if (!empty($filters['sla']) && $filters['sla'] !== 'all') {
            $sla = (int)(defined('SLA_HOURS') ? SLA_HOURS : 48);
            if ($filters['sla'] === 'breached') {
                $where[] = "((closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) > ?) OR (closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?))";
                $params[] = $sla; $params[] = $sla;
            } elseif ($filters['sla'] === 'open_breached') {
                $where[] = '(closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?)';
                $params[] = $sla;
            } elseif ($filters['sla'] === 'within') {
                $where[] = '(closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) <= ?)';
                $params[] = $sla;
            }
        }
        // Unit / department filter
        if (!empty($filters['unit'])) {
            $where[] = 'unit_dept = ?';
            $params[] = $filters['unit'];
        }
        // Assignee filter: numeric id or special 'unassigned'
        if (isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== null) {
            if ($filters['assignee'] === 'unassigned') {
                $where[] = '(assigned_to IS NULL OR assigned_to = \'\')';
            } elseif ($filters['assignee'] !== 'all') {
                $where[] = 'assigned_to = ?';
                $params[] = (int)$filters['assignee'];
            }
        }
        // Priority filter
        if (!empty($filters['priority'])) {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        // Reopened filter (heuristic)
        if (!empty($filters['reopened']) && $filters['reopened'] == '1') {
            $where[] = 'started_at IS NOT NULL AND finished_at IS NOT NULL AND started_at > finished_at';
        }
        // SLA filter: breached|open_breached|within
        if (!empty($filters['sla']) && $filters['sla'] !== 'all') {
            $sla = (int)(defined('SLA_HOURS') ? SLA_HOURS : 48);
            if ($filters['sla'] === 'breached') {
                $where[] = "((closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) > ?) OR (closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?))";
                $params[] = $sla; $params[] = $sla;
            } elseif ($filters['sla'] === 'open_breached') {
                $where[] = '(closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?)';
                $params[] = $sla;
            } elseif ($filters['sla'] === 'within') {
                $where[] = '(closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) <= ?)';
                $params[] = $sla;
            }
        }
        if (!empty($filters['q'])) {
            $where[] = '(ticket_code LIKE ? OR description LIKE ? OR nip LIKE ? OR nama LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }

        // Unit / department filter
        if (!empty($filters['unit'])) {
            $where[] = 'unit_dept = ?';
            $params[] = $filters['unit'];
        }
        // Assignee filter: numeric id or special 'unassigned'
        if (isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== null) {
            if ($filters['assignee'] === 'unassigned') {
                $where[] = '(assigned_to IS NULL OR assigned_to = \'\')';
            } elseif ($filters['assignee'] !== 'all') {
                $where[] = 'assigned_to = ?';
                $params[] = (int)$filters['assignee'];
            }
        }
        // Priority filter
        if (!empty($filters['priority'])) {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        // Reopened filter (heuristic)
        if (!empty($filters['reopened']) && $filters['reopened'] == '1') {
            $where[] = 'started_at IS NOT NULL AND finished_at IS NOT NULL AND started_at > finished_at';
        }
        // SLA filter: breached|open_breached|within
        if (!empty($filters['sla']) && $filters['sla'] !== 'all') {
            $sla = (int)(defined('SLA_HOURS') ? SLA_HOURS : 48);
            if ($filters['sla'] === 'breached') {
                $where[] = "((closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) > ?) OR (closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?))";
                $params[] = $sla; $params[] = $sla;
            } elseif ($filters['sla'] === 'open_breached') {
                $where[] = '(closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?)';
                $params[] = $sla;
            } elseif ($filters['sla'] === 'within') {
                $where[] = '(closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) <= ?)';
                $params[] = $sla;
            }
        }

        $whereSql = '';
        if (count($where) > 0) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        // Aggregates
        $statusSql = "SELECT status, COUNT(*) as count FROM tickets {$whereSql} GROUP BY status";
        $categorySql = "SELECT category, COUNT(*) as count FROM tickets {$whereSql} GROUP BY category";
        $reasonsSql = "SELECT closure_reason, COUNT(*) as count FROM tickets WHERE closure_reason IS NOT NULL";
        if ($whereSql !== '') {
            $reasonsSql = "SELECT closure_reason, COUNT(*) as count FROM tickets {$whereSql} AND closure_reason IS NOT NULL GROUP BY closure_reason";
        } else {
            $reasonsSql .= " GROUP BY closure_reason";
        }

        $stmt = $this->pdo->prepare($statusSql);
        $stmt->execute($params);
        $status = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare($categorySql);
        $stmt->execute($params);
        $category = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Units aggregation (count per unit_dept)
        $unitSql = "SELECT unit_dept as unit, COUNT(*) as count FROM tickets {$whereSql} GROUP BY unit_dept ORDER BY count DESC";
        $stmt = $this->pdo->prepare($unitSql);
        $stmt->execute($params);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare($reasonsSql);
        $stmt->execute($params);
        $reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['status' => $status, 'category' => $category, 'reasons' => $reasons, 'units' => $units];
    }

    public function delete(int $id): bool {
        // Remove files from uploads for the main attachment and attachments records
        try {
            $t = $this->getById($id);
            if ($t) {
                // delete primary attachment file
                if (!empty($t['attachment_path'])) {
                    $f = UPLOAD_DIR . $t['attachment_path'];
                    if (is_file($f)) @unlink($f);
                }
                // delete attachment rows files
                $stmt = $this->pdo->prepare("SELECT file_path FROM attachments WHERE ticket_id = ?");
                $stmt->execute([$id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $fp = $r['file_path'] ?? '';
                    if ($fp) { $ff = UPLOAD_DIR . $fp; if (is_file($ff)) @unlink($ff); }
                }
            }
            // Delete ticket row (attachments have FK ON DELETE CASCADE)
            $stmt = $this->pdo->prepare("DELETE FROM tickets WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            error_log('Failed to delete ticket ' . $id . ': ' . $e->getMessage());
            return false;
        }
    }

    public function getMetricsFiltered(array $filters = []): array {
        // Try cache first
        $cacheKey = 'metrics:' . md5(json_encode($filters));
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        // Build where clauses like other methods
        $where = [];
        $params = [];

        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(ticket_code LIKE ? OR description LIKE ? OR nip LIKE ? OR nama LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }

        $whereSql = '';
        if (count($where) > 0) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        // Total and closed counts
        $sqlTotal = "SELECT COUNT(*) as total FROM tickets {$whereSql}";
        $stmt = $this->pdo->prepare($sqlTotal);
        $stmt->execute($params);
        $total = (int)($stmt->fetchColumn() ?: 0);

        $sqlClosed = "SELECT COUNT(*) as closed FROM tickets {$whereSql} AND closed_at IS NOT NULL";
        if ($whereSql === '') $sqlClosed = "SELECT COUNT(*) as closed FROM tickets WHERE closed_at IS NOT NULL";
        $stmt = $this->pdo->prepare($sqlClosed);
        $stmt->execute($params);
        $closed = (int)($stmt->fetchColumn() ?: 0);

        // Average time to close (seconds) for closed tickets
        $sqlAvgClose = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avg_close FROM tickets {$whereSql} AND closed_at IS NOT NULL";
        if ($whereSql === '') $sqlAvgClose = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avg_close FROM tickets WHERE closed_at IS NOT NULL";
        $stmt = $this->pdo->prepare($sqlAvgClose);
        $stmt->execute($params);
        $avgClose = (int)($stmt->fetchColumn() ?: 0);

        // Average time to finish (seconds) between started_at and finished_at
        $sqlAvgFinish = "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) as avg_finish FROM tickets {$whereSql} AND started_at IS NOT NULL AND finished_at IS NOT NULL";
        if ($whereSql === '') $sqlAvgFinish = "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) as avg_finish FROM tickets WHERE started_at IS NOT NULL AND finished_at IS NOT NULL";
        $stmt = $this->pdo->prepare($sqlAvgFinish);
        $stmt->execute($params);
        $avgFinish = (int)($stmt->fetchColumn() ?: 0);

        // Reopened count heuristic: started_at > finished_at
        $sqlReopened = "SELECT COUNT(*) as reopened FROM tickets {$whereSql} AND started_at IS NOT NULL AND finished_at IS NOT NULL AND started_at > finished_at";
        if ($whereSql === '') $sqlReopened = "SELECT COUNT(*) as reopened FROM tickets WHERE started_at IS NOT NULL AND finished_at IS NOT NULL AND started_at > finished_at";
        $stmt = $this->pdo->prepare($sqlReopened);
        $stmt->execute($params);
        $reopened = (int)($stmt->fetchColumn() ?: 0);

        // SLA breaches: closed beyond SLA or open beyond SLA
        $slaHours = defined('SLA_HOURS') ? (int)SLA_HOURS : 48;
        // closed beyond SLA
        $sqlClosedBreach = "SELECT COUNT(*) as cb FROM tickets {$whereSql} AND closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) > ?";
        $paramsClosed = $params;
        $paramsClosed[] = $slaHours;
        if ($whereSql === '') {
            $sqlClosedBreach = "SELECT COUNT(*) as cb FROM tickets WHERE closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) > ?";
        }
        $stmt = $this->pdo->prepare($sqlClosedBreach);
        $stmt->execute($paramsClosed);
        $closedBreach = (int)($stmt->fetchColumn() ?: 0);

        // open beyond SLA
        $sqlOpenBreach = "SELECT COUNT(*) as ob FROM tickets {$whereSql} AND closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
        $paramsOpen = $params;
        $paramsOpen[] = $slaHours;
        if ($whereSql === '') {
            $sqlOpenBreach = "SELECT COUNT(*) as ob FROM tickets WHERE closed_at IS NULL AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?";
        }
        $stmt = $this->pdo->prepare($sqlOpenBreach);
        $stmt->execute($paramsOpen);
        $openBreach = (int)($stmt->fetchColumn() ?: 0);

        $slaBreach = $closedBreach + $openBreach;

        // closed within SLA
        $sqlClosedWithin = "SELECT COUNT(*) as cw FROM tickets {$whereSql} AND closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) <= ?";
        $paramsCW = $params;
        $paramsCW[] = $slaHours;
        if ($whereSql === '') $sqlClosedWithin = "SELECT COUNT(*) as cw FROM tickets WHERE closed_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, closed_at) <= ?";
        $stmt = $this->pdo->prepare($sqlClosedWithin);
        $stmt->execute($paramsCW);
        $closedWithin = (int)($stmt->fetchColumn() ?: 0);

        $percentClosedWithin = $closed > 0 ? round(($closedWithin / $closed) * 100, 2) : null;

        $result = [
            'total' => $total,
            'closed' => $closed,
            'avg_close_seconds' => $avgClose,
            'avg_finish_seconds' => $avgFinish,
            'reopened' => $reopened,
            'sla_breach' => $slaBreach,
            'closed_within_sla' => $closedWithin,
            'percent_closed_within_sla' => $percentClosedWithin,
            'sla_hours' => $slaHours,
        ];

        // cache for a short period to reduce DB load on repeated report views
        try { $this->cacheSet($cacheKey, $result, 300); } catch (Throwable $e) { /* ignore cache errors */ }

        return $result;
    }

    public function getTrendsFiltered(array $filters = [], int $maxDays = 60): array {
        // Try cache first
        $cacheKey = 'trends:' . md5(json_encode($filters)) . ':md' . (int)$maxDays;
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        // Determine date range
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $start = $filters['from'];
            $end = $filters['to'];
        } else {
            $endDt = new DateTime('today');
            $startDt = (clone $endDt)->modify('-' . min($maxDays, 30) . ' days');
            $start = $startDt->format('Y-m-d');
            $end = $endDt->format('Y-m-d');
        }

        // Prepare date map
        $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
        $dates = [];
        foreach ($period as $d) { $dates[] = $d->format('Y-m-d'); }

        // New (created) per day
        $stmt = $this->pdo->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM tickets WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY d ORDER BY d ASC");
        $stmt->execute([$start, $end]);
        $createdRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $createdMap = [];
        foreach ($createdRows as $r) { $createdMap[$r['d']] = (int)$r['c']; }

        // Closed per day
        $stmt = $this->pdo->prepare("SELECT DATE(closed_at) as d, COUNT(*) as c FROM tickets WHERE closed_at IS NOT NULL AND DATE(closed_at) BETWEEN ? AND ? GROUP BY d ORDER BY d ASC");
        $stmt->execute([$start, $end]);
        $closedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $closedMap = [];
        foreach ($closedRows as $r) { $closedMap[$r['d']] = (int)$r['c']; }

        // Avg close time per closed date
        $stmt = $this->pdo->prepare("SELECT DATE(closed_at) as d, AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avgsec FROM tickets WHERE closed_at IS NOT NULL AND DATE(closed_at) BETWEEN ? AND ? GROUP BY d ORDER BY d ASC");
        $stmt->execute([$start, $end]);
        $avgRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $avgMap = [];
        foreach ($avgRows as $r) { $avgMap[$r['d']] = (int)$r['avgsec']; }

        // Reopened heuristic per day using finished_at date
        $stmt = $this->pdo->prepare("SELECT DATE(finished_at) as d, COUNT(*) as c FROM tickets WHERE finished_at IS NOT NULL AND started_at IS NOT NULL AND started_at > finished_at AND DATE(finished_at) BETWEEN ? AND ? GROUP BY d ORDER BY d ASC");
        $stmt->execute([$start, $end]);
        $reopenRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reopenMap = [];
        foreach ($reopenRows as $r) { $reopenMap[$r['d']] = (int)$r['c']; }

        $created = [];
        $closed = [];
        $avgsec = [];
        $reopened = [];
        $reopenRate = [];
        foreach ($dates as $d) {
            $c = $createdMap[$d] ?? 0;
            $cl = $closedMap[$d] ?? 0;
            $as = $avgMap[$d] ?? 0;
            $ro = $reopenMap[$d] ?? 0;
            $created[] = $c;
            $closed[] = $cl;
            $avgsec[] = $as;
            $reopened[] = $ro;
            $reopenRate[] = $cl > 0 ? round(($ro / $cl) * 100, 2) : 0;
        }

        $result = ['dates' => $dates, 'created' => $created, 'closed' => $closed, 'avg_close_seconds' => $avgsec, 'reopened' => $reopened, 'reopen_rate' => $reopenRate, 'start' => $start, 'end' => $end];
        try { $this->cacheSet($cacheKey, $result, 300); } catch (Throwable $e) { /* ignore */ }
        return $result;
    }

    public function getSanityReport(array $filters = []): array {
        // Build basic where clauses (apply from/to/status/q/unit/assignee/priority similar to other methods)
        $where = [];
        $params = [];
        if (!empty($filters['from'])) { $where[] = 'created_at >= ?'; $params[] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'created_at <= ?'; $params[] = $filters['to'] . ' 23:59:59'; }
        if (!empty($filters['status']) && $filters['status'] !== 'all') { $where[] = 'status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['q'])) { $where[] = '(ticket_code LIKE ? OR description LIKE ? OR nip LIKE ? OR nama LIKE ?)'; $q = '%' . $filters['q'] . '%'; $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q; }
        if (!empty($filters['unit'])) { $where[] = 'unit_dept = ?'; $params[] = $filters['unit']; }
        if (isset($filters['assignee']) && $filters['assignee'] !== '' && $filters['assignee'] !== null && $filters['assignee'] !== 'all') {
            if ($filters['assignee'] === 'unassigned') { $where[] = '(assigned_to IS NULL OR assigned_to = \'\')'; }
            else { $where[] = 'assigned_to = ?'; $params[] = (int)$filters['assignee']; }
        }

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $issues = [];

        // 1) Closed but no closure_reason
        $sql = "SELECT COUNT(*) as c FROM tickets {$whereSql} AND status = 'closed' AND (closure_reason IS NULL OR TRIM(closure_reason) = '')";
        if ($whereSql === '') $sql = "SELECT COUNT(*) as c FROM tickets WHERE status = 'closed' AND (closure_reason IS NULL OR TRIM(closure_reason) = '')";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $cnt = (int)$stmt->fetchColumn();
        $samples = [];
        if ($cnt > 0) {
            $sSql = "SELECT id,ticket_code,created_at,status,assigned_to,assigned_at,started_at,finished_at,closed_at,closure_reason FROM tickets " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "status = 'closed' AND (closure_reason IS NULL OR TRIM(closure_reason) = '') ORDER BY created_at DESC LIMIT 5";
            $sStmt = $this->pdo->prepare($sSql); $sStmt->execute($params); $samples = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $issues[] = ['key' => 'closed_no_reason', 'label' => 'Closed without closure reason', 'count' => $cnt, 'samples' => $samples];

        // 2) Status is closed but closed_at is NULL or incorrect
        $sql = "SELECT COUNT(*) as c FROM tickets {$whereSql} AND status = 'closed' AND (closed_at IS NULL OR closed_at = '0000-00-00 00:00:00')";
        if ($whereSql === '') $sql = "SELECT COUNT(*) as c FROM tickets WHERE status = 'closed' AND (closed_at IS NULL OR closed_at = '0000-00-00 00:00:00')";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $cnt = (int)$stmt->fetchColumn(); $samples = [];
        if ($cnt > 0) {
            $sSql = "SELECT id,ticket_code,created_at,status,closed_at,closure_reason FROM tickets " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "status = 'closed' AND (closed_at IS NULL OR closed_at = '0000-00-00 00:00:00') ORDER BY created_at DESC LIMIT 5";
            $sStmt = $this->pdo->prepare($sSql); $sStmt->execute($params); $samples = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $issues[] = ['key' => 'closed_no_closed_at', 'label' => 'Status closed but closed_at missing', 'count' => $cnt, 'samples' => $samples];

        // 3) assigned_at set but assigned_to missing, or vice versa
        $sql = "SELECT COUNT(*) as c FROM tickets {$whereSql} AND ((assigned_at IS NOT NULL AND (assigned_to IS NULL OR TRIM(assigned_to)='')) OR (assigned_to IS NOT NULL AND (assigned_at IS NULL OR assigned_at = '0000-00-00 00:00:00')))";
        if ($whereSql === '') $sql = "SELECT COUNT(*) as c FROM tickets WHERE ((assigned_at IS NOT NULL AND (assigned_to IS NULL OR TRIM(assigned_to)='')) OR (assigned_to IS NOT NULL AND (assigned_at IS NULL OR assigned_at = '0000-00-00 00:00:00')))";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $cnt = (int)$stmt->fetchColumn(); $samples = [];
        if ($cnt > 0) {
            $sSql = "SELECT id,ticket_code,created_at,status,assigned_to,assigned_at FROM tickets " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "((assigned_at IS NOT NULL AND (assigned_to IS NULL OR TRIM(assigned_to)='')) OR (assigned_to IS NOT NULL AND (assigned_at IS NULL OR assigned_at = '0000-00-00 00:00:00'))) ORDER BY created_at DESC LIMIT 5";
            $sStmt = $this->pdo->prepare($sSql); $sStmt->execute($params); $samples = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $issues[] = ['key' => 'assigned_time_mismatch', 'label' => 'assigned_at/assigned_to mismatch', 'count' => $cnt, 'samples' => $samples];

        // 4) Time-travel issues: closed_at earlier than created_at
        $sql = "SELECT COUNT(*) as c FROM tickets {$whereSql} AND closed_at IS NOT NULL AND closed_at < created_at";
        if ($whereSql === '') $sql = "SELECT COUNT(*) as c FROM tickets WHERE closed_at IS NOT NULL AND closed_at < created_at";
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params); $cnt = (int)$stmt->fetchColumn(); $samples = [];
        if ($cnt > 0) {
            $sSql = "SELECT id,ticket_code,created_at,closed_at,status FROM tickets " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "closed_at IS NOT NULL AND closed_at < created_at ORDER BY created_at DESC LIMIT 5";
            $sStmt = $this->pdo->prepare($sSql); $sStmt->execute($params); $samples = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $issues[] = ['key' => 'time_travel', 'label' => 'closed_at earlier than created_at', 'count' => $cnt, 'samples' => $samples];

        // 5) Duplicate ticket_code
        $sql = "SELECT COUNT(*) as c FROM (SELECT ticket_code, COUNT(*) as cc FROM tickets " . ($whereSql ? $whereSql . " GROUP BY ticket_code HAVING cc > 1) t" : " GROUP BY ticket_code HAVING COUNT(*) > 1) t") ;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $cnt = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            // fallback if SQL dialect doesn't accept subselect aliasing
            $cnt = 0;
        }
        $samples = [];
        if ($cnt > 0) {
            $sSql = "SELECT id,ticket_code,created_at,status FROM tickets WHERE ticket_code IN (SELECT ticket_code FROM tickets GROUP BY ticket_code HAVING COUNT(*) > 1) ORDER BY ticket_code LIMIT 5";
            $sStmt = $this->pdo->query($sSql); $samples = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $issues[] = ['key' => 'duplicate_code', 'label' => 'Duplicate ticket_code', 'count' => $cnt, 'samples' => $samples];

        return ['issues' => $issues, 'generated_at' => date('c')];
    }

    public function getReportsList(array $filters = [], int $limit = 200, int $offset = 0): array {
        $where = [];
        $params = [];

        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(ticket_code LIKE ? OR description LIKE ? OR nip LIKE ? OR nama LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
        }

        $whereSql = '';
        if (count($where) > 0) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        // Bind params for WHERE clauses; append integer LIMIT directly to avoid driver quoting
        $limitInt = (int) $limit;
        $offsetInt = (int) $offset;
        $sql = "SELECT id, ticket_code, created_at, finished_at, closed_at, status, priority, nama, unit_dept, assigned_to, closure_reason, attachment_path, description FROM tickets {$whereSql} ORDER BY created_at DESC LIMIT " . $limitInt . " OFFSET " . $offsetInt;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}