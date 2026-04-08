<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,array<string,mixed>> $cartItems */
/** @var array<int,array<string,mixed>> $barangOptions */
/** @var array<int,array<string,mixed>> $jasaOptions */
/** @var array<int,array<string,mixed>> $frequentBarangOptions */
/** @var array<int,array<string,mixed>> $frequentJasaOptions */
/** @var array<int,array<string,mixed>> $pelangganOptions */
/** @var array<int,array<string,mixed>> $holdRows */
/** @var array<string,int> $summary */
/** @var array<int,string> $paymentMethods */
/** @var int $activeHoldId */
/** @var array<string,mixed> $lastReceipt */

$mediaUrl = static function ($rawPath): string {
    $path = trim((string) $rawPath);
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:image/')) {
        return $path;
    }
    if (str_starts_with($path, 'filemanager/')) {
        return '/media?path=' . rawurlencode($path);
    }
    return '';
};

$activeHoldCode = '';
if ((int) ($activeHoldId ?? 0) > 0) {
    foreach ($holdRows as $holdRow) {
        if (!is_array($holdRow)) {
            continue;
        }
        if ((int) ($holdRow['id'] ?? 0) === (int) $activeHoldId) {
            $activeHoldCode = (string) ($holdRow['hold_code'] ?? '');
            break;
        }
    }
}
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Penjualan'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'transaksi-penjualan'])) ?>

