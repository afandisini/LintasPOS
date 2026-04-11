<?php
// app/Views/security/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var string $tab */
/** @var array<string,int> $stats */
/** @var array<int,array<string,mixed>> $timeline */
/** @var array<int,array<string,mixed>> $topEvents */
/** @var array<int,array<string,mixed>> $topIps */
/** @var array<int,array<string,mixed>> $recentCritical */
/** @var array<int,string> $severities */
/** @var array<int,string> $categories */
/** @var array<int,string> $modules */
/** @var array<int,string> $actions */
/** @var int $activeCount */

$stats          = $stats ?? [];
$timeline       = $timeline ?? [];
$topEvents      = $topEvents ?? [];
$topIps         = $topIps ?? [];
$recentCritical = $recentCritical ?? [];
$severities     = $severities ?? [];
$modules        = $modules ?? [];
$actions        = $actions ?? [];
$activeCount    = $activeCount ?? 0;
$tab            = strtolower(trim((string) ($tab ?? 'overview')));
if (!in_array($tab, ['overview', 'events', 'audit', 'blocks'], true)) {
    $tab = 'overview';
}

$sevColor = static fn(string $s): string => match ($s) {
    'critical' => 'var(--danger)', 'high' => 'var(--warning)',
    'medium'   => 'var(--info)',   default => 'var(--success)',
};
$sevBadge = static fn(string $s): string => match ($s) {
    'critical' => 'danger', 'high' => 'warning',
    'medium'   => 'info',   default => 'success',
};

