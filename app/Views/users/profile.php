<?php
// app/Views/users/profile.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<string,mixed> $profile */

$profileAvatar = avatar_meta($profile['avatar'] ?? null, (string) ($profile['name'] ?? 'User'));
if (($profileAvatar['has_image'] ?? false) !== true) {
    $profileAvatar = avatar_meta($auth['avatar'] ?? null, (string) ($profile['name'] ?? ($auth['name'] ?? 'User')));
}
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Edit Profile'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'users'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>Edit Profile</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Perbarui data akun Anda. Perubahan akan berlaku pada sesi login saat ini.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                        ['label' => 'Pengguna', 'url' => site_url('users')],
                    ],
                    'current' => 'Edit Profile',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8 anim">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Data Profile</span>
                    <a class="panel-link" href="<?= e(site_url('dashboard')) ?>">Kembali ke Dashboard</a>
                </div>
                <div class="panel-body">
                    <form method="post" action="<?= e(site_url('profile')) ?>" enctype="multipart/form-data" autocomplete="off">
                        <?= raw(csrf_field()) ?>

                        <div class="fg">
                            <label class="fl" for="username">Username</label>
                            <input class="fi" id="username" type="text" name="username" maxlength="255" readonly placeholder="Username login" value="<?= e((string) ($profile['user'] ?? '')) ?>">
                            <div class="u-help">Username tidak dapat diubah.</div>
                        </div>

                        <div class="fg">
                            <label class="fl" for="name">Nama</label>
                            <input class="fi" id="name" type="text" name="name" maxlength="255" required placeholder="Nama lengkap" value="<?= e((string) ($profile['name'] ?? '')) ?>">
                        </div>

                        <div class="fg">
                            <label class="fl" for="email">Email</label>
                            <input class="fi" id="email" type="email" name="email" maxlength="255" placeholder="contoh@email.com" value="<?= e((string) ($profile['email'] ?? '')) ?>">
                        </div>

                        <div class="fg">
                            <label class="fl" for="telepon">Telepon</label>
                            <input class="fi" id="telepon" type="text" name="telepon" maxlength="20" placeholder="08xxxxxxxxxx" value="<?= e((string) ($profile['telepon'] ?? '')) ?>">
                        </div>

                        <div class="fg">
                            <label class="fl" for="alamat">Alamat</label>
                            <textarea class="fi" id="alamat" name="alamat" rows="3" placeholder="Alamat lengkap"><?= e((string) ($profile['alamat'] ?? '')) ?></textarea>
                        </div>

                        <div class="fg">
                            <label class="fl" for="avatar_file">Avatar</label>
                            <div class="fi-file">
                                <input class="fi-file-input" id="avatar_file" type="file" name="avatar_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                                <span class="fi-file-btn">Pilih File</span>
                                <span class="fi-file-name" id="avatar_file_name">Tidak ada file yang dipilih</span>
                            </div>
                            <div class="u-help">Opsional. Format: JPG, PNG, WEBP, GIF. Maksimal 5MB.</div>
                        </div>

                        <div class="sh-div"></div>

                        <div class="fg">
                            <label class="fl" for="current_password">Password Saat Ini</label>
                            <input class="fi" id="current_password" type="password" name="current_password" autocomplete="new-password" placeholder="Password saat ini">
                        </div>

                        <div class="fg">
                            <label class="fl" for="new_password">Password Baru</label>
                            <input class="fi" id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter">
                            <div class="u-help">Isi jika ingin mengganti password. Minimal 8 karakter.</div>
                        </div>

                        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">
                            <a href="<?= e(site_url('dashboard')) ?>" class="btn-g">Batal</a>
                            <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i><span>Simpan Perubahan</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 anim">
            <div class="panel" style="height:100%">
                <div class="panel-head">
                    <span class="panel-title">Info Akun</span>
                </div>
                <div class="panel-body">
                    <div class="act-item">
                        <div class="act-dot" style="background:var(--info)"></div>
                        <div class="act-text">
                            <p><strong>Role:</strong> <?= e((string) ($profile['role_name'] ?? ($auth['role'] ?? '-'))) ?></p>
                            <span class="atime">Akses login aktif</span>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot" style="background:var(--success)"></div>
                        <div class="act-text">
                            <p><strong>Status:</strong> <?= e(((string) ($profile['active'] ?? '0')) === '1' ? 'Aktif' : 'Nonaktif') ?></p>
                            <span class="atime">Status akun dari sistem</span>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot" style="background:var(--accent)"></div>
                        <div class="act-text">
                            <p><strong>Avatar:</strong></p>
                            <div class="profile-avatar-box">
                                <?php if ($profileAvatar['has_image']): ?>
                                    <img class="profile-avatar" src="<?= e($profileAvatar['url']) ?>" alt="<?= e((string) ($profile['name'] ?? 'User')) ?>">
                                <?php else: ?>
                                    <span class="profile-avatar is-initials"><?= e($profileAvatar['initials']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="atime">Jika gambar tidak tersedia, sistem menampilkan initials.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?= raw(view('partials/shared/toast')) ?>

<?= raw(helper_toast_script()) ?>
<script>
    (function() {
        var input = document.getElementById('avatar_file');
        var nameEl = document.getElementById('avatar_file_name');
        if (!input || !nameEl) return;

        input.addEventListener('change', function() {
            var files = input.files;
            if (!files || files.length < 1) {
                nameEl.textContent = 'Tidak ada file yang dipilih';
                return;
            }
            nameEl.textContent = files[0].name || '1 file dipilih';
        });
    })();
</script>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>