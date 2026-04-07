<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var string $activeTab */
/** @var string $today */
/** @var array<int,array<string,mixed>> $akunOptions */

$activeTabValue = in_array(($activeTab ?? 'keuangan'), ['akun', 'keuangan'], true) ? (string) $activeTab : 'keuangan';
$isAkunTab = $activeTabValue === 'akun';
$tableColumns = $isAkunTab
    ? ['Kode Akun', 'Nama Akun', 'Kategori', 'Tipe Arus', 'Status']
    : ['Tanggal', 'No Ref', 'Akun', 'Tipe Arus', 'Nominal'];

$extraHead = raw('<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">');
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Input Keuangan', 'extraHead' => $extraHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'keuangan-input'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Input Keuangan') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Fitur untuk Mengelola <?= e($title ?? 'Input Keuangan') ?></p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [['label' => 'Dashboard', 'url' => site_url('dashboard')]],
                    'current' => $title ?? 'Input Keuangan',
                ])) ?>
            </div>
        </div>
    </div>

    <section class="panel anim">
        <div class="panel-head">
            <span class="panel-title">Input Keuangan</span>
            <div class="d-flex align-items-center gap-2">
                <a class="keu-tab-link <?= e($isAkunTab ? 'is-active' : '') ?>" href="<?= e(site_url('keuangan/input?tab=akun')) ?>" data-keu-tab="akun">
                    <i class="bi bi-journal-text"></i><span>Input Akun</span>
                </a>
                <a class="keu-tab-link <?= e(!$isAkunTab ? 'is-active' : '') ?>" href="<?= e(site_url('keuangan/input?tab=keuangan')) ?>" data-keu-tab="keuangan">
                    <i class="bi bi-cash-coin"></i><span>Input Keuangan</span>
                </a>
            </div>
        </div>
        <div class="panel-body">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
                <span class="text-muted small" id="keuTabDesc"><?= e($isAkunTab ? 'Data master akun keuangan.' : 'Mutasi pemasukan/pengeluaran.') ?></span>
                <button type="button" class="btn-a" data-cm-open="cmAddKeuangan">
                    <i class="bi bi-plus-circle"></i><span id="keuAddBtnText"><?= e($isAkunTab ? 'Tambah Akun' : 'Tambah Input Keuangan') ?></span>
                </button>
            </div>
            <div class="dt-wrap generated-dt-wrap">
                <table class="dtable w-100 nowrap" id="keuanganTable">
                    <thead>
                        <tr id="keuanganTableHeadRow">
                            <th>No</th>
                            <?php foreach ($tableColumns as $columnLabel): ?>
                                <th><?= e((string) $columnLabel) ?></th>
                            <?php endforeach; ?>
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
    </section>
</main>

