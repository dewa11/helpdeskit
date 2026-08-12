/* report.js — extracted from ReportController charts() inline script
 * Wrapped in DOMContentLoaded so it can be loaded from head or bottom safely
 */

let statusChart, categoryChart, reasonsChart, trendChart, avgCloseChart, unitChart;
function renderCharts(data){
    const sLabels = (data.status||[]).map(d => d.status);
    const sData = (data.status||[]).map(d => d.count);
    const cLabels = (data.category||[]).map(d => d.category);
    const cData = (data.category||[]).map(d => d.count);
    const rLabels = (data.reasons||[]).map(d => d.closure_reason);
    const rData = (data.reasons||[]).map(d => d.count);
    const ctxS = document.getElementById("statusChart").getContext("2d");
    if(statusChart) statusChart.destroy();
    statusChart = new Chart(ctxS, {type: "bar", data: {labels: sLabels, datasets: [{label:"Jumlah", data: sData, backgroundColor: "#7fc9ff"}]}, options:{responsive:true,maintainAspectRatio:false, onClick: function(evt, elements){ try{ if(!elements || elements.length===0) return; const el = elements[0]; const idx = el.index; const label = this.data.labels[idx]; if(document.getElementById('filterStatus')){ document.getElementById('filterStatus').value = label; fetchReports(1); } }catch(e){console.error(e);} } }});
    const ctxC = document.getElementById("categoryChart").getContext("2d"); if(categoryChart) categoryChart.destroy(); categoryChart = new Chart(ctxC,{type:"pie",data:{labels:cLabels,datasets:[{data:cData}]},options:{responsive:true}});
    const ctxR = document.getElementById("reasonsChart").getContext("2d"); if(reasonsChart) reasonsChart.destroy(); reasonsChart = new Chart(ctxR,{type:"bar",data:{labels:rLabels,datasets:[{label:"Jumlah",data:rData,backgroundColor:"#9ee3a8"}]},options:{responsive:true}});

    // units chart
    try{
        const uLabels = (data.units||[]).map(d => d.unit || '');
        const uData = (data.units||[]).map(d => d.count || 0);
        const ctxU = document.getElementById('unitChart');
        if (ctxU) {
            try{ if (unitChart) unitChart.destroy(); }catch(e){}
            const ctx = ctxU.getContext('2d');
            unitChart = new Chart(ctx, { type: 'bar', data: { labels: uLabels, datasets: [{ label: 'Jumlah laporan', data: uData, backgroundColor: '#7bb0ff' }] }, options: { responsive:true, maintainAspectRatio:false, indexAxis: 'y', onClick: function(evt, elements){ try{ if(!elements || elements.length===0) return; const idx = elements[0].index; const label = this.data.labels[idx]; if(document.getElementById('filterUnit')){ document.getElementById('filterUnit').value = label; fetchReports(1); } }catch(e){console.error(e);} } } });
        }
    }catch(e){ /* ignore unit chart errors */ }
}

function formatDurationSeconds(sec){
    if (!sec || sec <= 0) return '-';
    const days = Math.floor(sec / 86400); sec -= days * 86400;
    const hrs = Math.floor(sec / 3600); sec -= hrs * 3600;
    const mins = Math.floor(sec / 60);
    let parts = [];
    if (days) parts.push(days + 'd');
    if (hrs) parts.push(hrs + 'h');
    if (mins) parts.push(mins + 'm');
    if (parts.length === 0) return '<1m';
    return parts.join(' ');
}

