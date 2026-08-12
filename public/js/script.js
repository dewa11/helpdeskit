// helpdeskit minimal frontend script
(function(){
    'use strict';

    function $(sel, ctx){ return (ctx||document).querySelector(sel); }
    function $all(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

    function initTooltips(){
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        $all('[data-bs-toggle="tooltip"]').forEach(function(el){
            try{ var inst = bootstrap.Tooltip.getInstance(el); if (inst) inst.dispose(); }catch(e){}
            try{ new bootstrap.Tooltip(el, { container: 'body', trigger: 'hover focus' }); }catch(e){}
        });
    }

    function ajaxPost(url, data){
        return fetch(url, { method: 'POST', body: data, headers: {'X-Requested-With':'XMLHttpRequest'} }).then(function(r){
            if (!r.ok) throw new Error('Network');
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) return r.json();
            return r.text().then(function(t){ try{ return JSON.parse(t); }catch(e){ return t; } });
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        initTooltips();

        var toggle = $('#sidebarToggle');
        var sidebar = document.querySelector('.sidebar');
        if (toggle && sidebar){
            var backdrop = document.createElement('div'); backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
            function isMobile(){ return window.innerWidth <= 900; }
            toggle.addEventListener('click', function(){
                if (isMobile()){
                    sidebar.classList.toggle('open');
                    backdrop.classList.toggle('show');
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
            backdrop.addEventListener('click', function(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); });
            window.addEventListener('resize', function(){ if (!isMobile()){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); } });
        }

        // Category buttons on the report form
        var categoryInput = document.getElementById('category');
        var categoryBtns = $all('.category-icon');
        var subCategoryWrap = document.getElementById('sub-category');

        function updateCategorySelection(val){
            categoryBtns.forEach(function(b){
                try{ b.classList.remove('selected'); }catch(e){}
                if (b.getAttribute('data-value') === val) {
                    b.classList.add('selected');
                }
            });
            if (categoryInput) categoryInput.value = val;
            if (subCategoryWrap){
                if (val === 'SIMRS') subCategoryWrap.style.display = 'block';
                else subCategoryWrap.style.display = 'none';
            }
        }

        if (categoryBtns.length > 0){
            categoryBtns.forEach(function(b){
                b.addEventListener('click', function(e){
                    var v = b.getAttribute('data-value');
                    if (!v) return;
                    updateCategorySelection(v);
                });
            });
            // initialize from hidden input value (if present)
            if (categoryInput && categoryInput.value) updateCategorySelection(categoryInput.value);
        }

        // Report form: submit via AJAX, open WA in new tab/app, then return to main menu
        var reportForm = document.getElementById('reportForm');
        var attachmentInput = document.getElementById('attachment');
        var attachmentCam = document.getElementById('attachmentCamera');
        var attachmentInfo = document.getElementById('attachment-info');
        function setAttachmentInfo(file){
            if (!attachmentInfo) return;
            if (!file) { attachmentInfo.textContent = 'Foto maks 2MB; video maks 10MB (15 detik). Gunakan "Ambil Foto" di ponsel untuk membuka kamera.'; return; }
            var sizeKB = Math.round(file.size/1024);
            attachmentInfo.textContent = file.name + ' (' + sizeKB + ' KB)';
        }
        if (attachmentInput){
            attachmentInput.addEventListener('change', function(){
                var f = this.files && this.files[0];
                setAttachmentInfo(f);
            });
        }
        if (attachmentCam){
            attachmentCam.addEventListener('change', function(){
                var f = this.files && this.files[0];
                setAttachmentInfo(f);
                // clear other input to avoid double-submit
                if (attachmentInput) attachmentInput.value = '';
            });
        }

        if (reportForm){
            // Intercept form submit to show review modal instead of immediate send
            reportForm.addEventListener('submit', function(e){
                e.preventDefault();
                var modalEl = document.getElementById('reviewReportModal');
                try{ var m = bootstrap.Modal.getOrCreateInstance(modalEl); }catch(e){}
                // build preview HTML
                var body = document.getElementById('reviewReportBody');
                if (!body) return;
                var previewEl = document.getElementById('reviewAttachmentPreview');
                var formData = new FormData(reportForm);
                var rows = [];
                function esc(s){ if (s===null || s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
                rows.push('<div class="small text-dark">Silakan periksa data sebelum mengirim. Tekan "Kirim" untuk mengirim laporan.</div>');
                rows.push('<dl class="row mt-2">');
                rows.push('<dt class="col-sm-4 text-dark">NIP</dt><dd class="col-sm-8 text-dark">' + esc(formData.get('nip')) + '</dd>');
                rows.push('<dt class="col-sm-4 text-dark">Nama</dt><dd class="col-sm-8 text-dark">' + esc(formData.get('nama')) + '</dd>');
                rows.push('<dt class="col-sm-4 text-dark">No. WA</dt><dd class="col-sm-8 text-dark">' + esc(formData.get('no_wa')) + '</dd>');
                rows.push('<dt class="col-sm-4 text-dark">Unit / Departemen</dt><dd class="col-sm-8 text-dark">' + esc(formData.get('unit_dept')) + '</dd>');
                var cat = esc(formData.get('category'));
                var sub = esc(formData.get('sub_category'));
                rows.push('<dt class="col-sm-4 text-dark">Kategori</dt><dd class="col-sm-8 text-dark">' + cat + (sub ? (' / ' + sub) : '') + '</dd>');
                rows.push('<dt class="col-sm-4 text-dark">Keterangan</dt><dd class="col-sm-8 text-dark">' + esc(formData.get('description')) + '</dd>');
                rows.push('</dl>');
                body.innerHTML = rows.join('');

                // Render attachment preview (image/video) if present
                var attachFile = (reportForm.querySelector('#attachment') && reportForm.querySelector('#attachment').files && reportForm.querySelector('#attachment').files[0]) || (reportForm.querySelector('#attachmentCamera') && reportForm.querySelector('#attachmentCamera').files && reportForm.querySelector('#attachmentCamera').files[0]);
                if (previewEl){
                    previewEl.innerHTML = '';
                    if (attachFile){
                        var type = attachFile.type || '';
                        try{
                            var objUrl = URL.createObjectURL(attachFile);
                            if (type.indexOf('image') === 0){
                                previewEl.innerHTML = '<img src="' + objUrl + '" class="img-fluid" style="max-height:240px;object-fit:contain">';
                            } else if (type.indexOf('video') === 0){
                                previewEl.innerHTML = '<video controls class="w-100" style="max-height:240px"><source src="' + objUrl + '"></video>';
                            } else {
                                previewEl.innerHTML = '<div>' + esc(attachFile.name) + '</div>';
                            }
                            if (modalEl) modalEl._previewUrl = objUrl;
                        }catch(e){ previewEl.innerHTML = '<div class="text-dark">Tidak dapat menampilkan pratinjau lampiran.</div>'; }
                    } else {
                        previewEl.innerHTML = '<div class="text-dark">Tidak ada lampiran</div>';
                    }
                }

                // reset alert area
                var alertEl = document.getElementById('reviewReportAlert'); if (alertEl) { alertEl.innerHTML = ''; }
                // show modal
                try{ var modal = new bootstrap.Modal(modalEl); modal.show(); }catch(e){ console.error('Failed to show review modal', e); }

                // cleanup handler: revoke object URL and reset button state when modal hidden
                if (modalEl && !modalEl._cleanupAttached){
                    modalEl.addEventListener('hidden.bs.modal', function(){
                        try{ if (modalEl._previewUrl) { URL.revokeObjectURL(modalEl._previewUrl); } }catch(e){}
                        modalEl._previewUrl = null;
                        var p = document.getElementById('reviewAttachmentPreview'); if (p) p.innerHTML = '';
                        var btn = document.getElementById('reviewReportSendBtn');
                        if (btn){ btn.disabled = false; btn.classList.remove('btn-primary'); btn.classList.add('btn-success'); btn.textContent = 'Kirim'; btn.dataset.done = '' ; }
                        modalEl._sent = false;
                    });
                    modalEl._cleanupAttached = true;
                }
            });

            // Modal send handler
            var reviewSendBtn = document.getElementById('reviewReportSendBtn');
            if (reviewSendBtn){
                reviewSendBtn.addEventListener('click', function(ev){
                    ev.preventDefault();
                    var btn = reviewSendBtn;
                    var modalEl = document.getElementById('reviewReportModal');
                    if (modalEl && modalEl._sent) return; // avoid double-send
                    btn.disabled = true;
                    var alertEl = document.getElementById('reviewReportAlert'); if (alertEl) alertEl.innerHTML = '';
                    var fd = new FormData(reportForm);
                    var actionUrl = reportForm.action || window.location.pathname;
                    if (actionUrl.indexOf('?') === -1) actionUrl += '?ajax=1'; else actionUrl += '&ajax=1';
                    fetch(actionUrl, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                        .then(handleJson)
                        .then(function(json){
                            if (json && json.success){
                                if (modalEl) modalEl._sent = true;
                                // show success message inside modal
                                var msg = 'Terimakasih telah mengirim laporan, team IT akan automatis ter-notifikasi tanpa perlu mengirim pesan WA dan segera menindaki laporan anda.(apabila masih slow respond, anda bisa segera menghubungi Kepala IT Ismul Aswan)';
                                if (alertEl) alertEl.innerHTML = '<div class="alert alert-success">' + msg + '</div>';
                                // replace send button with close button that also resets form
                                btn.classList.remove('btn-success'); btn.classList.add('btn-primary'); btn.textContent = 'Tutup';
                                btn.disabled = false;
                                btn.dataset.done = '1';
                                function closeAfter(){
                                    try{ var m = bootstrap.Modal.getInstance(modalEl); if (m) m.hide(); }catch(e){}
                                    try{ reportForm.reset(); setAttachmentInfo(null); }catch(e){}
                                    try { window.location.replace((typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/'); } catch(e) { /* ignore */ }
                                    btn.removeEventListener('click', closeAfter);
                                }
                                btn.addEventListener('click', closeAfter);
                            } else {
                                var errText = 'Gagal mengirim laporan.';
                                if (json && json.error) errText = json.error;
                                if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">' + errText + '</div>';
                                btn.disabled = false;
                            }
                        }).catch(function(err){
                            console.error('Report submit error', err);
                            var txt = 'Gagal mengirim laporan. Periksa koneksi Anda.';
                            if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">' + txt + '</div>';
                            btn.disabled = false;
                        });
                });
            }
        }

        // AJAX: priority and assign quick actions on dashboard
        // Safer JSON handler: tolerate non-JSON (treat HTTP 200 + empty/invalid JSON as success)
        function handleJson(res){
            return res.text().then(function(t){
                try {
                    if (t && t.trim() !== '') return JSON.parse(t);
                } catch (e) {
                    // fall through to ok fallback
                }
                if (res.ok) return { success: true, raw: t };
                return {};
            }).catch(function(){ return res.ok ? { success:true } : {}; });
        }

        function openWaLink(waLink, isMobile, waWin){
            if (!waLink) return;
            if (isMobile){
                try {
                    var a = document.createElement('a');
                    a.href = waLink;
                    a.style.display = 'none';
                    document.body.appendChild(a);
                    a.click();
                } catch(e){}
                try { window.location.href = waLink; } catch(e){}
                // retry if app handoff fails (still visible after short delay)
                setTimeout(function(){ if (document.visibilityState === 'visible') { try { window.location.href = waLink; } catch(e){} } }, 1200);
                setTimeout(function(){ if (document.visibilityState === 'visible') { try { window.location.href = waLink; } catch(e){} } }, 3000);
                return; // do not redirect away; let WA handoff proceed
            }
            try {
                if (waWin) waWin.location = waLink;
                else window.open(waLink, '_blank');
            } catch (e) {
                try { window.open(waLink, '_blank'); } catch(e2){}
            }
            // Final fallback: navigate current page to the WA URL if new-tab/open failed
            setTimeout(function(){
                try { if (!document.hidden && !document.webkitHidden) { window.location.href = waLink; } } catch(e) { try { window.location.href = waLink; } catch(e2){} }
            }, 700);
        }

        function refreshDashboardStats(){
            var stats = document.getElementById('dashboard-stats');
            if (!stats) return;
                fetch((typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/dashboard/updates', { headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(function(r){ return r.json ? r.json() : r.text().then(function(t){ try{return JSON.parse(t);}catch(e){return {}; } }); })
                .then(function(json){ if (json && json.stats_html) stats.innerHTML = json.stats_html; })
                .catch(function(err){ console.error('Failed to refresh dashboard stats', err); });
        }

        $all('.priority-form').forEach(function(f){
            f.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = f.querySelector('button[type=submit]') || f.querySelector('button');
                if (btn) btn.disabled = true;
                var fd = new FormData(f);
                fetch(f.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson).then(function(json){
                        if (json && json.success){
                            // update priority badge and status in the row
                            var row = f.closest('tr');
                            if (row){
                                var pc = row.querySelector('.priority-cell');
                                if (pc && json.priority_html) pc.innerHTML = json.priority_html;
                                var sc = row.querySelector('.status-cell');
                                if (sc && json.status_html) sc.innerHTML = json.status_html;
                                var ct = row.querySelector('.compact-times-wrap');
                                if (ct && json.compact_times_html) ct.innerHTML = json.compact_times_html;
                                // enable assign controls if present
                                var assignForm = row.querySelector('.assign-form');
                                if (assignForm){
                                    var sel = assignForm.querySelector('select[name=assignee_id]');
                                    var sub = assignForm.querySelector('button[type=submit]');
                                    if (sel) sel.removeAttribute('disabled');
                                    if (sub) sub.removeAttribute('disabled');
                                }
                            }
                            refreshDashboardStats();
                        }
                    }).catch(function(err){ console.error(err); })
                    .finally(function(){ if (btn) btn.disabled = false; });
            });
        });

        $all('.assign-form').forEach(function(f){
            f.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = f.querySelector('button[type=submit]') || f.querySelector('button');
                if (btn) btn.disabled = true;
                var fd = new FormData(f);
                fetch(f.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson).then(function(json){
                        if (json && json.success){
                            var row = f.closest('tr');
                            if (row){
                                var assignedCell = row.querySelector('.assigned-cell');
                                var statusCell = row.querySelector('.status-cell');
                                if (assignedCell && json.assigned) assignedCell.textContent = json.assigned;
                                if (statusCell && json.status_html) statusCell.innerHTML = json.status_html;
                                var ct = row.querySelector('.compact-times-wrap');
                                if (ct && json.compact_times_html) ct.innerHTML = json.compact_times_html;
                            }
                            refreshDashboardStats();
                        }
                    }).catch(function(err){ console.error(err); })
                    .finally(function(){ if (btn) btn.disabled = false; });
            });
        });

        // Delete ticket (confirm first)
        $all('.delete-form').forEach(function(f){
            f.addEventListener('submit', function(e){
                e.preventDefault();
                if (!confirm('Hapus tiket ini? Tindakan tidak dapat dibatalkan.')) return;
                var btn = f.querySelector('button'); if (btn) btn.disabled = true;
                var fd = new FormData(f);
                fetch(f.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson).then(function(json){
                        if (json && json.success) {
                            var row = f.closest('tr'); if (row) row.remove();
                            refreshDashboardStats();
                            // if on ticket detail page, navigate back to tickets
                            try { if (window.location.pathname.indexOf('/ticket/') === 0) window.location.href = (typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/tickets'; } catch(e){}
                        } else {
                            // fallback to form submit (non-AJAX)
                            f.submit();
                        }
                    }).catch(function(){ f.submit(); }).finally(function(){ if (btn) btn.disabled = false; });
            });
        });

        // Detail page: priority form should update dashboard row after server confirms
        var detailPriorityForm = document.getElementById('priorityForm');
        if (detailPriorityForm){
            detailPriorityForm.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = detailPriorityForm.querySelector('button[type=submit]') || detailPriorityForm.querySelector('button');
                if (btn) btn.disabled = true;
                var fd = new FormData(detailPriorityForm);
                fetch(detailPriorityForm.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson).then(function(json){
                        if (json && json.success){
                            // update badge on detail page
                            var badge = document.getElementById('detailPriorityBadge');
                            if (badge && json.priority_html) badge.innerHTML = json.priority_html;
                            var ds = document.getElementById('detailStatusWrap');
                            if (ds && json.status_html) ds.innerHTML = 'Status: ' + json.status_html + ' <span class="text-uppercase ms-1">' + (json.status || '') + '</span>';
                            // update dashboard row if present
                            var tid = detailPriorityForm.getAttribute('data-ticket-id');
                            if (tid){
                                var row = document.querySelector('tr[data-ticket-id="' + tid + '"]');
                                if (row){
                                    var pc = row.querySelector('.priority-cell');
                                    if (pc && json.priority_html) pc.innerHTML = json.priority_html;
                                    var sc = row.querySelector('.status-cell');
                                    if (sc && json.status_html) sc.innerHTML = json.status_html;
                                    var ct = row.querySelector('.compact-times-wrap');
                                    if (ct && json.compact_times_html) ct.innerHTML = json.compact_times_html;
                                }
                            }
                            refreshDashboardStats();
                        }
                    }).catch(function(err){ console.error(err); alert('Gagal menyimpan prioritas.'); })
                    .finally(function(){ if (btn) btn.disabled = false; });
            });
        }

        var reasonSel = document.getElementById('closeReasonSelect');
        var otherWrap = document.getElementById('otherReasonWrap');
        var otherInput = document.getElementById('otherReasonInput');
        var openBtn = document.getElementById('openCloseModalBtn');
        var confirmBtn = document.getElementById('confirmCloseBtn');
        var confirmModalEl = document.getElementById('confirmCloseModal');
        var confirmReasonText = document.getElementById('confirmReasonText');
        var confirmOtherText = document.getElementById('confirmOtherText');
        var closeForm = document.getElementById('closeForm');

        if (reasonSel){
            reasonSel.addEventListener('change', function(){
                if (this.value === 'Lainnya'){ if (otherWrap) otherWrap.style.display = 'block'; if (otherInput) otherInput.focus(); }
                else if (otherWrap) otherWrap.style.display = 'none';
            });
            if (reasonSel.value === 'Lainnya' && otherWrap) otherWrap.style.display = 'block';
        }

        if (openBtn && confirmModalEl){
            openBtn.addEventListener('click', function(){
                var reason = reasonSel && reasonSel.value ? reasonSel.value : '';
                var other = otherInput && otherInput.value ? otherInput.value.trim() : '';
                if (confirmReasonText) confirmReasonText.textContent = reason;
                if (confirmOtherText){
                    if (reason === 'Lainnya' && other){ confirmOtherText.style.display = 'block'; confirmOtherText.textContent = other; }
                    else { confirmOtherText.style.display = 'none'; confirmOtherText.textContent = ''; }
                }
                try{ var m = bootstrap.Modal.getOrCreateInstance(confirmModalEl); m.show(); }catch(e){}
            });
        }

        if (confirmBtn && closeForm) confirmBtn.addEventListener('click', function(){ closeForm.submit(); });

        // Head IT: confirm reassignment even on closed tickets
        var reassignForm = document.getElementById('reassignForm');
        var openReassignBtn = document.getElementById('openReassignModalBtn');
        var confirmReassignBtn = document.getElementById('confirmReassignBtn');
        var reassignModalEl = document.getElementById('reassignConfirmModal');
        var reassignTargetText = document.getElementById('reassignTargetText');
        if (openReassignBtn && reassignForm && reassignModalEl){
            openReassignBtn.addEventListener('click', function(){
                var sel = reassignForm.querySelector('select[name=assignee_id]');
                var name = sel ? (sel.options[sel.selectedIndex] || {}).textContent : '';
                if (reassignTargetText) reassignTargetText.textContent = name || '-';
                try{ var m = bootstrap.Modal.getOrCreateInstance(reassignModalEl); m.show(); }catch(e){}
            });
        }
        if (confirmReassignBtn && reassignForm){
            confirmReassignBtn.addEventListener('click', function(){ reassignForm.submit(); });
        }
        
        var copyBtn = document.getElementById('copyBtn');
        if (copyBtn){
            copyBtn.addEventListener('click', function(){
                var code = copyBtn.getAttribute('data-code') || '';
                if (!code) return;
                navigator.clipboard.writeText(code).then(function(){
                    copyBtn.classList.add('btn-success');
                    copyBtn.classList.remove('btn-outline-secondary');
                    setTimeout(function(){
                        copyBtn.classList.remove('btn-success');
                        copyBtn.classList.add('btn-outline-secondary');
                    }, 1200);
                }).catch(function(){ /* ignore */ });
            });
        }

        // Start and finish forms via AJAX (update DOM after server confirm)
        var startForm = document.getElementById('startForm');
        if (startForm){
            startForm.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = startForm.querySelector('button[type=submit]') || startForm.querySelector('button');
                if (btn) btn.disabled = true;
                var fd = new FormData(startForm);
                fetch(startForm.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson).then(function(json){
                        if (json && json.success){
                            var tid = startForm.getAttribute('data-ticket-id');
                            var ds = document.getElementById('detailStatusWrap');
                            if (ds && json.status_html) ds.innerHTML = 'Status: ' + json.status_html + ' <span class="text-uppercase ms-1">' + (json.status || '') + '</span>';
                            if (tid){
                                var row = document.querySelector('tr[data-ticket-id="' + tid + '"]');
                                if (row){ var statusCell = row.querySelector('.status-cell'); if (statusCell && json.status_html) statusCell.innerHTML = json.status_html; }
                                var ct = row.querySelector('.compact-times-wrap');
                                if (ct && json.compact_times_html) ct.innerHTML = json.compact_times_html;
                            }
                            refreshDashboardStats();
                        }
                    }).catch(function(err){ console.error('Start request failed', err); /* suppress modal */ })
                    .finally(function(){ if (btn) btn.disabled = false; });
            });
        }

        var finishForm = document.getElementById('finishForm');
        if (finishForm){
            finishForm.addEventListener('submit', function(e){
                e.preventDefault();
                var btn = finishForm.querySelector('button[type=submit]') || finishForm.querySelector('button');
                if (btn) btn.disabled = true;
                var fd = new FormData(finishForm);
                fetch(finishForm.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                    .then(handleJson)
                    .then(function(json){
                            console.log('finishForm: AJAX response', json);
                            if (json && json.success){
                                try {
                                    window.location.reload();
                                } catch(e) { window.location.href = (typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '/'); }
                            } else {
                                alert('Gagal mengirim laporan. Silakan coba lagi.');
                                if (btn) btn.disabled = false;
                            }
                        }).catch(function(err){
                            console.error('Report submit error', err);
                            alert('Gagal mengirim laporan. Periksa koneksi Anda.');
                            if (btn) btn.disabled = false;
                        });
            });
        }

        // Attachment lightbox (delegated)
        var lightboxModalEl = document.getElementById('attachmentLightbox');
        var lightboxContent = document.getElementById('attachmentLightboxContent');
        document.addEventListener('click', function(e){
            var t = e.target.closest && e.target.closest('.attachment-thumb');
            if (!t) return;
            e.preventDefault();
            var type = t.getAttribute('data-type') || (t.tagName === 'VIDEO' ? 'video' : 'image');
            var src = t.getAttribute('data-src') || t.getAttribute('src') || (t.querySelector && t.querySelector('source') && t.querySelector('source').getAttribute('src')) || '';
            if (!src) return;
            if (lightboxContent) {
                if (type === 'video') {
                    lightboxContent.innerHTML = '<video class="w-100" controls autoplay><source src="' + src + '"></video>';
                } else {
                    lightboxContent.innerHTML = '<img src="' + src + '" class="img-fluid d-block mx-auto" alt="Lampiran">';
                }
            }
            try { var m = bootstrap.Modal.getOrCreateInstance(lightboxModalEl); m.show(); } catch(e){}
        });
    });

})();
