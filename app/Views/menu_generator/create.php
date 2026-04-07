<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,string> $tables */
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Buat Generator'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'menu-generator'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>Buat Konfigurasi Generator</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Scan struktur tabel lalu simpan konfigurasi modul.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                        ['label' => 'Menu Generator', 'url' => site_url('menu-generator')],
                    ],
                    'current' => 'Buat Konfigurasi',
                ])) ?>
            </div>
        </div>
    </div>

    <form class="panel anim" method="post" action="<?= e(site_url('menu-generator/store')) ?>" id="mgConfigForm">
        <?= raw(csrf_field()) ?>
        <div class="panel-head">
            <span class="panel-title">Form Konfigurasi</span>
            <div class="d-flex gap-2">
                <a class="btn-g btn-sm" href="<?= e(site_url('menu-generator')) ?>">
                    <i class="bi bi-arrow-left-circle me-1"></i><span>Kembali</span>
                </a>
                <button type="submit" class="btn-a btn-sm">
                    <i class="bi bi-check2-circle me-1"></i><span>Simpan Konfigurasi</span>
                </button>
            </div>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="module_name">Nama Modul *</label>
                        <input class="fi" id="module_name" name="module_name" type="text" required maxlength="150" placeholder="Contoh: Artikel">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="module_slug">Slug Modul *</label>
                        <input class="fi" id="module_slug" name="module_slug" type="text" required maxlength="150" placeholder="contoh: artikel">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="table_name">Nama Tabel *</label>
                        <div class="d-flex gap-2">
                            <select class="fi" id="table_name" name="table_name" required>
                                <option value="">Pilih tabel</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= e((string) $table) ?>"><?= e((string) $table) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-g" id="mgScanTableBtn">
                                <i class="bi bi-search me-1"></i><span>Scan</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="controller_name">Nama Controller *</label>
                        <input class="fi" id="controller_name" name="controller_name" type="text" required maxlength="150" placeholder="Contoh: ArticleController">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="view_folder">Folder View *</label>
                        <input class="fi" id="view_folder" name="view_folder" type="text" required maxlength="150" placeholder="contoh: article">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="route_prefix">Route Prefix *</label>
                        <input class="fi" id="route_prefix" name="route_prefix" type="text" required maxlength="150" placeholder="contoh: article">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="menu_title">Judul Menu</label>
                        <input class="fi" id="menu_title" name="menu_title" type="text" maxlength="150" placeholder="Contoh: Data Artikel">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="menu_icon_picker_btn">Cari Icons</label>
                        <div class="fi-file">
                            <input type="hidden" id="menu_icon" name="menu_icon" value="bi bi-grid-3x3-gap-fill">
                            <button type="button" id="menu_icon_picker_btn" class="fi-file-btn is-clickable">Pilih icons</button>
                            <span class="fi-file-name" id="menu_icon_file_name">bi bi-grid-3x3-gap-fill</span>
                        </div>
                        <div class="u-help">Pilih icons Bootstrap yang Anda inginkan.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fg">
                        <label class="fl" for="parent_menu_key">Group Menu (parent_menu_key)</label>
                        <input class="fi" id="parent_menu_key" name="parent_menu_key" type="text" maxlength="150" placeholder="Contoh: master-data">
                    </div>
                </div>
            </div>

            <input type="hidden" id="fields_json" name="fields_json" value="[]">

            <div class="panel mt-3">
                <div class="panel-head">
                    <span class="panel-title">Preview Auto Detect Fields</span>
                    <span class="text-muted small" id="mgScanInfo">Belum ada hasil scan.</span>
                </div>
                <div class="panel-body">
                    <div class="dt-wrap" style="overflow-x:auto">
                        <table class="dtable" id="mgFieldsPreviewTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Field</th>
                                    <th>Label</th>
                                    <th>HTML</th>
                                    <th>Auto Rule</th>
                                    <th>Sistem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-muted">Belum ada hasil scan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<div class="cm-bg" id="cmMenuGeneratorIconPicker" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmMenuGeneratorIconPickerTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmMenuGeneratorIconPickerTitle">Pilih Bootstrap Icon</span>
            <button type="button" class="cm-x" id="menuIconPickerCloseBtn" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body">
            <div class="icon-picker-head">
                <input type="text" id="menuIconSearch" class="fi" placeholder="Cari icon, contoh: shop, person, house">
                <button type="button" class="btn-g btn-sm" id="clearMenuIconBtn"><i class="bi bi-eraser me-1"></i><span>Kosongkan</span></button>
            </div>
            <div class="u-help mt-2 mb-2">Sumber icon: `assets/vendor/bootstrap-icons/bootstrap-icons.min.css`</div>
            <div id="menuIconPickerStatus" class="text-muted small mb-2">Memuat icon...</div>
            <div id="menuIconList" class="icon-grid"></div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" id="menuIconPickerCloseBtnFooter"><i class="bi bi-x-circle me-1"></i><span>Tutup</span></button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script>
    (function() {
        var pickerBg = document.getElementById('cmMenuGeneratorIconPicker');
        var openBtn = document.getElementById('menu_icon_picker_btn');
        var searchEl = document.getElementById('menuIconSearch');
        var listEl = document.getElementById('menuIconList');
        var statusEl = document.getElementById('menuIconPickerStatus');
        var hiddenInput = document.getElementById('menu_icon');
        var selectedNameEl = document.getElementById('menu_icon_file_name');
        var clearBtn = document.getElementById('clearMenuIconBtn');
        var closeBtnHead = document.getElementById('menuIconPickerCloseBtn');
        var closeBtnFoot = document.getElementById('menuIconPickerCloseBtnFooter');
        var iconsCssUrl = '<?= e(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>';
        if (!pickerBg || !openBtn || !searchEl || !listEl || !statusEl || !hiddenInput || !selectedNameEl || !clearBtn || !closeBtnHead || !closeBtnFoot) return;

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
            document.body.style.overflow = 'hidden';
            if (!loaded && !loading) {
                loadIcons();
            } else {
                renderIcons(searchEl.value || '');
            }
        }

        function closePicker() {
            pickerBg.classList.remove('show');
            if (!document.querySelector('.cm-bg.show')) {
                document.body.style.overflow = '';
            }
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
            closePicker();
        });

        pickerBg.addEventListener('click', function(e) {
            if (e.target === pickerBg) {
                closePicker();
            }
        });

        closeBtnHead.addEventListener('click', function() {
            closePicker();
        });
        closeBtnFoot.addEventListener('click', function() {
            closePicker();
        });
    })();
</script>
<script>
    window.menuGeneratorFormConfig = {
        scanUrl: <?= json_encode(site_url('menu-generator/scan-table'), JSON_UNESCAPED_UNICODE) ?>,
        csrfToken: <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?= raw(module_script('MenuGenerator/js/menu-generator.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
