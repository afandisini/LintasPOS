<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var string $activeTab */
/** @var array<int,array<string,mixed>> $menuOrderItems */

$dataTablesHead = raw(
    '<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">'
        . '<style>'
        . '#cmMenuGeneratorGuide .panel-body{max-height:70vh;overflow-y:auto;padding-right:6px;}'
        . '</style>'
);
?>
<?= raw(view('partials/dashboard/head', [
    'title' => $title ?? 'Menu Generator',
    'extraHead' => $dataTablesHead,
])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'menu-generator'])) ?>

<main class="main" id="mainContent">


    <div class="pg-header mb-3 anim">
        <h1>Menu Generator</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Menu Generator, CRUD Fitur Baru</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => 'Menu Generator',
                ])) ?>
            </div>
        </div>
    </div>

    <?php $currentTab = in_array(($activeTab ?? 'config'), ['config', 'menu-order'], true) ? (string) ($activeTab ?? 'config') : 'config'; ?>
    <div class="keu-tab-wrap mb-3 anim">
        <a class="keu-tab-link <?= e($currentTab === 'config' ? 'is-active' : '') ?>" href="#" data-mg-tab="config">
            <i class="bi bi-grid-3x3-gap"></i><span>Konfigurasi</span>
        </a>
        <a class="keu-tab-link <?= e($currentTab === 'menu-order' ? 'is-active' : '') ?>" href="#" data-mg-tab="menu-order">
            <i class="bi bi-arrow-down-up"></i><span>Menu Order</span>
        </a>
    </div>

    <div class="mg-content <?= e($currentTab === 'config' ? 'active' : '') ?>" data-mg-content="config" <?= $currentTab === 'config' ? '' : 'style="display:none;"' ?>>
        <div class="panel anim">
            <div class="panel-head">
                <span class="panel-title">Daftar Konfigurasi Generator</span>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn-g btn-sm" data-cm-open="cmMenuGeneratorGuide">
                        <i class="bi bi-question-circle me-1"></i><span>Bantuan Aturan DB</span>
                    </button>
                    <a class="btn-a btn-sm" href="<?= e(site_url('menu-generator/create')) ?>">
                        <i class="bi bi-plus-circle me-1"></i><span>Tambah Konfigurasi</span>
                    </a>
                </div>
            </div>
            <div class="panel-body">
                <div class="dt-wrap generated-dt-wrap mg-dt-wrap">
                    <table class="dtable generated-table w-100 nowrap" id="mgTable">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Modul</th>
                                <th>Tabel</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th>Update</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-muted">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mg-content <?= e($currentTab === 'menu-order' ? 'active' : '') ?>" data-mg-content="menu-order" <?= $currentTab === 'menu-order' ? '' : 'style="display:none;"' ?>>
        <div class="panel anim">
            <div class="panel-head">
                <span class="panel-title">Menu Order Sidebar</span>
                <div class="small text-muted">Tahan Geser klik tombol, Untuk Pindah menu lalu simpan.</div>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= e(site_url('menu-generator/menu-order')) ?>" id="mgMenuOrderForm">
                    <?= raw(csrf_field()) ?>
                    <input type="hidden" name="order_json" id="mgOrderJson" value="[]">

                    <ul class="mg-order-list" id="mgOrderList">
                        <?php if (($menuOrderItems ?? []) === []): ?>
                            <li class="mg-order-empty">Belum ada menu dari database yang bisa diurutkan.</li>
                        <?php else: ?>
                            <?php foreach (($menuOrderItems ?? []) as $index => $item): ?>
                                <?php
                                $menuId = (int) ($item['id'] ?? 0);
                                $menuTitle = trim((string) ($item['menu_title'] ?? ''));
                                $moduleName = trim((string) ($item['module_name'] ?? ''));
                                $label = $menuTitle !== '' ? $menuTitle : ($moduleName !== '' ? $moduleName : ('Menu #' . $menuId));
                                $routePrefix = trim((string) ($item['route_prefix'] ?? ''), '/');
                                $parentKey = trim((string) ($item['parent_menu_key'] ?? ''));
                                ?>
                                <li class="mg-order-item" data-menu-id="<?= e((string) $menuId) ?>" draggable="true">
                                    <div class="mg-order-pos"><?= e((string) ((int) $index + 1)) ?></div>
                                    <div class="mg-order-main">
                                        <div class="mg-order-title">
                                            <span class="mg-order-handle" title="Drag untuk geser"><i class="bi bi-grip-vertical"></i></span>
                                            <span><?= e($label) ?></span>
                                        </div>
                                        <div class="mg-order-meta">
                                            <span><i class="bi bi-signpost-2 me-1"></i><?= e($routePrefix !== '' ? ('/' . $routePrefix) : '-') ?></span>
                                            <span><i class="bi bi-collection me-1"></i><?= e($parentKey !== '' ? $parentKey : '-') ?></span>
                                        </div>
                                    </div>
                                    <div class="mg-order-actions">
                                        <button type="button" class="btn-g btn-sm" data-order-action="up"><i class="bi bi-arrow-up"></i></button>
                                        <button type="button" class="btn-g btn-sm" data-order-action="down"><i class="bi bi-arrow-down"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn-a btn-sm" id="mgSaveOrderBtn">
                            <i class="bi bi-check2-circle me-1"></i><span>Simpan Urutan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<div class="cm-bg" id="cmMenuGeneratorConfirm" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmMenuGeneratorConfirmTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmMenuGeneratorConfirmTitle">Konfirmasi Aksi</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body" id="cmMenuGeneratorConfirmMessage">Yakin ingin melanjutkan aksi ini?</div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close><i class="bi bi-x-circle me-1"></i><span>Batal</span></button>
            <button type="button" class="btn-a" id="cmMenuGeneratorConfirmBtn"><i class="bi bi-check2-circle me-1"></i><span>Lanjutkan</span></button>
        </div>
    </div>
