<?php

declare(strict_types=1);

class TicketController
{
    private static function normalizeWaNumber(string $input): string
    {
        $raw = trim($input);
        if ($raw === '') return '';
        // Keep plus for initial inspection, remove other non-digit/plus
        $clean = preg_replace('/[^0-9+]/', '', $raw);
        if ($clean === '') return '';
        // Strip leading plus if present
        if ($clean[0] === '+') $clean = substr($clean, 1);
        // Now normalize Indonesian numbers:
        // - leading 0 -> replace with 62
        // - leading 8 (user omitted 0) -> prepend 62
        // - already 62... -> keep
        if (preg_match('/^0[0-9]+$/', $clean)) {
            return '62' . substr($clean, 1);
        }
        if (preg_match('/^8[0-9]+$/', $clean)) {
            return '62' . $clean;
        }
        return $clean;
    }
    public static function reportForm(): void
    {
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content = '<div class="card report-card mx-auto" style="max-width:720px; width:100%">';
        $content .= '<div class="card-body">';
        $content .= '<h2 class="card-title mb-2">Laporkan Masalah</h2>';
        $content .= '<p class="muted">Silakan isi formulir berikut. Lampiran maksimal: Foto 2MB, Video 10MB (maks 15 detik).</p>';
        $content .= '<form id="reportForm" action="' . $base . '/report" method="post" enctype="multipart/form-data">';
        $content .= '<div class="mb-3"><label class="form-label">NIP</label><input class="form-control form-control-lg" type="text" name="nip" required placeholder="NIP"></div>';
        $content .= '<div class="mb-3"><label class="form-label">Nama</label><input class="form-control form-control-lg" type="text" name="nama" required placeholder="Nama"></div>';
        $content .= '<div class="mb-3"><label class="form-label">No. WA</label><input class="form-control form-control-lg" type="text" name="no_wa" required placeholder="08xxxxxxxxxx"></div>';
        $unitModel = new Unit();
        try {
            $units = $unitModel->getAll();
        } catch (Exception $e) {
            error_log('Error fetching units for report page: ' . $e->getMessage());
            $units = [];
            $content .= '<div class="error">Terjadi kesalahan pada server saat memuat daftar unit. Silakan hubungi Head IT.</div>';
        }
        $content .= '<div class="mb-3"><label class="form-label">Unit / Departemen</label>';
        if (count($units) > 0) {
            $content .= '<select name="unit_dept" class="form-select" required>'; 
            foreach ($units as $u) {
                $content .= '<option value="' . htmlspecialchars($u['name']) . '">' . htmlspecialchars($u['name']) . '</option>';
            }
            $content .= '</select>';
        } else {
            $content .= '<select name="unit_dept" class="form-select" required><option value="">-- Belum ada unit (Hubungi Head IT) --</option></select>';
        }
        $content .= '</div>';
        $content .= '<div class="mb-3"><label class="form-label">Kategori</label>';
        $content .= '<input type="hidden" name="category" id="category" required value="Hardware/Printer">';
        $content .= '<div class="category-grid d-grid" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">';
        $content .= '<button type="button" class="category-icon btn btn-outline-secondary text-start p-3 selected" data-value="Hardware/Printer">';
        $content .= '<div class="cat-icon">🖨️</div><div class="cat-label">Hardware/Printer</div></button>';
        $content .= '<button type="button" class="category-icon btn btn-outline-secondary text-start p-3" data-value="Software/OS">';
        $content .= '<div class="cat-icon">💻</div><div class="cat-label">Software/OS</div></button>';
        $content .= '<button type="button" class="category-icon btn btn-outline-secondary text-start p-3" data-value="Jaringan/Internet">';
        $content .= '<div class="cat-icon">🌐</div><div class="cat-label">Jaringan/Internet</div></button>';
        $content .= '<button type="button" class="category-icon btn btn-outline-secondary text-start p-3" data-value="SIMRS">';
        $content .= '<div class="cat-icon">🏥</div><div class="cat-label">SIMRS</div></button>';
        $content .= '</div>';
        $content .= '<div id="sub-category" class="mb-3" style="display:none; margin-top:10px;">';
        $content .= '<label class="form-label">Ada apa dengan SIMRS ? Pilih kategory di bawah ini :</label><select name="sub_category" class="form-select">';
        $content .= '<option value="Error">Error</option>';
        $content .= '<option value="Salah Input">Salah Input</option>';
        $content .= '<option value="Pendampingan">Pendampingan</option>';
        $content .= '<option value="Pengembangan">Pengembangan</option>';
        $content .= '</select></div>';
        $content .= '<div class="mb-3"><label class="form-label">Keterangan</label><textarea class="form-control" name="description" required placeholder="Jelaskan masalah secara detail..." rows="4"></textarea></div>';
        $content .= '<div class="mb-3">';
        $content .= '<label class="form-label fw-semibold">Lampiran (opsional)</label>';
        $content .= '<div class="d-flex flex-column gap-2">';
        $content .= '<label class="btn btn-sm btn-outline-primary text-start mb-0">📷 Ambil Foto<input id="attachmentCamera" type="file" name="attachment_camera" accept="image/*" capture="environment" class="d-none"></label>';
        $content .= '<label class="btn btn-sm btn-outline-secondary text-start mb-0">🖼️ Pilih File<input id="attachment" type="file" name="attachment" accept="image/*,video/*" class="d-none"></label>';
        $content .= '</div>';
        $content .= '<div id="attachment-info" class="muted small mt-1">Foto maks 2MB; video maks 10MB (15 detik). Gunakan "Ambil Foto" di ponsel untuk membuka kamera.</div>';
        $content .= '</div>';
        $content .= '<div class="captcha-row d-flex gap-2 align-items-center flex-wrap mb-2">';
        $content .= '<img id="captchaImg" src="' . $base . '/captcha.php?generate=1" alt="captcha">';
        $content .= '<button type="button" id="refreshCaptcha" class="btn btn-outline-secondary btn-sm" aria-label="Segarkan Captcha">⟳</button>';
        $content .= '</div>';
        $content .= '<div class="mb-3"><label class="form-label">Captcha</label><input class="form-control" type="text" name="captcha" required placeholder="Masukkan angka"></div>';
        $content .= '<div class="form-actions mt-3"><button type="submit" class="btn btn-success">Kirim</button><button type="reset" class="btn btn-secondary ms-2">Batal</button></div>';
        $content .= '</form>';
        $content .= '</div>';
        $content .= '</div>';

        // Review modal for report preview before sending
        $content .= '<div class="modal fade" id="reviewReportModal" tabindex="-1" aria-labelledby="reviewReportModalLabel" aria-hidden="true">';
        $content .= '<div class="modal-dialog modal-lg modal-dialog-centered">';
        $content .= '<div class="modal-content text-dark" style="color:#000">';
        $content .= '<div class="modal-header" style="color:#000">';
        $content .= '<h5 class="modal-title text-dark" id="reviewReportModalLabel" style="color:#000">Tinjau Laporan Anda</h5>';
        $content .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $content .= '</div>';
        $content .= '<div class="modal-body">';
        $content .= '<div id="reviewAttachmentPreview" class="mb-3 text-center"></div>';
        $content .= '<div id="reviewReportBody" class="text-dark">Memuat ringkasan...</div>';
        $content .= '<div id="reviewReportAlert" class="mt-3"></div>';
        $content .= '</div>';
        $content .= '<div class="modal-footer">';
        $content .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>';
        $content .= '<button type="button" id="reviewReportSendBtn" class="btn btn-success">Kirim</button>';
        $content .= '</div></div></div></div>';

        Flight::render('public_layout', ['content' => $content]);
    }

