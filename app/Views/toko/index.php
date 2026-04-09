<?php
// app/Views/toko/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<string,mixed> $store */

$storeId = (int) ($store['id'] ?? 0);
$storeName = (string) ($store['nama_toko'] ?? '');
$storeAddress = (string) ($store['alamat_toko'] ?? '');
$storePhone = (string) ($store['tlp'] ?? '');
$storeOwner = (string) ($store['nama_pemilik'] ?? '');
$storeIcon = trim((string) ($store['icons'] ?? ''));
$storeLogoMode = trim((string) ($store['logo_mode'] ?? 'icon'));
$storeLogoMeta = avatar_meta($store['logo'] ?? null, $storeName !== '' ? $storeName : 'Toko');
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Pengaturan Toko'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'toko'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>Toko</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Perbarui pengaturan profil toko untuk POS.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => 'Toko',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8 anim">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title"><i class="bi bi-check-lg me-1"></i> Edit Data Toko</span>
                    <span class="text-muted small">Form Update</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="<?= e(site_url('toko')) ?>" enctype="multipart/form-data" autocomplete="off">
                        <?= raw(csrf_field()) ?>
                        <input type="hidden" name="id" value="<?= e((string) ($storeId > 0 ? $storeId : 1)) ?>">
                        <input type="hidden" name="logo_mode" id="logo_mode_input" value="<?= e($storeLogoMode) ?>">

                        <div class="fg">
                            <label class="fl" for="nama_toko">Nama Brand</label>
                            <input class="fi" id="nama_toko" type="text" name="nama_toko" maxlength="255" readonly value="<?= e(brand_name()) ?>">
                            <div class="u-help">Hard Branding aktif: nama brand dikunci di sistem.</div>
                        </div>

                        <div class="fg">
                            <label class="fl" for="alamat_toko">Alamat Toko</label>
                            <textarea class="fi" id="alamat_toko" name="alamat_toko" rows="3" required placeholder="Alamat toko"><?= e($storeAddress) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="fg">
                                    <label class="fl" for="nama_pemilik">Nama Pemilik</label>
                                    <input class="fi" id="nama_pemilik" type="text" name="nama_pemilik" maxlength="255" required placeholder="Nama pemilik toko" value="<?= e($storeOwner) ?>">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="fg">
                                    <label class="fl" for="tlp">Telepon</label>
                                    <input class="fi" id="tlp" type="text" name="tlp" maxlength="255" required placeholder="Nomor telepon toko" value="<?= e($storePhone) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="fg">
                                    <label class="fl" for="logo_file">Logo Toko</label>
                                    <div class="fi-file">
                                        <input class="fi-file-input" id="logo_file" type="file" name="logo_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,image/*">
                                        <span class="fi-file-btn">Pilih File</span>
                                        <span class="fi-file-name" id="logo_file_name">Tidak ada file yang dipilih</span>
                                    </div>
                                    <div class="u-help">Opsional. Format: JPG, PNG, WEBP, GIF, SVG. Maksimal 5MB.</div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="fg">
                                    <label class="fl" for="icons_picker_btn">Cari Icons</label>
                                    <div class="fi-file">
                                        <input type="hidden" id="icons_value" name="icons" value="<?= e($storeIcon) ?>">
                                        <button type="button" id="icons_picker_btn" class="fi-file-btn is-clickable">Pilih icons</button>
                                        <span class="fi-file-name" id="icons_file_name"><?= e($storeIcon !== '' ? $storeIcon : 'Tidak ada icon yang dipilih') ?></span>
                                    </div>
                                    <div class="u-help">Pilih icons Bootstrap yang Anda inginkan.</div>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">
                            <a href="<?= e(site_url('dashboard')) ?>" class="btn-g">Batal</a>
                            <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i><span>Update Toko</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 anim">
            <div class="panel" style="height:100%">
                <div class="panel-head">
                    <span class="panel-title">Info Toko</span>
                </div>
                <div class="panel-body">
                    <div class="act-item">
                        <div class="act-dot bg-warning"></div>
                        <div class="act-text">
                            <p><strong id="logo_label"><?= $storeLogoMode === 'gambar' ? 'Gambar:' : 'Icon:' ?></strong></p>
                            <div class="store-logo-box">
                                <?php if ($storeLogoMode === 'gambar' && $storeLogoMeta['has_image']): ?>
                                    <img class="store-logo" src="<?= e($storeLogoMeta['url']) ?>" alt="<?= e($storeName !== '' ? $storeName : 'Toko') ?>">
                                <?php elseif ($storeLogoMode === 'icon' && $storeIcon !== ''): ?>
                                    <span class="store-logo is-icon"><i class="<?= e($storeIcon) ?>"></i></span>
                                <?php else: ?>
                                    <span class="store-logo is-initials"><?= e($storeLogoMeta['initials']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="atime">Upload logo baru untuk mengganti logo lama.</span>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot bg-success"></div>
                        <div class="act-text">
                            <p><strong>Nama Toko:</strong> <?= e($storeName !== '' ? $storeName : '-') ?></p>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot bg-info"></div>
                        <div class="act-text">
                            <p><strong>Telepon:</strong> <?= e($storePhone !== '' ? $storePhone : '-') ?></p>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot bg-primary"></div>
                        <div class="act-text">
                            <p><strong>Alamat:</strong> <?= e($storeAddress !== '' ? $storeAddress : '-') ?></p>
                        </div>
                    </div>
                    <div class="act-item">
                        <div class="act-dot bg-danger"></div>
                        <div class="act-text">
                            <p><strong>Tampilkan sebagai:</strong></p>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <select class="u-input w-50" id="logo_type_select">
                                    <option value="">Pilih Gambar/Icon</option>
                                    <option value="gambar"<?= $storeLogoMode === 'gambar' ? ' selected' : '' ?>>Gambar</option>
                                    <option value="icon"<?= $storeLogoMode === 'icon' ? ' selected' : '' ?>>Icon</option>
                                </select>
                                <button class="btn-g btn-sm" id="logo_type_apply_btn">Terapkan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="cm-bg" id="cmIconPicker" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmIconPickerTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmIconPickerTitle">Pilih Bootstrap Icon</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body">
            <div class="icon-picker-head">
                <input type="text" id="iconSearch" class="fi" placeholder="Cari icon, contoh: shop, person, house">
                <button type="button" class="btn-g btn-sm" id="clearIconBtn">Kosongkan</button>
            </div>
            <div class="u-help mt-2 mb-2">Sumber icon: `assets/vendor/bootstrap-icons/bootstrap-icons.min.css`</div>
            <div id="iconPickerStatus" class="text-muted small mb-2">Memuat icon...</div>
            <div id="iconList" class="icon-grid"></div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Tutup</button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>

<?= raw(helper_toast_script()) ?>
<script>
    (function() {
        var input = document.getElementById('logo_file');
        var nameEl = document.getElementById('logo_file_name');
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

    (function() {
        var applyBtn = document.getElementById('logo_type_apply_btn');
        var typeSelect = document.getElementById('logo_type_select');
        var logoModeInput = document.getElementById('logo_mode_input');
        var logoLabel = document.getElementById('logo_label');
        var logoFileGroup = document.getElementById('logo_file') ? document.getElementById('logo_file').closest('.fg') : null;
        var iconsGroup = document.getElementById('icons_picker_btn') ? document.getElementById('icons_picker_btn').closest('.fg') : null;
        var logoBox = document.querySelector('.store-logo-box');

        if (applyBtn && typeSelect) {
            applyBtn.addEventListener('click', function() {
                var val = typeSelect.value;
                if (!val) return;
                if (logoModeInput) logoModeInput.value = val;
                if (logoLabel) logoLabel.textContent = val === 'gambar' ? 'Gambar:' : 'Icon:';
                if (val === 'gambar') {
                    if (logoFileGroup) logoFileGroup.style.display = '';
                    if (iconsGroup) iconsGroup.style.display = 'none';
                    if (logoBox) {
                        // Sembunyikan icon, tampilkan gambar atau initials
                        var iconSpan = logoBox.querySelector('span.is-icon');
                        if (iconSpan) iconSpan.style.display = 'none';
                        var img = logoBox.querySelector('img.store-logo');
                        var initialsSpan = logoBox.querySelector('span.is-initials');
                        if (img) {
                            img.style.display = '';
                            if (initialsSpan) initialsSpan.style.display = 'none';
                        } else if (initialsSpan) {
                            initialsSpan.style.display = '';
                        } else {
                            logoBox.insertAdjacentHTML('beforeend', '<span class="store-logo is-initials"><?= e($storeLogoMeta["initials"]) ?></span>');
                        }
                    }
                } else if (val === 'icon') {
                    if (logoFileGroup) logoFileGroup.style.display = 'none';
                    if (iconsGroup) iconsGroup.style.display = '';
                    if (logoBox) {
                        var img2 = logoBox.querySelector('img.store-logo');
                        if (img2) img2.style.display = 'none';
                        var initialsSpan2 = logoBox.querySelector('span.is-initials');
                        if (initialsSpan2) initialsSpan2.style.display = 'none';
                        var currentIcon = document.getElementById('icons_value') ? document.getElementById('icons_value').value : '';
                        var iconSpan2 = logoBox.querySelector('span.is-icon');
                        if (currentIcon !== '') {
                            if (iconSpan2) {
                                iconSpan2.style.display = '';
                                var iEl = iconSpan2.querySelector('i');
                                if (iEl) iEl.className = currentIcon;
                            } else {
                                logoBox.insertAdjacentHTML('beforeend', '<span class="store-logo is-icon"><i class="' + currentIcon + '"></i></span>');
                            }
                        } else if (iconSpan2) {
                            iconSpan2.style.display = '';
                        }
                    }
                }
            });
        }

        // Live-update preview when icon is selected from picker
        var iconsHidden = document.getElementById('icons_value');
        if (iconsHidden && logoBox) {
            var observer = new MutationObserver(function() {
                if (typeSelect && typeSelect.value !== 'icon') return;
                var cls = iconsHidden.value;
                var span = logoBox.querySelector('span.is-icon');
                if (cls !== '') {
                    if (span) {
                        span.style.display = '';
                        var iEl = span.querySelector('i');
                        if (iEl) iEl.className = cls;
                    } else {
                        logoBox.insertAdjacentHTML('beforeend', '<span class="store-logo is-icon"><i class="' + cls + '"></i></span>');
                    }
                }
            });
            observer.observe(iconsHidden, { attributes: true, attributeFilter: ['value'] });
            // Also listen via input event fallback
            iconsHidden.addEventListener('change', function() {
                iconsHidden.dispatchEvent(new Event('input'));
            });
        }
    })();

    (function() {
        var pickerBg = document.getElementById('cmIconPicker');
        var openBtn = document.getElementById('icons_picker_btn');
        var searchEl = document.getElementById('iconSearch');
        var listEl = document.getElementById('iconList');
        var statusEl = document.getElementById('iconPickerStatus');
        var hiddenInput = document.getElementById('icons_value');
        var selectedNameEl = document.getElementById('icons_file_name');
        var clearBtn = document.getElementById('clearIconBtn');
        var iconsCssUrl = '<?= e(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>';
        if (!pickerBg || !openBtn || !searchEl || !listEl || !statusEl || !hiddenInput || !selectedNameEl || !clearBtn) return;

        var allIcons = [];
        var loaded = false;
        var loading = false;

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function openPicker() {
            pickerBg.classList.add('show');
            pickerBg.style.display = 'flex';
            if (!loaded && !loading) {
                loadIcons();
            } else {
                renderIcons(searchEl.value || '');
            }
        }

        function closePicker() {
            pickerBg.classList.remove('show');
            pickerBg.style.display = 'none';
        }

        function loadIcons() {
            loading = true;
            statusEl.textContent = 'Memuat icon dari CSS...';

            fetch(iconsCssUrl, {
                    cache: 'no-store'
                })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function(cssText) {
                    var regex = /\.bi-([a-z0-9-]+)::?before/g;
                    var map = Object.create(null);
                    var match;
                    while ((match = regex.exec(cssText)) !== null) {
                        var icon = 'bi bi-' + match[1];
                        map[icon] = true;
                    }
                    allIcons = Object.keys(map).sort();
                    loaded = true;
                    statusEl.textContent = allIcons.length + ' icon ditemukan.';
                    renderIcons(searchEl.value || '');
                })
                .catch(function(err) {
                    statusEl.textContent = 'Gagal memuat icon. ' + (err && err.message ? err.message : '');
                    listEl.innerHTML = '';
                })
                .finally(function() {
                    loading = false;
                });
        }

        function renderIcons(keyword) {
            if (!loaded) return;
            var term = String(keyword || '').trim().toLowerCase();
            var filtered = term === '' ? allIcons : allIcons.filter(function(icon) {
                return icon.indexOf(term) !== -1;
            });

            if (filtered.length < 1) {
                listEl.innerHTML = '<div class="text-muted align-items-center small">Icon tidak ditemukan.</div>';
                return;
            }

            var html = '';
            for (var i = 0; i < filtered.length; i++) {
                var iconClass = filtered[i];
                var iconName = iconClass.replace('bi bi-', '');
                html += '<button type="button" class="icon-item" data-icon="' + escapeHtml(iconClass) + '">' +
                    '<i class="' + escapeHtml(iconClass) + '"></i>' +
                    '<span>' + escapeHtml(iconName) + '</span>' +
                    '</button>';
            }
            listEl.innerHTML = html;
        }

        openBtn.addEventListener('click', function() {
            openPicker();
        });

        searchEl.addEventListener('input', function() {
            renderIcons(searchEl.value || '');
        });

        clearBtn.addEventListener('click', function() {
            hiddenInput.value = '';
            selectedNameEl.textContent = 'Tidak ada icon yang dipilih';
            closePicker();
        });

        listEl.addEventListener('click', function(e) {
            var target = e.target;
            if (!target) return;
            var btn = target.closest('.icon-item');
            if (!btn) return;
            var iconClass = btn.getAttribute('data-icon') || '';
            hiddenInput.value = iconClass;
            selectedNameEl.textContent = iconClass !== '' ? iconClass : 'Tidak ada icon yang dipilih';
            hiddenInput.setAttribute('value', iconClass);
            hiddenInput.dispatchEvent(new Event('change'));
            closePicker();
        });

        pickerBg.addEventListener('click', function(e) {
            if (e.target === pickerBg) {
                closePicker();
            }
        });

        var closeButtons = pickerBg.querySelectorAll('[data-cm-close]');
        closeButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                closePicker();
            });
        });
    })();
</script>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>