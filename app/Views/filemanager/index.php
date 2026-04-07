<?php
// app/Views/filemanager/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var string $module */
/** @var string $ref */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalFiles */
/** @var int $totalPages */
/** @var array<int,array<string,mixed>> $files */
/** @var array<int,string> $modules */

$module = strtolower((string) ($module ?? ''));
$ref = strtolower((string) ($ref ?? ''));
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 10));
$totalFiles = max(0, (int) ($totalFiles ?? 0));
$totalPages = max(1, (int) ($totalPages ?? 1));
$modules = is_array($modules ?? null) ? $modules : [];
$baseFileManagerUrl = site_url('filemanager');
$rowStart = ($page - 1) * $perPage;
$buildFileManagerUrl = static function (int $targetPage) use ($baseFileManagerUrl, $module, $ref): string {
    $query = [];
    if ($module !== '') {
        $query['module'] = $module;
    }
    if ($ref !== '') {
        $query['ref'] = $ref;
    }
    if ($targetPage > 1) {
        $query['page'] = (string) $targetPage;
    }
    if ($query === []) {
        return $baseFileManagerUrl;
    }
    return $baseFileManagerUrl . '?' . http_build_query($query);
};

$pagerStart = max(1, $page - 2);
$pagerEnd = min($totalPages, $page + 2);
if ($pagerStart <= 3) {
    $pagerStart = 1;
    $pagerEnd = min($totalPages, 5);
}
if ($pagerEnd >= $totalPages - 2) {
    $pagerEnd = $totalPages;
    $pagerStart = max(1, $totalPages - 4);
}
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'File Manager'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'filemanager'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>File Manager</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Kelola File anda dari File Manager ini.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => 'File Manager',
                ])) ?>
            </div>
        </div>
    </div>

    <section class="panel anim fm-shell">
        <aside class="fm-left">
            <div class="fm-folder-head">
                <span class="fm-folder-title">Folders / Modul</span>
            </div>

            <form method="post" action="<?= e(site_url('filemanager/module')) ?>" class="fm-add-module" autocomplete="off">
                <?= raw(csrf_field()) ?>
                <input class="fi" type="text" name="module" list="fmModuleOptions" maxlength="64" required placeholder="Tambah modul: users/barang" value="">
                <button type="submit" class="btn-a" title="Tambah modul"><i class="bi bi-folder-plus"></i></button>
            </form>

            <ul class="fm-folder-list">
                <?php
                $allUrl = $baseFileManagerUrl . ($ref !== '' ? '?ref=' . urlencode($ref) : '');
                ?>
                <li class="fm-folder-item <?= e($module === '' ? 'active' : '') ?>">
                    <div class="fm-folder-line">
                        <a class="fm-folder-link" href="<?= e($allUrl) ?>">
                            <i class="bi bi-collection"></i>
                            <span>Semua Modul</span>
                        </a>
                    </div>
                </li>
                <?php foreach ($modules as $moduleItem): ?>
                    <?php
                    $moduleItem = strtolower((string) $moduleItem);
                    if ($moduleItem === '') {
                        continue;
                    }
                    $isActive = $moduleItem === $module;
                    $moduleUrl = $baseFileManagerUrl . '?module=' . urlencode($moduleItem);
                    ?>
                    <li class="fm-folder-item <?= e($isActive ? 'active' : '') ?>">
                        <div class="fm-folder-line">
                            <a class="fm-folder-link" href="<?= e($moduleUrl) ?>">
                                <i class="bi bi-folder2"></i>
                                <span><?= e($moduleItem) ?></span>
                            </a>
                            <div class="fm-folder-actions">
                                <form method="post" action="<?= e(site_url('filemanager/module/delete')) ?>" class="js-confirm-delete" data-delete-message="<?= e('Hapus modul ' . $moduleItem . '? Modul yang masih punya file aktif tidak bisa dihapus.') ?>">
                                    <?= raw(csrf_field()) ?>
                                    <input type="hidden" name="module" value="<?= e($moduleItem) ?>">
                                    <button type="submit" class="btn-g" title="Hapus modul"><i class="bi bi-trash3 text-danger"></i></button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <div class="fm-right">
            <div class="fm-context">
                <div>
                    <strong><?= e($module !== '' ? $module : 'Semua Modul') ?></strong>
                    <p class="u-help">Folder di panel kiri mewakili `module` pada tabel file manager.</p>
                </div>
                <div class="fm-actions">
                    <span class="text-muted fm-total-files">Total file: <?= e((string) $totalFiles) ?></span>
                    <button type="button" class="btn-a btn-sm" data-cm-open="cmFileAction"><i class="bi bi-plus-circle"></i><span>Upload & Filter</span></button>
                </div>
            </div>
            <div class="fm-table-toolbar">
                <div class="d-flex align-items-center w-100">

                    <!-- KIRI -->
                    <form method="post" id="fmBulkDeleteForm"
                        class="js-confirm-delete me-auto"
                        data-delete-bulk="true"
                        action="<?= e(site_url('filemanager/delete-bulk')) ?>">

                        <?= raw(csrf_field()) ?>
                        <input type="hidden" name="module" value="<?= e($module) ?>">
                        <input type="hidden" name="ref" value="<?= e($ref) ?>">
                        <input type="hidden" name="page" value="<?= e((string) $page) ?>">

                        <button type="submit" class="btn-g btn-sm" id="fmBulkDeleteBtn" disabled>
                            <i class="bi bi-trash3 me-2 text-danger"></i><span>Hapus Terpilih</span>
                        </button>
                    </form>

                    <!-- KANAN -->
                    <div class="fm-view-switch ms-auto" role="group">
                        <button type="button" class="btn-g btn-sm is-active" data-fm-view="list">
                            <i class="bi bi-list-ul"></i>
                        </button>
                        <button type="button" class="btn-g btn-sm" data-fm-view="grid">
                            <i class="bi bi-columns-gap"></i>
                        </button>
                    </div>

                </div>
            </div>

            <div class="fm-view" data-fm-view-panel="list">
                <div class="dt-wrap fm-table-wrap">
                    <table class="dtable">
                        <thead>
                            <tr>
                                <th class="fm-check-col"><input type="checkbox" class="fm-check" id="fmCheckAll"></th>
                                <th>No</th>
                                <th>info File</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($files === []): ?>
                                <tr>
                                    <td colspan="8" class="text-muted">Belum ada file.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($files as $idx => $file): ?>
                                    <?php
                                    $id = (int) ($file['id'] ?? 0);
                                    $size = (int) ($file['size_bytes'] ?? 0);
                                    $sizeLabel = $size > 0 ? number_format($size / 1024, 1) . ' KB' : '-';
                                    $mimeType = strtolower((string) ($file['mime_type'] ?? ''));
                                    $extension = strtolower((string) ($file['extension'] ?? ''));
                                    $visibility = strtolower((string) ($file['visibility'] ?? 'private'));
                                    $relativePath = ltrim((string) ($file['path'] ?? ''), '/');
                                    $isImage = str_starts_with($mimeType, 'image/')
                                        || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                                    $thumbUrl = ($isImage && $relativePath !== '')
                                        ? site_url('media?path=' . urlencode($relativePath))
                                        : '';
                                    $visibilityIcon = $visibility === 'public' ? 'bi-eye' : 'bi-eye-slash';
                                    $visibilityTitle = $visibility === 'public' ? 'Public' : 'Private';
                                    ?>
                                    <tr>
                                        <td class="fm-check-col">
                                            <input type="checkbox" class="fm-check fm-file-check" data-file-id="<?= e((string) $id) ?>" form="fmBulkDeleteForm" name="ids[]" value="<?= e((string) $id) ?>">
                                        </td>
                                        <td><?= e((string) ($rowStart + (int) $idx + 1)) ?></td>
                                        <td>
                                            <div class="fm-file-info">
                                                <?php if ($thumbUrl !== ''): ?>
                                                    <span class="fm-file-thumb-wrap">
                                                        <img class="fm-file-thumb" src="<?= e($thumbUrl) ?>" alt="<?= e((string) ($file['name'] ?? '')) ?>" onerror="this.style.display='none';">
                                                        <span class="fm-visibility-badge" title="<?= e($visibilityTitle) ?>" aria-label="<?= e($visibilityTitle) ?>">
                                                            <i class="bi <?= e($visibilityIcon) ?>"></i>
                                                        </span>
                                                    </span>
                                                <?php endif; ?>
                                                <div class="fm-file-meta">
                                                    <div class="fw-semibold"><?= e((string) ($file['name'] ?? '-')) ?> </div>
                                                    <div class="text-muted fm-file-mime"><?= e((string) ($file['mime_type'] ?? '-')) ?></div>
                                                    <div class="text-muted"><?= e($sizeLabel) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
                                                <a
                                                    href="#"
                                                    class="btn-g btn-sm js-preview-image <?= e($thumbUrl === '' ? 'is-disabled' : '') ?>"
                                                    data-preview-url="<?= e($thumbUrl) ?>"
                                                    data-preview-name="<?= e((string) ($file['name'] ?? '-')) ?>"
                                                    data-preview-mime="<?= e((string) ($file['mime_type'] ?? '-')) ?>"
                                                    data-preview-size="<?= e($sizeLabel) ?>"
                                                    data-preview-path="<?= e($relativePath !== '' ? $relativePath : '-') ?>"
                                                    data-preview-module="<?= e((string) ($file['module'] ?? '-')) ?>"
                                                    data-preview-ref="<?= e((string) ($file['ref_id'] ?? '-')) ?>"
                                                    data-preview-created="<?= e((string) ($file['created_at'] ?? '-')) ?>"
                                                    title="Zoom gambar">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <form method="post" action="<?= e(site_url('filemanager/' . $id . '/delete')) ?>" class="js-confirm-delete" data-delete-message="Hapus file ini?">
                                                    <?= raw(csrf_field()) ?>
                                                    <input type="hidden" name="module" value="<?= e($module) ?>">
                                                    <input type="hidden" name="ref" value="<?= e($ref) ?>">
                                                    <input type="hidden" name="page" value="<?= e((string) $page) ?>">
                                                    <button type="submit" class="btn-g btn-sm"><i class="bi bi-trash3 text-danger"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fm-view is-hidden" data-fm-view-panel="grid">
                <div class="fm-grid">
                    <?php if ($files === []): ?>
                        <div class="fm-grid-empty">Belum ada file.</div>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <?php
                            $id = (int) ($file['id'] ?? 0);
                            $size = (int) ($file['size_bytes'] ?? 0);
                            $sizeLabel = $size > 0 ? number_format($size / 1024, 1) . ' KB' : '-';
                            $mimeType = strtolower((string) ($file['mime_type'] ?? ''));
                            $extension = strtolower((string) ($file['extension'] ?? ''));
                            $visibility = strtolower((string) ($file['visibility'] ?? 'private'));
                            $relativePath = ltrim((string) ($file['path'] ?? ''), '/');
                            $isImage = str_starts_with($mimeType, 'image/')
                                || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                            $thumbUrl = ($isImage && $relativePath !== '')
                                ? site_url('media?path=' . urlencode($relativePath))
                                : '';
                            $visibilityIcon = $visibility === 'public' ? 'bi-eye' : 'bi-eye-slash';
                            $visibilityTitle = $visibility === 'public' ? 'Public' : 'Private';
                            ?>
                            <article class="fm-card">
                                <div class="fm-card-top">
                                    <input type="checkbox" class="fm-check fm-file-check" data-file-id="<?= e((string) $id) ?>" form="fmBulkDeleteForm" name="ids[]" value="<?= e((string) $id) ?>" disabled>
                                    <span class="fm-card-ext"><?= e($extension !== '' ? strtoupper($extension) : 'FILE') ?></span>
                                </div>
                                <div class="fm-card-thumb-wrap">
                                    <?php if ($thumbUrl !== ''): ?>
                                        <img class="fm-card-thumb" src="<?= e($thumbUrl) ?>" alt="<?= e((string) ($file['name'] ?? '')) ?>" onerror="this.style.display='none';">
                                        <span class="fm-visibility-badge" title="<?= e($visibilityTitle) ?>" aria-label="<?= e($visibilityTitle) ?>">
                                            <i class="bi <?= e($visibilityIcon) ?>"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="fm-card-fallback"><i class="bi bi-file-earmark"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fm-card-body">
                                    <div class="fm-card-name"><?= e((string) ($file['name'] ?? '-')) ?></div>
                                    <div class="fm-card-meta"><?= e((string) ($file['mime_type'] ?? '-')) ?></div>
                                    <div class="fm-card-meta"><?= e($sizeLabel) ?></div>
                                </div>
                                <div class="fm-card-actions">
                                    <a
                                        href="#"
                                        class="btn-g btn-sm js-preview-image <?= e($thumbUrl === '' ? 'is-disabled' : '') ?>"
                                        data-preview-url="<?= e($thumbUrl) ?>"
                                        data-preview-name="<?= e((string) ($file['name'] ?? '-')) ?>"
                                        data-preview-mime="<?= e((string) ($file['mime_type'] ?? '-')) ?>"
                                        data-preview-size="<?= e($sizeLabel) ?>"
                                        data-preview-path="<?= e($relativePath !== '' ? $relativePath : '-') ?>"
                                        data-preview-module="<?= e((string) ($file['module'] ?? '-')) ?>"
                                        data-preview-ref="<?= e((string) ($file['ref_id'] ?? '-')) ?>"
                                        data-preview-created="<?= e((string) ($file['created_at'] ?? '-')) ?>"
                                        title="Zoom gambar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="post" action="<?= e(site_url('filemanager/' . $id . '/delete')) ?>" class="js-confirm-delete" data-delete-message="Hapus file ini?">
                                        <?= raw(csrf_field()) ?>
                                        <input type="hidden" name="module" value="<?= e($module) ?>">
                                        <input type="hidden" name="ref" value="<?= e($ref) ?>">
                                        <input type="hidden" name="page" value="<?= e((string) $page) ?>">
                                        <button type="submit" class="btn-g btn-sm"><i class="bi bi-trash3 text-danger"></i></button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="fm-pager py-3 d-flex justify-content-center">
                <?php $firstPage = 1; ?>
                <?php $prevPage = max(1, $page - 1); ?>
                <?php $nextPage = min($totalPages, $page + 1); ?>
                <?php $lastPage = $totalPages; ?>

                <a class="btn-g btn-sm <?= e($page <= 1 ? 'is-disabled' : '') ?>" href="<?= e($page <= 1 ? '#' : $buildFileManagerUrl($firstPage)) ?>" title="Halaman pertama"><i class="bi bi-chevron-double-left"></i></a>
                <a class="btn-g btn-sm <?= e($page <= 1 ? 'is-disabled' : '') ?>" href="<?= e($page <= 1 ? '#' : $buildFileManagerUrl($prevPage)) ?>" title="Halaman sebelumnya"><i class="bi bi-chevron-left"></i></a>

                <?php if ($pagerStart > 1): ?>
                    <a class="<?= e(1 === $page ? 'btn-a btn-sm' : 'btn-g btn-sm') ?>" href="<?= e($buildFileManagerUrl(1)) ?>">1</a>
                <?php endif; ?>
                <?php if ($pagerStart > 2): ?>
                    <span class="fm-page-ellipsis">...</span>
                <?php endif; ?>

                <?php for ($i = $pagerStart; $i <= $pagerEnd; $i++): ?>
                    <a class="<?= e($i === $page ? 'btn-a btn-sm' : 'btn-g btn-sm') ?>" href="<?= e($buildFileManagerUrl($i)) ?>"><?= e((string) $i) ?></a>
                <?php endfor; ?>

                <?php if ($pagerEnd < $totalPages - 1): ?>
                    <span class="fm-page-ellipsis">...</span>
                <?php endif; ?>
                <?php if ($pagerEnd < $totalPages): ?>
                    <a class="<?= e($totalPages === $page ? 'btn-a btn-sm' : 'btn-g btn-sm') ?>" href="<?= e($buildFileManagerUrl($totalPages)) ?>"><?= e((string) $totalPages) ?></a>
                <?php endif; ?>

                <a class="btn-g btn-sm <?= e($page >= $totalPages ? 'is-disabled' : '') ?>" href="<?= e($page >= $totalPages ? '#' : $buildFileManagerUrl($nextPage)) ?>" title="Halaman berikutnya"><i class="bi bi-chevron-right"></i></a>
                <a class="btn-g btn-sm <?= e($page >= $totalPages ? 'is-disabled' : '') ?>" href="<?= e($page >= $totalPages ? '#' : $buildFileManagerUrl($lastPage)) ?>" title="Halaman terakhir"><i class="bi bi-chevron-double-right"></i></a>
            </div>
        </div>
    </section>