    public static function submitReport(): void
    {
        global $pdo;
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        if (empty($_POST['nip'] ?? '') || empty($_POST['nama'] ?? '') || empty($_POST['no_wa'] ?? '') || empty($_POST['unit_dept'] ?? '') || empty($_POST['category'] ?? '') ) {
            Flight::redirect($base . '/report?error=missing');
        }

        $attachmentPath = null;
        $file = null;
        if (isset($_FILES['attachment_camera']) && $_FILES['attachment_camera']['error'] == 0) {
            $file = $_FILES['attachment_camera'];
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $file = $_FILES['attachment'];
        }

        if ($file) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            $allowedImageExt = ['jpg','jpeg','png','gif'];
            $allowedVideoExt = ['mp4','mov','webm','mkv'];
            $newName = uniqid() . '.' . $ext;
            $path = UPLOAD_DIR . $newName;

                if (strpos($file['type'], 'image') === 0) {
                if (!in_array($ext, $allowedImageExt, true)) Flight::redirect($base . '/report?error=type');
                if ($file['size'] > MAX_IMAGE_SIZE) Flight::redirect($base . '/report?error=toolarge');
                if (!move_uploaded_file($file['tmp_name'], $path)) Flight::redirect($base . '/report?error=upload');
                $attachmentPath = $newName;
            } elseif (strpos($file['type'], 'video') === 0) {
                if (!in_array($ext, $allowedVideoExt, true)) Flight::redirect($base . '/report?error=type');
                if ($file['size'] > MAX_VIDEO_SIZE) Flight::redirect($base . '/report?error=toolarge');
                if (!move_uploaded_file($file['tmp_name'], $path)) Flight::redirect($base . '/report?error=upload');
                $attachmentPath = $newName;
            } else {
                Flight::redirect($base . '/report?error=type');
            }
        }

        $data = [
            'nip' => $_POST['nip'],
            'nama' => $_POST['nama'],
            'no_wa' => $_POST['no_wa'],
            'unit_dept' => $_POST['unit_dept'],
            'category' => $_POST['category'],
            'sub_category' => $_POST['sub_category'] ?? null,
            'description' => $_POST['description'],
            'attachment_path' => $attachmentPath
        ];

        $ticket = new Ticket();
        $id = $ticket->create($data);