function renderMetrics(metrics){
    const container = document.getElementById('metricsCards');
    if (!container) return;
    if (!metrics) { container.innerHTML = '<div class="text-muted">Tidak ada metrik.</div>'; return; }
    const cards = [];
    cards.push('<div class="card p-2"><div class="small text-muted">Total</div><div class="h5 m-0">' + (metrics.total||0) + '</div></div>');
    const closedPct = metrics.closed ? ((metrics.closed/metrics.total*100)||0).toFixed(1) : '0.0';
    cards.push('<div class="card p-2"><div class="small text-muted">Closed</div><div class="h5 m-0">' + (metrics.closed||0) + ' (' + closedPct + '%)</div></div>');
    cards.push('<div class="card p-2"><div class="small text-muted">Avg to Close</div><div class="h6 m-0">' + formatDurationSeconds(metrics.avg_close_seconds) + '</div></div>');
    cards.push('<div class="card p-2"><div class="small text-muted">Avg to Finish</div><div class="h6 m-0">' + formatDurationSeconds(metrics.avg_finish_seconds) + '</div></div>');
    cards.push('<div class="card p-2" data-filter="reopened"><div class="small text-muted">Reopened</div><div class="h6 m-0">' + (metrics.reopened||0) + '</div></div>');
    cards.push('<div class="card p-2 text-danger" data-filter="sla_breached"><div class="small text-muted">SLA Breaches ('+ (metrics.sla_hours || '?') +'h)</div><div class="h6 m-0">' + (metrics.sla_breach||0) + '</div></div>');
    const pctWithin = metrics.percent_closed_within_sla !== null && metrics.percent_closed_within_sla !== undefined ? (metrics.percent_closed_within_sla + '%') : '-';
    cards.push('<div class="card p-2"><div class="small text-muted">Closed within SLA</div><div class="h6 m-0">' + pctWithin + '</div></div>');
    container.innerHTML = cards.join(' ');
    // attach click handlers to metric cards for quick filters
    try{
        container.querySelectorAll('[data-filter]').forEach(el=>{
            el.style.cursor = 'pointer';
            el.addEventListener('click', ()=>{
                const f = el.getAttribute('data-filter');
                if (f === 'reopened') {
                    // no visible checkbox anymore; use transient flag for reopened filter
                    window._filterReopenedFlag = true;
                } else if (f === 'sla_breached') {
                    const sel = document.getElementById('filterSla'); if (sel) sel.value = 'breached';
                } else if (f === 'within_sla') {
                    const sel = document.getElementById('filterSla'); if (sel) sel.value = 'within';
                }
                fetchReports(1);
            });
        });
    }catch(e){/* ignore */}
}

function renderTrendCharts(trends){
    if (!trends || !trends.dates) return;
    const labels = trends.dates.map(d=>d);
    // new vs closed
    const newData = trends.created || [];
    const closedData = trends.closed || [];
    const ctxT = document.getElementById('trendNewClosed');
    if (ctxT) {
        try{ if (trendChart) trendChart.destroy(); }catch(e){}
        const ctx = ctxT.getContext('2d');
        trendChart = new Chart(ctx, {
            type: 'line',
            data: { labels: labels, datasets: [
                { label: 'Baru', data: newData, borderColor: '#4e79a7', backgroundColor: 'rgba(78,121,167,0.1)', fill:true },
                { label: 'Tertutup', data: closedData, borderColor: '#59a14f', backgroundColor: 'rgba(89,161,79,0.08)', fill:true }
            ] },
            options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{tooltip:{callbacks:{label:function(ctx){ return ctx.dataset.label+': '+ctx.formattedValue; }}}}, onClick: function(evt, elements){ try{ if(!elements || elements.length===0) return; const idx = elements[0].index; const date = this.data.labels[idx]; if(document.getElementById('filterFrom') && document.getElementById('filterTo')){ document.getElementById('filterFrom').value = date; document.getElementById('filterTo').value = date; fetchReports(1); } }catch(e){console.error(e);} } }
        });
    }

    // avg close time (convert seconds to minutes for display)
    const avgSec = trends.avg_close_seconds || [];
    const avgMin = (avgSec || []).map(v => { const n = Number(v)||0; return n>0 ? Math.round((n/60)*10)/10 : 0; });
    const ctxA = document.getElementById('trendAvgClose');
    if (ctxA) {
        try{ if (avgCloseChart) avgCloseChart.destroy(); }catch(e){}
        const ctx = ctxA.getContext('2d');
        avgCloseChart = new Chart(ctx, {
            type: 'line',
            data: { labels: labels, datasets: [ { label: 'Rata-rata (menit)', data: avgMin, borderColor: '#e15759', backgroundColor: 'rgba(225,87,89,0.08)', fill:true } ] },
            options: { responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{tooltip:{callbacks:{label:function(ctx){ const v = Number(ctx.raw)||0; return ctx.dataset.label+': '+ (v>0? (v.toFixed? v.toFixed(1):Number(v).toFixed(1)) + ' m' : '-'); }}}}, onClick: function(evt,elements){ try{ if(!elements||elements.length===0) return; const idx = elements[0].index; const date = this.data.labels[idx]; if(document.getElementById('filterFrom') && document.getElementById('filterTo')){ document.getElementById('filterFrom').value = date; document.getElementById('filterTo').value = date; fetchReports(1); } }catch(e){console.error(e);} } }
        });
    }
}