$extraHead = raw(
    '<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">'
);
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Security Monitor', 'extraHead' => $extraHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'security'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><i class="bi bi-shield-lock-fill me-2" style="color:var(--danger)"></i>Security Monitor</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <p class="small mb-0">Pantau aktivitas keamanan, audit trail, dan blokir ancaman secara real-time.</p>
            <?= raw(view('partials/dashboard/breadcrumb', [
                'items'   => [['label' => 'Dashboard', 'url' => site_url('dashboard')]],
                'current' => 'Security Monitor',
            ])) ?>
        </div>
    </div>

    <!-- Tab Nav -->
    <div class="keu-tab-wrap mb-3 anim">
        <?php foreach (['overview' => ['bi-speedometer2', 'Overview'], 'events' => ['bi-exclamation-triangle', 'Events'], 'audit' => ['bi-journal-text', 'Audit Log'], 'blocks' => ['bi-slash-circle', 'Blocks']] as $t => [$icon, $label]): ?>
            <a href="<?= e(site_url('security?tab=' . $t)) ?>"
               class="keu-tab-link<?= $tab === $t ? ' is-active' : '' ?>">
                <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'overview'): ?>
    <!-- ── OVERVIEW ──────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-3">
        <?php
        $cards = [
            ['Events Hari Ini',       $stats['total_events_today']    ?? 0, 'bi-activity',                'var(--accent)'],
            ['Events 7 Hari',         $stats['total_events_week']     ?? 0, 'bi-calendar-week',           'var(--info)'],
            ['High/Critical Hari Ini',$stats['critical_events_today'] ?? 0, 'bi-exclamation-octagon-fill','var(--danger)'],
            ['Active Blocks',         $stats['active_blocks']         ?? 0, 'bi-slash-circle-fill',       'var(--warning)'],
            ['Login Gagal Hari Ini',  $stats['failed_logins_today']   ?? 0, 'bi-person-x-fill',           'var(--danger)'],
            ['Audit Hari Ini',        $stats['audit_actions_today']   ?? 0, 'bi-journal-check',           'var(--success)'],
        ];
        ?>
        <?php foreach ($cards as [$label, $val, $icon, $color]): ?>
        <div class="col-6 col-lg-2 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:<?= e($color) ?>"></div>
                <div class="st-icon" style="background:<?= e($color) ?>22;color:<?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div class="st-val"><?= e(number_format((int) $val)) ?></div>
                <div class="st-label"><?= e($label) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8 anim">
            <div class="panel">
                <div class="panel-head"><span class="panel-title"><i class="bi bi-graph-up me-1"></i>Event Timeline (24 Jam)</span></div>
                <div class="panel-body"><div class="chart-box"><canvas id="secTimelineChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4 anim">
            <div class="panel h-100">
                <div class="panel-head"><span class="panel-title"><i class="bi bi-geo-alt me-1"></i>Top IP Hari Ini</span></div>
                <div class="panel-body">
                    <?php if ($topIps === []): ?>
                        <div class="text-muted small">Tidak ada data.</div>
                    <?php else: ?>
                        <?php foreach ($topIps as $row): ?>
                        <div class="act-item">
                            <div class="act-dot" style="background:var(--danger)"></div>
                            <div class="act-text">
                                <p><strong><?= e((string) ($row['ip_address'] ?? '-')) ?></strong></p>
                                <span class="atime"><?= e((string) ($row['cnt'] ?? 0)) ?> events · max risk <?= e((string) ($row['max_risk'] ?? 0)) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6 anim">
            <div class="panel">
                <div class="panel-head"><span class="panel-title"><i class="bi bi-bar-chart me-1"></i>Top Event Codes Hari Ini</span></div>
                <div class="panel-body" style="max-height:280px;overflow-y:auto">
                    <?php if ($topEvents === []): ?>
                        <div class="text-muted small">Tidak ada event hari ini.</div>
                    <?php else: ?>
                        <?php foreach ($topEvents as $row): ?>
                        <div class="act-item">
                            <div class="act-dot" style="background:<?= e($sevColor((string) ($row['severity'] ?? 'low'))) ?>"></div>
                            <div class="act-text">
                                <p><code><?= e((string) ($row['event_code'] ?? '-')) ?></code>
                                   <span class="sbadge <?= e($sevBadge((string) ($row['severity'] ?? 'low'))) ?> small ms-1"><span class="sd"></span><?= e((string) ($row['severity'] ?? '-')) ?></span>
                                </p>
                                <span class="atime"><?= e((string) ($row['cnt'] ?? 0)) ?> kali</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6 anim">
            <div class="panel">
                <div class="panel-head"><span class="panel-title"><i class="bi bi-exclamation-triangle-fill me-1" style="color:var(--danger)"></i>Recent High/Critical</span></div>
                <div class="panel-body" style="max-height:280px;overflow-y:auto">
                    <?php if ($recentCritical === []): ?>
                        <div class="text-muted small">Tidak ada event kritis.</div>
                    <?php else: ?>
                        <?php foreach ($recentCritical as $row): ?>
                        <div class="act-item">
                            <div class="act-dot" style="background:<?= e($sevColor((string) ($row['severity'] ?? 'low'))) ?>"></div>
                            <div class="act-text">
                                <p><code><?= e((string) ($row['event_code'] ?? '-')) ?></code>
                                   <span class="sbadge <?= e($sevBadge((string) ($row['severity'] ?? 'low'))) ?> small ms-1"><span class="sd"></span><?= e((string) ($row['severity'] ?? '-')) ?></span>
                                   <span class="sbadge inf small ms-1"><span class="sd"></span><?= e((string) ($row['action_taken'] ?? '-')) ?></span>
                                </p>
                                <span class="atime"><?= e((string) ($row['occurred_at'] ?? '-')) ?> · <?= e((string) ($row['ip_address'] ?? '-')) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'events'): ?>
    <!-- ── EVENTS ─────────────────────────────────────────────────────────── -->
    <div class="panel anim">
        <div class="panel-head"><span class="panel-title"><i class="bi bi-exclamation-triangle me-1"></i>Security Events</span></div>
        <div class="panel-body">
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <select class="fi" id="fSeverity">
                        <option value="">Semua Severity</option>
                        <?php foreach ($severities as $s): ?>
                            <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input class="fi" type="text" id="fIp" placeholder="Filter IP...">
                </div>
                <div class="col-6 col-md-3">
                    <input class="fi" type="date" id="fDari">
                </div>
                <div class="col-6 col-md-3">
                    <input class="fi" type="date" id="fSampai">
                </div>
            </div>
            <div class="dt-wrap">
                <table class="dtable" id="eventsTable" style="width:100%">
                    <thead><tr>
                        <th>Waktu</th><th>Event Code</th><th>Severity</th>
                        <th>Risk</th><th>IP</th><th>Stage</th><th>Action</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'audit'): ?>
    <!-- ── AUDIT LOG ──────────────────────────────────────────────────────── -->
    <div class="panel anim">
        <div class="panel-head"><span class="panel-title"><i class="bi bi-journal-text me-1"></i>Audit Log</span></div>
        <div class="panel-body">
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <select class="fi" id="fModule">
                        <option value="">Semua Modul</option>
                        <?php foreach ($modules as $m): ?>
                            <option value="<?= e($m) ?>"><?= e($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="fi" id="fAction">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= e($a) ?>"><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="fi" id="fSensitive">
                        <option value="">Semua</option>
                        <option value="1">Sensitif Saja</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <input class="fi" type="date" id="fAuditDari">
                </div>
                <div class="col-6 col-md-3">
                    <input class="fi" type="date" id="fAuditSampai">
                </div>
            </div>
            <div class="dt-wrap">
                <table class="dtable" id="auditTable" style="width:100%">
                    <thead><tr>
                        <th>Waktu</th><th>User</th><th>Modul</th><th>Aksi</th>
                        <th>Target</th><th>Diff</th><th>Risk</th><th>IP</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'blocks'): ?>
    <!-- ── BLOCKS ─────────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:var(--danger)"></div>
                <div class="st-icon" style="background:var(--danger-light,#fee2e2);color:var(--danger)"><i class="bi bi-slash-circle-fill"></i></div>
                <div class="st-val"><?= e(number_format($activeCount)) ?></div>
                <div class="st-label">Active Blocks</div>
            </div>
        </div>
    </div>
    <div class="panel anim">
        <div class="panel-head"><span class="panel-title"><i class="bi bi-slash-circle me-1"></i>Security Blocks</span></div>
        <div class="panel-body">
            <div class="mb-3">
                <label class="d-flex align-items-center gap-2 small" style="cursor:pointer">
                    <input type="checkbox" id="fActiveOnly" checked> Aktif saja
                </label>
            </div>
            <div class="dt-wrap">
                <table class="dtable" id="blocksTable" style="width:100%">
                    <thead><tr>
                        <th>Waktu</th><th>Type</th><th>Value</th><th>Reason</th>
                        <th>Severity</th><th>Expires</th><th>By</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Hidden form for unblock action -->