</div>

<div class="cm-bg" id="cmMenuGeneratorGuide" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmMenuGeneratorGuideTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmMenuGeneratorGuideTitle">Panduan Struktur Database Untuk Menu Generator</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body">
            <div class="small text-muted mb-2">Tujuan: hasil generate bisa sempurna untuk daftar data, form create/edit, aksi delete, dan datatable AJAX.</div>

            <div class="fg">
                <div class="fl display-4">1) Struktur Wajib Tabel</div>
                <div class="small text-muted">
                    - Wajib punya <code>id</code> sebagai primary key numeric.<br>
                    - Disarankan tambahkan <code>created_at</code>, <code>updated_at</code>, <code>deleted_at</code>.<br>
                    - Hindari nama kolom dengan spasi/simbol. Gunakan <code>snake_case</code>.
                </div>
            </div>

            <div class="fg">
                <div class="fl display-4">2) Aturan Penamaan Kolom (Auto-Detect Generator)</div>
                <div class="small text-muted">
                    - <code>xxx_id</code> - dibaca sebagai relasi (input select).<br>
                    - <code>xxx_textarea</code> - dibaca sebagai input textarea.<br>
                    - <code>xxx_img</code> - dibaca sebagai upload gambar.<br>
                    - <code>xxx_file</code> - dibaca sebagai upload file.<br>
                    - Tipe DB <code>text/mediumtext/longtext</code> juga otomatis jadi textarea.<br>
                    - Tipe DB <code>date</code> jadi date, <code>datetime/timestamp</code> jadi datetime-local.
                </div>
            </div>

            <div class="fg">
                <div class="fl display-4">3) Cara Join Relasi Otomatis (Rumus: [nama_tabel]_id)</div>
                <div class="small text-muted mb-2">
                    Gunakan rumus kolom relasi: <code>[nama_tabel]_id</code> maka generator akan mencari tabel <code>[nama_tabel]</code>.
                    Contoh tabel <code>barang</code>:
                </div>
                <pre class="fi" style="white-space:pre-wrap; height: auto;">id BIGINT PRIMARY KEY AUTO_INCREMENT,
satuan_id BIGINT NOT NULL,
kategori_id BIGINT NOT NULL,
nama_barang VARCHAR(150) NOT NULL</pre>
                <div class="small text-muted mt-2">
                    Lalu siapkan tabel referensi <code>kategori</code> dan <code>satuan</code>:
                </div>
                <pre class="fi" style="white-space:pre-wrap; height: auto;">id BIGINT PRIMARY KEY AUTO_INCREMENT,
nama VARCHAR(120) NOT NULL</pre>
                <pre class="fi" style="white-space:pre-wrap; height: auto;">id BIGINT PRIMARY KEY AUTO_INCREMENT,
nama VARCHAR(120) NOT NULL</pre>
                <div class="small text-muted mt-2">
                    Dengan pola ini: <code>barang.kategori_id</code> otomatis join ke tabel <code>kategori</code>, dan <code>barang.satuan_id</code> ke tabel <code>satuan</code>.
                    Untuk label tampilan, arahkan field relasi ke <code>relation_label_field=nama</code>.
                </div>
            </div>

            <div class="fg">
                <div class="fl display-4">4) Cara Agar Tampil Textarea</div>
                <div class="small text-muted">
                    Pilihan aman:
                    - Nama kolom pakai suffix <code>_textarea</code>, contoh <code>deskripsi_textarea</code>.<br>
                    - Atau tipe kolom pakai <code>TEXT</code>/<code>MEDIUMTEXT</code>/<code>LONGTEXT</code>.
                </div>
            </div>

            <div class="fg">
                <div class="fl display-4">5) Field Wajib (Required) di Form Generate</div>
                <div class="small text-muted">
                    Kolom akan dianggap wajib jika di DB:
                    - <code>NOT NULL</code>, dan
                    - tidak memiliki <code>DEFAULT</code>.<br>
                    Jika field wajib kosong, form create/edit akan ditolak dan muncul toast error.
                </div>
            </div>

            <div class="fg">
                <div class="fl display-4">6) Tips Supaya Generate Stabil</div>
                <div class="small text-muted">
                    - Semua tabel sebaiknya punya kolom <code>id</code> numeric auto increment.<br>
                    - Hindari trigger/constraint kompleks dulu saat awal generate.<br>
                    - Generate ulang setelah ubah struktur tabel.<br>
                    - Jika route/menu belum muncul, cek status konfigurasi harus <code>generated</code>.
                </div>
            </div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-a" data-cm-close><i class="bi bi-check2-circle me-1"></i><span>Tutup</span></button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>
    window.menuGeneratorConfig = {
        datatableUrl: <?= json_encode(site_url('menu-generator/datatable'), JSON_UNESCAPED_UNICODE) ?>,
        languageUrl: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>,
        editBaseUrl: <?= json_encode(site_url('menu-generator/edit?id='), JSON_UNESCAPED_UNICODE) ?>,
        generateUrl: <?= json_encode(site_url('menu-generator/generate'), JSON_UNESCAPED_UNICODE) ?>,
        deleteUrl: <?= json_encode(site_url('menu-generator/delete'), JSON_UNESCAPED_UNICODE) ?>,
        deleteGeneratedUrl: <?= json_encode(site_url('menu-generator/delete-generated'), JSON_UNESCAPED_UNICODE) ?>,
        csrfToken: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
        activeTab: <?= json_encode($currentTab, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?= raw(module_script('MenuGenerator/js/menu-generator.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>