// Open a larger chart in modal
let chartModalInstance = null;
function openChartModal(chartInst, title){
    try{
        const modalTitle = document.getElementById('chartModalTitle');
        const canvas = document.getElementById('chartModalCanvas');
        if (!canvas) return;
        if (modalTitle) modalTitle.textContent = title || 'Grafik';
        if (chartModalInstance) { try{ chartModalInstance.destroy(); }catch(e){} }
        const ctx = canvas.getContext('2d');
        const cfg = {
            type: chartInst.config.type,
            data: JSON.parse(JSON.stringify(chartInst.data || {})),
            options: JSON.parse(JSON.stringify(chartInst.options || {}))
        };
        chartModalInstance = new Chart(ctx, cfg);
            showModalById('chartModal');
    }catch(e){ console.error('openChartModal', e); }
}

// Attach click handlers to canvases to open preview
function attachChartPreviewHandlers(){
    try{
        const s = document.getElementById('statusChart'); if (s) s.addEventListener('click', ()=> openChartModal(statusChart,'Tiket menurut Status'));
        const c = document.getElementById('categoryChart'); if (c) c.addEventListener('click', ()=> openChartModal(categoryChart,'Tiket menurut Kategori'));
        const r = document.getElementById('reasonsChart'); if (r) r.addEventListener('click', ()=> openChartModal(reasonsChart,'Alasan Penutupan'));
        const u = document.getElementById('unitChart'); if (u) u.addEventListener('click', ()=> openChartModal(unitChart,'Tiket per Unit'));
        const t = document.getElementById('trendNewClosed'); if (t) t.addEventListener('click', ()=> openChartModal(trendChart,'Tren: Baru vs Tertutup'));
        const a = document.getElementById('trendAvgClose'); if (a) a.addEventListener('click', ()=> openChartModal(avgCloseChart,'Tren: Rata-rata waktu penyelesaian'));
    }catch(e){ /* ignore */ }
}

// Ensure modal cleanup to remove stray backdrops and body.lock if modal left behind
function ensureModalCleanup(modalEl){
    if (!modalEl) return;
    if (modalEl._cleanupAttached) return;
    modalEl.addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.parentNode && b.parentNode.removeChild(b); });
        if (document.body.classList.contains('modal-open')) document.body.classList.remove('modal-open');
    });
    modalEl._cleanupAttached = true;
}

function showModalById(id){
    const modalEl = document.getElementById(id);
    if (!modalEl) return null;
    ensureModalCleanup(modalEl);
    const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
    inst.show();
    return inst;
}