<form id="secUnblockForm" method="POST" action="<?= e(site_url('security/unblock')) ?>" style="display:none">
    <?= raw(csrf_field()) ?>
    <input type="hidden" id="secUnblockType" name="type">
    <input type="hidden" id="secUnblockValue" name="value">
</form>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>

<script src="<?= e(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>

<!-- jQuery + DataTables (same pattern as users/index.php) -->
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>

<script>
(function ($) {
    'use strict';

    var tab     = <?= json_encode($tab) ?>;
    var langUrl = <?= json_encode(base_url('assets/vendor/datatables/id.json')) ?>;

    // ── Helpers ───────────────────────────────────────────────────────────
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function sevBadge(s) {
        var map = { critical:'danger', high:'warning', medium:'info', low:'success' };
        return '<span class="sbadge ' + (map[s]||'success') + ' small"><span class="sd"></span>' + esc(s) + '</span>';
    }
    function infoBadge(s) {
        return '<span class="sbadge inf small"><span class="sd"></span>' + esc(s) + '</span>';
    }

    // ── DataTable factory ─────────────────────────────────────────────────
    function makeDT(id, url, columns, extraData) {
        return $('#' + id).DataTable({
            processing : true,
            serverSide : true,
            ajax: {
                url  : url,
                type : 'GET',
                data : function (d) { return $.extend({}, d, extraData()); }
            },
            columns    : columns,
            order      : [[0, 'desc']],
            pageLength : 25,
            language   : { url: langUrl }
        });
    }

    // ── Overview: Chart.js timeline ───────────────────────────────────────
    if (tab === 'overview') {
        var canvas = document.getElementById('secTimelineChart');
        if (canvas && typeof Chart !== 'undefined') {
            var raw    = <?= json_encode(array_map(static function ($row): array {
                $row = is_array($row) ? $row : [];
                return [
                    'hour'       => (string) ($row['hour'] ?? ''),
                    'total'      => (int) (string) ($row['total'] ?? 0),
                    'high_count' => (int) (string) ($row['high_count'] ?? 0),
                ];
            }, array_values($timeline))) ?>;
            var labels = raw.map(function(r){ return r.hour; });
            var totals = raw.map(function(r){ return parseInt(r.total, 10); });
            var highs  = raw.map(function(r){ return parseInt(r.high_count, 10); });
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label:'Total Events',  data:totals, backgroundColor:'rgba(99,102,241,0.5)', borderColor:'rgba(99,102,241,1)', borderWidth:1 },
                        { label:'High/Critical', data:highs,  backgroundColor:'rgba(239,68,68,0.5)',  borderColor:'rgba(239,68,68,1)',  borderWidth:1 }
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    plugins:{ legend:{ position:'top' } },
                    scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
                }
            });
        }
    }

    // ── Events tab ────────────────────────────────────────────────────────
    if (tab === 'events') {
        var dtEvents = makeDT('eventsTable', <?= json_encode(site_url('security/events/datatable')) ?>, [
            { data:'occurred_at',    title:'Waktu' },
            { data:'event_code',     title:'Event Code',  render: function(d){ return '<code>'+esc(d)+'</code>'; } },
            { data:'severity',       title:'Severity',    render: function(d){ return sevBadge(d); } },
            { data:'risk_score',     title:'Risk',        render: function(d){ return '<strong>'+esc(d)+'</strong>'; } },
            { data:'ip_address',     title:'IP' },
            { data:'detection_stage',title:'Stage',       render: function(d){ return infoBadge(d); } },
            { data:'action_taken',   title:'Action' }
        ], function() {
            return {
                severity : $('#fSeverity').val(),
                ip       : $('#fIp').val(),
                dari     : $('#fDari').val(),
                sampai   : $('#fSampai').val()
            };
        });

        $('#fSeverity,#fDari,#fSampai').on('change', function(){ dtEvents.ajax.reload(); });
        $('#fIp').on('keyup', function(){ dtEvents.ajax.reload(); });
    }

    // ── Audit tab ─────────────────────────────────────────────────────────
    if (tab === 'audit') {
        var dtAudit = makeDT('auditTable', <?= json_encode(site_url('security/audit/datatable')) ?>, [
            { data:'occurred_at',  title:'Waktu' },
            { data:'user_name',    title:'User',   render: function(d,t,r){ return esc(d || ('ID:'+r.user_id)); } },
            { data:'module_name',  title:'Modul' },
            { data:'action_name',  title:'Aksi',   render: function(d){ return infoBadge(d); } },
            { data:'target_id',    title:'Target', render: function(d,t,r){ return esc(r.target_type)+'#'+esc(d); } },
            { data:'diff_summary', title:'Diff',   render: function(d){ return d ? '<span class="small text-muted">'+esc(d)+'</span>' : '-'; } },
            { data:'risk_score',   title:'Risk' },
            { data:'ip_address',   title:'IP' }
        ], function() {
            return {
                module    : $('#fModule').val(),
                action    : $('#fAction').val(),
                sensitive : $('#fSensitive').val(),
                dari      : $('#fAuditDari').val(),
                sampai    : $('#fAuditSampai').val()
            };
        });

        $('#fModule,#fAction,#fSensitive,#fAuditDari,#fAuditSampai').on('change', function(){ dtAudit.ajax.reload(); });
    }

    // ── Blocks tab ────────────────────────────────────────────────────────
    if (tab === 'blocks') {
        var dtBlocks = makeDT('blocksTable', <?= json_encode(site_url('security/blocks/datatable')) ?>, [
            { data:'created_at',  title:'Waktu' },
            { data:'block_type',  title:'Type',     render: function(d){ return infoBadge(d); } },
            { data:'block_value', title:'Value',    render: function(d){ return '<code>'+esc(d)+'</code>'; } },
            { data:'reason_code', title:'Reason',   render: function(d){ return '<code class="small">'+esc(d)+'</code>'; } },
            { data:'severity',    title:'Severity', render: function(d){ return sevBadge(d); } },
            { data:'expires_at',  title:'Expires',  render: function(d){ return d ? esc(d) : '<em>Permanent</em>'; } },
            { data:'blocked_by',  title:'By' },
            { data:'is_active',   title:'Status',   render: function(d){
                return d == 1
                    ? '<span class="sbadge danger small"><span class="sd"></span>Active</span>'
                    : '<span class="sbadge success small"><span class="sd"></span>Inactive</span>';
            }},
            { data:null, title:'Aksi', orderable:false, render: function(d,t,r){
                if (r.is_active == 1 && (r.block_type === 'ip' || r.block_type === 'user')) {
                    return '<button class="btn-g small btn-unblock" data-type="'+esc(r.block_type)+'" data-value="'+esc(r.block_value)+'"><i class="bi bi-unlock"></i> Unblock</button>';
                }
                return '-';
            }}
        ], function() {
            return { active_only: $('#fActiveOnly').is(':checked') ? '1' : '0' };
        });

        $('#fActiveOnly').on('change', function(){ dtBlocks.ajax.reload(); });

        // Unblock via delegated click
        $('#blocksTable').on('click', '.btn-unblock', function() {
            var type  = $(this).data('type');
            var value = $(this).data('value');
            if (!confirm('Unblock ' + type + ' ' + value + '?')) return;
            $('#secUnblockType').val(type);
            $('#secUnblockValue').val(value);
            $('#secUnblockForm').submit();
        });
    }

}(jQuery));
</script>
<?= raw(view('partials/dashboard/shell_close')) ?>
