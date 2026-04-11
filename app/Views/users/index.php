<?php
// app/Views/users/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,array<string,mixed>> $users */
/** @var array<int,array<string,mixed>> $roles */
?>
<?php
$dataTablesHead = raw(
    '<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">'
);
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? ('Pengguna ' . brand_name()), 'extraHead' => $dataTablesHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'users'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>Pengguna</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Fitur untuk Mengelola Pengguna</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => 'Pengguna',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 anim">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Daftar Pengguna</span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-a" data-cm-open="cmAddUser">
                            <i class="bi bi-person-plus-fill"></i><span>Tambah Pengguna</span>
                        </button>
                        <span class="text-muted small">Total: <?= e((string) count($users)) ?></span>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="dt-wrap users-dt-wrap">
                        <table class="dtable users-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-muted">Memuat data pengguna...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="cm-bg" id="cmAddUser" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmAddUserTitle">
        <form class="barang-modern-form" method="post" action="<?= e(site_url('users')) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmAddUserTitle">Tambah Pengguna</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body barang-modern-body">
                <div class="u-form-grid">
                    <div>
                        <label class="u-label">Nama *</label>
                        <input class="u-input" type="text" name="name" required maxlength="255" placeholder="Nama pengguna">
                    </div>
                    <div>
                        <label class="u-label">Username *</label>
                        <input class="u-input" type="text" name="username" required maxlength="255" placeholder="Username login">
                    </div>
                    <div>
                        <label class="u-label">Email</label>
                        <input class="u-input" type="email" name="email" maxlength="255" placeholder="contoh@email.com">
                    </div>
                    <div>
                        <label class="u-label">Telepon</label>
                        <input class="u-input" type="text" name="telepon" maxlength="20" placeholder="08xxxxxxxxxx">
                    </div>
                    <div>
                        <label class="u-label">Hak Akses *</label>
                        <select class="u-input" name="akses" required>
                            <option value="">Pilih Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e((string) ($role['id'] ?? '')) ?>"><?= e((string) ($role['hak_akses'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Status</label>
                        <select class="u-input" name="active">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="u-col-full">
                        <label class="u-label">Alamat</label>
                        <textarea class="u-input" name="alamat" rows="2" placeholder="Alamat pengguna"></textarea>
                    </div>
                    <div class="u-col-full">
                        <label class="u-label">Password *</label>
                        <input class="u-input" type="password" name="password" minlength="8" required placeholder="Minimal 8 karakter">
                        <div class="u-help">Minimal 8 karakter.</div>
                    </div>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmEditUser" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmEditUserTitle">
        <form class="barang-modern-form" method="post" id="formEditUser" action="<?= e(site_url('users/0/update')) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmEditUserTitle">Edit Pengguna</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body barang-modern-body">
                <div class="u-form-grid">
                    <div>
                        <label class="u-label">Nama *</label>
                        <input class="u-input" type="text" id="edit_name" name="name" required maxlength="255" placeholder="Nama pengguna">
                    </div>
                    <div>
                        <label class="u-label">Username *</label>
                        <input class="u-input" type="text" id="edit_username" name="username" required maxlength="255" placeholder="Username login">
                    </div>
                    <div>
                        <label class="u-label">Email</label>
                        <input class="u-input" type="email" id="edit_email" name="email" maxlength="255" placeholder="contoh@email.com">
                    </div>
                    <div>
                        <label class="u-label">Telepon</label>
                        <input class="u-input" type="text" id="edit_telepon" name="telepon" maxlength="20" placeholder="08xxxxxxxxxx">
                    </div>
                    <div>
                        <label class="u-label">Hak Akses *</label>
                        <select class="u-input" id="edit_akses" name="akses" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e((string) ($role['id'] ?? '')) ?>"><?= e((string) ($role['hak_akses'] ?? '-')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="u-label">Status</label>
                        <select class="u-input" id="edit_active" name="active">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="u-col-full">
                        <label class="u-label">Alamat</label>
                        <textarea class="u-input" id="edit_alamat" name="alamat" rows="2" placeholder="Alamat pengguna"></textarea>
                    </div>
                    <div class="u-col-full">
                        <label class="u-label">Password Baru (Opsional)</label>
                        <input class="u-input" type="password" id="edit_password" name="password" minlength="8" placeholder="Kosongkan jika tidak diubah">
                        <div class="u-help">Kosongkan jika tidak ingin mengubah password.</div>
                    </div>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Update Pengguna</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmDeleteUser" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmDeleteUserTitle">
        <form method="post" id="formDeleteUser" action="<?= e(site_url('users/0/delete')) ?>">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmDeleteUserTitle">Konfirmasi Hapus</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                Hapus pengguna <strong id="delete_user_name">-</strong>? Tindakan ini tidak bisa dibatalkan.
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>

<!-- Modal Hak Akses -->
<div class="cm-bg" id="cmHakAkses" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmHakAksesTitle" style="max-width:680px">
        <div class="panel-head">
            <span class="panel-title" id="cmHakAksesTitle">Hak Akses — <span id="hakAksesUserName">-</span></span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body" style="max-height:65vh;overflow-y:auto;padding:0">
            <div id="hakAksesBody">
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">
                    <i class="bi bi-arrow-repeat spin-icon"></i> Memuat...
                </div>
            </div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Batal</button>
            <button type="button" class="btn-a" id="btnSaveHakAkses"><i class="bi bi-shield-check"></i> Simpan Akses</button>
        </div>
    </div>
</div>

<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>
    window.usersCrudConfig = {
        datatableUrl: <?= json_encode(site_url('users/datatable'), JSON_UNESCAPED_UNICODE) ?>,
        languageUrl: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?= raw(module_script('Users/js/users.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