function renderList(rowsHtml){ const container = document.getElementById("reportsList"); container.innerHTML = rowsHtml; attachPreviewHandlers(); if(!rowsHtml || rowsHtml.trim()==="") container.innerHTML = "<div class=\"text-muted\">Tidak ada laporan untuk filter yang dipilih.</div>"; }
function renderTruncatedRows(rowsHtml, visibleCount = 5){
    const container = document.getElementById('reportsList');
    container.innerHTML = '';
    if(!rowsHtml || rowsHtml.trim()===''){
        container.innerHTML = '<div class="text-muted">Tidak ada laporan untuk filter yang dipilih.</div>';
        document.getElementById('viewAllReportsBtn').style.display = 'none';
        return;
    }
    const tmp = document.createElement('div'); tmp.innerHTML = rowsHtml;
    const children = Array.from(tmp.children || []);
    const showAllBtn = document.getElementById('viewAllReportsBtn');
    // append first N children
    children.slice(0, visibleCount).forEach(ch => container.appendChild(ch));
    attachPreviewHandlers();
    // show 'Lihat semua' if there are more than visibleCount
    if (showAllBtn) showAllBtn.style.display = (children.length > visibleCount) ? 'inline-block' : 'none';
}

function appendList(rowsHtml){ const container = document.getElementById("reportsList"); if(!rowsHtml || rowsHtml.trim()==="") return; const div = document.createElement('div'); div.innerHTML = rowsHtml; // rowsHtml may contain multiple rows
    // append children
    while (div.firstChild) container.appendChild(div.firstChild);
    attachPreviewHandlers(); }

function attachPreviewHandlers(){
    document.querySelectorAll(".preview-attachment").forEach(el=>{
        el.addEventListener("click", e=>{
            e.preventDefault();
            const url = el.getAttribute("data-url");
            const body = document.getElementById("attachmentPreviewBody");
            body.innerHTML = "";
            if(url.match(/\.(jpg|jpeg|png|gif)$/i)){
                body.innerHTML = `<img src="${url}" class="img-fluid"/>`;
            } else {
                body.innerHTML = `<a href="${url}" target="_blank">Buka lampiran</a>`;
            }
            showModalById('attachmentPreviewModal');
        });
    });
}

