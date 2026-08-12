<?php
// reports view fragment. Expects $today variable set by caller.
?>
<h2>Laporan</h2>
<div class="card mb-3 p-3 sticky-top" style="top:1rem; z-index:1020;">
<div class="d-flex flex-wrap gap-2 align-items-center">
	<div><label class="small d-block mb-0 text-dark">Dari</label><input id="filterFrom" class="form-control form-control-sm" type="date" value="<?php echo htmlspecialchars($today); ?>"></div>
	<div><label class="small d-block mb-0 text-dark">Sampai</label><input id="filterTo" class="form-control form-control-sm" type="date" value="<?php echo htmlspecialchars($today); ?>"></div>
	<div style="min-width:180px"><label class="small d-block mb-0 text-dark">Status</label><select id="filterStatus" class="form-select form-select-sm"><option value="all">Semua</option><option value="waiting">Menunggu</option><option value="assigned">Ditugaskan</option><option value="in_progress">Sedang Dikerjakan</option><option value="waiting_confirmation">Menunggu Konfirmasi</option><option value="closed">Tertutup</option></select></div>
	<div style="min-width:220px;flex:1"><label class="small d-block mb-0 text-dark">Cari</label><input id="filterQ" class="form-control form-control-sm" placeholder="kode tiket, nama, NIP, deskripsi"></div>

	<div class="ms-auto d-flex align-items-center gap-2">
		<button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#moreFiltersCollapse" aria-expanded="false" aria-controls="moreFiltersCollapse" title="Lebih banyak filter" data-bs-tooltip="true">⚙️</button>
		<button id="applyFilters" class="btn btn-sm btn-primary" title="Terapkan filter" data-bs-tooltip="true" aria-label="Terapkan filter">🔍</button>
		<div class="btn-group">
			<button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Ekspor" data-bs-tooltip="true" aria-label="Ekspor">⤓</button>
			<ul class="dropdown-menu">
				<li><a class="dropdown-item export-option" href="#" data-format="csv">CSV</a></li>
				<li><a class="dropdown-item export-option" href="#" data-format="xls">XLS</a></li>
				<li><a class="dropdown-item export-option" href="#" data-format="pdf">PDF (HTML)</a></li>
			</ul>
		</div>
		<button id="sanityChecks" class="btn btn-sm btn-outline-warning" title="Sanity Checks" data-bs-tooltip="true" aria-label="Sanity Checks">🧪</button>
	</div>

	<div class="w-100 collapse mt-2" id="moreFiltersCollapse">
		<div class="d-flex gap-2 flex-wrap align-items-end">
			<div>
				<label class="small d-block mb-0 text-dark">Unit</label>
				<select id="filterUnit" class="form-select form-select-sm">
					<option value="">Semua unit</option>
					<?php foreach (($units ?? []) as $u): ?>
						<option value="<?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label class="small d-block mb-0 text-dark">Petugas</label>
				<select id="filterAssignee" class="form-select form-select-sm">
					<option value="all">Semua</option>
					<option value="unassigned">Belum ditugaskan</option>
					<?php foreach (($users ?? []) as $usr): ?>
						<option value="<?php echo (int)$usr['id']; ?>"><?php echo htmlspecialchars($usr['name']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label class="small d-block mb-0 text-dark">Prioritas</label>
				<select id="filterPriority" class="form-select form-select-sm">
					<option value="">Semua</option>
					<option value="Low">Low</option
					<option value="Normal">Normal</option>
					<option value="Urgent">Urgent</option>
				</select>
			</div>
			<div>
				<label class="small d-block mb-0 text-dark">SLA</label>
				<select id="filterSla" class="form-select form-select-sm">
					<option value="all">Semua</option>
					<option value="breached">SLA Dilanggar</option>
					<option value="open_breached">Masih terbuka & melewati SLA</option>
					<option value="within">Tutup dalam SLA</option>
				</select>
			</div>
		</div>
	</div>
</div>
</div>

<div class="row">
<div class="col-md-8 order-md-first">
<div class="small-charts">
<div id="reportsMetrics" class="mb-3">
	<div class="d-flex gap-2 flex-wrap" id="metricsCards">Memuat metrik&hellip;</div>
</div>
<div class="chart-wrap mb-3">
	<canvas id="trendNewClosed" height="180" style="max-height:240px;min-height:140px;cursor:pointer"></canvas>
	<div class="chart-hint text-muted small">Tren: Tiket baru vs Tertutup</div>
</div>
<div class="chart-wrap mb-3">
	<canvas id="trendAvgClose" height="140" style="max-height:200px;min-height:120px;cursor:pointer"></canvas>
	<div class="chart-hint text-muted small">Tren: Rata-rata waktu penyelesaian (menit)</div>
</div>
<div class="chart-wrap mb-3"><canvas id="statusChart" height="140" style="max-height:200px;min-height:120px;cursor:pointer"></canvas><div class="chart-hint text-muted small">Klik untuk memperbesar</div></div>
<div class="d-flex gap-3">
<div class="chart-wrap" style="flex:0 0 180px"><canvas id="categoryChart" width="180" height="140" style="cursor:pointer"></canvas><div class="chart-hint text-muted small">Klik untuk memperbesar</div></div>
<div class="chart-wrap" style="flex:1"><canvas id="reasonsChart" width="300" height="140" style="cursor:pointer"></canvas><div class="chart-hint text-muted small">Klik untuk memperbesar</div></div>
</div>
<div class="chart-wrap mb-3"><canvas id="unitChart" height="160" style="max-height:240px;min-height:120px;cursor:pointer"></canvas><div class="chart-hint text-muted small">Tiket per Unit</div></div>
</div>
</div>
<div class="col-md-4 order-md-last">
<h5>Laporan Terbaru</h5>
<div id="reportsList">Memuat&hellip;</div>
<div id="loadMoreWrap" class="mt-2 text-center"></div>
<div class="mt-2 text-center"><button id="viewAllReportsBtn" class="btn btn-sm btn-outline-primary" style="display:none">Lihat semua</button></div>
</div>
</div>

<!-- Modal for attachment preview -->
<div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Pratinjau</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body text-center" id="attachmentPreviewBody"></div>
</div></div></div>

<!-- Modal for chart preview -->
<div class="modal fade" id="chartModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="chartModalTitle">Grafik</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body" id="chartModalBody"><canvas id="chartModalCanvas" style="width:100%;height:400px"></canvas></div>
</div></div></div>

<!-- Modal for sanity checks -->
<div class="modal fade" id="sanityModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Sanity Checks</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body" id="sanityModalBody">
	<div id="sanityResults">Memuat&hellip;</div>
</div>
</div></div></div>

<!-- Modal for showing all filtered reports -->
<div class="modal fade" id="allReportsModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Semua Laporan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
<div class="modal-body"><div id="allReportsModalBody">Memuat&hellip;</div></div>
<div class="modal-footer no-print">
	<div id="allReportsModalFooter" class="w-100 d-flex justify-content-between align-items-center">
		<div>
			<div class="btn-group" role="group" aria-label="Paging">
				<button id="allReportsPrevBtn" class="btn btn-sm btn-outline-secondary" type="button">Prev</button>
				<span id="allReportsPageInfo" class="mx-2 small text-muted">Halaman 1</span>
				<button id="allReportsNextBtn" class="btn btn-sm btn-outline-secondary" type="button">Next</button>
			</div>
		</div>
		<div class="d-flex align-items-center gap-2">
			<select id="allReportsPageSize" class="form-select form-select-sm" style="width:auto;display:inline-block">
				<option value="50">50</option>
				<option value="100">100</option>
				<option value="200">200</option>
				<option value="500">500</option>
			</select>
			<button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
		</div>
	</div>
</div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/js/report.js'; ?>"></script>