</main>

<div class="cm-bg" id="cmFileAction" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmFileActionTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmFileActionTitle">Upload File & Filter Data</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body">
            <div class="fm-modal-grid">
                <section class="fm-modal-panel">
                    <div class="panel-head"><span class="panel-title">Upload File</span></div>
                    <div class="panel-body">
                        <form method="post" action="<?= e(site_url('filemanager/upload')) ?>" enctype="multipart/form-data" autocomplete="off">
                            <?= raw(csrf_field()) ?>
                            <div class="fg">
                                <label class="fl" for="fm_upload_module">Modul *</label>
                                <input class="fi" id="fm_upload_module" type="text" list="fmModuleOptions" name="module" required maxlength="64" placeholder="Contoh: users / barang" value="<?= e($module !== '' ? $module : 'users') ?>">
                                <div class="u-help">Pilih modul yang sudah ada atau ketik modul baru.</div>
                            </div>
                            <div class="fg">
                                <label class="fl" for="fm_upload_ref_id">ID Referensi *</label>
                                <input class="fi" id="fm_upload_ref_id" type="text" name="ref_id" required maxlength="64" placeholder="Contoh: 13 / BRG001" value="<?= e($ref !== '' ? $ref : '') ?>">
                            </div>
                            <div class="fg">
                                <label class="fl" for="fm_upload_visibility">Visibilitas</label>
                                <select class="fi" id="fm_upload_visibility" name="visibility">
                                    <option value="private">Private</option>
                                    <option value="public">Public</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label class="fl" for="fm_upload_file">Pilih File *</label>
                                <div class="fi-file">
                                    <input class="fi-file-input" id="fm_upload_file" type="file" name="upload_files[]" multiple required>
                                    <span class="fi-file-btn">Pilih File</span>
                                    <span class="fi-file-name" id="fm_upload_file_name">Tidak ada file yang dipilih</span>
                                </div>
                                <div class="u-help">Bisa upload beberapa file sekaligus ke folder modul/ref yang sama.</div>
                            </div>
                            <div class="fm-upload-actions">
                                <button type="submit" class="btn-a"><i class="bi bi-cloud-upload"></i><span>Upload</span></button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="fm-modal-panel">
                    <div class="panel-head"><span class="panel-title">Filter Data</span></div>
                    <div class="panel-body">
                        <form method="get" action="<?= e($baseFileManagerUrl) ?>">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input class="fi" type="text" list="fmModuleOptions" name="module" placeholder="Filter modul: users/barang" value="<?= e($module) ?>">
                                </div>
                                <div class="col-md-5">
                                    <input class="fi" type="text" name="ref" placeholder="Filter ID: 13 / BRG001" value="<?= e($ref) ?>">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn-g"><i class="bi bi-funnel"></i></button>
                                </div>
                            </div>
                        </form>
                        <div class="u-help mt-2">Struktur path otomatis: <code>storage/filemanager/&lt;modul&gt;/&lt;id&gt;</code></div>
                    </div>
                </section>
            </div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Tutup</button>
        </div>
    </div>