async function fetchReports(page = 1){
    const btn = document.getElementById("applyFilters");
    const list = document.getElementById("reportsList");
    const from = document.getElementById("filterFrom").value;
    const to = document.getElementById("filterTo").value;
    const status = document.getElementById("filterStatus").value;
    const q = document.getElementById("filterQ").value;
    const unit = (document.getElementById("filterUnit") || {}).value || '';
    const assignee = (document.getElementById("filterAssignee") || {}).value || 'all';
    const priority = (document.getElementById("filterPriority") || {}).value || '';
    const sla = (document.getElementById("filterSla") || {}).value || 'all';
    const reopened = window._filterReopenedFlag ? '1' : '';
    const params = new URLSearchParams(); if(from) params.set("from",from); if(to) params.set("to",to); if(status) params.set("status",status); if(q) params.set("q",q);
    if (unit) params.set('unit', unit);
    if (assignee) params.set('assignee', assignee);
    if (priority) params.set('priority', priority);
    if (sla) params.set('sla', sla);
    if (reopened) params.set('reopened', reopened);
    // include page param for server paging
    if (page && Number(page) > 1) params.set('page', String(page));
        btn.disabled = true; const prevHtml = btn.innerHTML; try{ btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'; }catch(e){ btn.innerText = 'Memuat...'; } list.innerHTML = 'Memuat...';
    try{
        const res = await fetch((typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/reports/data?' + params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}, credentials: 'same-origin'});
    // if server redirected to login or returned HTML, show friendly message
    const ctype = res.headers.get('Content-Type') || '';
    if (!res.ok) {
        const txt = await res.text().catch(()=>res.statusText);
        console.error('reports/data returned', res.status, txt);
        if (res.status === 302 || res.status === 303 || ctype.indexOf('text/html') !== -1) {
            list.innerHTML = '<div class="text-danger">Sesi berakhir atau tidak berwenang — silakan login sebagai Head IT.</div>';
        } else {
            list.innerHTML = '<div class="text-danger">Gagal memuat laporan: '+res.status+' '+(txt||'')+'</div>';
        }
        btn.disabled = false; btn.innerText = prevLabel; return;
    }
    const json = await res.json();
    // store fetched data for later modal rendering
    window._reportsData = json.data || {status:[],category:[],reasons:[]};
    // render QA/QC metrics if provided
    try{ renderMetrics(json.metrics || window._reportsData.metrics); }catch(e){/* ignore */}
    // render charts summary (we will create full charts on demand)
    renderCharts(window._reportsData);
    // render trend charts if provided
    try{ renderTrendCharts(json.trends); }catch(e){/* ignore */}
    if (page === 1){ document.getElementById('reportsList').innerHTML = '';
        // responsive visible count: xs -> 3, sm -> 4, md+ -> 5
        const vw = Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0);
        const visibleCount = vw < 576 ? 3 : (vw < 768 ? 4 : 5);
        renderTruncatedRows(json.rows_html || '', visibleCount);
    }
    else { appendList(json.rows_html || ''); }
    attachChartPreviewHandlers();
    // manage load more
    const loadWrap = document.getElementById('loadMoreWrap');
    if (loadWrap){ loadWrap.innerHTML = ''; if (json.has_more){ const btn = document.createElement('button'); btn.className = 'btn btn-outline-secondary'; btn.textContent = 'Muat lebih banyak'; btn.addEventListener('click', function(){ page = (page || 1) + 1; fetchReports(page); }); loadWrap.appendChild(btn); } }
    // if server indicated there's more, ensure viewAll button visible (if not already)
    try{ const vAll = document.getElementById('viewAllReportsBtn'); if (vAll){ if (json.has_more) vAll.style.display = 'inline-block'; } }catch(e){}
    }catch(e){ console.error('fetchReports error', e); list.innerHTML = '<div class="text-danger">Terjadi kesalahan saat mengambil laporan: '+(e.message||e)+'</div>'; }
        finally{ btn.disabled = false; try{ btn.innerHTML = prevHtml; }catch(e){ btn.innerText = 'Terapkan'; } }
}

// wire up after DOM ready
document.addEventListener('DOMContentLoaded', function(){
    // transient reopened filter flag (since checkbox removed)
    window._filterReopenedFlag = false;
    const applyBtn = document.getElementById("applyFilters");
    if (applyBtn) applyBtn.addEventListener('click', e=>{ e.preventDefault(); window._filterReopenedFlag = false; fetchReports(1); });
        // initialize Bootstrap tooltips for elements using data-bs-tooltip
        try{
            document.querySelectorAll('[data-bs-tooltip]').forEach(el=>{ if (!el.getAttribute('title')) return; try{ new bootstrap.Tooltip(el); }catch(e){} });
        }catch(e){}
    // export dropdown items
    document.querySelectorAll('.export-option').forEach(el=>{ el.addEventListener('click', e=>{ e.preventDefault(); const fmt = el.getAttribute('data-format') || 'csv'; exportReports(fmt); }); });
    const sanityBtn = document.getElementById('sanityChecks'); if (sanityBtn) sanityBtn.addEventListener('click', e=>{ e.preventDefault(); runSanityChecks(); });
    const viewAllBtn = document.getElementById('viewAllReportsBtn'); if (viewAllBtn) viewAllBtn.addEventListener('click', e=>{ e.preventDefault(); openAllReportsModal(); });
    // modal pagination controls
    const prevBtn = document.getElementById('allReportsPrevBtn'); if (prevBtn) prevBtn.addEventListener('click', e=>{ e.preventDefault(); const cur = window._allReportsModalPage || 1; if (cur > 1) openAllReportsModal(cur - 1); });
    const nextBtn = document.getElementById('allReportsNextBtn'); if (nextBtn) nextBtn.addEventListener('click', e=>{ e.preventDefault(); const cur = window._allReportsModalPage || 1; openAllReportsModal(cur + 1); });
    const pageSizeSel = document.getElementById('allReportsPageSize'); if (pageSizeSel) pageSizeSel.addEventListener('change', e=>{ e.preventDefault(); openAllReportsModal(1); });
    // Presets removed — use date range inputs instead
    // initial load
    fetchReports(1);

    // Mobile FAB and bottom-bar wiring
    try{
        const fab = document.getElementById('createTicketFab');
        const bottomBar = document.getElementById('mobileBottomBar');
        const openFiltersBtn = document.getElementById('openFiltersBtn');
        const applyBottom = document.getElementById('applyFiltersBottom');
        function updateMobileUI(){
            const vw = Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0);
            if (vw <= 768) {
                if (fab) fab.classList.remove('d-none');
                if (bottomBar) bottomBar.classList.remove('d-none');
            } else {
                if (fab) fab.classList.add('d-none');
                if (bottomBar) bottomBar.classList.add('d-none');
            }
        }
        updateMobileUI();
        window.addEventListener('resize', updateMobileUI);
        if (openFiltersBtn) openFiltersBtn.addEventListener('click', e=>{ e.preventDefault(); const toggle = document.getElementById('sidebarToggle'); if (toggle) toggle.click(); else { document.querySelector('.sidebar')?.classList.toggle('open'); } });
        if (applyBottom) applyBottom.addEventListener('click', e=>{ e.preventDefault(); const applyBtn = document.getElementById('applyFilters'); if (applyBtn) applyBtn.click(); else fetchReports(1); });
        // export options already wired using .export-option selector earlier
    }catch(e){ console.error('mobile UI wiring', e); }
});

function exportReports(fmt){ const from = document.getElementById("filterFrom").value; const to = document.getElementById("filterTo").value; const status = document.getElementById("filterStatus").value; const q = document.getElementById("filterQ").value; const params = new URLSearchParams(); if(from) params.set("from",from); if(to) params.set("to",to); if(status) params.set("status",status); if(q) params.set("q",q); params.set("format",fmt); const url = (typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/reports/export?'+params.toString(); window.open(url, "_blank"); }

async function runSanityChecks(){
    const params = new URLSearchParams();
    const from = document.getElementById('filterFrom').value; const to = document.getElementById('filterTo').value; const status = document.getElementById('filterStatus').value; const q = document.getElementById('filterQ').value;
    if (from) params.set('from', from); if (to) params.set('to', to); if (status) params.set('status', status); if (q) params.set('q', q);
    const url = (typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/reports/sanity?' + params.toString();
    const body = document.getElementById('sanityResults'); if (body) body.innerHTML = 'Memuat…';
    try{
        const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
        if (!res.ok) { body.innerHTML = '<div class="text-danger">Gagal memuat sanity checks: ' + res.status + '</div>'; return; }
        const json = await res.json();
        const sanity = json.sanity || {issues:[]};
        const html = [];
        sanity.issues.forEach(issue => {
            html.push('<div class="mb-3"><h6>' + issue.label + ' — <small class="text-muted">' + issue.count + '</small></h6>');
            if (issue.samples && issue.samples.length) {
                html.push('<table class="table table-sm"><thead><tr><th>ID</th><th>Kode</th><th>Status</th><th>Created</th><th>Notes</th></tr></thead><tbody>');
                issue.samples.forEach(s => {
                    const notes = [];
                    if (s.assigned_to !== undefined) notes.push('assigned_to:'+ (s.assigned_to||'null'));
                    if (s.assigned_at !== undefined) notes.push('assigned_at:'+ (s.assigned_at||'null'));
                    if (s.closed_at !== undefined) notes.push('closed_at:'+ (s.closed_at||'null'));
                    if (s.closure_reason !== undefined) notes.push('closure_reason:'+ (s.closure_reason||''));
                    html.push('<tr><td>' + (s.id||'') + '</td><td>' + (s.ticket_code||'') + '</td><td>' + (s.status||'') + '</td><td>' + (s.created_at||'') + '</td><td>' + notes.join(', ') + '</td></tr>');
                });
                html.push('</tbody></table>');
            }
            html.push('</div>');
        });
        if (html.length === 0) body.innerHTML = '<div class="text-success">Tidak ada masalah ditemukan.</div>'; else body.innerHTML = html.join('');
        showModalById('sanityModal');
    }catch(e){ console.error('sanity checks', e); if (body) body.innerHTML = '<div class="text-danger">Terjadi kesalahan saat memuat sanity checks.</div>'; }
}

function currentFilterParams(){
    const params = new URLSearchParams();
    const from = document.getElementById('filterFrom').value; const to = document.getElementById('filterTo').value; const status = document.getElementById('filterStatus').value; const q = document.getElementById('filterQ').value;
    const unit = (document.getElementById('filterUnit') || {}).value || '';
    const assignee = (document.getElementById('filterAssignee') || {}).value || '';
    const priority = (document.getElementById('filterPriority') || {}).value || '';
    const sla = (document.getElementById('filterSla') || {}).value || '';
    const reopened = window._filterReopenedFlag ? '1' : '';
    if (from) params.set('from', from); if (to) params.set('to', to); if (status) params.set('status', status); if (q) params.set('q', q);
    if (unit) params.set('unit', unit); if (assignee) params.set('assignee', assignee); if (priority) params.set('priority', priority); if (sla) params.set('sla', sla); if (reopened) params.set('reopened', reopened);
    return params;
}

function updateAllReportsFooter(page, hasMore, pageSize){
    window._allReportsModalPage = page || 1;
    const prev = document.getElementById('allReportsPrevBtn');
    const next = document.getElementById('allReportsNextBtn');
    const info = document.getElementById('allReportsPageInfo');
    const sizeSel = document.getElementById('allReportsPageSize');
    if (info) info.textContent = 'Halaman ' + (page || 1);
    if (prev) prev.disabled = (page || 1) <= 1;
    if (next) next.disabled = !hasMore;
    if (sizeSel && pageSize) sizeSel.value = String(pageSize);
}

async function openAllReportsModal(page = 1){
    const body = document.getElementById('allReportsModalBody'); if (body) body.innerHTML = 'Memuat…';
    const params = currentFilterParams(); params.set('full','1');
    const sizeSel = document.getElementById('allReportsPageSize');
    if (sizeSel && sizeSel.value) params.set('page_size', String(sizeSel.value));
    if (page && Number(page) > 1) params.set('page', String(page));
    try{
        const res = await fetch((typeof APP_BASE_PATH !== 'undefined' && APP_BASE_PATH ? APP_BASE_PATH : '') + '/reports/data?'+params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}, credentials: 'same-origin'});
        if (!res.ok) { body.innerHTML = '<div class="text-danger">Gagal memuat daftar lengkap: '+res.status+'</div>'; return; }
        const json = await res.json();
        body.innerHTML = json.rows_html || '<div class="text-muted">Tidak ada laporan.</div>';
        attachPreviewHandlers();
        // update footer controls
        updateAllReportsFooter(json.page || page, json.has_more || false, json.page_size || (sizeSel && sizeSel.value ? Number(sizeSel.value) : undefined));
        // Use getOrCreateInstance to avoid creating multiple modal instances
        var modalEl = document.getElementById('allReportsModal');
            showModalById('allReportsModal');
    }catch(e){ console.error('openAllReportsModal', e); if (body) body.innerHTML = '<div class="text-danger">Terjadi kesalahan.</div>'; }
}