        $t = $ticket->getById($id);
        $code = $t['ticket_code'] ?? $id;
        $hasAttachment = $attachmentPath ? 'Ada' : 'Tidak ada';
        $kategori = $data['category'];
        // If sub_category is set but equals 'Error' (common default), omit it from terse display
        if (!empty($data['sub_category']) && strtolower((string)$data['sub_category']) !== 'error') {
            $kategori .= ' / ' . $data['sub_category'];
        }
        $noWa = isset($data['no_wa']) ? $data['no_wa'] : '';
        $msg = "Kode Tiket : $code\n";
        $msg .= "Nama : " . $data['nama'] . "\n";
        $msg .= "NIP : " . $data['nip'] . "\n";
        $msg .= "Unit : " . ($data['unit_dept'] ?? '') . "\n";
        $msg .= "No. WA : " . $noWa . "\n";
        $msg .= "Kategori Trouble : " . $kategori . "\n";
        $msg .= "Photo/Video : " . $hasAttachment . "\n";
        $msg .= "Keterangan : " . $data['description'];
        // Use configured WhatsApp destination (Indonesia number provided by user)
        $waUrl = "https://wa.me/6285230719075?text=" . rawurlencode($msg);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        $forceAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        // TEMP DEBUG: log WA URL and request context to help diagnose handoff issues.
        // Remove this logging after debugging.
        try {
            $dbg = __DIR__ . '/../debug_wa.log';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ctx = date('c') . " | wa_url=" . $waUrl . " | isAjax=" . ($isAjax ? '1' : '0') . " | forceAjax=" . ($forceAjax ? '1' : '0') . " | ua=" . str_replace("\n", ' ', $ua) . "\n";
            @file_put_contents($dbg, $ctx, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // ignore logging failures
        }

        // Server-side: send notification to Telegram IT group using configured bot
        $telegram_sent = false;
        if (defined('TELEGRAM_BOT_TOKEN') && defined('TELEGRAM_CHAT_ID')) {
            try {
                $tgToken = TELEGRAM_BOT_TOKEN;
                $tgChat = TELEGRAM_CHAT_ID;
                $tgUrl = 'https://api.telegram.org/bot' . $tgToken . '/sendMessage';
                $post = [
                    'chat_id' => $tgChat,
                    'text' => $msg,
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $tgUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                // Send as application/x-www-form-urlencoded to avoid multipart encoding
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                // Slightly more generous timeouts for slow connections
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                // Allow connections on servers missing CA bundle (helps many shared hosts)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                $tgResp = curl_exec($ch);
                $tgErr = curl_errno($ch) ? curl_error($ch) : null;
                curl_close($ch);
                if ($tgResp) {
                    $r = @json_decode($tgResp, true);
                    if (is_array($r) && !empty($r['ok'])) $telegram_sent = true;
                }
                // Log telegram response for debugging
                try { @file_put_contents($dbg, date('c') . " | telegram_sent=" . ($telegram_sent ? '1' : '0') . " | resp=" . substr(($tgResp ?? ''), 0, 1000) . " | err=" . ($tgErr ?? '') . "\n", FILE_APPEND | LOCK_EX); } catch (Throwable $_){}
            } catch (Throwable $_) {
                try { @file_put_contents($dbg, date('c') . " | telegram_error\n", FILE_APPEND | LOCK_EX); } catch (Throwable $_){}
            }
        }
        if ($isAjax || $forceAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'wa_url' => $waUrl, 'telegram_sent' => ($telegram_sent ? 1 : 0), 'ticket_id' => $id]);
            exit;
        }