<div class="cm-bg" id="cmAddKeuangan" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmAddKeuanganTitle">
        <form method="post" action="<?= e(site_url('keuangan/input')) ?>" autocomplete="off" id="formAddKeuangan">
            <?= raw(csrf_field()) ?>
            <input type="hidden" name="_action" id="add_action" value="<?= e($isAkunTab ? 'create_akun' : 'create_keuangan') ?>">
            <input type="hidden" name="_tab" id="add_tab" value="<?= e($activeTabValue) ?>">
            <div class="panel-head">
                <span class="panel-title" id="cmAddKeuanganTitle"><?= e($isAkunTab ? 'Tambah Akun' : 'Tambah Input Keuangan') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid keu-mode keu-mode-akun">
                    <div><label class="u-label">Kode Akun <span class="text-danger">*</span></label><input class="u-input" type="text" name="kode_akun" placeholder="Contoh: 1106" required></div>
                    <div><label class="u-label">Nama Akun <span class="text-danger">*</span></label><input class="u-input" type="text" name="nama_akun" placeholder="Contoh: Kas Kecil" required></div>
                    <div>
                        <label class="u-label">Kategori <span class="text-danger">*</span></label>
                        <select class="u-input" name="kategori" required>
                            <option value="aset">Aset</option>
                            <option value="liabilitas">Liabilitas</option>
                            <option value="ekuitas">Ekuitas</option>
                            <option value="pendapatan">Pendapatan</option>
                            <option value="beban">Beban</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Tipe Arus <span class="text-danger">*</span></label>
                        <select class="u-input" name="tipe_arus" required>
                            <option value="netral">Netral</option>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div><label class="u-label">Deskripsi</label><textarea class="u-input" name="deskripsi" rows="3" placeholder="Catatan akun"></textarea></div>
                    <div>
                        <label class="u-label">Properti</label>
                        <div class="keu-check-wrap">
                            <label class="keu-check"><input type="checkbox" name="is_kas" value="1"> <span>Akun Kas</span></label>
                            <label class="keu-check"><input type="checkbox" name="is_modal" value="1"> <span>Akun Modal</span></label>
                        </div>
                    </div>
                </div>

                <div class="u-form-grid keu-mode keu-mode-keuangan">
                    <div><label class="u-label">Tanggal <span class="text-danger">*</span></label><input class="u-input" type="date" name="tanggal" value="<?= e((string) ($today ?? date('Y-m-d'))) ?>" required></div>
                    <div><label class="u-label">No Ref</label><input class="u-input" type="text" name="no_ref" placeholder="Contoh: OPR-001"></div>
                    <div>
                        <label class="u-label">Akun <span class="text-danger">*</span></label>
                        <select class="u-input" name="akun_keuangan_id" required>
                            <option value="">- Pilih Akun -</option>
                            <?php foreach (($akunOptions ?? []) as $opt): ?>
                                <?php if (!is_array($opt)) continue; ?>
                                <option value="<?= e((string) ($opt['id'] ?? '0')) ?>"><?= e((string) ($opt['kode_akun'] ?? '-')) ?> - <?= e((string) ($opt['nama_akun'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Tipe Arus <span class="text-danger">*</span></label>
                        <select class="u-input" name="tipe_arus" required>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                            <option value="netral">Netral</option>
                        </select>
                    </div>
                    <div><label class="u-label">Nominal <span class="text-danger">*</span></label><input class="u-input" type="number" min="1" name="nominal" placeholder="Masukkan nominal" required></div>
                    <div>
                        <label class="u-label">Metode Pembayaran</label>
                        <select class="u-input" name="metode_pembayaran">
                            <option value="">- Pilih Metode -</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-wallet">E-wallet</option>
                            <option value="QRIS">QRIS</option>
                        </select>
                    </div>
                    <div><label class="u-label">Keterangan</label><textarea class="u-input" name="deskripsi" rows="3" placeholder="Catatan transaksi"></textarea></div>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmEditKeuangan" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmEditKeuanganTitle">
        <form method="post" id="formEditKeuangan" action="<?= e(site_url('keuangan/input')) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <input type="hidden" name="_action" id="edit_action" value="<?= e($isAkunTab ? 'update_akun' : 'update_keuangan') ?>">
            <input type="hidden" name="_tab" id="edit_tab" value="<?= e($activeTabValue) ?>">
            <input type="hidden" name="id" id="edit_id" value="0">
            <div class="panel-head">
                <span class="panel-title" id="cmEditKeuanganTitle"><?= e($isAkunTab ? 'Edit Akun' : 'Edit Input Keuangan') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid keu-mode keu-mode-akun">
                    <div><label class="u-label">Kode Akun <span class="text-danger">*</span></label><input class="u-input" type="text" id="edit_kode_akun" name="kode_akun" required></div>
                    <div><label class="u-label">Nama Akun <span class="text-danger">*</span></label><input class="u-input" type="text" id="edit_nama_akun" name="nama_akun" required></div>
                    <div>
                        <label class="u-label">Kategori <span class="text-danger">*</span></label>
                        <select class="u-input" id="edit_kategori" name="kategori" required>
                            <option value="aset">Aset</option>
                            <option value="liabilitas">Liabilitas</option>
                            <option value="ekuitas">Ekuitas</option>
                            <option value="pendapatan">Pendapatan</option>
                            <option value="beban">Beban</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Tipe Arus <span class="text-danger">*</span></label>
                        <select class="u-input" id="edit_tipe_arus_akun" name="tipe_arus" required>
                            <option value="netral">Netral</option>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div><label class="u-label">Deskripsi</label><textarea class="u-input" id="edit_deskripsi_akun" name="deskripsi" rows="3"></textarea></div>
                    <div>
                        <label class="u-label">Properti</label>
                        <div class="keu-check-wrap">
                            <label class="keu-check"><input type="checkbox" id="edit_is_kas" name="is_kas" value="1"> <span>Akun Kas</span></label>
                            <label class="keu-check"><input type="checkbox" id="edit_is_modal" name="is_modal" value="1"> <span>Akun Modal</span></label>
                        </div>
                    </div>
                    <div>
                        <label class="u-label">Status <span class="text-danger">*</span></label>
                        <select class="u-input" id="edit_status" name="status" required>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="u-form-grid keu-mode keu-mode-keuangan">
                    <div><label class="u-label">Tanggal <span class="text-danger">*</span></label><input class="u-input" type="date" id="edit_tanggal" name="tanggal" required></div>
                    <div><label class="u-label">No Ref</label><input class="u-input" type="text" id="edit_no_ref" name="no_ref"></div>
                    <div>
                        <label class="u-label">Akun <span class="text-danger">*</span></label>
                        <select class="u-input" id="edit_akun_keuangan_id" name="akun_keuangan_id" required>
                            <option value="">- Pilih Akun -</option>
                            <?php foreach (($akunOptions ?? []) as $opt): ?>
                                <?php if (!is_array($opt)) continue; ?>
                                <option value="<?= e((string) ($opt['id'] ?? '0')) ?>"><?= e((string) ($opt['kode_akun'] ?? '-')) ?> - <?= e((string) ($opt['nama_akun'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Tipe Arus <span class="text-danger">*</span></label>
                        <select class="u-input" id="edit_tipe_arus_keuangan" name="tipe_arus" required>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                            <option value="netral">Netral</option>
                        </select>
                    </div>
                    <div><label class="u-label">Nominal <span class="text-danger">*</span></label><input class="u-input" type="number" min="1" id="edit_nominal" name="nominal" required></div>
                    <div>
                        <label class="u-label">Metode Pembayaran</label>
                        <select class="u-input" id="edit_metode_pembayaran" name="metode_pembayaran">
                            <option value="">- Pilih Metode -</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-wallet">E-wallet</option>
                            <option value="QRIS">QRIS</option>
                        </select>
                    </div>
                    <div><label class="u-label">Keterangan</label><textarea class="u-input" id="edit_deskripsi_keuangan" name="deskripsi" rows="3"></textarea></div>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Update</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmDeleteKeuangan" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmDeleteKeuanganTitle">
        <form method="post" id="formDeleteKeuangan" action="<?= e(site_url('keuangan/input')) ?>">
            <?= raw(csrf_field()) ?>
            <input type="hidden" name="_action" id="delete_action" value="<?= e($isAkunTab ? 'delete_akun' : 'delete_keuangan') ?>">
            <input type="hidden" name="_tab" id="delete_tab" value="<?= e($activeTabValue) ?>">
            <input type="hidden" name="id" id="delete_id" value="0">
            <div class="panel-head">
                <span class="panel-title" id="cmDeleteKeuanganTitle">Konfirmasi Hapus</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">Hapus data <strong id="delete_label">-</strong>? Tindakan ini tidak bisa dibatalkan.</div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>
    window.keuanganCrudConfig = {
        activeTab: <?= json_encode($activeTabValue, JSON_UNESCAPED_UNICODE) ?>,
        datatableBaseUrl: <?= json_encode(site_url('keuangan/input/datatable'), JSON_UNESCAPED_UNICODE) ?>,
        inputPageUrl: <?= json_encode(site_url('keuangan/input'), JSON_UNESCAPED_UNICODE) ?>,
        languageUrl: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>,
    };
</script>
<?= raw(module_script('Keuangan/js/input.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