<main class="main" id="mainContent">

    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Penjualan') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Fitur untuk Mengelola <?= e($title ?? 'Penjualan') ?></p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => $title ?? 'Penjualan',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="keu-tab-wrap mb-3 anim">
        <a class="keu-tab-link is-active" href="#" data-sales-tab="transaksi"><i class="bi bi-cart-check"></i><span>Transaksi</span></a>
        <a class="keu-tab-link" href="#" data-sales-tab="history"><i class="bi bi-clock-history"></i><span>History</span></a>
    </div>

    <div data-sales-pane="transaksi">
        <div id="holdSection">
            <?php if ($holdRows !== []): ?>
                <section class="anim mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-pause-circle" style="color:var(--accent);font-size:15px;"></i>
                        <span style="font-size:13px;font-weight:700;color:var(--text-primary);">Transaksi Ditahan</span>
                        <span class="sbadge wrn"><?= count($holdRows) ?></span>
                    </div>
                    <div class="hold-scroll">
                        <?php foreach ($holdRows as $row): ?>
                            <?php if (!is_array($row)) {
                                continue;
                            } ?>
                            <div class="hold-card" data-hold-id="<?= e((string) ($row['id'] ?? '0')) ?>" data-hold-code="<?= e((string) ($row['hold_code'] ?? '-')) ?>" onclick="openResumeHoldModal(this)">
                                <div class="hold-card-top">
                                    <span class="hold-card-code"><?= e((string) ($row['hold_code'] ?? '-')) ?></span>
                                    <div class="hold-card-tools">
                                        <?php if (!empty($row['payment_method'])): ?>
                                            <span class="sbadge inf" style="font-size:10px;padding:3px 6px;"><?= e((string) $row['payment_method']) ?></span>
                                        <?php endif; ?>
                                        <button type="button" class="hold-card-close" title="Hapus hold" onclick="event.stopPropagation(); posDeleteHold(<?= e((string) ($row['id'] ?? '0')) ?>, this);">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if (!empty($row['catatan'])): ?>
                                    <div class="hold-card-note"><?= e((string) $row['catatan']) ?></div>
                                <?php endif; ?>
                                <div class="hold-card-bottom">
                                    <span class="hold-card-time"><i class="bi bi-clock"></i> <?= e((string) ($row['created_at'] ?? '-')) ?></span>
                                    <span class="hold-card-action"><i class="bi bi-play-fill"></i> Lanjutkan</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="pos-grid anim" id="posMain">
            <div class="pos-cart">
                <div class="panel pos-cart-panel">
                    <div class="panel-head">
                        <div class="d-flex align-items-center gap-2">
                            <span class="panel-title"><i class="bi bi-cart3"></i> Keranjang Aktif</span>
                            <span id="transactionModeBadge"
                                class="sbadge <?= (int) ($activeHoldId ?? 0) > 0 ? 'inf' : 'scc' ?>"
                                data-mode="<?= (int) ($activeHoldId ?? 0) > 0 ? 'hold' : 'new' ?>">
                                <?= (int) ($activeHoldId ?? 0) > 0 ? e('Hold: ' . ($activeHoldCode !== '' ? $activeHoldCode : ('#' . (string) ((int) $activeHoldId)))) : 'Transaksi Baru' ?>
                            </span>
                        </div>
                        <form method="post" action="/transaksi/penjualan/cart/clear" data-pos-ajax data-pos-msg="Keranjang dikosongkan">
                            <?= raw(csrf_field()) ?>
                            <button type="submit" class="btn-g btn-sm"><i class="bi bi-trash3"></i> Kosongkan</button>
                        </form>
                    </div>
                    <div class="panel-body pos-cart-body" style="padding:0;">
                        <div class="table-responsive" style="overflow-x:visible;">
                            <table class="dtable w-100" style="table-layout:fixed;">
                                <thead>
                                    <tr>
                                        <th style="width:auto;">Item</th>
                                        <th style="width:105px;text-align:right;">Harga</th>
                                        <th style="width:80px;text-align:center;">Qty</th>
                                        <th style="width:115px;text-align:right;">Total</th>
                                        <th style="width:42px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cartTableBody">
                                    <?php if ($cartItems === []): ?>
                                        <tr>
                                            <td colspan="5" class="pos-empty-cell">
                                                <div class="pos-empty-state">
                                                    <i class="bi bi-cart-x"></i>
                                                    <p>Keranjang masih kosong</p>
                                                    <p class="pos-empty-hint">Pilih barang atau jasa untuk memulai transaksi</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cartItems as $idx => $item): ?>
                                            <?php if (!is_array($item)) {
                                                continue;
                                            } ?>
                                            <?php
                                            $qty = max(1, (int) ((string) ($item['jumlah'] ?? '1')));
                                            $harga = max(0, (int) ((string) ($item['jual'] ?? '0')));
                                            $diskon = max(0, (int) ((string) ($item['diskon'] ?? '0')));
                                            $hargaSetelahDiskon = max(0, $harga - $diskon);
                                            $lineTotal = max(0, ($harga - $diskon) * $qty);
                                            $itemType = (string) ($item['item_type'] ?? 'barang');
                                            ?>
                                            <tr class="pos-cart-row" data-cart-id="<?= e((string) ($item['id'] ?? '0')) ?>">
                                                <td>
                                                    <div class="pos-item-info">
                                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                            <span class="sbadge <?= $itemType === 'jasa' ? 'inf' : 'wrn' ?>"><?= e(ucfirst($itemType)) ?></span>
                                                            <span class="pos-item-name"><?= e((string) ($item['nama_barang'] ?? '-')) ?></span>
                                                        </div>
                                                        <?php if ($diskon > 0): ?>
                                                            <span class="pos-item-disc"><i class="bi bi-tag"></i> Disc: <?= e(format_currency_id($diskon)) ?>/item</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td style="text-align:right;font-size:13px;color:var(--text-secondary);">
                                                    <?php if ($diskon > 0): ?>
                                                        <div style="text-decoration:line-through;opacity:.65;"><?= e(format_currency_id($harga)) ?></div>
                                                        <div style="font-weight:700;color:var(--accent);"><?= e(format_currency_id($hargaSetelahDiskon)) ?></div>
                                                    <?php else: ?>
                                                        <?= e(format_currency_id($harga)) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:center;">
                                                    <form method="post" action="/transaksi/penjualan/cart/update" class="pos-qty-form">
                                                        <?= raw(csrf_field()) ?>
                                                        <input type="hidden" name="cart_id" value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                                        <input class="fi pos-qty-input" type="number" name="qty" min="0" value="<?= e((string) $qty) ?>" required>
                                                    </form>
                                                </td>
                                                <td style="text-align:right;font-weight:700;font-size:13px;"><?= e(format_currency_id($lineTotal)) ?></td>
                                                <td>
                                                    <form method="post" action="/transaksi/penjualan/cart/remove" data-pos-ajax data-pos-msg="Item dihapus dari keranjang">
                                                        <?= raw(csrf_field()) ?>
                                                        <input type="hidden" name="cart_id" value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                                        <button type="submit" class="pos-del-btn" title="Hapus item"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="pos-cart-summary" id="cartSummary">
                        <div class="pos-summary-row">
                            <span>Total Qty</span>
                            <span id="summaryQty"><?= e((string) ((int) ((string) ($summary['qty'] ?? '0')))) ?></span>
                        </div>
                        <div class="pos-summary-row">
                            <span>Subtotal</span>
                            <span id="summarySubtotal"><?= e(format_currency_id((int) ((string) ($summary['subtotal'] ?? '0')))) ?></span>
                        </div>
                        <div class="pos-summary-row">
                            <span>Diskon</span>
                            <span id="summaryDiskon" style="color:var(--danger);"><?= e(format_currency_id((int) ((string) ($summary['diskon'] ?? '0')))) ?></span>
                        </div>
                        <div class="pos-summary-row grand">
                            <span>Grand Total</span>
                            <span id="summaryGrandTotal" data-value="<?= e((string) ((int) ((string) ($summary['grand_total'] ?? '0')))) ?>"><?= e(format_currency_id((int) ((string) ($summary['grand_total'] ?? '0')))) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pos-actions">
                <div class="pos-tabs">
                    <button class="pos-tab active" data-tab="barang" onclick="switchTab('barang')">
                        <i class="bi bi-box-seam"></i> Item
                    </button>
                    <button class="pos-tab" data-tab="checkout" onclick="switchTab('checkout')">
                        <i class="bi bi-credit-card"></i> Bayar
                    </button>
                </div>

                <div class="panel p-3 pos-tab-panel active" id="tabBarang">
                    <form method="post" action="/transaksi/penjualan/cart/add" data-pos-ajax data-pos-msg="Barang ditambahkan ke keranjang">
                        <?= raw(csrf_field()) ?>
                        <input type="hidden" name="item_type" value="barang">
                        <div class="fg">
                            <label class="fl" for="selBarang">Pilih item</label>
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <button type="button" class="btn-a" data-cm-open="cmAddBarang">
                                    <i class="bi bi-plus-circle"></i><span>Tambah <?= e($barang ?? 'Barang') ?></span>
                                </button>
                                <button type="button" class="btn-g" data-cm-open="cmAddJasa">
                                    <i class="bi bi-plus-circle me-1"></i><span>Tambah <?= e($jasa ?? 'Jasa') ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="sb-midline anim" aria-hidden="true">
                            <span class="sb-midline-bar"></span>
                            <span class="sb-midline-text">Produk yang Sering Dibeli</span>
                            <span class="sb-midline-bar"></span>
                        </div>
                        <div class="pos-add-hint small"><i class="bi bi-info-circle"></i> Pilih Barang/Jasa</div>
                    </form>
                </div>

                <div class="panel p-3 pos-tab-panel" id="tabCheckout">
                    <div class="pos-checkout-total">
                        <div class="pos-checkout-total-label">Total Pembayaran</div>
                        <div class="pos-checkout-total-value" id="checkoutDisplayTotal"><?= e(format_currency_id((int) ((string) ($summary['grand_total'] ?? '0')))) ?></div>
                    </div>
                    <form method="post" action="/transaksi/penjualan/checkout" data-pos-checkout id="formCheckout">
                        <?= raw(csrf_field()) ?>
                        <input type="hidden" id="activeHoldIdInput" name="active_hold_id" value="<?= e((string) ((int) ($activeHoldId ?? 0))) ?>" data-hold-code="<?= e($activeHoldCode) ?>">
                        <div class="fg">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label class="fl" for="checkoutPelanggan" style="margin-bottom:0;">Pelanggan</label>
                                <a href="javascript:void(0)" class="hold-card-action" data-cm-open="cmQuickPelanggan">
                                    <i class="bi bi-plus me-1"></i>Tambah Pelanggan
                                </a>
                            </div>
                            <select class="fi" id="checkoutPelanggan" name="id_pelanggan">
                                <option value="0">Umum / Non Member</option>
                                <?php foreach ($pelangganOptions as $item): ?>
                                    <?php if (!is_array($item)) {
                                        continue;
                                    } ?>
                                    <option value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                        <?= e((string) ($item['nama_pelanggan'] ?? '-')) ?> (<?= e((string) ($item['kode_pelanggan'] ?? '-')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fg">
                            <label class="fl" for="checkoutMethod">Metode Pembayaran</label>
                            <select class="fi" id="checkoutMethod" name="payment_method" required>
                                <option value="">— Pilih Metode —</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= e((string) $method) ?>"><?= e((string) $method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fg">
                            <label class="fl" for="checkoutBayar">Nominal Bayar</label>
                            <input class="fi" id="checkoutBayar" type="number" min="1" name="bayar" value="<?= e((string) ((int) ((string) ($summary['grand_total'] ?? '0')))) ?>" required placeholder="Masukkan nominal" style="font-size:15px;font-weight:700;">
                        </div>
                        <div id="kembalianBox" class="pos-kembalian-row pos-nol" style="display:none;">
                            <span>Kembalian</span>
                            <span id="kembalianValue">Rp 0</span>
                        </div>
                        <div class="fg">
                            <label class="fl" for="checkoutKeterangan">Keterangan</label>
                            <input class="fi" id="checkoutKeterangan" type="text" name="keterangan" placeholder="Catatan transaksi (opsional)">
                        </div>

                        <div class="pos-btn-row">
                            <button type="submit" class="btn-a" data-pos-checkout-btn><i class="bi bi-check2-circle"></i> Proses Checkout</button>
                        </div>
                    </form>

                    <button type="button" class="pos-btn-hold" onclick="openHoldModal()">
                        <i class="bi bi-pause-circle"></i> Tahan Transaksi Ini
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="panel anim" data-sales-pane="history" style="display:none;">
        <div class="panel-head">
            <span class="panel-title"><i class="bi bi-clock-history"></i> Histori Penjualan Harian</span>
            <button type="button" class="btn-g btn-sm" data-sales-history-refresh><i class="bi bi-arrow-repeat"></i> Refresh</button>
        </div>
        <div class="panel-body">
            <div class="small text-muted mb-2">Menampilkan riwayat transaksi penjualan per hari.</div>
            <div id="salesHistoryContainer">
                <div class="text-muted small">Memuat histori...</div>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="resumeHoldModal" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="resumeHoldModalTitle">
            <div class="panel-head">
                <span class="panel-title" id="resumeHoldModalTitle"><i class="bi bi-play-circle me-1"></i> Lanjutkan Transaksi Ditahan</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <p style="font-size:12.5px;color:var(--text-secondary);margin:0 0 12px;">Pilih transaksi hold yang ingin dilanjutkan ke keranjang aktif.</p>
                <div class="pos-kembalian-row pos-nol" style="display:flex;">
                    <span>Kode Hold</span>
                    <span id="resumeHoldCodeText">-</span>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="button" class="btn-a" id="resumeHoldConfirmBtn"><i class="bi bi-play-fill"></i> Lanjutkan</button>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmQuickPelanggan" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmQuickPelangganTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmQuickPelangganTitle"><i class="bi bi-person-plus me-1"></i> Tambah Pelanggan Cepat</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <form method="post" action="/transaksi/penjualan/pelanggan/quick" data-pos-ajax data-pos-msg="Pelanggan baru berhasil ditambahkan" id="formQuickPelanggan">
                    <?= raw(csrf_field()) ?>
                    <div class="fg">
                        <label class="fl" for="quickNamaPelanggan">Nama Pelanggan</label>
                        <input class="fi" id="quickNamaPelanggan" type="text" name="nama_pelanggan" placeholder="Masukkan nama pelanggan" required>
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickTelpPelanggan">Telepon (opsional)</label>
                        <input class="fi" id="quickTelpPelanggan" type="text" name="telepon_pelanggan" placeholder="Masukkan nomor telepon">
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickAlamatPelanggan">Alamat (opsional)</label>
                        <input class="fi" id="quickAlamatPelanggan" type="text" name="alamat_pelanggan" placeholder="Masukkan alamat pelanggan">
                    </div>
                    <div class="fg" style="margin-bottom:0;">
                        <label class="fl" for="quickEmailPelanggan">Email (opsional)</label>
                        <input class="fi" id="quickEmailPelanggan" type="email" name="email_pelanggan" placeholder="Masukkan email pelanggan">
                    </div>
                    <div class="cm-foot" style="padding:12px 0 0;border-top:none;">
                        <button type="button" class="btn-g" data-cm-close>Batal</button>
                        <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i> Simpan Pelanggan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="holdModal">
        <div class="panel cm-box">
            <div class="panel-head">
                <span class="panel-title"><i class="bi bi-pause-circle" style="color:var(--accent);"></i> Tahan Transaksi</span>
                <button class="cm-x" onclick="closeHoldModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <p style="font-size:12.5px;color:var(--text-secondary);margin:0 0 14px;">Transaksi aktif akan disimpan dan dapat dilanjutkan kembali nanti.</p>
                <form method="post" action="/transaksi/penjualan/hold" data-pos-ajax data-pos-msg="Transaksi berhasil ditahan" id="formHoldModal" class="hold-modal-form">
                    <?= raw(csrf_field()) ?>
                    <div class="fg">
                        <label class="fl" for="holdPelanggan">Pelanggan (opsional)</label>
                        <select class="fi" id="holdPelanggan" name="id_pelanggan">
                            <option value="0">Umum / Non Member</option>
                            <?php foreach ($pelangganOptions as $item): ?>
                                <?php if (!is_array($item)) {
                                    continue;
                                } ?>
                                <option value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                    <?= e((string) ($item['nama_pelanggan'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl" for="holdMethod">Metode Pembayaran (opsional)</label>
                        <select class="fi" id="holdMethod" name="payment_method">
                            <option value="">Belum dipilih</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= e((string) $method) ?>"><?= e((string) $method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl" for="holdCatatan">Catatan</label>
                        <input class="fi" id="holdCatatan" type="text" name="catatan" placeholder="Catatan hold (opsional)">
                    </div>
                    <div style="margin-top:6px;">
                        <button type="submit" class="btn-a" style="width:100%;justify-content:center;"><i class="bi bi-pause-circle"></i> Tahan Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmAddBarang" data-cm-bg>
        <div class="panel cm-box cm-box-lg pos-pick-modal" role="dialog" aria-modal="true" aria-labelledby="cmAddBarangTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmAddBarangTitle"><i class="bi bi-box-seam me-2"></i> Pilih <?= e($barang ?? 'Barang') ?></span>
                <button type="button" class="cm-x" data-cm-close><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="pos-pick-toolbar">
                    <label class="pos-pick-checkall">
                        <input type="checkbox" class="pos-pick-checkall-input">
                        <span>Pilih semua</span>
                    </label>
                    <div class="pos-pick-view">
                        <button type="button" class="btn-g btn-sm is-active" data-pick-view="grid"><i class="bi bi-grid-3x2-gap"></i> Grid</button>
                        <button type="button" class="btn-g btn-sm" data-pick-view="list"><i class="bi bi-list-ul"></i> List</button>
                        <button type="button" class="btn-g btn-sm" data-cm-open="cmAddJasa">
                            <i class="bi bi-arrow-left-right me-1"></i><span>Pindah <?= e($jasa ?? 'Jasa') ?></span>
                        </button>
                    </div>
                </div>
                <div class="pos-pick-search mb-2" data-pick-search-wrap>
                    <label class="fl pos-pick-label" style="margin-bottom:6px;">Pencarian Produk</label>
                    <div class="pos-pick-search-box" data-pick-search-box>
                        <i class="bi bi-search pos-pick-search-icon" data-pick-search-icon aria-hidden="true"></i>
                        <div class="pos-pick-search-loading" data-pick-loading style="display:none;">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span>Memuat...</span>
                        </div>
                        <input type="text" class="fi pos-pick-search-input" data-pick-search placeholder="Cari nama / kode produk..." autocomplete="off">
                    </div>
                </div>
                <div class="pos-pick-items pos-pick-grid" data-pick-items>
                    <?php foreach ($barangOptions as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <?php
                        $itemId = max(0, (int) ($item['id'] ?? 0));
                        if ($itemId <= 0) {
                            continue;
                        }
                        $itemName = (string) ($item['nama_barang'] ?? '-');
                        $itemCode = (string) ($item['id_barang'] ?? '-');
                        $itemPrice = max(0, (int) ($item['harga_jual'] ?? 0));
                        $itemStock = max(0, (int) ($item['stok'] ?? 0));
                        $itemImage = $mediaUrl($item['gambar_path'] ?? $item['gambar'] ?? '');
                        $itemDiskon = max(0, (int) ($item['diskon_aktif'] ?? 0));
                        $itemPriceAfter = max(0, (int) ($item['harga_jual_diskon'] ?? $itemPrice));
                        ?>
                        <article class="pos-pick-card" data-pick-item data-item-type="barang" data-item-id="<?= e((string) $itemId) ?>" data-pick-keywords="<?= e(strtolower(trim($itemName . ' ' . $itemCode))) ?>">
                            <label class="pos-pick-select">
                                <input type="checkbox" class="pos-pick-check" data-pick-check <?= $itemStock > 0 ? '' : 'disabled' ?>>
                                <span>Pilih</span>
                            </label>
                            <?php if ($itemDiskon > 0): ?>
                                <span class="pos-diskon-badge"><i class="bi bi-tag-fill"></i> Diskon</span>
                            <?php endif; ?>
                            <div class="pos-pick-cover">
                                <?php if ($itemImage !== ''): ?>
                                    <img src="<?= e($itemImage) ?>" alt="<?= e($itemName) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="bi bi-box-seam"></i>
                                <?php endif; ?>
                                <div class="pos-pick-cover-overlay"></div>
                            </div>
                            <div class="pos-pick-main">
                                <div class="pos-pick-name"><?= e($itemName) ?></div>
                                <div class="pos-pick-meta"><?= e($itemCode) ?></div>
                                <?php if ($itemDiskon > 0): ?>
                                    <div class="pos-pick-price-wrap">
                                        <span class="pos-pick-price-strike"><?= e(format_currency_id($itemPrice)) ?></span>
                                        <span class="pos-pick-price pos-pick-price-diskon"><?= e(format_currency_id($itemPriceAfter)) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="pos-pick-price"><?= e(format_currency_id($itemPrice)) ?></div>
                                <?php endif; ?>
                                <span class="sbadge <?= $itemStock > 0 ? 'scc' : 'wrn' ?>">Stok: <?= e((string) $itemStock) ?></span>
                            </div>
                            <div class="pos-pick-qty">
                                <span>Qty</span>
                                <input type="number" class="fi pos-pick-qty-input" data-pick-qty min="1" value="1" <?= $itemStock > 0 ? 'max="' . e((string) $itemStock) . '"' : 'disabled' ?>>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="pos-pick-empty" data-pick-empty hidden>Belum ada item terpilih.</p>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="button" class="btn-a" data-pick-submit data-item-type="barang"><i class="bi bi-cart-plus"></i> Tambah Terpilih</button>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmAddJasa" data-cm-bg>
        <div class="panel cm-box cm-box-lg pos-pick-modal" role="dialog" aria-modal="true" aria-labelledby="cmAddJasaTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmAddJasaTitle"><i class="bi bi-tools me-2"></i> Pilih <?= e($jasa ?? 'Jasa') ?></span>
                <button type="button" class="cm-x" data-cm-close><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="pos-pick-toolbar">
                    <label class="pos-pick-checkall">
                        <input type="checkbox" class="pos-pick-checkall-input">
                        <span>Pilih semua</span>
                    </label>
                    <div class="pos-pick-view">
                        <button type="button" class="btn-g btn-sm is-active" data-pick-view="grid"><i class="bi bi-grid-3x2-gap"></i> Grid</button>
                        <button type="button" class="btn-g btn-sm" data-pick-view="list"><i class="bi bi-list-ul"></i> List</button>
                        <button type="button" class="btn-g btn-sm" data-cm-open="cmAddBarang">
                            <i class="bi bi-arrow-left-right me-1"></i><span>Pindah <?= e($barang ?? 'Barang') ?></span>
                        </button>
                    </div>
                </div>
                <div class="pos-pick-search mb-2" data-pick-search-wrap>
                    <label class="fl pos-pick-label" style="margin-bottom:6px;">Pencarian Produk</label>
                    <div class="pos-pick-search-box" data-pick-search-box>
                        <i class="bi bi-search pos-pick-search-icon" data-pick-search-icon aria-hidden="true"></i>
                        <div class="pos-pick-search-loading" data-pick-loading style="display:none;">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span>Memuat...</span>
                        </div>
                        <input type="text" class="fi pos-pick-search-input" data-pick-search placeholder="Cari nama / kode produk..." autocomplete="off">
                    </div>
                </div>
                <div class="pos-pick-items pos-pick-grid" data-pick-items>
                    <?php foreach ($jasaOptions as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <?php
                        $itemId = max(0, (int) ($item['id'] ?? 0));
                        if ($itemId <= 0) {
                            continue;
                        }
                        $itemName = (string) ($item['nama'] ?? '-');
                        $itemCode = (string) ($item['id_jasa'] ?? '-');
                        $itemPrice = max(0, (int) ($item['harga'] ?? 0));
                        $itemImage = $mediaUrl($item['gambar_path'] ?? $item['gambar_img'] ?? '');
                        ?>
                        <article class="pos-pick-card" data-pick-item data-item-type="jasa" data-item-id="<?= e((string) $itemId) ?>" data-pick-keywords="<?= e(strtolower(trim($itemName . ' ' . $itemCode))) ?>">
                            <label class="pos-pick-select">
                                <input type="checkbox" class="pos-pick-check" data-pick-check>
                                <span>Pilih</span>
                            </label>
                            <div class="pos-pick-cover">
                                <?php if ($itemImage !== ''): ?>
                                    <img src="<?= e($itemImage) ?>" alt="<?= e($itemName) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="bi bi-tools"></i>
                                <?php endif; ?>
                                <div class="pos-pick-cover-overlay"></div>
                            </div>
                            <div class="pos-pick-main">
                                <div class="pos-pick-name"><?= e($itemName) ?></div>
                                <div class="pos-pick-meta"><?= e($itemCode) ?></div>
                                <div class="pos-pick-price"><?= e(format_currency_id($itemPrice)) ?></div>
                                <span class="sbadge inf">Jasa</span>
                            </div>
                            <div class="pos-pick-qty">
                                <span>Qty</span>
                                <input type="number" class="fi pos-pick-qty-input" data-pick-qty min="1" value="1">
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="pos-pick-empty" data-pick-empty hidden>Belum ada item terpilih.</p>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="button" class="btn-a" data-pick-submit data-item-type="jasa"><i class="bi bi-cart-plus"></i> Tambah Terpilih</button>
            </div>
        </div>
    </div>
    <div class="cm-bg" id="cmReceiptPreview" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmReceiptPreviewTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmReceiptPreviewTitle"><i class="bi bi-receipt-cutoff me-1"></i> Preview Nota Penjualan</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body" style="padding:10px;">
                <iframe id="receiptPreviewFrame" title="Preview Nota" style="width:100%;height:65vh;border:1px solid var(--line);border-radius:10px;background:#fff;"></iframe>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="button" class="btn-a" data-receipt-print><i class="bi bi-printer"></i> Cetak</button>
            </div>
        </div>
    </div>

</main>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
<style>
    .pos-diskon-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 3;
        background: #fff1d6;
        color: #c76a00;
        border: 1px solid #ffd28a;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .pos-pick-price-wrap {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-top: 2px;
    }

    .pos-pick-price-strike {
        color: #9a9a9a;
        font-size: 12px;
        text-decoration: line-through;
        text-decoration-thickness: 1.5px;
    }

    .pos-pick-price-diskon {
        color: var(--accent);
        font-weight: 800;
    }

    .pos-pick-search .pos-pick-search-box {
        position: relative;
    }

    .pos-pick-search .pos-pick-search-input {
        padding-left: 38px;
    }

    .pos-pick-search .pos-pick-search-icon,
    .pos-pick-search .pos-pick-search-loading {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
    }

    .pos-pick-search .pos-pick-search-icon {
        color: var(--text-secondary);
        font-size: 14px;
        pointer-events: none;
    }

    .pos-pick-search .pos-pick-search-loading {
        display: none;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: var(--text-secondary);
        pointer-events: none;
        white-space: nowrap;
    }

    .pos-pick-search.is-loading .pos-pick-search-icon {
        display: none;
    }

    .pos-pick-search.is-loading .pos-pick-search-loading {
        display: inline-flex !important;
    }

    .pos-pick-search.is-loading .pos-pick-search-input {
        padding-left: 112px;
    }
</style>

<script>
    window.posPenjualanConfig = <?= json_encode([
                                    'lastReceipt' => is_array($lastReceipt ?? null) ? $lastReceipt : [],
                                ], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script type="application/json" id="posLastReceiptData">
    <?= raw(json_encode(is_array($lastReceipt ?? null) ? $lastReceipt : [], JSON_UNESCAPED_UNICODE)) ?>
</script>
<script>
    (function() {
        'use strict';
        var salesHistoryLoaded = false;

        function formatRp(n) {
            n = parseInt(n) || 0;
            return 'Rp ' + n.toLocaleString('id-ID');
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /* ===== TOAST — pakai CSS bawaan .toast-wrap .toast-i .toast-icon .toast-msg ===== */

        var toastCfg = {
            success: {
                icon: 'bi-check-circle-fill',
                bg: 'var(--success-light)',
                color: 'var(--success)'
            },
            error: {
                icon: 'bi-x-circle-fill',
                bg: 'var(--danger-light)',
                color: 'var(--danger)'
            },
            warning: {
                icon: 'bi-exclamation-triangle-fill',
                bg: 'var(--accent-light)',
                color: 'var(--accent)'
            },
            info: {
                icon: 'bi-info-circle-fill',
                bg: 'var(--info-light)',
                color: 'var(--info)'
            }
        };

        function posToast(type, title, msg) {
            var c = toastCfg[type] || toastCfg.info;
            var wrap = document.querySelector('.toast-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'toast-wrap';
                document.body.appendChild(wrap);
            }
            var el = document.createElement('div');
            el.className = 'toast-i';
            el.innerHTML =
                '<div class="toast-icon" style="background:' + c.bg + ';color:' + c.color + ';"><i class="bi ' + c.icon + '"></i></div>' +
                '<div class="toast-msg"><strong>' + title + '</strong><br>' + msg + '</div>';
            wrap.appendChild(el);
            setTimeout(function() {
                el.classList.add('out');
                el.addEventListener('animationend', function() {
                    el.remove();
                }, {
                    once: true
                });
            }, 3200);
        }

        function setBtnLoading(btn, on, text) {
            if (!btn) return;
            if (on) {
                btn._posOrig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i> ';
            } else {
                btn.disabled = false;
                btn.innerHTML = btn._posOrig || btn.innerHTML;
                delete btn._posOrig;
            }
        }

        function closeAllPopups(exceptId) {
            document.querySelectorAll('.cm-bg.show').forEach(function(modal) {
                if (exceptId && modal.id === exceptId) return;
                modal.classList.remove('show');
            });
            if (!document.querySelector('.cm-bg.show')) {
                document.body.style.overflow = '';
            }
        }

        function openModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            closeAllPopups(id);
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(el) {
            var modal = el instanceof HTMLElement && el.classList.contains('cm-bg') ? el : (el instanceof HTMLElement ? el.closest('.cm-bg') : null);
            if (!modal) return;
            modal.classList.remove('show');
            if (!document.querySelector('.cm-bg.show')) {
                document.body.style.overflow = '';
            }
        }

        function formatDateLabel(raw) {
            var txt = String(raw || '').trim();
            if (!txt) return '-';
            var d = new Date(txt + 'T00:00:00');
            if (Number.isNaN(d.getTime())) return txt;
            return d.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });
        }

        function formatDateTimeLabel(raw) {
            var txt = String(raw || '').trim();
            if (!txt) return '-';
            var normalized = txt.replace(' ', 'T');
            var d = new Date(normalized);
            if (Number.isNaN(d.getTime())) return txt;
            return d.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function setSalesTab(tab) {
            var next = tab === 'history' ? 'history' : 'transaksi';
            document.querySelectorAll('[data-sales-tab]').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-sales-tab') === next);
            });
            document.querySelectorAll('[data-sales-pane]').forEach(function(pane) {
                pane.style.display = pane.getAttribute('data-sales-pane') === next ? '' : 'none';
            });
            if (next === 'history' && !salesHistoryLoaded) {
                loadSalesHistory(true);
            }
        }

        function renderSalesHistory(payload) {
            var container = document.getElementById('salesHistoryContainer');
            if (!container) return;
            var groups = payload && Array.isArray(payload.groups) ? payload.groups : [];
            var totalTrx = payload && payload.total_transactions ? parseInt(payload.total_transactions, 10) || 0 : 0;
            var periodLabel = payload && payload.period_label ? String(payload.period_label) : formatDateLabel(new Date().toISOString().slice(0, 10));
            var totalNominal = payload && payload.total_nominal ? parseInt(payload.total_nominal, 10) || 0 : 0;
            var summaryHtml = '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">' +
                '<span class="sbadge scc border rounded-4 small">' + '<i class="bi bi-cart-check"></i> ' + totalTrx + ' Trx <i class="bi bi-calendar-check"></i> ' + escapeHtml(formatDateLabel(periodLabel)) + '</span>' +
                '<span class="sbadge scc border rounded-4 small">' + 'Total: ' + escapeHtml(formatRp(totalNominal)) + '</span>' +
                '</div>';
            if (!Array.isArray(groups) || groups.length === 0) {
                container.innerHTML = summaryHtml + '<div class="text-muted small">Belum ada histori penjualan.</div>';
                return;
            }
            var allRows = [];
            groups.forEach(function(group) {
                var rows = Array.isArray(group.rows) ? group.rows : [];
                rows.forEach(function(row) {
                    allRows.push(row);
                });
            });
            var tableRows = allRows.map(function(row, idx) {
                var noTrx = escapeHtml(String(row.no_trx || '-'));
                return '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><strong>' + noTrx + '</strong><br><span class="small text-muted">' + escapeHtml(formatDateTimeLabel(row.created_at || row.tanggal_input || '')) + '</span></td>' +
                    '<td>' + escapeHtml(String(row.pelanggan || 'Umum / Non Member')) + '</td>' +
                    '<td>' + escapeHtml(String(row.payment_method || '-')) + '</td>' +
                    '<td class="text-end">' + formatRp(row.total || 0) + '</td>' +
                    '<td><button type="button" class="btn-g btn-sm" data-sales-history-reprint="' + noTrx + '"><i class="bi bi-printer"></i></button></td>' +
                    '</tr>';
            }).join('');
            container.innerHTML = summaryHtml +
                '<div class="table-responsive" style="overflow-x:auto;">' +
                '<table class="dtable w-100">' +
                '<thead><tr><th style="width:56px;">No</th><th>Transaksi</th><th>Pelanggan</th><th>Metode</th><th style="width:130px;">Total</th><th style="width:150px;">Aksi</th></tr></thead>' +
                '<tbody>' + tableRows + '</tbody>' +
                '</table></div>';
        }

        async function loadSalesHistory(force) {
            var container = document.getElementById('salesHistoryContainer');
            if (!container) return;
            if (!force && salesHistoryLoaded) return;
            container.innerHTML = '<div class="text-muted small"><i class="bi bi-arrow-repeat spin-icon"></i> Memuat histori...</div>';
            try {
                var resp = await fetch('/transaksi/penjualan/history/daily', {
                    method: 'GET'
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var payload = await resp.json();
                renderSalesHistory(payload || {});
                salesHistoryLoaded = true;
            } catch (e) {
                container.innerHTML = '<div class="text-danger small">Gagal memuat histori penjualan.</div>';
            }
        }

        async function reprintSalesFromHistory(noTrx) {
            var key = String(noTrx || '').trim();
            if (key === '') return;
            try {
                var resp = await fetch('/transaksi/penjualan/receipt?no_trx=' + encodeURIComponent(key), {
                    method: 'GET'
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var payload = await resp.json();
                var data = payload && payload.data ? payload.data : null;
                if (!data || !data.no_trx) {
                    throw new Error('invalid');
                }
                showReceiptPreviewModal(data);
            } catch (e) {
                posToast('error', 'Gagal', 'Data nota tidak ditemukan untuk cetak ulang.');
            }
        }

        /* ===== CORE: UPDATE DOM ===== */

        function updateCsrf(doc) {
            var t = doc.querySelector('input[name="_token"]');
            if (t) document.querySelectorAll('input[name="_token"]').forEach(function(el) {
                el.value = t.value;
            });
        }

        function updatePosFromHtml(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            updateCsrf(doc);
            var nc = doc.getElementById('cartTableBody');
            var ns = doc.getElementById('cartSummary');
            var nh = doc.getElementById('holdSection');
            if (nc) document.getElementById('cartTableBody').innerHTML = nc.innerHTML;
            if (ns) document.getElementById('cartSummary').innerHTML = ns.innerHTML;
            if (nh) document.getElementById('holdSection').innerHTML = nh.innerHTML;
            var incomingHoldInput = doc.querySelector('#formCheckout input[name="active_hold_id"]');
            var currentHoldInput = document.querySelector('#formCheckout input[name="active_hold_id"]');
            if (incomingHoldInput && currentHoldInput) {
                currentHoldInput.value = incomingHoldInput.value || '0';
                currentHoldInput.setAttribute('data-hold-code', incomingHoldInput.getAttribute('data-hold-code') || '');
            }
            var gt = document.getElementById('summaryGrandTotal');
            if (gt) {
                var val = gt.getAttribute('data-value') || '0';
                var dt = document.getElementById('checkoutDisplayTotal');
                if (dt) dt.textContent = formatRp(val);
                var bi = document.getElementById('checkoutBayar');
                if (bi && !bi._userEdited) bi.value = val;
                triggerKembalian();
            }
            var receipt = extractReceiptFromDoc(doc);
            if (receipt && receipt.no_trx) {
                if (!window.posPenjualanConfig) window.posPenjualanConfig = {};
                window.posPenjualanConfig.lastReceipt = receipt;
            }
            syncTransactionModeBadge();
            initQtyForms();
        }

        function extractPosState(doc) {
            var qtyEl = doc.querySelector('#summaryQty');
            var grandEl = doc.querySelector('#summaryGrandTotal');
            return {
                cartRows: doc.querySelectorAll('#cartTableBody tr[data-cart-id]').length,
                qty: parseInt(qtyEl ? qtyEl.textContent : '0', 10) || 0,
                grandTotal: parseInt(grandEl ? (grandEl.getAttribute('data-value') || '0') : '0', 10) || 0
            };
        }

        function extractServerToasts(doc) {
            var script = doc.querySelector('script');
            var matched = null;
            doc.querySelectorAll('script').forEach(function(sc) {
                var txt = String(sc.textContent || '');
                if (txt.indexOf('window.__APP_TOASTS') !== -1) {
                    matched = txt;
                }
            });
            if (!matched) return [];
            var m = matched.match(/window\.__APP_TOASTS\s*=\s*(\[[\s\S]*?\]);/);
            if (!m || !m[1]) return [];
            try {
                var parsed = JSON.parse(m[1]);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function extractReceiptFromDoc(doc) {
            var jsonNode = doc.getElementById('posLastReceiptData');
            if (jsonNode) {
                try {
                    var fromJsonNode = JSON.parse(String(jsonNode.textContent || '{}'));
                    if (fromJsonNode && fromJsonNode.no_trx) {
                        return fromJsonNode;
                    }
                } catch (e) {}
            }

            var matched = null;
            doc.querySelectorAll('script').forEach(function(sc) {
                var txt = String(sc.textContent || '');
                if (txt.indexOf('window.posPenjualanConfig') !== -1 && txt.indexOf('lastReceipt') !== -1) {
                    matched = txt;
                }
            });
            if (!matched) return null;
            var m = matched.match(/window\.posPenjualanConfig\s*=\s*(\{[\s\S]*?\});/);
            if (!m || !m[1]) return null;
            try {
                var parsed = JSON.parse(m[1]);
                if (parsed && parsed.lastReceipt && parsed.lastReceipt.no_trx) {
                    return parsed.lastReceipt;
                }
            } catch (e) {
                // fallback: parse lastReceipt object directly if assignment JSON parse failed
                var mReceipt = matched.match(/"lastReceipt"\s*:\s*(\{[\s\S]*\})/);
                if (mReceipt && mReceipt[1]) {
                    try {
                        var parsedReceipt = JSON.parse(mReceipt[1]);
                        if (parsedReceipt && parsedReceipt.no_trx) {
                            return parsedReceipt;
                        }
                    } catch (err) {}
                }
                return null;
            }
            return null;
        }

        function hasServerErrorToast(toasts) {
            var state = analyzeServerToasts(toasts);
            return state.hasError || (state.hasWarning && !state.hasSuccess);
        }

        function firstServerToast(toasts) {
            if (!Array.isArray(toasts) || toasts.length === 0) return null;
            return toasts[0] || null;
        }

        function analyzeServerToasts(toasts) {
            var state = {
                hasSuccess: false,
                hasWarning: false,
                hasError: false,
                firstSuccess: null,
                firstError: null
            };
            if (!Array.isArray(toasts) || toasts.length === 0) return state;
            toasts.forEach(function(t) {
                var type = String(t && t.type ? t.type : '').toLowerCase();
                if (type === 'success') {
                    state.hasSuccess = true;
                    if (!state.firstSuccess) state.firstSuccess = t;
                    return;
                }
                if (type === 'warning') {
                    state.hasWarning = true;
                    if (!state.firstError) state.firstError = t;
                    return;
                }
                if (type === 'error') {
                    state.hasError = true;
                    if (!state.firstError) state.firstError = t;
                }
            });
            return state;
        }

        function buildReceiptHtml(data, autoPrint) {
            if (!data || !data.no_trx) return;

            function asText(value, fallback) {
                if (value == null) return fallback || '-';
                if (typeof value === 'object') {
                    if (Array.isArray(value)) {
                        return value.length > 0 ? asText(value[0], fallback) : (fallback || '-');
                    }
                    if (Object.prototype.hasOwnProperty.call(value, 'value')) {
                        return asText(value.value, fallback);
                    }
                    if (Object.prototype.hasOwnProperty.call(value, 'message')) {
                        return asText(value.message, fallback);
                    }
                    try {
                        return JSON.stringify(value);
                    } catch (e) {
                        return fallback || '-';
                    }
                }
                var txt = String(value).trim();
                return txt === '' ? (fallback || '-') : txt;
            }

            var items = Array.isArray(data.items) ? data.items : [];
            var itemRows = items.map(function(it) {
                var name = escapeHtml(asText(it && it.name ? it.name : '-', '-'));
                var qty = parseInt(it && it.qty ? it.qty : '0', 10) || 0;
                var price = parseInt(it && it.jual ? it.jual : '0', 10) || 0;
                var total = parseInt(it && it.total ? it.total : '0', 10) || 0;
                return '<tr><td style="padding:2px 0;">' + name + '<br><span style="color:#666;">' + qty + ' x ' + formatRp(price) + '</span></td><td style="padding:2px 0;text-align:right;vertical-align:top;">' + formatRp(total) + '</td></tr>';
            }).join('');

            var pageCss = autoPrint ?
                '@page{size:58mm auto;margin:2mm;} html,body{margin:0;padding:0;background:#fff;color:#000;font-family:monospace;font-size:11px;line-height:1.35;} .wrap{width:54mm;padding:2mm;margin:0;}' :
                'html,body{margin:0;padding:0;color:#111;font-family:monospace;font-size:12px;line-height:1.4;} .wrap{width:min(100%,520px);box-sizing:border-box;padding:12px 14px;margin:10px auto;background:#fff;}';

            var html = '' +
                '<!doctype html><html><head><meta charset="utf-8"><title>Struk ' + escapeHtml(asText(data.no_trx, '-')) + '</title>' +
                '<style>' +
                pageCss +
                '.center{text-align:center;} .right{text-align:right;} .line{border-top:1px dashed #000;margin:6px 0;}' +
                'table{width:100%;border-collapse:collapse;} td{vertical-align:top;} .meta td{padding:1px 0;} .tot td{padding:1px 0;}' +
                '.title{font-size:13px;font-weight:700;} .small{font-size:10px;color:#333;}' +
                '</style></head><body><div class="wrap">' +
                '<div class="center"><div class="title">' + escapeHtml('' + <?= json_encode(toko('nama_toko', 'LintasPos'), JSON_UNESCAPED_UNICODE) ?> + '') + '</div>' +
                '<div class="small">' + escapeHtml('' + <?= json_encode(toko('alamat_toko', ''), JSON_UNESCAPED_UNICODE) ?> + '') + '</div>' +
                '<div class="small">' + escapeHtml('' + <?= json_encode(toko('tlp', ''), JSON_UNESCAPED_UNICODE) ?> + '') + '</div></div>' +
                '<div class="line"></div>' +
                '<table class="meta">' +
                '<tr><td>No</td><td class="right">' + escapeHtml(asText(data.no_trx, '-')) + '</td></tr>' +
                '<tr><td>Tgl</td><td class="right">' + escapeHtml(asText(data.tanggal, '-')) + '</td></tr>' +
                '<tr><td>Kasir</td><td class="right">' + escapeHtml(asText(data.kasir, '-')) + '</td></tr>' +
                '<tr><td>Cust</td><td class="right">' + escapeHtml(asText(data.pelanggan, 'Umum')) + '</td></tr>' +
                '<tr><td>Metode</td><td class="right">' + escapeHtml(asText(data.payment_method, '-')) + '</td></tr>' +
                '</table>' +
                '<div class="line"></div>' +
                '<table>' + itemRows + '</table>' +
                '<div class="line"></div>' +
                '<table class="tot">' +
                '<tr><td>Total</td><td class="right">' + formatRp(data.total || 0) + '</td></tr>' +
                '<tr><td>Bayar</td><td class="right">' + formatRp(data.bayar || 0) + '</td></tr>' +
                '<tr><td>Kembali</td><td class="right">' + formatRp(data.kembalian || 0) + '</td></tr>' +
                '</table>' +
                (asText(data.keterangan, '') !== '' ? '<div class="line"></div><div>Keterangan:<br>' + escapeHtml(asText(data.keterangan, '')) + '</div>' : '') +
                '<div class="line"></div><div class="center">Terima kasih</div>' +
                '</div>' +
                (autoPrint ? '<script>window.onload=function(){window.print();};<\/script>' : '') +
                '</body></html>';

            return html;
        }

        function openReceiptPopup(data, popupRef) {
            if (!data || !data.no_trx) return;
            var html = buildReceiptHtml(data, true);
            if (!html) return;
            var popup = popupRef && !popupRef.closed ?
                popupRef :
                window.open('', 'pos_receipt_' + String(data.no_trx), 'width=420,height=760');
            if (!popup) return;
            popup.document.open();
            popup.document.write(html);
            popup.document.close();
        }

        function showReceiptPreviewModal(data) {
            if (!data || !data.no_trx) return;
            if (!window.posPenjualanConfig) window.posPenjualanConfig = {};
            window.posPenjualanConfig.previewReceipt = data;
            var frame = document.getElementById('receiptPreviewFrame');
            if (frame) {
                var html = buildReceiptHtml(data, false);
                if (html) frame.srcdoc = html;
            }
            openModal('cmReceiptPreview');
        }

        function syncTransactionModeBadge() {
            var badge = document.getElementById('transactionModeBadge');
            var holdInput = document.getElementById('activeHoldIdInput');
            if (!badge || !holdInput) return;
            var holdId = parseInt(holdInput.value || '0', 10) || 0;
            var holdCode = holdInput.getAttribute('data-hold-code') || '';
            badge.classList.remove('scc', 'inf');
            if (holdId > 0) {
                badge.classList.add('inf');
                badge.setAttribute('data-mode', 'hold');
                badge.textContent = 'Lanjut Hold: ' + (holdCode !== '' ? holdCode : ('#' + holdId));
                return;
            }
            badge.classList.add('scc');
            badge.setAttribute('data-mode', 'new');
            badge.textContent = 'Transaksi Baru';
        }

        /* ===== CORE: AJAX SUBMIT ===== */

        async function posAjaxSubmit(form, msg, btn) {
            setBtnLoading(btn, true);
            try {
                var beforeState = extractPosState(document);
                var fd = new FormData(form);
                var resp = await fetch(form.action, {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var afterState = extractPosState(doc);
                var serverToasts = extractServerToasts(doc);
                var toastState = analyzeServerToasts(serverToasts);
                updatePosFromHtml(html);
                if (toastState.hasError || (toastState.hasWarning && !toastState.hasSuccess)) {
                    var toastErr = toastState.firstError || firstServerToast(serverToasts);
                    posToast((toastErr && toastErr.type) ? toastErr.type : 'error', 'Gagal', (toastErr && toastErr.message) ? toastErr.message : 'Proses gagal.');
                    setBtnLoading(btn, false);
                    return;
                }
                if (afterState.qty <= beforeState.qty && form.action.indexOf('/cart/add') !== -1 && !toastState.hasSuccess) {
                    posToast('error', 'Gagal', 'Item tidak berhasil masuk ke keranjang.');
                    setBtnLoading(btn, false);
                    return;
                }
                var toastOk = toastState.firstSuccess || firstServerToast(serverToasts);
                if (toastOk && toastOk.message) {
                    posToast((toastOk.type || 'success'), 'Berhasil', toastOk.message);
                } else {
                    posToast('success', 'Berhasil', msg);
                }
                if (form.querySelector('select[name="item_id"]')) {
                    form.querySelector('select[name="item_id"]').value = '';
                    var qi = form.querySelector('input[name="qty"]');
                    if (qi) qi.value = '1';
                    form.querySelector('select[name="item_id"]').focus();
                }
                if (form.id === 'formHoldModal') {
                    form.reset();
                    closeHoldModal();
                }
                if (form.id === 'formQuickPelanggan') {
                    form.reset();
                    closeModal(document.getElementById('cmQuickPelanggan'));
                }
            } catch (e) {
                posToast('error', 'Gagal', 'Terjadi kesalahan. Coba lagi.');
            }
            setBtnLoading(btn, false);
        }

        async function posDeleteHold(holdId, btnEl) {
            if (!holdId) return;
            var card = btnEl ? btnEl.closest('.hold-card') : null;
            if (card) card.classList.add('hold-loading');
            try {
                var token = document.querySelector('input[name="_token"]');
                var fd = new FormData();
                if (token) fd.append('_token', token.value);
                fd.append('hold_id', String(holdId));
                var resp = await fetch('/transaksi/penjualan/hold/delete', {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                updatePosFromHtml(html);
                posToast('success', 'Berhasil', 'Data hold dihapus.');
            } catch (e) {
                if (card) card.classList.remove('hold-loading');
                posToast('error', 'Gagal', 'Gagal menghapus data hold.');
            }
        }

        function updatePickerState(modal) {
            if (!modal) return;
            var checks = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-check]'));
            var enabledChecks = checks.filter(function(chk) {
                return !chk.disabled;
            });
            var checked = checks.filter(function(chk) {
                return chk.checked;
            }).length;
            var allBox = modal.querySelector('.pos-pick-checkall-input');
            if (allBox) {
                allBox.checked = enabledChecks.length > 0 && checked === enabledChecks.length;
                allBox.indeterminate = checked > 0 && checked < enabledChecks.length;
                allBox.disabled = enabledChecks.length === 0;
            }
            var emptyHint = modal.querySelector('[data-pick-empty]');
            if (emptyHint) emptyHint.hidden = checked !== 0;
        }

        function setPickerView(modal, view) {
            if (!modal) return;
            var itemsWrap = modal.querySelector('[data-pick-items]');
            if (!itemsWrap) return;
            itemsWrap.classList.toggle('pos-pick-grid', view === 'grid');
            itemsWrap.classList.toggle('pos-pick-list', view === 'list');
            modal.querySelectorAll('[data-pick-view]').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-pick-view') === view);
            });
        }

        function applyPickerSearch(modal, query) {
            if (!modal) return;
            var term = String(query || '').trim().toLowerCase();
            var items = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-item]'));
            items.forEach(function(row) {
                var keywords = String(row.getAttribute('data-pick-keywords') || '').toLowerCase();
                var visible = term === '' || keywords.indexOf(term) !== -1;
                row.hidden = !visible;
                if (!visible) {
                    var check = row.querySelector('[data-pick-check]');
                    if (check) check.checked = false;
                }
            });
            updatePickerState(modal);
        }

        function initPickerSearch(modal) {
            if (!modal) return;
            var wrap = modal.querySelector('[data-pick-search-wrap]');
            if (!wrap) return;
            var input = wrap.querySelector('[data-pick-search]');
            var loading = wrap.querySelector('[data-pick-loading]');
            if (!input || input._pickSearchInit) return;
            input._pickSearchInit = true;
            setSearchLoading(wrap, false, loading);

            input.addEventListener('input', function() {
                var self = this;
                setSearchLoading(wrap, true, loading);
                if (self._pickSearchTimer) {
                    clearTimeout(self._pickSearchTimer);
                }
                self._pickSearchTimer = setTimeout(function() {
                    applyPickerSearch(modal, self.value || '');
                    setSearchLoading(wrap, false, loading);
                }, 250);
            });
        }

        function setSearchLoading(wrap, isLoading, loadingEl) {
            if (!wrap) return;
            wrap.classList.toggle('is-loading', !!isLoading);
            if (loadingEl) {
                loadingEl.style.display = isLoading ? 'inline-flex' : 'none';
            }
        }

        function findModalByItemType(itemType) {
            if (itemType === 'jasa') return document.getElementById('cmAddJasa');
            return document.getElementById('cmAddBarang');
        }

        function markPickerItem(itemType, itemId, qty, openTarget) {
            var modal = findModalByItemType(itemType);
            if (!modal) return;
            var row = modal.querySelector('[data-pick-item][data-item-type="' + itemType + '"][data-item-id="' + itemId + '"]');
            if (!row) return;
            var check = row.querySelector('[data-pick-check]');
            var qtyInput = row.querySelector('[data-pick-qty]');
            if (qtyInput && !qtyInput.disabled) {
                var max = parseInt(qtyInput.getAttribute('max') || '0', 10);
                var val = Math.max(1, parseInt(String(qty || 1), 10) || 1);
                if (max > 0 && val > max) val = max;
                qtyInput.value = String(val);
            }
            if (check && !check.disabled) {
                check.checked = true;
            }
            updatePickerState(modal);
            if (openTarget) openModal(modal.id);
        }

        async function addSelectedFromPicker(modal, btn) {
            if (!modal) return;
            var rows = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-item]')).filter(function(row) {
                var check = row.querySelector('[data-pick-check]');
                return !!check && !check.disabled && check.checked;
            });
            if (rows.length === 0) {
                posToast('warning', 'Perhatian', 'Pilih minimal satu item.');
                return;
            }

            setBtnLoading(btn, true);
            var token = document.querySelector('input[name="_token"]');
            var tokenVal = token ? token.value : '';
            var okCount = 0;
            var failedCount = 0;
            var failMessages = [];

            for (var i = 0; i < rows.length; i += 1) {
                var row = rows[i];
                var check = row.querySelector('[data-pick-check]');
                if (!check || check.disabled || !check.checked) {
                    continue;
                }
                var itemType = row.getAttribute('data-item-type') || '';
                var itemId = row.getAttribute('data-item-id') || '0';
                var qtyInput = row.querySelector('[data-pick-qty]');
                var qty = qtyInput ? Math.max(1, parseInt(qtyInput.value || '1', 10) || 1) : 1;
                var max = qtyInput ? parseInt(qtyInput.getAttribute('max') || '0', 10) : 0;
                if (max > 0 && qty > max) qty = max;

                if (!itemType || parseInt(itemId, 10) <= 0) {
                    failedCount += 1;
                    failMessages.push('Data item tidak valid.');
                    continue;
                }

                if (qtyInput && qtyInput.disabled) {
                    failedCount += 1;
                    failMessages.push('Stok item habis.');
                    continue;
                }

                if (itemType === 'barang' && max <= 0) {
                    failedCount += 1;
                    failMessages.push('Stok barang habis.');
                    continue;
                }

                if (qtyInput) qtyInput.value = String(qty);

                var fd = new FormData();
                if (tokenVal !== '') fd.append('_token', tokenVal);
                fd.append('item_type', itemType);
                fd.append('item_id', itemId);
                fd.append('qty', String(qty));

                try {
                    var beforeState = extractPosState(document);
                    var resp = await fetch('/transaksi/penjualan/cart/add', {
                        method: 'POST',
                        body: fd
                    });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    var html = await resp.text();
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var afterState = extractPosState(doc);
                    var serverToasts = extractServerToasts(doc);
                    var toastState = analyzeServerToasts(serverToasts);
                    updatePosFromHtml(html);

                    var newToken = document.querySelector('input[name="_token"]');
                    if (newToken && newToken.value) tokenVal = newToken.value;

                    if (afterState.qty > beforeState.qty || (toastState.hasSuccess && !toastState.hasError)) {
                        okCount += 1;
                    } else {
                        failedCount += 1;
                        if (toastState.firstError && toastState.firstError.message) {
                            failMessages.push(String(toastState.firstError.message));
                        } else {
                            failMessages.push('Item gagal ditambahkan ke keranjang.');
                        }
                    }
                } catch (e) {
                    failedCount += 1;
                    failMessages.push('Terjadi kesalahan saat menambah item.');
                }
            }

            if (okCount > 0) {
                rows.forEach(function(row) {
                    var check = row.querySelector('[data-pick-check]');
                    var qtyInput = row.querySelector('[data-pick-qty]');
                    if (check) check.checked = false;
                    if (qtyInput && !qtyInput.disabled) qtyInput.value = '1';
                });
                updatePickerState(modal);
                closeModal(modal);
            }

            if (okCount > 0 && failedCount === 0) {
                posToast('success', 'Berhasil', okCount + ' item ditambahkan ke keranjang.');
            } else if (okCount > 0 && failedCount > 0) {
                posToast('warning', 'Sebagian Berhasil', okCount + ' item masuk, ' + failedCount + ' item gagal.');
            } else {
                posToast('error', 'Gagal', failMessages.length > 0 ? failMessages[0] : 'Item gagal ditambahkan ke keranjang.');
            }

            setBtnLoading(btn, false);
        }

        /* ===== CORE: QTY UPDATE ===== */

        function initQtyForms() {
            document.querySelectorAll('.pos-qty-form').forEach(function(form) {
                if (form._posInit) return;
                form._posInit = true;
                var input = form.querySelector('.pos-qty-input');
                if (!input) return;
                var lastVal = input.value;
                var isSubmitting = false;

                function submitIfChanged() {
                    if (isSubmitting) return;
                    var nv = parseInt(input.value, 10);
                    if (Number.isNaN(nv) || nv < 0) {
                        input.value = lastVal;
                        return;
                    }
                    if (input.value === lastVal) return;
                    isSubmitting = true;
                    handleQtySubmit(form, input).finally(function() {
                        isSubmitting = false;
                    });
                    lastVal = input.value;
                }

                input.addEventListener('focus', function() {
                    lastVal = this.value;
                });
                input.addEventListener('blur', function() {
                    submitIfChanged();
                });
                input.addEventListener('change', function() {
                    submitIfChanged();
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitIfChanged();
                    }
                });
            });
        }

        async function handleQtySubmit(form, input) {
            var orig = input.value;
            input.classList.add('pos-loading');
            try {
                var fd = new FormData(form);
                var resp = await fetch(form.action, {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                updatePosFromHtml(html);
                posToast('success', 'Diperbarui', 'Jumlah item diperbarui');
            } catch (e) {
                input.value = orig;
                posToast('error', 'Gagal', 'Gagal memperbarui jumlah');
            }
            input.classList.remove('pos-loading');
        }

        /* ===== CORE: CHECKOUT ===== */

        async function posCheckout(form, btn) {
            var paymentMethod = document.getElementById('checkoutMethod');
            var bayarInput = document.getElementById('checkoutBayar');
            var cartRowsBefore = document.querySelectorAll('#cartTableBody tr[data-cart-id]').length;
            var grandEl = document.getElementById('summaryGrandTotal');
            var grandBefore = parseInt(grandEl ? (grandEl.getAttribute('data-value') || '0') : '0', 10) || 0;

            if (cartRowsBefore < 1 || grandBefore <= 0) {
                posToast('warning', 'Perhatian', 'Keranjang masih kosong. Tambahkan barang/jasa terlebih dahulu.');
                switchTab('barang');
                return;
            }
            if (!paymentMethod || String(paymentMethod.value || '').trim() === '') {
                posToast('warning', 'Perhatian', 'Metode pembayaran wajib dipilih.');
                switchTab('checkout');
                if (paymentMethod) paymentMethod.focus();
                return;
            }
            if (!bayarInput || (parseInt(bayarInput.value || '0', 10) || 0) <= 0) {
                posToast('warning', 'Perhatian', 'Nominal bayar harus lebih dari 0.');
                switchTab('checkout');
                if (bayarInput) bayarInput.focus();
                return;
            }

            setBtnLoading(btn, true, 'Memproses...');
            try {
                var fd = new FormData(form);
                var resp = await fetch(form.action, {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                if (html.indexOf('cartTableBody') !== -1) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var serverToasts = extractServerToasts(doc);
                    updatePosFromHtml(html);
                    var cartRowsAfter = doc.querySelectorAll('#cartTableBody tr[data-cart-id]').length;
                    var okToast = serverToasts.some(function(t) {
                        return String((t && t.type) || '').toLowerCase() === 'success';
                    });
                    var hardErrorToast = serverToasts.some(function(t) {
                        var type = String((t && t.type) || '').toLowerCase();
                        return type === 'error';
                    });
                    if (cartRowsBefore > 0 && cartRowsAfter === 0 && (okToast || !hardErrorToast)) {
                        var toastOk = firstServerToast(serverToasts);
                        posToast('success', 'Berhasil', (toastOk && toastOk.message) ? toastOk.message : 'Transaksi berhasil diproses');
                        form.reset();
                        switchTab('barang');
                        if (window.posPenjualanConfig && window.posPenjualanConfig.lastReceipt && window.posPenjualanConfig.lastReceipt.no_trx) {
                            showReceiptPreviewModal(window.posPenjualanConfig.lastReceipt);
                            window.posPenjualanConfig.lastReceipt = null;
                        }
                    } else {
                        var toastErr = firstServerToast(serverToasts);
                        posToast((toastErr && toastErr.type) ? toastErr.type : 'warning', 'Checkout Ditolak', (toastErr && toastErr.message) ? toastErr.message : 'Checkout gagal diproses.');
                    }
                } else {
                    document.open();
                    document.write(html);
                    document.close();
                }
            } catch (e) {
                posToast('error', 'Gagal', 'Terjadi kesalahan saat checkout');
            }
            setBtnLoading(btn, false);
        }

        /* ===== CORE: HOLD RESUME ===== */

        async function posResumeHold(holdId, cardEl) {
            if (!holdId) return;
            var card = cardEl instanceof HTMLElement ? cardEl : document.querySelector('.hold-card[data-hold-id="' + String(holdId) + '"]');
            if (card) card.classList.add('hold-loading');
            var token = document.querySelector('input[name="_token"]');
            var fd = new FormData();
            if (token) fd.append('_token', token.value);
            fd.append('hold_id', holdId);
            try {
                var resp = await fetch('/transaksi/penjualan/hold/resume', {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                updatePosFromHtml(html);
                var currentHoldInput = document.getElementById('activeHoldIdInput');
                if (card && currentHoldInput) {
                    var code = card.getAttribute('data-hold-code') || '';
                    if (code !== '') currentHoldInput.setAttribute('data-hold-code', code);
                }
                syncTransactionModeBadge();
                initQtyForms();
                initKembalian();
                initAddFormEnterKeys();
                closeModal(document.getElementById('resumeHoldModal'));
                posToast('success', 'Dilanjutkan', 'Transaksi ditahan berhasil dipulihkan');
            } catch (e) {
                posToast('error', 'Gagal', 'Gagal memulihkan transaksi');
            } finally {
                if (card) card.classList.remove('hold-loading');
            }
        }

        /* ===== TABS ===== */

        function switchTab(name) {
            document.querySelectorAll('.pos-tab').forEach(function(t) {
                t.classList.toggle('active', t.getAttribute('data-tab') === name);
            });
            document.querySelectorAll('.pos-tab-panel').forEach(function(p) {
                p.classList.toggle('active', p.id === 'tab' + name.charAt(0).toUpperCase() + name.slice(1));
            });
            var panel = document.getElementById('tab' + name.charAt(0).toUpperCase() + name.slice(1));
            if (panel) {
                var firstInput = panel.querySelector('select,input:not([type="hidden"])');
                if (firstInput) setTimeout(function() {
                    firstInput.focus();
                }, 100);
            }
        }

        /* ===== HOLD MODAL ===== */

        function openHoldModal() {
            document.getElementById('holdModal').classList.add('show');
        }

        function closeHoldModal() {
            document.getElementById('holdModal').classList.remove('show');
        }

        function openResumeHoldModal(cardEl) {
            if (!(cardEl instanceof HTMLElement)) return;
            var holdId = parseInt(cardEl.getAttribute('data-hold-id') || '0', 10);
            if (!holdId) return;
            var holdCode = cardEl.getAttribute('data-hold-code') || ('#' + holdId);
            var modal = document.getElementById('resumeHoldModal');
            if (!modal) return;
            modal.setAttribute('data-hold-id', String(holdId));
            var codeText = document.getElementById('resumeHoldCodeText');
            if (codeText) codeText.textContent = holdCode;
            openModal('resumeHoldModal');
        }

        /* ===== KEMBALIAN ===== */

        function triggerKembalian() {
            var bi = document.getElementById('checkoutBayar');
            var gt = document.getElementById('summaryGrandTotal');
            var box = document.getElementById('kembalianBox');
            var val = document.getElementById('kembalianValue');
            if (!bi || !gt || !box || !val) return;
            var bayar = parseInt(bi.value) || 0;
            var total = parseInt(gt.getAttribute('data-value')) || 0;
            var diff = bayar - total;
            box.style.display = 'flex';
            box.className = 'pos-kembalian-row';
            if (diff > 0) {
                box.classList.add('pos-lebih');
                val.textContent = formatRp(diff);
            } else if (diff < 0) {
                box.classList.add('pos-kurang');
                val.textContent = '- ' + formatRp(Math.abs(diff));
            } else {
                box.classList.add('pos-nol');
                val.textContent = 'Rp 0 — Pas';
            }
        }

        function initKembalian() {
            var bi = document.getElementById('checkoutBayar');
            if (!bi) return;
            bi._userEdited = false;
            bi.addEventListener('input', function() {
                this._userEdited = true;
                triggerKembalian();
            });
            triggerKembalian();
        }

        /* ===== ADD FORM ENTER ===== */

        function initAddFormEnterKeys() {
            document.querySelectorAll('#tabBarang form,#tabJasa form').forEach(function(form) {
                if (form._posEnterInit) return;
                form._posEnterInit = true;
                var sel = form.querySelector('select[name="item_id"]');
                var qty = form.querySelector('input[name="qty"]');
                if (!sel) return;
                sel.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (qty) qty.focus();
                        else form.dispatchEvent(new Event('submit', {
                            bubbles: true,
                            cancelable: true
                        }));
                    }
                });
            });
        }

        /* ===== FORM DELEGATION ===== */

        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (form.matches('[data-pos-ajax]')) {
                e.preventDefault();
                posAjaxSubmit(form, form.getAttribute('data-pos-msg') || 'Berhasil', form.querySelector('[type="submit"]'));
                return;
            }
            if (form.matches('[data-pos-checkout]')) {
                e.preventDefault();
                posCheckout(form, form.querySelector('[data-pos-checkout-btn]'));
                return;
            }
            if (form.matches('.pos-qty-form')) {
                e.preventDefault();
                handleQtySubmit(form, form.querySelector('.pos-qty-input'));
                return;
            }
        });

        document.addEventListener('click', function(e) {
            var salesTabBtn = e.target.closest('[data-sales-tab]');
            if (salesTabBtn) {
                e.preventDefault();
                setSalesTab(salesTabBtn.getAttribute('data-sales-tab') || 'transaksi');
                return;
            }

            var salesRefreshBtn = e.target.closest('[data-sales-history-refresh]');
            if (salesRefreshBtn) {
                e.preventDefault();
                loadSalesHistory(true);
                return;
            }

            var salesReprintBtn = e.target.closest('[data-sales-history-reprint]');
            if (salesReprintBtn) {
                e.preventDefault();
                reprintSalesFromHistory(salesReprintBtn.getAttribute('data-sales-history-reprint') || '');
                return;
            }

            var printBtn = e.target.closest('[data-receipt-print]');
            if (printBtn) {
                e.preventDefault();
                var data = window.posPenjualanConfig && window.posPenjualanConfig.previewReceipt ?
                    window.posPenjualanConfig.previewReceipt :
                    null;
                if (!data || !data.no_trx) {
                    posToast('warning', 'Perhatian', 'Data nota tidak tersedia.');
                    return;
                }
                openReceiptPopup(data);
                return;
            }

            var openBtn = e.target.closest('[data-cm-open]');
            if (openBtn) {
                e.preventDefault();
                var id = openBtn.getAttribute('data-cm-open') || '';
                if (id !== '') openModal(id);
                return;
            }

            var closeBtn = e.target.closest('[data-cm-close]');
            if (closeBtn) {
                closeModal(closeBtn);
                return;
            }

            var viewBtn = e.target.closest('[data-pick-view]');
            if (viewBtn) {
                var modal = viewBtn.closest('.cm-bg');
                setPickerView(modal, viewBtn.getAttribute('data-pick-view') || 'grid');
                return;
            }

            var submitBtn = e.target.closest('[data-pick-submit]');
            if (submitBtn) {
                addSelectedFromPicker(submitBtn.closest('.cm-bg'), submitBtn);
                return;
            }

            var quickBtn = e.target.closest('[data-quick-pick]');
            if (quickBtn) {
                var quickType = quickBtn.getAttribute('data-item-type') || 'barang';
                var quickId = quickBtn.getAttribute('data-item-id') || '0';
                if (parseInt(quickId, 10) > 0) {
                    markPickerItem(quickType, quickId, 1, true);
                }
                return;
            }

            var card = e.target.closest('[data-pick-item]');
            if (card) {
                if (e.target.closest('[data-pick-qty], [data-pick-check], .pos-pick-select, button, a, label')) return;
                var check = card.querySelector('[data-pick-check]');
                if (!check || check.disabled) return;
                check.checked = !check.checked;
                updatePickerState(card.closest('.cm-bg'));
            }
        });

        document.addEventListener('change', function(e) {
            var allBox = e.target.closest('.pos-pick-checkall-input');
            if (allBox) {
                var modal = allBox.closest('.cm-bg');
                if (!modal) return;
                modal.querySelectorAll('[data-pick-check]').forEach(function(chk) {
                    if (chk.disabled) return;
                    chk.checked = allBox.checked;
                });
                updatePickerState(modal);
                if (allBox.checked) {
                    closeModal(modal);
                }
                return;
            }

            var check = e.target.closest('[data-pick-check]');
            if (check) {
                updatePickerState(check.closest('.cm-bg'));
            }
        });

        document.querySelectorAll('[data-cm-bg]').forEach(function(bg) {
            bg.addEventListener('click', function(e) {
                if (e.target === bg) closeModal(bg);
            });
        });

        document.getElementById('holdModal').addEventListener('click', function(e) {
            if (e.target === this) closeHoldModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            closeAllPopups();
            closeHoldModal();
        });

        window.switchTab = switchTab;
        window.posResumeHold = posResumeHold;
        window.openResumeHoldModal = openResumeHoldModal;
        window.posDeleteHold = posDeleteHold;
        window.openHoldModal = openHoldModal;
        window.closeHoldModal = closeHoldModal;

        document.addEventListener('DOMContentLoaded', function() {
            initQtyForms();
            initKembalian();
            initAddFormEnterKeys();
            syncTransactionModeBadge();
            setSalesTab('transaksi');
            document.querySelectorAll('.pos-pick-qty-input').forEach(function(input) {
                input.addEventListener('input', function() {
                    var max = parseInt(this.getAttribute('max') || '0', 10);
                    var value = parseInt(this.value || '1', 10) || 1;
                    if (value < 1) value = 1;
                    if (max > 0 && value > max) value = max;
                    this.value = String(value);
                });
            });
            document.querySelectorAll('[data-cm-bg]').forEach(function(modal) {
                updatePickerState(modal);
                initPickerSearch(modal);
            });
            var resumeBtn = document.getElementById('resumeHoldConfirmBtn');
            if (resumeBtn) {
                resumeBtn.addEventListener('click', function() {
                    var modal = document.getElementById('resumeHoldModal');
                    var holdId = modal ? parseInt(modal.getAttribute('data-hold-id') || '0', 10) : 0;
                    if (!holdId) return;
                    posResumeHold(holdId, document.querySelector('.hold-card[data-hold-id="' + String(holdId) + '"]'));
                });
            }

            if (window.posPenjualanConfig && window.posPenjualanConfig.lastReceipt && window.posPenjualanConfig.lastReceipt.no_trx) {
                showReceiptPreviewModal(window.posPenjualanConfig.lastReceipt);
                window.posPenjualanConfig.lastReceipt = null;
            }
        });

    })();
</script>
