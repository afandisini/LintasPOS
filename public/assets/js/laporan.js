(function () {
    'use strict';

    if (typeof window.jQuery === 'undefined') {
        console.error('jQuery not loaded');
        return;
    }

    var canViewModal = window.laporanCanViewModal || false;
    var activeTab = 'transaksi';
    var dtInstance = null;
    var dtRugiLabaInstance = null;
    var dtKeuanganInstance = null;
    var summaryTimer = null;

    function formatRp(n) {
        n = parseInt(n) || 0;
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    // Tab switching
    document.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('[data-lap-tab]');
        if (tabBtn) {
            e.preventDefault();
            var tab = tabBtn.getAttribute('data-lap-tab');
            if (tab === activeTab) return;
            activeTab = tab;
            document.querySelectorAll('[data-lap-tab]').forEach(function (btn) {
                btn.classList.remove('is-active');
            });
            tabBtn.classList.add('is-active');
            document.querySelectorAll('.lap-content').forEach(function (c) {
                c.style.display = 'none';
            });
            var content = document.querySelector('[data-lap-content="' + tab + '"]');
            if (content) content.style.display = 'block';

            if (tab === 'rugi-laba' && !dtRugiLabaInstance) {
                initRugiLabaDatatable();
            } else if (tab === 'keuangan' && !dtKeuanganInstance) {
                initKeuanganDatatable();
            }
        }
    });

    // TAB 1: TRANSAKSI
    function getFilters() {
        return {
            tipe: document.getElementById('filterTipe').value,
            tanggal_dari: document.getElementById('filterTanggalDari').value,
            tanggal_sampai: document.getElementById('filterTanggalSampai').value,
            id_pelanggan: document.getElementById('filterPelanggan').value,
            nm_supplier: document.getElementById('filterSupplier').value,
            id_kategori: document.getElementById('filterKategori').value,
            nama_produk: document.getElementById('filterProduk').value,
            payment_method: document.getElementById('filterMetode').value,
        };
    }

    function toggleFilterFields() {
        var tipe = document.getElementById('filterTipe').value;
        document.getElementById('wrapPelanggan').style.display = tipe === 'penjualan' ? '' : 'none';
        document.getElementById('wrapSupplier').style.display = tipe === 'pembelian' ? '' : 'none';
        document.getElementById('tableTitle').innerHTML = '<i class="bi bi-table me-1"></i> Data ' + (tipe === 'penjualan' ? 'Penjualan' : 'Pembelian');
    }

    function applyPeriode(val) {
        var now = new Date();
        var dari = '', sampai = '';
        function fmt(d) { return d.toISOString().slice(0, 10); }
        if (val === 'today') {
            dari = sampai = fmt(now);
        } else if (val === 'week') {
            var day = now.getDay() || 7;
            var mon = new Date(now); mon.setDate(now.getDate() - day + 1);
            var sun = new Date(mon); sun.setDate(mon.getDate() + 6);
            dari = fmt(mon); sampai = fmt(sun);
        } else if (val === 'month') {
            dari = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
            sampai = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
        } else if (val === 'year') {
            dari = now.getFullYear() + '-01-01';
            sampai = now.getFullYear() + '-12-31';
        }
        document.getElementById('filterTanggalDari').value = dari;
        document.getElementById('filterTanggalSampai').value = sampai;
        var showCustom = val === 'custom' || val === '';
        document.getElementById('wrapTanggalDari').style.display = showCustom ? '' : 'none';
        document.getElementById('wrapTanggalSampai').style.display = showCustom ? '' : 'none';
    }

    function buildColumns(tipe) {
        var cols = [];
        if (tipe === 'penjualan') {
            cols = [
                { title: 'No Trx', data: 'no_trx' },
                { title: 'Tanggal', data: 'tanggal_input' },
                { title: 'Pelanggan', data: 'nama_pelanggan', defaultContent: 'Umum' },
                { title: 'Qty', data: 'total_qty', className: 'text-end' },
                { title: 'Total', data: 'total', className: 'text-end', render: function (d) { return formatRp(d); } },
                { title: 'Bayar', data: 'bayar', className: 'text-end', render: function (d) { return formatRp(d); } },
                { title: 'Metode', data: 'payment_method', defaultContent: '-' },
                { title: 'Status', data: 'status_bayar', render: function (d) {
                    var cls = d === 'Lunas' ? 'scc' : 'wrn';
                    return '<span class="sbadge ' + cls + '">' + (d || '-') + '</span>';
                }},
            ];
        } else {
            cols = [
                { title: 'No Trx', data: 'no_trx' },
                { title: 'Tanggal', data: 'tanggal_input' },
                { title: 'Supplier', data: 'nm_supplier', defaultContent: '-' },
                { title: 'Qty', data: 'total_qty', className: 'text-end' },
                { title: 'Total Beli', data: 'total', className: 'text-end', render: function (d) { return formatRp(d); } },
                { title: 'Status', data: 'status_bayar', render: function (d) {
                    var cls = d === 'Lunas' ? 'scc' : 'wrn';
                    return '<span class="sbadge ' + cls + '">' + (d || '-') + '</span>';
                }},
            ];
        }
        if (canViewModal && tipe === 'penjualan') {
            cols.push({ title: 'Modal', data: 'total_modal', className: 'text-end', render: function (d) { return formatRp(d); } });
        }
        return cols;
    }

    function initDatatable() {
        var f = getFilters();
        var tipe = f.tipe;
        var cols = buildColumns(tipe);

        if (dtInstance) {
            dtInstance.destroy();
            dtInstance = null;
            document.getElementById('laporanTable').innerHTML = '<thead><tr id="laporanTableHead"></tr></thead><tbody></tbody>';
        }

        dtInstance = $('#laporanTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: '/laporan/datatable',
                type: 'GET',
                data: function (d) {
                    d.tipe = f.tipe;
                    d.tanggal_dari = f.tanggal_dari;
                    d.tanggal_sampai = f.tanggal_sampai;
                    d.id_pelanggan = f.id_pelanggan;
                    d.nm_supplier = f.nm_supplier;
                    d.id_kategori = f.id_kategori;
                    d.nama_produk = f.nama_produk;
                    d.payment_method = f.payment_method;
                },
                dataSrc: function (json) {
                    updateSummaryFromDt(json, tipe);
                    return json.data || [];
                },
            },
            columns: cols,
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '/assets/vendor/datatables/id.json' },
            responsive: true,
        });
    }

    function updateSummaryFromDt(json, tipe) {
        clearTimeout(summaryTimer);
        summaryTimer = setTimeout(function () {
            var f = getFilters();
            var p = new URLSearchParams();
            p.set('tipe', f.tipe);
            if (f.tanggal_dari) p.set('tanggal_dari', f.tanggal_dari);
            if (f.tanggal_sampai) p.set('tanggal_sampai', f.tanggal_sampai);
            if (f.id_pelanggan) p.set('id_pelanggan', f.id_pelanggan);
            if (f.nm_supplier) p.set('nm_supplier', f.nm_supplier);
            if (f.id_kategori) p.set('id_kategori', f.id_kategori);
            if (f.nama_produk) p.set('nama_produk', f.nama_produk);
            if (f.payment_method) p.set('payment_method', f.payment_method);
            p.set('start', '0');
            p.set('length', '99999');
            p.set('draw', '1');

            fetch('/laporan/datatable?' + p.toString())
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var rows = data.data || [];
                    var totalTrx = rows.length;
                    var totalQty = 0, grandTotal = 0, totalModal = 0;
                    rows.forEach(function (r) {
                        totalQty += parseInt(r.total_qty) || 0;
                        grandTotal += parseInt(r.total) || 0;
                        totalModal += parseInt(r.total_modal) || 0;
                    });
                    var laba = grandTotal - totalModal;
                    document.getElementById('cardTotalTrx').textContent = totalTrx.toLocaleString('id-ID');
                    document.getElementById('cardTotalQty').textContent = totalQty.toLocaleString('id-ID');
                    document.getElementById('cardGrandTotal').textContent = formatRp(grandTotal);
                    var cm = document.getElementById('cardTotalModal');
                    var cl = document.getElementById('cardLaba');
                    if (cm) cm.textContent = formatRp(totalModal);
                    if (cl) cl.textContent = formatRp(laba);
                })
                .catch(function () {});
        }, 400);
    }

    function applyFilters() {
        toggleFilterFields();
        initDatatable();
    }

    // TAB 2: RUGI LABA
    function initRugiLabaDatatable() {
        var tahun = document.getElementById('filterTahunRugiLaba').value;
        if (dtRugiLabaInstance) {
            dtRugiLabaInstance.destroy();
            dtRugiLabaInstance = null;
        }
        dtRugiLabaInstance = $('#rugiLabaTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: '/laporan/datatable/rugi-laba',
                type: 'GET',
                data: function (d) { d.tahun = tahun; },
                dataSrc: function (json) {
                    updateRugiLabaSummary(json.data || []);
                    return json.data || [];
                },
            },
            columns: [
                { data: 'bulan' },
                { data: 'total_penjualan', className: 'text-end', render: function (d) { return formatRp(d); } },
                canViewModal ? { data: 'total_modal', className: 'text-end', render: function (d) { return formatRp(d); } } : null,
                canViewModal ? { data: 'laba_kotor', className: 'text-end', render: function (d) { return formatRp(d); } } : null,
                { data: 'total_pembelian', className: 'text-end', render: function (d) { return formatRp(d); } },
                { data: 'laba_bersih', className: 'text-end', render: function (d) {
                    var cls = parseInt(d) >= 0 ? 'text-success' : 'text-danger';
                    return '<span class="' + cls + '">' + formatRp(d) + '</span>';
                }},
            ].filter(Boolean),
            paging: false,
            searching: false,
            info: false,
            order: [],
            language: { url: '/assets/vendor/datatables/id.json' },
        });
    }

    function updateRugiLabaSummary(rows) {
        var totalPenjualan = 0, totalModal = 0, labaKotor = 0, totalPembelian = 0, labaBersih = 0;
        rows.forEach(function (r) {
            totalPenjualan += parseInt(r.total_penjualan) || 0;
            totalModal += parseInt(r.total_modal) || 0;
            labaKotor += parseInt(r.laba_kotor) || 0;
            totalPembelian += parseInt(r.total_pembelian) || 0;
            labaBersih += parseInt(r.laba_bersih) || 0;
        });
        document.getElementById('cardRLTotalPenjualan').textContent = formatRp(totalPenjualan);
        var cm = document.getElementById('cardRLTotalModal');
        var ck = document.getElementById('cardRLLabaKotor');
        if (cm) cm.textContent = formatRp(totalModal);
        if (ck) ck.textContent = formatRp(labaKotor);
        document.getElementById('cardRLTotalPembelian').textContent = formatRp(totalPembelian);
        document.getElementById('cardRLLabaBersih').textContent = formatRp(labaBersih);
    }

    // TAB 3: KEUANGAN
    function initKeuanganDatatable() {
        var dari = document.getElementById('filterKeuTanggalDari').value;
        var sampai = document.getElementById('filterKeuTanggalSampai').value;
        var tipeArus = document.getElementById('filterKeuTipeArus').value;
        var akun = document.getElementById('filterKeuAkun').value;

        if (dtKeuanganInstance) {
            dtKeuanganInstance.destroy();
            dtKeuanganInstance = null;
        }
        dtKeuanganInstance = $('#keuanganTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: '/laporan/datatable/keuangan',
                type: 'GET',
                data: function (d) {
                    d.tanggal_dari = dari;
                    d.tanggal_sampai = sampai;
                    d.tipe_arus = tipeArus;
                    d.akun_keuangan_id = akun;
                },
            },
            columns: [
                { data: 'tanggal' },
                { data: 'no_ref', defaultContent: '-' },
                { data: null, render: function (d) {
                    return (d.kode_akun || '-') + ' - ' + (d.nama_akun || '-');
                }},
                { data: 'tipe_arus', render: function (d) {
                    var cls = d === 'pemasukan' ? 'scc' : (d === 'pengeluaran' ? 'wrn' : 'inf');
                    return '<span class="sbadge ' + cls + '">' + (d || '-') + '</span>';
                }},
                { data: 'nominal', className: 'text-end', render: function (d) { return formatRp(d); } },
                { data: 'metode_pembayaran', defaultContent: '-' },
                { data: 'status', render: function (d) {
                    var cls = d === 'posted' ? 'scc' : 'wrn';
                    return '<span class="sbadge ' + cls + '">' + (d || '-') + '</span>';
                }},
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            language: { url: '/assets/vendor/datatables/id.json' },
        });
    }

    // Event Listeners
    function initEventListeners() {
        var filterTipe = document.getElementById('filterTipe');
        var filterPeriode = document.getElementById('filterPeriode');
        
        if (filterTipe) filterTipe.addEventListener('change', applyFilters);
        if (filterPeriode) {
            filterPeriode.addEventListener('change', function () {
                applyPeriode(this.value);
                if (this.value !== 'custom') applyFilters();
            });
        }

        ['filterTanggalDari', 'filterTanggalSampai', 'filterPelanggan', 'filterSupplier', 'filterKategori', 'filterMetode'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', applyFilters);
        });

        var filterProduk = document.getElementById('filterProduk');
        var produkTimer;
        if (filterProduk) {
            filterProduk.addEventListener('input', function () {
                clearTimeout(produkTimer);
                produkTimer = setTimeout(applyFilters, 500);
            });
        }

        var btnResetFilter = document.getElementById('btnResetFilter');
        if (btnResetFilter) {
            btnResetFilter.addEventListener('click', function () {
                document.getElementById('filterTipe').value = 'penjualan';
                document.getElementById('filterPeriode').value = '';
                document.getElementById('filterTanggalDari').value = '';
                document.getElementById('filterTanggalSampai').value = '';
                document.getElementById('filterPelanggan').value = '';
                document.getElementById('filterSupplier').value = '';
                document.getElementById('filterKategori').value = '';
                document.getElementById('filterProduk').value = '';
                document.getElementById('filterMetode').value = '';
                document.getElementById('wrapTanggalDari').style.display = '';
                document.getElementById('wrapTanggalSampai').style.display = '';
                applyFilters();
            });
        }

        var btnExportPdf = document.getElementById('btnExportPdf');
        if (btnExportPdf) {
            btnExportPdf.addEventListener('click', function () {
                var f = getFilters();
                var p = new URLSearchParams();
                p.set('tipe', f.tipe);
                if (f.tanggal_dari) p.set('tanggal_dari', f.tanggal_dari);
                if (f.tanggal_sampai) p.set('tanggal_sampai', f.tanggal_sampai);
                if (f.id_pelanggan) p.set('id_pelanggan', f.id_pelanggan);
                if (f.nm_supplier) p.set('nm_supplier', f.nm_supplier);
                if (f.id_kategori) p.set('id_kategori', f.id_kategori);
                if (f.nama_produk) p.set('nama_produk', f.nama_produk);
                if (f.payment_method) p.set('payment_method', f.payment_method);
                window.open('/laporan/export?tab=transaksi&' + p.toString(), '_blank');
            });
        }

        var filterTahunRugiLaba = document.getElementById('filterTahunRugiLaba');
        if (filterTahunRugiLaba) {
            filterTahunRugiLaba.addEventListener('change', function () {
                if (activeTab === 'rugi-laba') initRugiLabaDatatable();
            });
        }

        var btnResetRugiLaba = document.getElementById('btnResetRugiLaba');
        if (btnResetRugiLaba) {
            btnResetRugiLaba.addEventListener('click', function () {
                document.getElementById('filterTahunRugiLaba').value = new Date().getFullYear();
                if (activeTab === 'rugi-laba') initRugiLabaDatatable();
            });
        }

        var btnExportRugiLaba = document.getElementById('btnExportRugiLaba');
        if (btnExportRugiLaba) {
            btnExportRugiLaba.addEventListener('click', function () {
                var tahun = document.getElementById('filterTahunRugiLaba').value;
                window.open('/laporan/export?tab=rugi-laba&tahun=' + tahun, '_blank');
            });
        }

        ['filterKeuTanggalDari', 'filterKeuTanggalSampai', 'filterKeuTipeArus', 'filterKeuAkun'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    if (activeTab === 'keuangan') initKeuanganDatatable();
                });
            }
        });

        var btnResetKeuangan = document.getElementById('btnResetKeuangan');
        if (btnResetKeuangan) {
            btnResetKeuangan.addEventListener('click', function () {
                document.getElementById('filterKeuTanggalDari').value = '';
                document.getElementById('filterKeuTanggalSampai').value = '';
                document.getElementById('filterKeuTipeArus').value = '';
                document.getElementById('filterKeuAkun').value = '';
                if (activeTab === 'keuangan') initKeuanganDatatable();
            });
        }

        var btnExportKeuangan = document.getElementById('btnExportKeuangan');
        if (btnExportKeuangan) {
            btnExportKeuangan.addEventListener('click', function () {
                var dari = document.getElementById('filterKeuTanggalDari').value;
                var sampai = document.getElementById('filterKeuTanggalSampai').value;
                var tipeArus = document.getElementById('filterKeuTipeArus').value;
                var akun = document.getElementById('filterKeuAkun').value;
                var p = new URLSearchParams();
                p.set('tab', 'keuangan');
                if (dari) p.set('tanggal_dari', dari);
                if (sampai) p.set('tanggal_sampai', sampai);
                if (tipeArus) p.set('tipe_arus', tipeArus);
                if (akun) p.set('akun_keuangan_id', akun);
                var pdfUrl = '/laporan/export?' + p.toString();
                document.getElementById('pdfPreviewKeuangan').src = pdfUrl;
            });
        }

        var btnCetakKeuangan = document.getElementById('btnCetakKeuangan');
        if (btnCetakKeuangan) {
            btnCetakKeuangan.addEventListener('click', function () {
                var dari = document.getElementById('filterKeuTanggalDari').value;
                var sampai = document.getElementById('filterKeuTanggalSampai').value;
                var tipeArus = document.getElementById('filterKeuTipeArus').value;
                var akun = document.getElementById('filterKeuAkun').value;
                var p = new URLSearchParams();
                p.set('tab', 'keuangan');
                if (dari) p.set('tanggal_dari', dari);
                if (sampai) p.set('tanggal_sampai', sampai);
                if (tipeArus) p.set('tipe_arus', tipeArus);
                if (akun) p.set('akun_keuangan_id', akun);
                window.open('/laporan/export?' + p.toString(), '_blank');
                document.getElementById('cmExportKeuangan').style.display = 'none';
            });
        }

        var btnUnduhKeuangan = document.getElementById('btnUnduhKeuangan');
        if (btnUnduhKeuangan) {
            btnUnduhKeuangan.addEventListener('click', function () {
                var dari = document.getElementById('filterKeuTanggalDari').value;
                var sampai = document.getElementById('filterKeuTanggalSampai').value;
                var tipeArus = document.getElementById('filterKeuTipeArus').value;
                var akun = document.getElementById('filterKeuAkun').value;
                var p = new URLSearchParams();
                p.set('tab', 'keuangan');
                p.set('download', '1');
                if (dari) p.set('tanggal_dari', dari);
                if (sampai) p.set('tanggal_sampai', sampai);
                if (tipeArus) p.set('tipe_arus', tipeArus);
                if (akun) p.set('akun_keuangan_id', akun);
                window.location.href = '/laporan/export?' + p.toString();
                document.getElementById('cmExportKeuangan').style.display = 'none';
            });
        }
    }

    // INIT
    canViewModal = window.laporanCanViewModal || false;
    initEventListeners();
    applyPeriode('month');
    applyFilters();
})();