</div>

<datalist id="fmModuleOptions">
    <?php foreach ($modules as $moduleItem): ?>
        <?php $moduleItem = strtolower((string) $moduleItem); ?>
        <?php if ($moduleItem === '') {
            continue;
        } ?>
        <option value="<?= e($moduleItem) ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="cm-bg" id="cmDeleteConfirm" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmDeleteConfirmTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmDeleteConfirmTitle">Konfirmasi Hapus</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body" id="cmDeleteConfirmMessage">Yakin ingin menghapus data ini?</div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Batal</button>
            <button type="button" class="btn-a" id="cmDeleteConfirmBtn"><i class="bi bi-trash3 text-danger"></i><span>Ya, Hapus</span></button>
        </div>
    </div>
</div>

<div class="cm-bg" id="cmImagePreview" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmImagePreviewTitle">
        <div class="panel-head">
            <span class="panel-title" id="cmImagePreviewTitle">Preview Gambar</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-7">
                    <div class="fm-preview-wrap">
                        <img id="fmPreviewImage" class="fm-preview-image" src="" alt="Preview image">
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="panel fm-panel-full">
                        <div class="panel-head">
                            <span class="panel-title">Informasi File</span>
                        </div>
                        <div class="panel-body">
                            <div class="fg">
                                <label class="fl">Nama File</label>
                                <div class="fi" id="fmPreviewName">-</div>
                            </div>
                            <div class="fg">
                                <label class="fl">MIME Type</label>
                                <div class="fi" id="fmPreviewMime">-</div>
                            </div>
                            <div class="fg">
                                <label class="fl">Ukuran</label>
                                <div class="fi" id="fmPreviewSize">-</div>
                            </div>
                            <div class="fg">
                                <label class="fl">Path</label>
                                <div class="fi" id="fmPreviewPath">-</div>
                            </div>
                            <div class="fg">
                                <label class="fl">Modul / Ref ID</label>
                                <div class="fi"><span id="fmPreviewModule">-</span> / <span id="fmPreviewRef">-</span></div>
                            </div>
                            <div class="fg">
                                <label class="fl">Uploaded At</label>
                                <div class="fi" id="fmPreviewCreated">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Tutup</button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>

<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(module_script('FileManager/js/filemanager.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>