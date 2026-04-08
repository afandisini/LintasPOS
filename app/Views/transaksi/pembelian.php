<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,array<string,mixed>> $cartItems */
/** @var array<int,array<string,mixed>> $barangOptions */
/** @var array<int,array<string,mixed>> $supplierOptions */
/** @var array<string,int> $summary */
/** @var array<int,string> $paymentMethods */
/** @var bool $canViewHargaModal */
/** @var bool $canEditHargaModal */
/** @var bool $canManagePo */
/** @var int $saldoKas */

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
?>
<?php
$extraHead = raw('<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">');
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Pembelian', 'extraHead' => $extraHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'transaksi-pembelian'])) ?>

<main class="main" id="mainContent">

    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Pembelian') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small fw-light">Fitur untuk Mengelola <?= e($title ?? 'Pembelian') ?></p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => $title ?? 'Pembelian',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="keu-tab-wrap mb-3 anim">
        <a class="keu-tab-link is-active" href="#" data-purchase-tab="pembelian"><i class="bi bi-bag-check"></i><span>Pembelian</span></a>
        <a class="keu-tab-link" href="#" data-purchase-tab="po"><i class="bi bi-file-earmark-text"></i><span>PO (Purchase Order)</span></a>
        <a class="keu-tab-link" href="#" data-purchase-tab="history"><i class="bi bi-clock-history"></i><span>History</span></a>
    </div>

    <div class="pos-grid anim" id="purchaseMain" data-purchase-pane="pembelian">
        <div class="pos-cart">
            <div class="panel pos-cart-panel">
                <div class="panel-head">
                    <div class="d-flex align-items-center gap-2">
                        <span class="panel-title"><i class="bi bi-bag-check"></i> Keranjang Pembelian</span>
                        <span class="sbadge inf">Transaksi Baru</span>
                    </div>
                    <form method="post" action="/transaksi/pembelian/cart/clear" data-purchase-ajax data-msg="Keranjang pembelian dikosongkan">
                        <?= raw(csrf_field()) ?>
                        <button type="submit" class="btn-g btn-sm"><i class="bi bi-trash3"></i> Kosongkan</button>
                    </form>
                </div>
                <div class="panel-body pos-cart-body" style="padding:0;">
                    <div class="table-responsive" style="overflow-x:visible;">
                        <table class="dtable w-100" style="table-layout:fixed;">
                            <thead>
                                <tr>
                                    <th style="width:auto;">Barang</th>
                                    <th style="width:120px;text-align:right;">Harga Beli</th>
                                    <th style="width:80px;text-align:center;">Qty</th>
                                    <th style="width:120px;text-align:right;">Total</th>
                                    <th style="width:42px;"></th>
                                </tr>
                            </thead>
                            <tbody id="purchaseCartTableBody">
                                <?php if ($cartItems === []): ?>
                                    <tr>
                                        <td colspan="5" class="pos-empty-cell">
                                            <div class="pos-empty-state">
                                                <i class="bi bi-cart-x"></i>
                                                <p>Keranjang pembelian masih kosong</p>
                                                <p class="pos-empty-hint">Pilih barang untuk memulai transaksi pembelian</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cartItems as $item): ?>
                                        <?php if (!is_array($item)) {
                                            continue;
                                        } ?>
                                        <?php
                                        $qty = max(1, (int) ((string) ($item['jumlah'] ?? '1')));
                                        $harga = max(0, (int) ((string) ($item['beli'] ?? '0')));
                                        $lineTotal = $harga * $qty;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="pos-item-info">
                                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                        <span class="sbadge wrn">Barang</span>
                                                        <span class="pos-item-name"><?= e((string) ($item['nama_barang'] ?? '-')) ?></span>
                                                    </div>
                                                    <span class="pos-item-disc"><i class="bi bi-tag"></i> Harga Jual: <?= e(format_currency_id((int) ((string) ($item['jual'] ?? '0')))) ?></span>
                                                </div>
                                            </td>
                                            <td style="text-align:right;font-size:13px;color:var(--text-secondary);">
                                                <?php if ($canViewHargaModal ?? false): ?>
                                                    <?= e(format_currency_id($harga)) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Disembunyikan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <form method="post" action="/transaksi/pembelian/cart/update" class="pos-qty-form">
                                                    <?= raw(csrf_field()) ?>
                                                    <input type="hidden" name="cart_id" value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                                    <input class="fi pos-qty-input" type="number" name="qty" min="0" value="<?= e((string) $qty) ?>" required>
                                                </form>
                                            </td>
                                            <td style="text-align:right;font-weight:700;font-size:13px;"><?= e(format_currency_id($lineTotal)) ?></td>
                                            <td>
                                                <form method="post" action="/transaksi/pembelian/cart/remove" data-purchase-ajax data-msg="Item dihapus dari keranjang">
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
                <div class="pos-cart-summary" id="purchaseCartSummary">
                    <div class="pos-summary-row">
                        <span>Total Qty</span>
                        <span><?= e((string) ((int) ((string) ($summary['qty'] ?? '0')))) ?></span>
                    </div>
                    <div class="pos-summary-row grand">
                        <span>Total Pembelian</span>
                        <span id="purchaseGrandTotal" data-value="<?= e((string) ((int) ((string) ($summary['grand_total'] ?? '0')))) ?>"><?= e(format_currency_id((int) ((string) ($summary['grand_total'] ?? '0')))) ?></span>
                    </div>
                </div>
            </div>


        </div>

        <div class="pos-actions">
            <div class="panel p-3 pos-tab-panel active">
                <div class="mb-3 d-flex align-items-center justify-content-end">
                    <button type="button" class="btn-a" data-cm-open="cmAddPembelianBarang" style="width:100%;justify-content:center;">
                        <i class="bi bi-box-seam"></i><span>Pilih Barang</span>
                    </button>
                </div>
                <div class="fg">
                    <div class="pos-kembalian-row pos-nol pos-balance-row">
                        <span>Saldo Kas Saat Ini</span>
                        <span id="purchaseSaldoKas" data-saldo="<?= e((string) ((int) ($saldoKas ?? 0))) ?>"><?= e(format_currency_id((int) ($saldoKas ?? 0))) ?></span>
                    </div>
                    <div class="row">
                        <div class="col-sm-7">
                            <small class="text-muted small" id="purchaseSaldoInfo">Jika saldo kas tidak cukup, checkout otomatis menjadi PO.</small>
                        </div>
                        <div class="col-sm-5">
                            <button type="button" class="btn-g btn-sm pos-modal-quick-btn" data-cm-open="cmQuickModalPembelian"><i class="bi bi-plus-circle me-1"></i> Tambah Modal Cepat</button>
                        </div>
                    </div>
                </div>
                <div class="pos-checkout-total">
                    <div class="pos-checkout-total-label">Total Pembelian</div>
                    <div class="pos-checkout-total-value" id="purchaseDisplayTotal"><?= e(format_currency_id((int) ((string) ($summary['grand_total'] ?? '0')))) ?></div>
                </div>
                <form method="post" action="/transaksi/pembelian/checkout" id="formCheckoutPembelian">
                    <?= raw(csrf_field()) ?>

                    <div class="fg">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <label class="fl" for="purchaseSupplier" style="margin-bottom:0;">Supplier</label>
                            <a href="javascript:void(0)" class="hold-card-action" data-cm-open="cmQuickSupplier">
                                <i class="bi bi-plus me-1"></i>Tambah Supplier
                            </a>
                        </div>
                        <select class="fi" id="purchaseSupplier" name="supplier_id" required>
                            <option value="0">Supplier Umum</option>
                            <?php foreach ($supplierOptions as $item): ?>
                                <?php if (!is_array($item)) {
                                    continue;
                                } ?>
                                <option value="<?= e((string) ($item['id'] ?? '0')) ?>">
                                    <?= e((string) ($item['nama_supplier'] ?? '-')) ?>
                                    <?php if (!empty($item['telepon_supplier'])): ?>
                                        (<?= e((string) $item['telepon_supplier']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label class="fl" for="purchaseMethod">Metode Pembayaran</label>
                        <select class="fi" id="purchaseMethod" name="payment_method" required>
                            <option value="">- Pilih Metode -</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= e((string) $method) ?>"><?= e((string) $method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fg">
                        <label class="fl" for="purchaseBayar">Nominal Bayar</label>
                        <input class="fi" id="purchaseBayar" type="number" min="0" name="bayar" value="<?= e((string) ((int) ((string) ($summary['grand_total'] ?? '0')))) ?>" required placeholder="Isi 0 jika belum bayar (tempo)">
                    </div>

                    <div class="fg">
                        <label class="fl" for="purchaseKeterangan">Keterangan</label>
                        <input class="fi" id="purchaseKeterangan" type="text" name="keterangan" placeholder="Catatan pembelian (opsional)">
                    </div>

                    <div class="pos-btn-row">
                        <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i> Proses Checkout Pembelian</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="panel anim" data-purchase-pane="po" style="display:none;">
        <div class="panel-head">
            <span class="panel-title"><i class="bi bi-file-earmark-text"></i> Request PO</span>
            <span class="text-muted small">Daftar pengajuan PO pembelian</span>
        </div>
        <div class="panel-body">
            <div class="dt-wrap generated-dt-wrap">
                <table class="dtable w-100 nowrap" id="purchasePoTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No.Reg.PO</th>
                            <th>No.PO</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-muted">Memuat data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel anim" data-purchase-pane="history" style="display:none;">
        <div class="panel-head">
            <span class="panel-title"><i class="bi bi-clock-history"></i> Histori Pembelian Berhasil</span>
            <button type="button" class="btn-g btn-sm" data-purchase-history-refresh><i class="bi bi-arrow-repeat"></i> Refresh</button>
        </div>
        <div class="panel-body">
            <div class="small text-muted mb-2">Riwayat pembelian yang berhasil diproses. Transaksi gagal yang hanya masuk PO tidak ditampilkan.</div>
            <div id="purchaseHistoryContainer">
                <div class="text-muted small">Memuat histori...</div>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmAddPembelianBarang" data-cm-bg>
        <div class="panel cm-box cm-box-lg pos-pick-modal" role="dialog" aria-modal="true" aria-labelledby="cmAddPembelianBarangTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmAddPembelianBarangTitle"><i class="bi bi-box-seam me-2"></i> Pilih Barang Pembelian</span>
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
                        <input type="text" class="fi pos-pick-search-input" data-pick-search placeholder="Cari nama / kode barang..." autocomplete="off">
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
                        $itemPrice = max(0, (int) ($item['harga_beli'] ?? 0));
                        $itemJual = max(0, (int) ($item['harga_jual'] ?? 0));
                        $itemStock = max(0, (int) ($item['stok'] ?? 0));
                        $itemImage = $mediaUrl($item['gambar_path'] ?? $item['gambar'] ?? '');
                        ?>
                        <article class="pos-pick-card" data-pick-item data-item-id="<?= e((string) $itemId) ?>" data-pick-keywords="<?= e(strtolower(trim($itemName . ' ' . $itemCode))) ?>">
                            <label class="pos-pick-select">
                                <input type="checkbox" class="pos-pick-check" data-pick-check>
                                <span>Pilih</span>
                            </label>
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
                                <div class="pos-pick-price">Modal: <?= e(format_currency_id($itemPrice)) ?></div>
                                <div class="pos-pick-meta">Jual: <?= e(format_currency_id($itemJual)) ?></div>
                            </div>
                            <div class="pos-pick-pricing">
                                <div class="pos-pick-qty pos-pick-qty-main">
                                    <span>Qty</span>
                                    <input type="number" class="fi pos-pick-qty-input" data-pick-qty min="1" value="1">
                                </div>
                                <?php if ($canEditHargaModal ?? false): ?>
                                    <div class="pos-pick-qty">
                                        <input type="hidden" class="fi pos-pick-beli-input" data-pick-beli min="1" value="<?= e((string) $itemPrice) ?>">
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" data-pick-beli value="<?= e((string) $itemPrice) ?>">
                                <?php endif; ?>
                                <div class="pos-pick-qty">
                                    <input type="hidden" class="fi pos-pick-jual-input" data-pick-jual min="1" value="<?= e((string) $itemJual) ?>">
                                </div>
                            </div>
                            <div class="pos-pick-meta" style="font-size:11px;">Stok saat ini: <?= e((string) $itemStock) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="pos-pick-empty" data-pick-empty hidden>Belum ada item terpilih.</p>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Batal</button>
                <button type="button" class="btn-a" data-pick-submit><i class="bi bi-cart-plus"></i> Tambah Terpilih</button>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmQuickSupplier" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmQuickSupplierTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmQuickSupplierTitle"><i class="bi bi-truck me-1"></i> Tambah Supplier Cepat</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <form method="post" action="/transaksi/pembelian/supplier/quick" id="formQuickSupplier">
                    <?= raw(csrf_field()) ?>
                    <div class="fg">
                        <label class="fl" for="quickNamaSupplier">Nama Supplier</label>
                        <input class="fi" id="quickNamaSupplier" type="text" name="nama_supplier" placeholder="Masukkan nama supplier" required>
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickTelpSupplier">Telepon (opsional)</label>
                        <input class="fi" id="quickTelpSupplier" type="text" name="telepon_supplier" placeholder="Masukkan nomor telepon">
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickAlamatSupplier">Alamat (opsional)</label>
                        <input class="fi" id="quickAlamatSupplier" type="text" name="alamat_supplier" placeholder="Masukkan alamat supplier">
                    </div>
                    <div class="fg" style="margin-bottom:0;">
                        <label class="fl" for="quickEmailSupplier">Email/Kontak PIC (opsional)</label>
                        <input class="fi" id="quickEmailSupplier" type="text" name="email_supplier" placeholder="Masukkan email atau nama PIC">
                    </div>
                    <div class="cm-foot" style="padding:12px 0 0;border-top:none;">
                        <button type="button" class="btn-g" data-cm-close>Batal</button>
                        <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i> Simpan Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmQuickModalPembelian" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmQuickModalPembelianTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmQuickModalPembelianTitle"><i class="bi bi-cash-stack me-1"></i> Tambah Modal Cepat</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <form method="post" action="/transaksi/pembelian/modal/quick">
                    <?= raw(csrf_field()) ?>
                    <div class="fg">
                        <label class="fl" for="quickTanggalModal">Tanggal</label>
                        <input class="fi" id="quickTanggalModal" type="date" name="tanggal_modal" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickNominalModal">Nominal Modal</label>
                        <input class="fi" id="quickNominalModal" type="number" min="1" name="nominal_modal" placeholder="Masukkan nominal modal" required>
                    </div>
                    <div class="fg">
                        <label class="fl" for="quickKetModal">Keterangan</label>
                        <textarea class="fi" id="quickKetModal" name="keterangan_modal" placeholder="Contoh: Setoran modal pemilik" rows="3"></textarea>
                    </div>
                    <div class="cm-foot" style="padding:12px 0 0;border-top:none;">
                        <button type="button" class="btn-g" data-cm-close>Batal</button>
                        <button type="submit" class="btn-a"><i class="bi bi-check2-circle"></i> Simpan Modal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmPoView" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmPoViewTitle">
            <div class="panel-head">
                <span class="panel-title" id="cmPoViewTitle">Detail PO (Purchase Order)</span>
                <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="panel-body">
                <div class="fg"><label class="fw-bold">No.Reg.PO</label>
                    <div class="small fw-light" id="poViewReg">-</div>
                </div>
                <div class="fg"><label class="fw-bold">No.PO</label>
                    <div class="small fw-light" id="poViewNoTrx">-</div>
                </div>
                <div class="fg"><label class="fw-bold">Supplier</label>
                    <div class="small fw-light" id="poViewSupplier">-</div>
                </div>
                <div class="fg"><label class="fw-bold">Status</label>
                    <div class="small fw-light" id="poViewStatus">-</div>
                </div>
                <div class="fg"><label class="fw-bold">Keterangan</label>
                    <div class="small fw-light" id="poViewKet">-</div>
                </div>
                <div class="fg"><label class="fw-bold">Catatan Review</label>
                    <div class="small fw-light" id="poViewReview">-</div>
                </div>
            </div>
            <div class="cm-foot">
                <button type="button" class="btn-g" data-cm-close>Tutup</button>
            </div>
        </div>
    </div>

    <div class="cm-bg" id="cmPoEdit" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmPoEditTitle">
            <form method="post" action="/transaksi/pembelian/po/update">
                <?= raw(csrf_field()) ?>
                <input type="hidden" name="po_id" id="poEditId" value="0">
                <div class="panel-head">
                    <span class="panel-title" id="cmPoEditTitle">Edit PO</span>
                    <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="panel-body">
                    <div class="fg">
                        <label class="fl" for="poEditAction">Status</label>
                        <select class="fi" id="poEditAction" name="po_action" required>
                            <option value="diterima">Diterima</option>
                            <option value="ditolak">Ditolak</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl" for="poEditNote">Keterangan Review</label>
                        <textarea class="fi" id="poEditNote" name="po_note" rows="3" placeholder="Catatan review PO"></textarea>
                    </div>
                </div>
                <div class="cm-foot">
                    <button type="button" class="btn-g" data-cm-close>Batal</button>
                    <button type="submit" class="btn-a">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cm-bg" id="cmPoDelete" data-cm-bg>
        <div class="panel cm-box" role="dialog" aria-modal="true" aria-labelledby="cmPoDeleteTitle">
            <form method="post" action="/transaksi/pembelian/po/delete">
                <?= raw(csrf_field()) ?>
                <input type="hidden" name="po_id" id="poDeleteId" value="0">
                <div class="panel-head">
                    <span class="panel-title" id="cmPoDeleteTitle">Hapus PO</span>
                    <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="panel-body">Hapus request PO <strong id="poDeleteLabel">-</strong>? Data akan disembunyikan (soft delete).</div>
                <div class="cm-foot">
                    <button type="button" class="btn-g" data-cm-close>Batal</button>
                    <button type="submit" class="btn-a">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<style>
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
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>
    (function() {
        'use strict';

        var poCanManage = <?= json_encode((bool) ($canManagePo ?? false), JSON_UNESCAPED_UNICODE) ?>;
        var poRowsMap = {};
        var poTable = null;
        var purchaseHistoryLoaded = false;

        function formatRp(n) {
            n = parseInt(n, 10) || 0;
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
            }, 2800);
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

        function setPurchaseTab(tab) {
            var next = 'pembelian';
            if (tab === 'po') next = 'po';
            if (tab === 'history') next = 'history';
            document.querySelectorAll('[data-purchase-tab]').forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-purchase-tab') === next);
            });
            document.querySelectorAll('[data-purchase-pane]').forEach(function(pane) {
                pane.style.display = pane.getAttribute('data-purchase-pane') === next ? '' : 'none';
            });
            if (next === 'po') {
                initPoTable();
                return;
            }
            if (next === 'history' && !purchaseHistoryLoaded) {
                loadPurchaseHistory(true);
            }
        }

        function renderPurchaseHistory(payload) {
            var container = document.getElementById('purchaseHistoryContainer');
            if (!container) return;
            var groups = payload && Array.isArray(payload.groups) ? payload.groups : [];
            var totalTrx = payload && payload.total_transactions ? parseInt(payload.total_transactions, 10) || 0 : 0;
            var periodLabel = payload && payload.period_label ? String(payload.period_label) : '';
            var totalNominal = payload && payload.total_nominal ? parseInt(payload.total_nominal, 10) || 0 : 0;
            var summaryHtml = '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">' +
                '<span class="sbadge scc border rounded-4 small">' + '<i class="bi bi-cart-check"></i> ' + totalTrx + ' Trx <i class="bi bi-calendar-check"></i> ' + escapeHtml(periodLabel || 'Bulan Ini') + '</span>' +
                '<span class="sbadge scc border rounded-4 small">' + 'Total: ' + escapeHtml(formatRp(totalNominal)) + '</span>' +
                '</div>';
            if (!Array.isArray(groups) || groups.length === 0) {
                container.innerHTML = summaryHtml + '<div class="text-muted small">Belum ada histori pembelian berhasil.</div>';
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
                return '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td><strong>' + escapeHtml(String(row.no_trx || '-')) + '</strong><br><span class="small text-muted">' + escapeHtml(formatDateTimeLabel(row.created_at || row.tanggal_input || '')) + '</span></td>' +
                    '<td>' + escapeHtml(String(row.nm_supplier || 'Supplier Umum')) + '</td>' +
                    '<td>' + escapeHtml(String(row.kasir || '-')) + '</td>' +
                    '<td class="text-center">' + escapeHtml(String(row.jumlah || 0)) + '</td>' +
                    '<td class="text-end">' + formatRp(row.total || 0) + '</td>' +
                    '</tr>';
            }).join('');
            container.innerHTML = summaryHtml +
                '<div class="table-responsive" style="overflow-x:auto;">' +
                '<table class="dtable w-100">' +
                '<thead><tr><th style="width:56px;">No</th><th>No.Transaksi</th><th>Supplier</th><th>Petugas</th><th style="width:80px;">Qty</th><th style="width:130px;">Total</th></tr></thead>' +
                '<tbody>' + tableRows + '</tbody>' +
                '</table></div>';
        }

        async function loadPurchaseHistory(force) {
            var container = document.getElementById('purchaseHistoryContainer');
            if (!container) return;
            if (!force && purchaseHistoryLoaded) return;
            container.innerHTML = '<div class="text-muted small"><i class="bi bi-arrow-repeat spin-icon"></i> Memuat histori...</div>';
            try {
                var resp = await fetch('/transaksi/pembelian/history/daily', {
                    method: 'GET'
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var payload = await resp.json();
                renderPurchaseHistory(payload || {});
                purchaseHistoryLoaded = true;
            } catch (e) {
                container.innerHTML = '<div class="text-danger small">Gagal memuat histori pembelian.</div>';
            }
        }

        function renderPoStatus(status, label) {
            var cls = 'sbadge inf';
            if (status === 'diterima') cls = 'sbadge suc';
            if (status === 'ditolak') cls = 'sbadge dng';
            return '<span class="' + cls + '">' + (label || status || '-') + '</span>';
        }

        function initPoTable() {
            if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') return;
            if (poTable) {
                poTable.ajax.reload(null, false);
                return;
            }

            poTable = window.jQuery('#purchasePoTable').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                ordering: true,
                lengthChange: true,
                pageLength: 10,
                scrollX: true,
                language: {
                    url: <?= json_encode(base_url('assets/vendor/datatables/id.json'), JSON_UNESCAPED_UNICODE) ?>
                },
                ajax: {
                    url: '/transaksi/pembelian/po/datatable',
                    type: 'GET'
                },
                columns: [{
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row, meta) {
                            var start = meta && meta.settings && meta.settings._iDisplayStart ? meta.settings._iDisplayStart : 0;
                            return start + meta.row + 1;
                        }
                    },
                    {
                        data: 'po_no_reg',
                        defaultContent: '-'
                    },
                    {
                        data: 'no_trx',
                        defaultContent: '-'
                    },
                    {
                        data: 'keterangan',
                        defaultContent: '-'
                    },
                    {
                        data: null,
                        defaultContent: '-',
                        render: function(data, type, row) {
                            return renderPoStatus(String(row.po_status || ''), String(row.po_status_label || ''));
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var id = parseInt(row.id || 0, 10) || 0;
                            poRowsMap[id] = row;
                            var html = '<div class="d-flex gap-2">';
                            html += '<button type="button" class="btn-g btn-sm btn-po-view" data-po-id="' + id + '"><i class="bi bi-eye"></i></button>';
                            if (poCanManage) {
                                html += '<button type="button" class="btn-g btn-sm btn-po-edit" data-po-id="' + id + '"><i class="bi bi-pencil-square"></i></button>';
                                html += '<button type="button" class="btn-a btn-sm btn-po-delete" data-po-id="' + id + '"><i class="bi bi-trash3"></i></button>';
                            }
                            html += '</div>';
                            return html;
                        }
                    }
                ]
            });
        }

        function updatePickerState(modal) {
            if (!modal) return;
            var checks = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-check]'));
            var enabledChecks = checks.filter(function(chk) {
                return !chk.disabled;
            });
            var checkedCount = checks.filter(function(chk) {
                return chk.checked;
            }).length;
            var allBox = modal.querySelector('.pos-pick-checkall-input');
            if (allBox) {
                allBox.checked = enabledChecks.length > 0 && checkedCount === enabledChecks.length;
                allBox.indeterminate = checkedCount > 0 && checkedCount < enabledChecks.length;
                allBox.disabled = enabledChecks.length === 0;
            }
            var emptyHint = modal.querySelector('[data-pick-empty]');
            if (emptyHint) emptyHint.hidden = checkedCount !== 0;
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
            var rows = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-item]'));
            rows.forEach(function(row) {
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

        function setBtnLoading(btn, on) {
            if (!btn) return;
            if (on) {
                btn.disabled = true;
                btn.dataset.orig = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i>';
            } else {
                btn.disabled = false;
                if (btn.dataset.orig) btn.innerHTML = btn.dataset.orig;
            }
        }

        function updateCsrf(doc) {
            var token = doc.querySelector('input[name="_token"]');
            if (!token) return;
            document.querySelectorAll('input[name="_token"]').forEach(function(el) {
                el.value = token.value;
            });
        }

        function syncPurchaseTotalsFromDom() {
            var grand = document.getElementById('purchaseGrandTotal');
            var display = document.getElementById('purchaseDisplayTotal');
            var bayar = document.getElementById('purchaseBayar');
            if (!grand || !display || !bayar) return;

            var raw = grand.getAttribute('data-value') || '0';
            var numeric = parseInt(raw, 10) || 0;
            display.textContent = formatRp(numeric);
            if (!bayar._userEdited) {
                bayar.value = String(numeric);
            }

            // Update indikator saldo cukup/tidak
            var saldoEl = document.getElementById('purchaseSaldoKas');
            var infoEl = document.getElementById('purchaseSaldoInfo');
            var balanceRow = document.querySelector('.pos-balance-row');
            if (saldoEl && balanceRow) {
                var saldo = parseInt(saldoEl.getAttribute('data-saldo') || '0', 10) || 0;
                balanceRow.classList.remove('pos-lebih', 'pos-kurang', 'pos-nol');
                if (numeric === 0) {
                    balanceRow.classList.add('pos-nol');
                } else if (saldo >= numeric) {
                    balanceRow.classList.add('pos-lebih');
                    if (infoEl) infoEl.textContent = 'Saldo mencukupi. Pembelian akan langsung diproses.';
                } else {
                    balanceRow.classList.add('pos-kurang');
                    if (infoEl) infoEl.textContent = 'Saldo kas Rp ' + saldo.toLocaleString('id-ID') + ' tidak cukup untuk Rp ' + numeric.toLocaleString('id-ID') + '. Transaksi akan menjadi PO.';
                }
            }
        }

        function updatePurchaseFromHtml(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            updateCsrf(doc);

            var incomingBody = doc.getElementById('purchaseCartTableBody');
            var currentBody = document.getElementById('purchaseCartTableBody');
            if (incomingBody && currentBody) {
                currentBody.innerHTML = incomingBody.innerHTML;
            }

            var incomingSummary = doc.getElementById('purchaseCartSummary');
            var currentSummary = document.getElementById('purchaseCartSummary');
            if (incomingSummary && currentSummary) {
                currentSummary.innerHTML = incomingSummary.innerHTML;
            }

            syncPurchaseTotalsFromDom();
            initPurchaseQtyForms();
        }

        async function handlePurchaseQtySubmit(form, input) {
            input.classList.add('pos-loading');
            try {
                var fd = new FormData(form);
                var resp = await fetch(form.action, {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                updatePurchaseFromHtml(html);
                posToast('success', 'Diperbarui', 'Jumlah item diperbarui');
            } catch (e) {
                posToast('error', 'Gagal', 'Gagal memperbarui jumlah');
            } finally {
                input.classList.remove('pos-loading');
            }
        }

        async function purchaseAjaxSubmit(form, btn, successMsg) {
            setBtnLoading(btn, true);
            try {
                var fd = new FormData(form);
                var resp = await fetch(form.action, {
                    method: 'POST',
                    body: fd
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                var html = await resp.text();
                updatePurchaseFromHtml(html);
                posToast('success', 'Berhasil', successMsg || 'Berhasil diproses');
            } catch (e) {
                posToast('error', 'Gagal', 'Terjadi kesalahan. Coba lagi.');
            } finally {
                setBtnLoading(btn, false);
            }
        }

        async function addSelectedFromPicker(modal, btn) {
            if (!modal) return;
            var rows = Array.prototype.slice.call(modal.querySelectorAll('[data-pick-item]')).filter(function(row) {
                var check = row.querySelector('[data-pick-check]');
                return !!check && check.checked;
            });
            if (rows.length === 0) {
                posToast('warning', 'Perhatian', 'Pilih minimal satu barang.');
                return;
            }

            var tokenEl = document.querySelector('input[name="_token"]');
            var token = tokenEl ? tokenEl.value : '';
            setBtnLoading(btn, true);

            var okCount = 0;
            var failedCount = 0;
            var lastHtml = '';
            for (var i = 0; i < rows.length; i += 1) {
                var row = rows[i];
                var itemId = row.getAttribute('data-item-id') || '0';
                var qtyEl = row.querySelector('[data-pick-qty]');
                var qty = qtyEl ? Math.max(1, parseInt(qtyEl.value || '1', 10) || 1) : 1;
                var beliEl = row.querySelector('[data-pick-beli]');
                var jualEl = row.querySelector('[data-pick-jual]');
                var beli = beliEl ? Math.max(0, parseInt(beliEl.value || '0', 10) || 0) : 0;
                var jual = jualEl ? Math.max(0, parseInt(jualEl.value || '0', 10) || 0) : 0;
                var fd = new FormData();
                if (token !== '') fd.append('_token', token);
                fd.append('item_id', itemId);
                fd.append('qty', String(qty));
                fd.append('beli', String(beli));
                fd.append('jual', String(jual));

                try {
                    var resp = await fetch('/transaksi/pembelian/cart/add', {
                        method: 'POST',
                        body: fd
                    });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    lastHtml = await resp.text();
                    okCount += 1;
                } catch (e) {
                    failedCount += 1;
                }
            }

            setBtnLoading(btn, false);
            if (okCount > 0) {
                if (lastHtml !== '') {
                    updatePurchaseFromHtml(lastHtml);
                }
                rows.forEach(function(row) {
                    var check = row.querySelector('[data-pick-check]');
                    var qtyInput = row.querySelector('[data-pick-qty]');
                    if (check) check.checked = false;
                    if (qtyInput) qtyInput.value = '1';
                });
                updatePickerState(modal);
                closeModal(modal);
                if (failedCount === 0) {
                    posToast('success', 'Berhasil', okCount + ' barang ditambahkan ke keranjang.');
                } else {
                    posToast('warning', 'Sebagian Berhasil', okCount + ' barang masuk, ' + failedCount + ' gagal.');
                }
                return;
            }
            posToast('error', 'Gagal', 'Gagal menambahkan barang ke keranjang.');
        }

        document.addEventListener('click', function(e) {
            var tabBtn = e.target.closest('[data-purchase-tab]');
            if (tabBtn) {
                e.preventDefault();
                setPurchaseTab(tabBtn.getAttribute('data-purchase-tab') || 'pembelian');
                return;
            }

            var historyRefreshBtn = e.target.closest('[data-purchase-history-refresh]');
            if (historyRefreshBtn) {
                e.preventDefault();
                loadPurchaseHistory(true);
                return;
            }

            var poViewBtn = e.target.closest('.btn-po-view');
            if (poViewBtn) {
                e.preventDefault();
                var poIdView = parseInt(poViewBtn.getAttribute('data-po-id') || '0', 10) || 0;
                var poView = poRowsMap[poIdView] || null;
                if (!poView) return;
                var reg = document.getElementById('poViewReg');
                var noTrx = document.getElementById('poViewNoTrx');
                var sup = document.getElementById('poViewSupplier');
                var stat = document.getElementById('poViewStatus');
                var ket = document.getElementById('poViewKet');
                var rev = document.getElementById('poViewReview');
                if (reg) reg.textContent = String(poView.po_no_reg || '-');
                if (noTrx) noTrx.textContent = String(poView.no_trx || '-');
                if (sup) sup.textContent = String(poView.nm_supplier || '-');
                if (stat) stat.textContent = String(poView.po_status_label || poView.po_status || '-');
                if (ket) ket.textContent = String(poView.keterangan || '-');
                if (rev) rev.textContent = String(poView.po_review_note || '-');
                openModal('cmPoView');
                return;
            }

            var poEditBtn = e.target.closest('.btn-po-edit');
            if (poEditBtn) {
                e.preventDefault();
                var poIdEdit = parseInt(poEditBtn.getAttribute('data-po-id') || '0', 10) || 0;
                var poEdit = poRowsMap[poIdEdit] || null;
                if (!poEdit) return;
                var editId = document.getElementById('poEditId');
                var editAction = document.getElementById('poEditAction');
                var editNote = document.getElementById('poEditNote');
                if (editId) editId.value = String(poIdEdit);
                if (editAction) {
                    var st = String(poEdit.po_status || 'pending');
                    editAction.value = (st === 'ditolak' ? 'ditolak' : 'diterima');
                }
                if (editNote) editNote.value = String(poEdit.po_review_note || '');
                openModal('cmPoEdit');
                return;
            }

            var poDeleteBtn = e.target.closest('.btn-po-delete');
            if (poDeleteBtn) {
                e.preventDefault();
                var poIdDelete = parseInt(poDeleteBtn.getAttribute('data-po-id') || '0', 10) || 0;
                var poDelete = poRowsMap[poIdDelete] || null;
                var deleteId = document.getElementById('poDeleteId');
                var deleteLabel = document.getElementById('poDeleteLabel');
                if (deleteId) deleteId.value = String(poIdDelete);
                if (deleteLabel) deleteLabel.textContent = String((poDelete && poDelete.po_no_reg) || (poDelete && poDelete.no_trx) || '-');
                openModal('cmPoDelete');
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
                var viewModal = viewBtn.closest('.cm-bg');
                setPickerView(viewModal, viewBtn.getAttribute('data-pick-view') || 'grid');
                return;
            }

            var submitBtn = e.target.closest('[data-pick-submit]');
            if (submitBtn) {
                addSelectedFromPicker(submitBtn.closest('.cm-bg'), submitBtn);
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
                return;
            }

            var check = e.target.closest('[data-pick-check]');
            if (check) {
                updatePickerState(check.closest('.cm-bg'));
            }
        });

        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (form.matches('.pos-qty-form')) {
                e.preventDefault();
                var input = form.querySelector('.pos-qty-input');
                if (!input) return;
                handlePurchaseQtySubmit(form, input);
                return;
            }
            if (form.matches('[data-purchase-ajax]')) {
                e.preventDefault();
                var submitBtn = form.querySelector('[type="submit"]');
                purchaseAjaxSubmit(form, submitBtn, form.getAttribute('data-msg') || 'Berhasil diproses');
            }
        });

        document.querySelectorAll('[data-cm-bg]').forEach(function(bg) {
            bg.addEventListener('click', function(e) {
                if (e.target === bg) closeModal(bg);
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAllPopups();
        });

        document.querySelectorAll('.pos-pick-qty-input').forEach(function(input) {
            input.addEventListener('input', function() {
                var value = parseInt(this.value || '1', 10) || 1;
                if (value < 1) value = 1;
                this.value = String(value);
            });
        });
        document.querySelectorAll('.pos-pick-beli-input,.pos-pick-jual-input').forEach(function(input) {
            input.addEventListener('input', function() {
                var value = parseInt(this.value || '0', 10) || 0;
                if (value < 0) value = 0;
                this.value = String(value);
            });
        });

        document.querySelectorAll('.cm-bg').forEach(function(modal) {
            updatePickerState(modal);
            initPickerSearch(modal);
        });

        function initPurchaseQtyForms() {
            document.querySelectorAll('.pos-qty-form').forEach(function(form) {
                if (form._qtyInit) return;
                form._qtyInit = true;
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
                    lastVal = input.value;
                    isSubmitting = true;
                    handlePurchaseQtySubmit(form, input).finally(function() {
                        isSubmitting = false;
                    });
                }

                input.addEventListener('focus', function() {
                    lastVal = this.value;
                });
                input.addEventListener('change', submitIfChanged);
                input.addEventListener('blur', submitIfChanged);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitIfChanged();
                    }
                });
            });
        }

        function initPurchaseBayarInput() {
            var bayar = document.getElementById('purchaseBayar');
            if (!bayar || bayar._purchaseInit) return;
            bayar._purchaseInit = true;
            bayar._userEdited = false;
            bayar.addEventListener('input', function() {
                this._userEdited = true;
            });
        }

        initPurchaseBayarInput();
        syncPurchaseTotalsFromDom();
        initPurchaseQtyForms();
        setPurchaseTab('pembelian');
    })();
</script>
<?= raw(view('partials/dashboard/shell_close')) ?>