        // For non-AJAX (normal form submit), prefer rendering a small interstitial page
        // on mobile to improve app handoff reliability. For desktop, perform a direct redirect
        // to the wa.me URL which opens WhatsApp Web in a new tab.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isMobile = preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i', $ua);
        if ($isMobile) {
            $home = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '/');
            $safeWa = htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8');
            // app deep link: prefer whatsapp app; include phone param if present in wa.me link
            $appLink = '';
            // Extract the text param from wa.me if any
            $textParam = '';
            $parts = parse_url($waUrl);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                if (!empty($q['text'])) $textParam = $q['text'];
            }
            // Try phone param from path (wa.me/<phone>)
            $phone = '';
            if (!empty($parts['path'])) {
                $p = trim($parts['path'], '/');
                if (ctype_digit($p)) $phone = $p;
            }
            if ($phone !== '') {
                $appLink = 'whatsapp://send?phone=' . $phone . '&text=' . rawurlencode($textParam);
            } else {
                $appLink = 'whatsapp://send?text=' . rawurlencode($textParam);
            }

            $safeApp = htmlspecialchars($appLink, ENT_QUOTES, 'UTF-8');
            $safeHome = htmlspecialchars($home, ENT_QUOTES, 'UTF-8');

            // Render interstitial that first tries the app deep link, then falls back to wa.me, then to home
            header('Content-Type: text/html; charset=utf-8');
            echo "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>Open WhatsApp</title></head><body style=\"font-family:system-ui,Arial,sans-serif;padding:16px;\">";
            echo "<p>Membuka WhatsApp untuk mengirim notifikasi tiket... Jika tidak terbuka, <a id=\"waHref\" href=\"$safeWa\">ketuk di sini</a>.</p>";
            echo "<script>
                (function(){
                    var app = '" . $safeApp . "';
                    var web = '" . $safeWa . "';
                    // Try opening the app via a hidden iframe (more reliable on many mobiles)
                    try {
                        var ifr = document.createElement('iframe');
                        ifr.style.display = 'none';
                        ifr.src = app;
                        document.body.appendChild(ifr);
                    } catch(e) {}
                    // Fallback to wa.me after short delay
                    setTimeout(function(){ window.location.href = web; }, 1200);
                    // Final fallback to home to avoid leaving user stranded
                    setTimeout(function(){ window.location.href = '" . $safeHome . "'; }, 7000);
                })();
            </script>";
            echo "</body></html>";
            exit;
        }

        // Use 303 See Other to ensure browsers perform a GET after this POST
        header('Location: ' . $waUrl, true, 303);
        exit;
    }

    public static function viewTicket($id): void
    {
        $ticket = new Ticket();
        $t = $ticket->getById($id);
        if (!$t) Flight::notFound();
        $priority = $t['priority'] ?? 'Normal';
        $pBadge = renderPriorityBadge($priority, 'ms-2');

        $content = '';
        $content .= '<div class="card mb-4 shadow-sm">';
        $content .= '<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">';
        $content .= '<div class="d-flex flex-column">';
        $content .= '<div class="d-flex align-items-center gap-2">';
        $content .= '<span class="badge bg-secondary text-uppercase">Tiket #' . htmlspecialchars((string)$t['id']) . '</span>';
        $content .= renderStatusIcon($t['status'] ?? '');
        $content .= '<span id="detailPriorityBadge">' . $pBadge . '</span>';
        $content .= '</div>';
        $content .= '<div class="d-flex align-items-center gap-2 mt-1">';
        $content .= '<span class="fw-semibold">Kode: <span id="ticketCodeText">' . htmlspecialchars((string)($t['ticket_code'] ?? '')) . '</span></span>';
        $content .= '<button id="copyTicketCodeBtn" type="button" class="btn btn-sm btn-outline-secondary" data-code="' . htmlspecialchars((string)($t['ticket_code'] ?? '')) . '"><i class="bi bi-clipboard"></i></button>';
        $content .= '</div>';
        $content .= '</div>';
        $content .= '<div class="d-flex align-items-center gap-2">';
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $content .= '<a class="btn btn-outline-secondary btn-sm" href="' . $base . '/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>';
        $content .= '<a class="btn btn-primary btn-sm" href="' . $base . '/tickets"><i class="bi bi-card-list"></i> Daftar Tiket</a>';
        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
            $content .= '<form method="post" action="' . $base . '/ticket/' . $id . '/delete" class="delete-form d-inline ms-2" style="display:inline;">';
            $content .= '<button class="btn btn-sm btn-outline-danger" type="submit" data-ticket-id="' . $id . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Hapus Tiket"><i class="bi bi-trash"></i> Hapus</button>';
            $content .= '</form>';
        }
        // Notify client form (visible to head_it and it_staff)
        if (isset($_SESSION['user']) && in_array(($_SESSION['user']['role'] ?? ''), ['head_it','it_staff'], true)) {
            $content .= '<form action="' . $base . '/ticket/' . $id . '/notify" method="post" target="_blank" class="d-inline ms-2 notify-form">';
            $content .= '<button class="btn btn-sm btn-outline-success" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="Notify Client via WhatsApp"><i class="bi bi-chat-dots"></i> Notify</button>';
            $content .= '</form>';
        }
        // Staff-only notify Head IT to close ticket (bell icon)
        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'it_staff') {
            $headWa = '6285230719075';
            $msg = "Tiket: " . (($t['ticket_code'] ?? '') !== '' ? $t['ticket_code'] : $id) . "\nPetugas: " . ($_SESSION['user']['name'] ?? '-') . "\nStatus: Selesai. Mohon ditutup.";
            $waHref = 'https://wa.me/' . $headWa . '?text=' . rawurlencode($msg);
            $content .= '<a class="btn btn-outline-warning btn-sm ms-2" href="' . htmlspecialchars($waHref, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" data-bs-placement="top" title="Notifikasi Head IT untuk menutup tiket"><i class="bi bi-bell-fill"></i></a>';
        }
        $content .= '</div>';
        $content .= '</div>';

        $content .= '<div class="card-body">';
        $content .= '<div class="row g-3">';
        $content .= '<div class="col-lg-8">';
        $content .= '<div class="card border-0 bg-light mb-3"><div class="card-body">';
        $content .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">';
        $content .= '<span class="badge bg-secondary">NIP ' . htmlspecialchars((string)$t['nip']) . '</span>';
        $content .= '<span class="badge bg-info text-dark">' . htmlspecialchars((string)$t['unit_dept']) . '</span>';
        // Display client's WhatsApp number with wa.me link when available (normalize local formats)
        $waNum = self::normalizeWaNumber((string)($t['no_wa'] ?? ''));
        if ($waNum !== '') {
            $safeDisplay = htmlspecialchars((string)$t['no_wa']);
            $safeHref = htmlspecialchars('https://wa.me/' . $waNum, ENT_QUOTES, 'UTF-8');
            $content .= '<span class="badge bg-success"><a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer" class="text-white text-decoration-none">WA: ' . $safeDisplay . '</a></span>';
        }
        $catLabel = renderCategoryLabel($t['category'] ?? '', $t['sub_category'] ?? null);
        $content .= '<span class="badge bg-warning text-dark">' . $catLabel . '</span>';
        $content .= '</div>';
        $content .= '<h5 class="mb-1">' . htmlspecialchars((string)$t['nama']) . '</h5>';
        $content .= '<div id="detailStatusWrap" class="text-muted mb-3">Status: <span class="fw-semibold text-capitalize">' . htmlspecialchars((string)str_replace('_', ' ', $t['status'])) . '</span></div>';
        $content .= '<div class="mb-2"><span class="fw-semibold">Keterangan</span><br>' . nl2br(htmlspecialchars((string)$t['description'])) . '</div>';
        if (!empty($t['closure_reason'])) {
            $content .= '<div class="alert alert-secondary py-2 px-3 mb-2"><i class="bi bi-info-circle me-2"></i>Alasan penutupan: ' . htmlspecialchars((string)$t['closure_reason']) . '</div>';
        }
        if ($t['status'] == 'waiting' && ($_SESSION['user']['role'] ?? '') == 'it_staff') {
            $content .= '<form action="' . $base . '/ticket/' . $id . '/assign" method="post" class="mb-2 assign-form" data-ticket-id="' . $id . '"><button class="btn btn-sm btn-primary">Ambil Tiket</button></form>';
        }
        if ($t['status'] == 'assigned' && $t['assigned_to'] == ($_SESSION['user']['id'] ?? null)) {
            $content .= '<form id="startForm" action="' . $base . '/ticket/' . $id . '/start" method="post" class="mb-2" data-ticket-id="' . $id . '"><button class="btn btn-sm btn-outline-primary">Mulai Kerja</button></form>';
        }
        if ($t['status'] == 'in_progress' && $t['assigned_to'] == ($_SESSION['user']['id'] ?? null)) {
            $content .= '<form id="finishForm" action="' . $base . '/ticket/' . $id . '/finish" method="post" enctype="multipart/form-data" class="mb-2" data-ticket-id="' . $id . '">';
            $content .= '<div class="mb-2"><label class="form-label">Bukti (opsional)</label><input class="form-control form-control-sm" type="file" name="proof"></div>';
            $content .= '<button class="btn btn-sm btn-success">Selesai</button></form>';
        }
        $content .= '</div></div>';

        // Move attachments and close-ticket controls into left column (below main description)
        $proofs = [];
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT file_path, type, uploaded_at FROM attachments WHERE ticket_id = ? ORDER BY uploaded_at DESC, id DESC");
            $stmt->execute([$id]);
            $proofs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // optional, ignore
        }

        if (!empty($t['attachment_path']) || !empty($proofs)) {
            $content .= '<div class="card border-0 shadow-sm mb-3"><div class="card-body">';
            $content .= '<h6 class="card-title">Lampiran & Bukti</h6>';
            $content .= '<div class="row g-2 attachments-gallery">';
            if (!empty($t['attachment_path'])) {
                $attUrl = uploadUrl($t['attachment_path']);
                $safeUrl = htmlspecialchars($attUrl, ENT_QUOTES, 'UTF-8');
                $content .= '<div class="col-6 col-md-4">';
                $content .= '<div class="card h-100">';
                $content .= '<div class="ratio ratio-4x3 position-relative overflow-hidden">';
                $content .= '<img loading="lazy" src="' . $safeUrl . '" class="w-100 h-100 attachment-thumb" style="object-fit:cover;" data-type="image" data-src="' . $safeUrl . '">';
                $content .= '<a class="stretched-link" href="' . $safeUrl . '" target="_blank" aria-label="Lihat lampiran"></a>';
                $content .= '</div>';
                $content .= '<div class="card-footer py-1 small text-muted text-center">Lampiran pelapor</div>';
                $content .= '</div></div>';
            }
            foreach ($proofs as $p) {
                $fileUrl = uploadUrl($p['file_path'] ?? '');
                $type = strtolower((string)($p['type'] ?? ''));
                $uploaded = !empty($p['uploaded_at']) ? date('Y-m-d H:i', strtotime((string)$p['uploaded_at'])) : '';
                $safeUrl = htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8');
                $content .= '<div class="col-6 col-md-4">';
                $content .= '<div class="card h-100">';
                $content .= '<div class="ratio ratio-4x3 position-relative overflow-hidden">';
                if ($type === 'video') {
                    $content .= '<video class="w-100 h-100 attachment-thumb" controls preload="metadata" data-type="video"><source src="' . $safeUrl . '"></video>';
                } else {
                    $content .= '<img loading="lazy" src="' . $safeUrl . '" class="w-100 h-100 attachment-thumb" style="object-fit:cover;" data-type="image" data-src="' . $safeUrl . '">';
                }
                $content .= '<a class="stretched-link" href="' . $safeUrl . '" target="_blank" aria-label="Lihat bukti"></a>';
                $content .= '</div>';
                if ($uploaded) { $content .= '<div class="card-footer py-1 small text-muted text-center">' . $uploaded . '</div>'; }
                $content .= '</div>';
                $content .= '</div>';
            }
            $content .= '</div>'; // row
            $content .= '</div></div>';
        }

        // Close ticket controls (move to left column)
        if (($t['status'] ?? '') == 'waiting_confirmation' && ($_SESSION['user']['role'] ?? '') == 'head_it') {
            $content .= '<div class="card p-3 bg-light">';
            $content .= '<h6 class="mb-2">Tutup Tiket</h6>';
            $content .= '<form id="closeForm" action="' . $base . '/ticket/' . $id . '/close" method="post">';
            $content .= '<div class="mb-2"><label class="form-label">Alasan Penutupan</label>';
            $content .= '<select id="closeReasonSelect" name="reason" class="form-select" required>';
            $reasons = [
                "Kesalahan Pengguna",
                "Kerusakan Perangkat Keras",
                "Bug Perangkat Lunak",
                "Masalah Jaringan",
                "Kesalahan Konfigurasi",
                "Perlu Pelatihan",
                "Faktor Eksternal/Pihak Ketiga",
                "Terselesaikan Tanpa Tindakan",
                "Lainnya"
            ];
            foreach ($reasons as $r) { $content .= '<option value="' . htmlspecialchars($r) . '">' . htmlspecialchars($r) . '</option>'; }
            $content .= '</select></div>';
            $content .= '<div id="otherReasonWrap" class="mb-2" style="display:none;">';
            $content .= '<label class="form-label">Jelaskan (Lainnya)</label>';
            $content .= '<textarea id="otherReasonInput" name="other_reason" class="form-control" rows="3" placeholder="Jelaskan alasan..."></textarea>';
            $content .= '</div>';
            $content .= '<div class="d-grid">';
            $content .= '<button id="openCloseModalBtn" type="button" class="btn btn-danger">Tutup Tiket</button>';
            $content .= '</div>';
            $content .= '</form>';
            $content .= '</div>';

            $content .= '<div class="modal fade" id="confirmCloseModal" tabindex="-1" aria-hidden="true">';
            $content .= '<div class="modal-dialog modal-dialog-centered">';
            $content .= '<div class="modal-content">';
            $content .= '<div class="modal-header"><h5 class="modal-title">Konfirmasi Penutupan Tiket</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>';
            $content .= '<div class="modal-body">';
            $content .= '<p>Anda akan menutup tiket ini dengan alasan berikut:</p>';
            $content .= '<p id="confirmReasonText" class="fw-semibold"></p>';
            $content .= '<p id="confirmOtherText" class="small text-muted"></p>';
            $content .= '</div>';
            $content .= '<div class="modal-footer">';
            $content .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i><span class="visually-hidden">Batal</span></button>';
            $content .= '<button id="confirmCloseBtn" type="button" class="btn btn-danger"><i class="bi bi-check-circle-fill"></i><span class="visually-hidden">Konfirmasi Tutup</span></button>';
            $content .= '</div></div></div></div>';
        }

        $content .= '</div>';

        $content .= '<div class="col-lg-4">';
        $content .= '<div class="card border-0 shadow-sm mb-3"><div class="card-body">';
        $content .= '<h6 class="card-title">Status</h6>';
        $content .= '<p class="mb-2">' . renderStatusIcon($t['status'] ?? '') . ' <span class="text-uppercase ms-1">' . htmlspecialchars((string)$t['status']) . '</span></p>';

        $assignedName = '-';
        global $pdo;
        if (!empty($t['assigned_to'])) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$t['assigned_to']]);
            $assignedName = $stmt->fetchColumn() ?: '-';
        }
        $content .= '<div class="mb-3"><div class="fw-semibold">Petugas</div><div>' . htmlspecialchars($assignedName) . '</div></div>';

        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
            $userStmt = $pdo->query("SELECT id, name FROM users ORDER BY name ASC");
            $allUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            $content .= '<div class="mb-3">';
            $content .= '<div class="fw-semibold mb-1">Ganti Petugas</div>';
            $content .= '<form id="reassignForm" action="' . $base . '/ticket/' . $id . '/reassign" method="post" class="d-flex gap-2 align-items-center flex-wrap">';
            $content .= '<select name="assignee_id" class="form-select form-select-sm w-auto" required>';
            foreach ($allUsers as $usr) {
                $sel = ($t['assigned_to'] ?? null) == $usr['id'] ? ' selected' : '';
                $content .= '<option value="' . $usr['id'] . '"' . $sel . '>' . htmlspecialchars($usr['name']) . '</option>';
            }
            $content .= '</select>';
            $content .= '<button id="openReassignModalBtn" type="button" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Ganti</button>';
            $content .= '</form>';
            $content .= '<div class="text-muted small mt-1">Status tiket tidak berubah saat ganti petugas.</div>';
            $content .= '</div>';
        }

        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it' && ($t['status'] ?? '') != 'closed') {
            $content .= '<div class="mb-3">';
            $content .= '<div class="fw-semibold mb-1">Prioritas</div>';
            $content .= '<form id="priorityForm" data-ticket-id="' . $id . '" method="post" action="' . $base . '/ticket/' . $id . '/priority" class="d-flex gap-2 align-items-center">';
            $content .= '<select name="priority" class="form-select form-select-sm w-auto">';
            $opts = ['Low'=>'Low','Normal'=>'Normal','Urgent'=>'Urgent'];
            foreach ($opts as $k => $v) { $sel = ($v == $priority) ? ' selected' : ''; $content .= '<option value="' . $v . '"' . $sel . '>' . $k . '</option>'; }
            $content .= '</select>';
            $content .= '<button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Simpan</button>';
            $content .= '</form>';
            $content .= '</div>';
        }

        // Head IT can finish staff's job if needed
        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it' && ($t['status'] ?? '') == 'in_progress') {
            $content .= '<div class="mb-3">';
            $content .= '<div class="fw-semibold mb-1">Selesaikan Pekerjaan (Head IT)</div>';
            $content .= '<form class="finish-form d-flex gap-2 align-items-center" action="' . $base . '/ticket/' . $id . '/finish" method="post" enctype="multipart/form-data" data-ticket-id="' . $id . '">';
            $content .= '<input type="file" name="proof" class="form-control form-control-sm w-50">';
            $content .= '<button class="btn btn-sm btn-danger">Selesai untuk Petugas</button>';
            $content .= '</form>';
            $content .= '<div class="text-muted small mt-1">Gunakan jika petugas lupa menandai selesai.</div>';
            $content .= '</div>';
        }

        $content .= '<div class="mb-3">';
        $content .= '<div class="fw-semibold mb-1">Timeline</div>';
        $timeline = [
            ['label' => 'Dilapor', 'value' => $t['created_at'] ?? null, 'icon' => 'bi-flag'],
            ['label' => 'Ditugaskan', 'value' => $t['assigned_at'] ?? null, 'icon' => 'bi-person-check'],
            ['label' => 'Mulai Kerja', 'value' => $t['started_at'] ?? null, 'icon' => 'bi-play-circle'],
            ['label' => 'Selesai', 'value' => $t['finished_at'] ?? null, 'icon' => 'bi-check-circle'],
            ['label' => 'Ditutup', 'value' => $t['closed_at'] ?? null, 'icon' => 'bi-x-circle']
        ];
        $content .= '<ul class="list-unstyled timeline-stepper">';
        foreach ($timeline as $step) {
            $date = $step['value'] ? date('Y-m-d H:i', strtotime((string)$step['value'])) : '-';
            $rel = formatRelativeTime($step['value'] ?? null);
            $content .= '<li class="d-flex align-items-start gap-2 mb-2">';
            $content .= '<span class="timeline-dot"><i class="bi ' . $step['icon'] . '"></i></span>';
            $content .= '<div><div class="fw-semibold">' . htmlspecialchars($step['label']) . '</div><div class="text-muted small">' . $date . ' (' . $rel . ')</div></div>';
            $content .= '</li>';
        }
        $content .= '</ul>';
        $content .= '</div>';

        $content .= '</div></div>';

        

        if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') == 'head_it') {
            $content .= '<div class="modal fade" id="reassignConfirmModal" tabindex="-1" aria-hidden="true">';
            $content .= '<div class="modal-dialog modal-dialog-centered">';
            $content .= '<div class="modal-content">';
            $content .= '<div class="modal-header"><h5 class="modal-title">Konfirmasi Ganti Petugas</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>';
            $content .= '<div class="modal-body">';
            $content .= '<p>Petugas akan diganti menjadi: <span id="reassignTargetText" class="fw-semibold"></span>.</p>';
            $content .= '<p class="text-muted small mb-0">Status tiket tidak berubah. Lanjutkan?</p>';
            $content .= '</div>';
            $content .= '<div class="modal-footer">';
            $content .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i><span class="visually-hidden">Batal</span></button>';
            $content .= '<button id="confirmReassignBtn" type="button" class="btn btn-primary"><i class="bi bi-check-circle"></i><span class="visually-hidden">Konfirmasi</span></button>';
            $content .= '</div></div></div></div>';
        }

        $content .= '</div>';
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</div>';

        Flight::render('layout', ['content' => $content]);
    }

    public static function setPriority($id): void
    {
        requireRole('head_it');
        $priority = $_POST['priority'] ?? 'Normal';
        $ticket = new Ticket();
        try {
            $ticket->setPriority($id, $priority);
        } catch (Throwable $e) {
            error_log('Failed to set priority for ticket ' . $id . ': ' . $e->getMessage());
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            $priorityVal = $t['priority'] ?? 'Normal';
            $priorityHtml = renderPriorityBadge($priorityVal);

            $status = $t['status'] ?? '';
            $statusHtml = renderStatusIcon($status);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'priority_html' => $priorityHtml, 'status_html' => $statusHtml, 'priority' => $priorityVal, 'status' => $status, 'ticket_id' => $id, 'compact_times_html' => renderCompactTimes($t)]);
            exit;
        }

        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function assign($id): void
    {
        $ticket = new Ticket();
        $assigneeId = $_SESSION['user']['id'];
        if (isset($_POST['assignee_id']) && $_SESSION['user']['role'] === 'head_it') {
            $assigneeId = intval($_POST['assignee_id']);
        }
        $ticket->assign($id, $assigneeId);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            global $pdo;
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$assigneeId]);
            $assignedName = $stmt->fetchColumn() ?: 'Staff';
            $status = $t['status'] ?? 'assigned';
            $statusHtml = renderStatusIcon($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'assigned' => $assignedName, 'status_html' => $statusHtml, 'status' => $status, 'ticket_id' => $id, 'compact_times_html' => renderCompactTimes($t)]);
            exit;
        }
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function reassign($id): void
    {
        requireRole('head_it');
        $assigneeId = intval($_POST['assignee_id'] ?? 0);
        if ($assigneeId <= 0) { $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : ''); Flight::redirect($base . '/ticket/' . $id); }
        $ticket = new Ticket();
        $ticket->reassignKeepStatus($id, $assigneeId);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$assigneeId]);
            $assignedName = $stmt->fetchColumn() ?: 'Staff';
            $t = $ticket->getById($id);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'assigned' => $assignedName, 'ticket_id' => $id, 'compact_times_html' => renderCompactTimes($t)]);
            exit;
        }
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function start($id): void
    {
        $ticket = new Ticket();
        $ticket->startProgress($id);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            $status = $t['status'] ?? 'in_progress';
            $statusHtml = renderStatusIcon($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $status, 'status_html' => $statusHtml, 'ticket_id' => $id, 'compact_times_html' => renderCompactTimes($t)]);
            exit;
        }
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function finish($id): void
    {
        $proofPath = null;
        if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
            $file = $_FILES['proof'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid() . '.' . $ext;
            $path = UPLOAD_DIR . $newName;
            move_uploaded_file($file['tmp_name'], $path);
            $proofPath = $newName;
            global $pdo;
            $stmt = $pdo->prepare("INSERT INTO attachments (ticket_id, file_path, type) VALUES (?, ?, ?)");
            $stmt->execute([$id, $newName, strpos($file['type'], 'image') === 0 ? 'image' : 'video']);
        }
        $ticket = new Ticket();
        $ticket->finish($id);
        // Send Telegram notification to inform Head IT that the ticket is finished and needs closing
        $telegram_sent = false;
        try {
            $t = $ticket->getById($id);
            $code = $t['ticket_code'] ?? $id;
            $petugas = $_SESSION['user']['name'] ?? '-';
            $clientName = $t['nama'] ?? '-';
            $clientNip = $t['nip'] ?? '-';
            $clientWa = $t['no_wa'] ?? '';
            $clientUnit = $t['unit_dept'] ?? '-';
            $kategori = $t['category'] ?? '';
            if (!empty($t['sub_category']) && strtolower((string)$t['sub_category']) !== 'error') {
                $kategori .= ' / ' . $t['sub_category'];
            }
            $hasAttachment = ($proofPath || !empty($t['attachment_path'])) ? 'Ada' : 'Tidak ada';
            $msgLines = [];
            $msgLines[] = "Kode Tiket : " . $code;
            $msgLines[] = "Nama : " . $clientName;
            $msgLines[] = "NIP : " . $clientNip;
            $msgLines[] = "Unit : " . $clientUnit;
            $msgLines[] = "No. WA : " . $clientWa;
            $msgLines[] = "Petugas : " . $petugas;
            $msgLines[] = "Kategori Trouble : " . $kategori;
            $msgLines[] = "Photo/Video : " . $hasAttachment;
            $msgLines[] = "Keterangan : " . ($t['description'] ?? '');
            $msgLines[] = "Status : Selesai. Mohon ditutup.";
            $msg = implode("\n", $msgLines);
            if (defined('TELEGRAM_BOT_TOKEN') && defined('TELEGRAM_CHAT_ID')) {
                $tgToken = TELEGRAM_BOT_TOKEN;
                $tgChat = TELEGRAM_CHAT_ID;
                $tgUrl = 'https://api.telegram.org/bot' . $tgToken . '/sendMessage';
                $post = [
                    'chat_id' => $tgChat,
                    'text' => $msg,
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $tgUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                $tgResp = curl_exec($ch);
                $tgErr = curl_errno($ch) ? curl_error($ch) : null;
                curl_close($ch);
                if ($tgResp) {
                    $r = @json_decode($tgResp, true);
                    if (is_array($r) && !empty($r['ok'])) $telegram_sent = true;
                }
            }
        } catch (Throwable $_) {
            // suppress; notification is best-effort
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            $status = $t['status'] ?? 'finished';
            $statusHtml = renderStatusIcon($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $status, 'status_html' => $statusHtml, 'ticket_id' => $id, 'compact_times_html' => renderCompactTimes($t), 'telegram_sent' => ($telegram_sent ? 1 : 0)]);
            exit;
        }
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function close($id): void
    {
        requireRole('head_it');
        $ticket = new Ticket();
        $reason = trim((string)($_POST['reason'] ?? ''));
        $other = trim((string)($_POST['other_reason'] ?? ''));
        if ($reason === 'Lainnya' && $other !== '') {
            $useReason = $other;
        } elseif ($other !== '') {
            $useReason = $reason . ' - ' . $other;
        } else {
            $useReason = $reason;
        }
        $ticket->close($id, $useReason);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function confirm($id): void
    {
        requireRole('head_it');
        $ticket = new Ticket();
        $ticket->close($id, 'Confirmed by Head IT');

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            $status = $t['status'] ?? 'closed';
            $statusHtml = renderStatusIcon($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $status, 'status_html' => $statusHtml]);
            exit;
        }

        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function reopen($id): void
    {
        requireRole('head_it');
        $ticket = new Ticket();
        $ticket->reopenToInProgress($id);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            $t = $ticket->getById($id);
            $status = $t['status'] ?? 'in_progress';
            $statusHtml = renderStatusIcon($status);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $status, 'status_html' => $statusHtml]);
            exit;
        }

        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        Flight::redirect($base . '/ticket/' . $id);
    }

    public static function notifyClient($id): void
    {
        $ticket = new Ticket();
        $t = $ticket->getById($id);
        if (!$t) Flight::notFound();
        global $pdo;

        // Normalize phone number to wa.me format (convert 08... or 8... => 62...)
        $waNum = self::normalizeWaNumber((string)($t['no_wa'] ?? ''));
        if ($waNum === '') {
            // No WA number - redirect back
            $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
            Flight::redirect($base . '/ticket/' . $id . '?notify=missing_number');
        }

        // Get petugas (assigned_to) name if present; otherwise use current user
        $petugas = '-';
        if (!empty($t['assigned_to'])) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$t['assigned_to']]);
            $petugas = $stmt->fetchColumn() ?: '-';
        } else {
            $petugas = $_SESSION['user']['name'] ?? '-';
        }

        // Compose concise message (no attachments)
        $code = $t['ticket_code'] ?? (string)$id;
        $clientNip = $t['nip'] ?? '-';
        $clientName = $t['nama'] ?? '-';
        $clientUnit = $t['unit_dept'] ?? '-';
        $statusLabel = 'Selesai';

        $msgLines = [];
        $msgLines[] = "Tickets Code: " . $code;
        $msgLines[] = "Client's NIP: " . $clientNip;
        $msgLines[] = "Client's Name: " . $clientName;
        $msgLines[] = "Client's Unit: " . $clientUnit;
        $msgLines[] = "Status: " . $statusLabel;
        $msgLines[] = "Petugas: " . $petugas;

        $msg = implode("\n", $msgLines) . "\n";

        $waLink = 'https://wa.me/' . $waNum . '?text=' . rawurlencode($msg);
        header('Location: ' . $waLink);
        exit;
    }

    public static function deleteTicket($id): void
    {
        requireRole('head_it');
        $ticket = new Ticket();
        $ok = $ticket->delete((int)$id);
        $base = (defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? APP_BASE_PATH : '');
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => (bool)$ok, 'ticket_id' => (int)$id]);
            exit;
        }
        // Non-AJAX: redirect back to tickets list with a simple flag
        if ($ok) {
            Flight::redirect($base . '/tickets?deleted=1');
        } else {
            Flight::redirect($base . '/tickets?deleted=0');
        }
    }

    public static function serveUpload(): void
    {
        $req = Flight::request()->url;
        // extract relative path after /uploads/
        $rel = preg_replace('#^.*/uploads/#', '', $req);
        $rel = urldecode($rel);
        $rel = str_replace("\0", '', $rel);
        // prevent directory traversal
        $rel = str_replace(['..', '../', '..\\'], '', $rel);
        $path = realpath(UPLOAD_DIR . ltrim($rel, '/')) ?: false;
        $uploadDirReal = realpath(UPLOAD_DIR);
        if ($path && $uploadDirReal && strpos($path, $uploadDirReal) === 0 && is_file($path)) {
            header('Content-Type: ' . mime_content_type($path));
            readfile($path);
            return;
        }
        Flight::notFound();
    }
}
