<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,array<string,mixed>> $rows */
/** @var int $totalRows */
/** @var array<int,array{name: string, label: string, input_type: string, required: bool, relation_table: string|null}> $editableColumns */
/** @var array<int,array{name: string, label: string}> $displayColumns */
/** @var array<string, array{table: string, key: string, label: string, options: array<int, array{id: string, label: string}>}> $relations */
/** @var string $routePrefix */
?>
<?php
$displayColumnsJs = [];
foreach ((array) $displayColumns as $column) {
    if (!is_array($column)) {
        continue;
    }
    $displayColumnsJs[] = [
        'name' => (string) ($column['name'] ?? ''),
        'label' => (string) ($column['label'] ?? ''),
    ];
}
$routePrefixJs = (string) $routePrefix;

$dataTablesHead = raw(
    '<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">'
);
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Kategori', 'extraHead' => $dataTablesHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'kategori'])) ?>
<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Kategori') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Fitur untuk Mengelola <?= e($title ?? 'Kategori') ?></p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => $title ?? 'Kategori',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 anim">
            <div class="panel">
                <div class="panel-head">
                    <span class="panel-title">Daftar <?= e($title ?? 'Kategori') ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-a" data-cm-open="cmAddGenerated">
                            <i class="bi bi-plus-circle"></i><span>Tambah <?= e($title ?? 'Kategori') ?></span>
                        </button>
                        <span class="text-muted small">Total: <?= e((string) ($totalRows ?? count($rows))) ?></span>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="dt-wrap generated-dt-wrap">
                        <table class="dtable generated-table" id="generatedTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <?php foreach ($displayColumns as $column): ?>
                                        <th><?= e((string) ($column['label'] ?? '-')) ?></th>
                                    <?php endforeach; ?>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="<?= e((string) (count($displayColumns) + 2)) ?>" class="text-muted">Memuat data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="cm-bg" id="cmAddGenerated" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmAddGeneratedTitle">
        <form method="post" action="<?= e(site_url($routePrefix)) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmAddGeneratedTitle">Tambah <?= e($title ?? 'Kategori') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid">
                    <?php foreach ($editableColumns as $column): ?>
                        <?php
                        $name = (string) ($column['name'] ?? '');
                        $label = (string) ($column['label'] ?? $name);
                        $inputType = (string) ($column['input_type'] ?? 'text');
                        $required = (bool) ($column['required'] ?? false);
                        $relationMeta = is_array($relations[$name] ?? null) ? $relations[$name] : null;
                        $relationOptions = is_array($relationMeta['options'] ?? null) ? $relationMeta['options'] : [];
                        ?>
                        <div>
                            <label class="u-label"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
                            <?php if ($inputType === 'textarea'): ?>
                                <textarea class="u-input" name="<?= e($name) ?>" rows="3" placeholder="Masukkan <?= e(strtolower($label)) ?>" <?= $required ? 'required' : '' ?>></textarea>
                            <?php elseif ($inputType === 'select'): ?>
                                <select class="u-input" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                                    <option value="">Pilih <?= e($label) ?></option>
                                    <?php foreach ($relationOptions as $option): ?>
                                        <?php $optionId = (string) ($option['id'] ?? ''); ?>
                                        <?php $optionLabel = (string) ($option['label'] ?? $optionId); ?>
                                        <option value="<?= e($optionId) ?>"><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="u-input" type="<?= e($inputType) ?>" name="<?= e($name) ?>" placeholder="Masukkan <?= e(strtolower($label)) ?>" <?= $required ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmEditGenerated" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmEditGeneratedTitle">
        <form method="post" id="formEditGenerated" action="<?= e(site_url($routePrefix . '/0/update')) ?>" autocomplete="off">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmEditGeneratedTitle">Edit <?= e($title ?? 'Kategori') ?></span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="u-form-grid">
                    <?php foreach ($editableColumns as $column): ?>
                        <?php
                        $name = (string) ($column['name'] ?? '');
                        $label = (string) ($column['label'] ?? $name);
                        $inputType = (string) ($column['input_type'] ?? 'text');
                        $required = (bool) ($column['required'] ?? false);
                        $relationMeta = is_array($relations[$name] ?? null) ? $relations[$name] : null;
                        $relationOptions = is_array($relationMeta['options'] ?? null) ? $relationMeta['options'] : [];
                        ?>
                        <div>
                            <label class="u-label"><?= e($label) ?><?= $required ? ' *' : '' ?></label>
                            <?php if ($inputType === 'textarea'): ?>
                                <textarea class="u-input" id="edit_<?= e($name) ?>" name="<?= e($name) ?>" rows="3" <?= $required ? 'required' : '' ?>></textarea>
                            <?php elseif ($inputType === 'select'): ?>
                                <select class="u-input" id="edit_<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                                    <option value="">Pilih <?= e($label) ?></option>
                                    <?php foreach ($relationOptions as $option): ?>
                                        <?php $optionId = (string) ($option['id'] ?? ''); ?>
                                        <?php $optionLabel = (string) ($option['label'] ?? $optionId); ?>
                                        <option value="<?= e($optionId) ?>"><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input class="u-input" type="<?= e($inputType) ?>" id="edit_<?= e($name) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="submit" class="btn-a">Update</button>
            </div>
        </form>
    </div>
</div>

<div class="cm-bg" id="cmDeleteGenerated" data-cm-bg>
    <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmDeleteGeneratedTitle">
        <form method="post" id="formDeleteGenerated" action="<?= e(site_url($routePrefix . '/0/delete')) ?>">
            <?= raw(csrf_field()) ?>
            <div class="panel-head">
                <span class="panel-title" id="cmDeleteGeneratedTitle">Konfirmasi Hapus</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                Hapus data <strong id="delete_generated_label">-</strong>? Tindakan ini tidak bisa dibatalkan.
            </div>
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
    window.generatedCrudConfig = {
        datatableUrl: <?= json_encode(site_url($routePrefix . '/datatable'), JSON_UNESCAPED_UNICODE) ?>,
        languageUrl: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>,
        displayColumns: <?= json_encode($displayColumnsJs, JSON_UNESCAPED_UNICODE) ?>,
        routePrefix: <?= json_encode($routePrefixJs, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?= raw(module_script('Kategori/js/kategori.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
