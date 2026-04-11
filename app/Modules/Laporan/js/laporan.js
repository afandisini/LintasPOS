(function () {
  "use strict";

  if (typeof window.jQuery === "undefined") {
    console.error("jQuery not loaded");
    return;
  }

  var canViewModal = window.laporanCanViewModal || false;
  var currentExportUrl = { transaksi: "", rugiLaba: "", keuangan: "" };

  function loadPreview(containerId, url) {
    var el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML =
      '<div style="text-align:center;padding:40px;color:#888;">Memuat...</div>';
    fetch(url, { credentials: "same-origin" })
      .then(function (r) {
        return r.text();
      })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, "text/html");
        // Remove autoPrint scripts
        doc.querySelectorAll("script").forEach(function (s) {
          s.remove();
        });
        var styles = Array.from(doc.querySelectorAll("style"))
          .map(function (s) {
            return s.outerHTML;
          })
          .join("");
        var bodyContent = doc.body ? doc.body.innerHTML : html;
        el.innerHTML = "";
        var host = document.createElement("div");
        host.style.cssText = "width:100%;height:100%;";
        el.appendChild(host);
        var shadow = host.attachShadow({ mode: "open" });
        shadow.innerHTML =
          styles + '<div style="padding:0;">' + bodyContent + "</div>";
      })
      .catch(function () {
        el.innerHTML =
          '<div style="text-align:center;padding:40px;color:#c00;">Gagal memuat preview.</div>';
      });
  }

  var activeTab = "transaksi";
  var dtInstance = null;
  var dtRugiLabaInstance = null;
  var dtKeuanganInstance = null;
  var summaryTimer = null;

  function formatRp(n) {
    n = parseInt(n) || 0;
    return "Rp " + n.toLocaleString("id-ID");
  }

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  function closeModal(el) {
    var modal =
      el && el.classList.contains("cm-bg")
        ? el
        : el
          ? el.closest(".cm-bg")
          : null;
    if (!modal) return;
    modal.classList.remove("show");
    if (!document.querySelector(".cm-bg.show")) {
      document.body.style.overflow = "";
    }
  }

  // Global modal close handlers (data-cm-close & backdrop click)
  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-cm-close]")) {
      closeModal(e.target.closest("[data-cm-close]"));
      return;
    }
    if (e.target.hasAttribute("data-cm-bg")) {
      closeModal(e.target);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".cm-bg.show").forEach(function (m) {
        m.classList.remove("show");
      });
      document.body.style.overflow = "";
    }
  });

  // Tab switching
  document.addEventListener("click", function (e) {
    var tabBtn = e.target.closest("[data-lap-tab]");
    if (tabBtn) {
      e.preventDefault();
      var tab = tabBtn.getAttribute("data-lap-tab");
      if (tab === activeTab) return;
      activeTab = tab;
      document.querySelectorAll("[data-lap-tab]").forEach(function (btn) {
        btn.classList.remove("is-active");
      });
      tabBtn.classList.add("is-active");
      document.querySelectorAll(".lap-content").forEach(function (c) {
        c.style.display = "none";
      });
      var content = document.querySelector('[data-lap-content="' + tab + '"]');
      if (content) content.style.display = "block";

      if (tab === "rugi-laba" && !dtRugiLabaInstance) {
        initRugiLabaDatatable();
      } else if (tab === "keuangan" && !dtKeuanganInstance) {
        initKeuanganDatatable();
      }
    }
  });

  // TAB 1: TRANSAKSI
  function getFilters() {
    return {
      tipe: document.getElementById("filterTipe").value,
      tanggal_dari: document.getElementById("filterTanggalDari").value,
      tanggal_sampai: document.getElementById("filterTanggalSampai").value,
      id_pelanggan: document.getElementById("filterPelanggan").value,
      nm_supplier: document.getElementById("filterSupplier").value,
      id_kategori: document.getElementById("filterKategori").value,
      nama_produk: document.getElementById("filterProduk").value,
      payment_method: document.getElementById("filterMetode").value,
    };
  }

  function toggleFilterFields() {
    var tipe = document.getElementById("filterTipe").value;
    var wrapPelanggan = document.getElementById("wrapPelanggan");
    var wrapSupplier = document.getElementById("wrapSupplier");
    var wrapKategori = document.getElementById("wrapKategori");
    var wrapProduk = document.getElementById("filterProduk").closest("div");
    var wrapMetode = document.getElementById("filterMetode").closest("div");
    if (wrapPelanggan) wrapPelanggan.style.display = tipe === "penjualan" ? "" : "none";
    if (wrapSupplier)
      wrapSupplier.style.display = tipe === "penjualan" ? "none" : "";
    if (wrapKategori) {
      wrapKategori.style.display = tipe === "hutang" ? "none" : "";
    }
    if (wrapProduk) {
      wrapProduk.style.display = tipe === "hutang" ? "none" : "";
    }
    if (wrapMetode) {
      wrapMetode.style.display =
        tipe === "penjualan" || tipe === "pembelian" ? "" : "none";
    }

    var title = "Penjualan";
    if (tipe === "pembelian") title = "Pembelian";
    if (tipe === "po") title = "PO";
    if (tipe === "hutang") title = "Hutang Supplier";
    document.getElementById("tableTitle").innerHTML =
      '<i class="bi bi-table me-1"></i> Data ' + title;
  }

  function applyPeriode(val) {
    var now = new Date();
    var dari = "",
      sampai = "";
    function fmt(d) {
      return d.toISOString().slice(0, 10);
    }
    if (val === "today") {
      dari = sampai = fmt(now);
    } else if (val === "week") {
      var day = now.getDay() || 7;
      var mon = new Date(now);
      mon.setDate(now.getDate() - day + 1);
      var sun = new Date(mon);
      sun.setDate(mon.getDate() + 6);
      dari = fmt(mon);
      sampai = fmt(sun);
    } else if (val === "month") {
      dari = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
      sampai = fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
    } else if (val === "year") {
      dari = now.getFullYear() + "-01-01";
      sampai = now.getFullYear() + "-12-31";
    }
    document.getElementById("filterTanggalDari").value = dari;
    document.getElementById("filterTanggalSampai").value = sampai;
    var showCustom = val === "custom" || val === "";
    document.getElementById("wrapTanggalDari").style.display = showCustom
      ? ""
      : "none";
    document.getElementById("wrapTanggalSampai").style.display = showCustom
      ? ""
      : "none";
  }

  function buildColumns(tipe) {
    var cols = [];
    if (tipe === "penjualan") {
      cols = [
        { title: "No Trx", data: "no_trx" },
        { title: "Tanggal", data: "tanggal_input" },
        { title: "Pelanggan", data: "nama_pelanggan", defaultContent: "Umum" },
        { title: "Qty", data: "total_qty", className: "text-end" },
        {
          title: "Total",
          data: "total",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          title: "Bayar",
          data: "bayar",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        { title: "Metode", data: "payment_method", defaultContent: "-" },
        {
          title: "Status",
          data: "status_bayar",
          render: function (d) {
            var cls = d === "Lunas" ? "scc" : "wrn";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
      ];
    } else if (tipe === "pembelian") {
      cols = [
        { title: "No Trx", data: "no_trx" },
        { title: "Tanggal", data: "tanggal_input" },
        { title: "Supplier", data: "nm_supplier", defaultContent: "-" },
        { title: "Qty", data: "total_qty", className: "text-end" },
        {
          title: "Total Beli",
          data: "total",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          title: "Bayar",
          data: "paid_amount",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          title: "Sisa",
          data: "remaining_amount",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        { title: "Metode", data: "payment_method", defaultContent: "-" },
        {
          title: "Status",
          data: "payment_status",
          render: function (d) {
            var cls = d === "paid" ? "scc" : "wrn";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
      ];
    } else if (tipe === "po") {
      cols = [
        { title: "No Reg PO", data: "po_no_reg", defaultContent: "-" },
        { title: "No Trx", data: "no_trx" },
        { title: "Tanggal", data: "tanggal_input" },
        { title: "Supplier", data: "nm_supplier", defaultContent: "-" },
        { title: "Qty", data: "total_qty", className: "text-end" },
        {
          title: "Total PO",
          data: "total",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        { title: "Metode", data: "payment_method", defaultContent: "-" },
        {
          title: "Status PO",
          data: "po_status",
          render: function (d) {
            var cls = d === "diterima" ? "scc" : d === "ditolak" ? "dng" : "wrn";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
      ];
    } else if (tipe === "hutang") {
      cols = [
        { title: "No Hutang", data: "debt_no", defaultContent: "-" },
        { title: "Tanggal", data: "debt_date" },
        { title: "Supplier", data: "nama_supplier", defaultContent: "-" },
        { title: "No Pembelian", data: "no_trx", defaultContent: "-" },
        {
          title: "Total",
          data: "total_amount",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          title: "Bayar",
          data: "paid_amount",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          title: "Sisa",
          data: "remaining_amount",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        { title: "Jatuh Tempo", data: "due_date", defaultContent: "-" },
        {
          title: "Status",
          data: "status",
          render: function (d) {
            var cls = d === "paid" ? "scc" : d === "partial" ? "wrn" : "dng";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
      ];
    }
    if (canViewModal && tipe === "penjualan") {
      cols.push({
        title: "Modal",
        data: "total_modal",
        className: "text-end",
        render: function (d) {
          return formatRp(d);
        },
      });
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
      document.getElementById("laporanTable").innerHTML =
        '<thead><tr id="laporanTableHead"></tr></thead><tbody></tbody>';
    }

    dtInstance = $("#laporanTable").DataTable({
      serverSide: true,
      processing: true,
      ajax: {
        url: "/laporan/datatable",
        type: "GET",
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
      order: [[0, "desc"]],
      pageLength: 25,
      language: { url: "/assets/vendor/datatables/id.json" },
      responsive: true,
    });
  }

  function updateSummaryFromDt(json, tipe) {
    clearTimeout(summaryTimer);
    summaryTimer = setTimeout(function () {
      var f = getFilters();
      var p = new URLSearchParams();
      p.set("tipe", f.tipe);
      if (f.tanggal_dari) p.set("tanggal_dari", f.tanggal_dari);
      if (f.tanggal_sampai) p.set("tanggal_sampai", f.tanggal_sampai);
      if (f.id_pelanggan) p.set("id_pelanggan", f.id_pelanggan);
      if (f.nm_supplier) p.set("nm_supplier", f.nm_supplier);
      if (f.id_kategori) p.set("id_kategori", f.id_kategori);
      if (f.nama_produk) p.set("nama_produk", f.nama_produk);
      if (f.payment_method) p.set("payment_method", f.payment_method);
      p.set("start", "0");
      p.set("length", "99999");
      p.set("draw", "1");

      fetch("/laporan/datatable?" + p.toString())
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var rows = data.data || [];
          var totalTrx = rows.length;
          var totalQty = 0,
            grandTotal = 0,
            totalModal = 0;
          rows.forEach(function (r) {
            if (tipe === "hutang") {
              totalQty += 0;
              grandTotal += parseInt(r.remaining_amount) || 0;
              totalModal += parseInt(r.paid_amount) || 0;
            } else if (tipe === "po") {
              totalQty += parseInt(r.total_qty) || 0;
              grandTotal += parseInt(r.total) || 0;
              totalModal += parseInt(r.paid_amount) || 0;
            } else if (tipe === "pembelian") {
              totalQty += parseInt(r.total_qty) || 0;
              grandTotal += parseInt(r.total) || 0;
              totalModal += parseInt(r.paid_amount) || 0;
            } else {
              totalQty += parseInt(r.total_qty) || 0;
              grandTotal += parseInt(r.total) || 0;
              totalModal += parseInt(r.total_modal) || 0;
            }
          });
          var laba = grandTotal - totalModal;
          if (tipe === "po" || tipe === "hutang") {
            laba = grandTotal;
          }
          var labelTrx = document.getElementById("labelTotalTrx");
          var labelQty = document.getElementById("labelTotalQty");
          var labelGrand = document.getElementById("labelGrandTotal");
          var labelModal = document.getElementById("labelTotalModal");
          var labelLaba = document.getElementById("labelLaba");
          if (labelTrx) labelTrx.lastChild.textContent = "Total " + (tipe === "po" ? "Dokumen" : tipe === "hutang" ? "Hutang" : "Transaksi");
          if (labelQty) labelQty.lastChild.textContent = tipe === "hutang" ? "Total Item" : "Total Qty";
          if (labelGrand) {
            labelGrand.lastChild.textContent =
              tipe === "penjualan"
                ? "Total Pendapatan"
                : tipe === "pembelian"
                  ? "Total Pembelian"
                  : tipe === "po"
                    ? "Total PO"
                    : "Total Hutang";
          }
          if (labelModal) {
            labelModal.lastChild.textContent =
              tipe === "penjualan"
                ? "Total Modal"
                : tipe === "pembelian"
                  ? "Total Bayar"
                  : "Total Bayar";
          }
          if (labelLaba) {
            labelLaba.lastChild.textContent =
              tipe === "penjualan"
                ? "Laba Kotor"
                : tipe === "pembelian"
                  ? "Sisa Hutang"
                  : tipe === "po"
                    ? "Nilai PO"
                    : "Sisa Hutang";
          }
          document.getElementById("cardTotalTrx").textContent =
            totalTrx.toLocaleString("id-ID");
          document.getElementById("cardTotalQty").textContent =
            totalQty.toLocaleString("id-ID");
          document.getElementById("cardGrandTotal").textContent =
            formatRp(grandTotal);
          var cm = document.getElementById("cardTotalModal");
          var cl = document.getElementById("cardLaba");
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
    var tahun = document.getElementById("filterTahunRugiLaba").value;
    if (dtRugiLabaInstance) {
      dtRugiLabaInstance.destroy();
      dtRugiLabaInstance = null;
    }
    dtRugiLabaInstance = $("#rugiLabaTable").DataTable({
      serverSide: true,
      processing: true,
      ajax: {
        url: "/laporan/datatable/rugi-laba",
        type: "GET",
        data: function (d) {
          d.tahun = tahun;
        },
        dataSrc: function (json) {
          updateRugiLabaSummary(json.data || []);
          return json.data || [];
        },
      },
      columns: [
        { data: "bulan" },
        {
          data: "total_penjualan",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        canViewModal
          ? {
              data: "total_modal",
              className: "text-end",
              render: function (d) {
                return formatRp(d);
              },
            }
          : null,
        canViewModal
          ? {
              data: "laba_kotor",
              className: "text-end",
              render: function (d) {
                return formatRp(d);
              },
            }
          : null,
        {
          data: "total_pembelian",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        {
          data: "laba_bersih",
          className: "text-end",
          render: function (d) {
            var cls = parseInt(d) >= 0 ? "text-success" : "text-danger";
            return '<span class="' + cls + '">' + formatRp(d) + "</span>";
          },
        },
      ].filter(Boolean),
      paging: false,
      searching: false,
      info: false,
      order: [],
      language: { url: "/assets/vendor/datatables/id.json" },
    });
  }

  function updateRugiLabaSummary(rows) {
    var totalPenjualan = 0,
      totalModal = 0,
      labaKotor = 0,
      totalPembelian = 0,
      labaBersih = 0;
    rows.forEach(function (r) {
      totalPenjualan += parseInt(r.total_penjualan) || 0;
      totalModal += parseInt(r.total_modal) || 0;
      labaKotor += parseInt(r.laba_kotor) || 0;
      totalPembelian += parseInt(r.total_pembelian) || 0;
      labaBersih += parseInt(r.laba_bersih) || 0;
    });
    document.getElementById("cardRLTotalPenjualan").textContent =
      formatRp(totalPenjualan);
    var cm = document.getElementById("cardRLTotalModal");
    var ck = document.getElementById("cardRLLabaKotor");
    if (cm) cm.textContent = formatRp(totalModal);
    if (ck) ck.textContent = formatRp(labaKotor);
    document.getElementById("cardRLTotalPembelian").textContent =
      formatRp(totalPembelian);
    document.getElementById("cardRLLabaBersih").textContent =
      formatRp(labaBersih);
  }

  // TAB 3: KEUANGAN
  function initKeuanganDatatable() {
    var dari = document.getElementById("filterKeuTanggalDari").value;
    var sampai = document.getElementById("filterKeuTanggalSampai").value;
    var tipeArus = document.getElementById("filterKeuTipeArus").value;
    var akun = document.getElementById("filterKeuAkun").value;

    if (dtKeuanganInstance) {
      dtKeuanganInstance.destroy();
      dtKeuanganInstance = null;
    }
    dtKeuanganInstance = $("#keuanganTable").DataTable({
      serverSide: true,
      processing: true,
      ajax: {
        url: "/laporan/datatable/keuangan",
        type: "GET",
        data: function (d) {
          d.tanggal_dari = dari;
          d.tanggal_sampai = sampai;
          d.tipe_arus = tipeArus;
          d.akun_keuangan_id = akun;
        },
      },
      columns: [
        { data: "tanggal" },
        { data: "no_ref", defaultContent: "-" },
        {
          data: null,
          render: function (d) {
            return (d.kode_akun || "-") + " - " + (d.nama_akun || "-");
          },
        },
        {
          data: "tipe_arus",
          render: function (d) {
            var cls =
              d === "pemasukan" ? "scc" : d === "pengeluaran" ? "wrn" : "inf";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
        {
          data: "nominal",
          className: "text-end",
          render: function (d) {
            return formatRp(d);
          },
        },
        { data: "metode_pembayaran", defaultContent: "-" },
        {
          data: "status",
          render: function (d) {
            var cls = d === "posted" ? "scc" : "wrn";
            return '<span class="sbadge ' + cls + '">' + (d || "-") + "</span>";
          },
        },
      ],
      order: [[0, "desc"]],
      pageLength: 25,
      language: { url: "/assets/vendor/datatables/id.json" },
    });
  }

  function getKeuParams() {
    var p = new URLSearchParams();
    p.set("tab", "keuangan");
    var dari = document.getElementById("filterKeuTanggalDari").value;
    var sampai = document.getElementById("filterKeuTanggalSampai").value;
    var tipeArus = document.getElementById("filterKeuTipeArus").value;
    var akun = document.getElementById("filterKeuAkun").value;
    if (dari) p.set("tanggal_dari", dari);
    if (sampai) p.set("tanggal_sampai", sampai);
    if (tipeArus) p.set("tipe_arus", tipeArus);
    if (akun) p.set("akun_keuangan_id", akun);
    return p;
  }

  // Event Listeners
  function initEventListeners() {
    var filterTipe = document.getElementById("filterTipe");
    var filterPeriode = document.getElementById("filterPeriode");

    if (filterTipe) filterTipe.addEventListener("change", applyFilters);
    if (filterPeriode) {
      filterPeriode.addEventListener("change", function () {
        applyPeriode(this.value);
        if (this.value !== "custom") applyFilters();
      });
    }

    [
      "filterTanggalDari",
      "filterTanggalSampai",
      "filterPelanggan",
      "filterSupplier",
      "filterKategori",
      "filterMetode",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", applyFilters);
    });

    var filterProduk = document.getElementById("filterProduk");
    var produkTimer;
    if (filterProduk) {
      filterProduk.addEventListener("input", function () {
        clearTimeout(produkTimer);
        produkTimer = setTimeout(applyFilters, 500);
      });
    }

    // Reset Transaksi
    var btnResetFilter = document.getElementById("btnResetFilter");
    if (btnResetFilter) {
      btnResetFilter.addEventListener("click", function () {
        document.getElementById("filterTipe").value = "penjualan";
        document.getElementById("filterPeriode").value = "";
        document.getElementById("filterTanggalDari").value = "";
        document.getElementById("filterTanggalSampai").value = "";
        document.getElementById("filterPelanggan").value = "";
        document.getElementById("filterSupplier").value = "";
        document.getElementById("filterKategori").value = "";
        document.getElementById("filterProduk").value = "";
        document.getElementById("filterMetode").value = "";
        document.getElementById("wrapTanggalDari").style.display = "";
        document.getElementById("wrapTanggalSampai").style.display = "";
        applyFilters();
      });
    }

    // Export Transaksi
    var btnExportPdf = document.getElementById("btnExportPdf");
    if (btnExportPdf) {
      btnExportPdf.addEventListener("click", function () {
        var f = getFilters();
        var p = new URLSearchParams();
        p.set("tab", "transaksi");
        p.set("tipe", f.tipe);
        if (f.tanggal_dari) p.set("tanggal_dari", f.tanggal_dari);
        if (f.tanggal_sampai) p.set("tanggal_sampai", f.tanggal_sampai);
        if (f.id_pelanggan) p.set("id_pelanggan", f.id_pelanggan);
        if (f.nm_supplier) p.set("nm_supplier", f.nm_supplier);
        if (f.id_kategori) p.set("id_kategori", f.id_kategori);
        if (f.nama_produk) p.set("nama_produk", f.nama_produk);
        if (f.payment_method) p.set("payment_method", f.payment_method);
        currentExportUrl.transaksi = "/laporan/export?" + p.toString();
        loadPreview("pdfPreviewTransaksi", currentExportUrl.transaksi);
        openModal("cmExportTransaksi");
      });
    }

    var btnCetakTransaksi = document.getElementById("btnCetakTransaksi");
    if (btnCetakTransaksi) {
      btnCetakTransaksi.addEventListener("click", function () {
        window.location.href = currentExportUrl.transaksi + "&print=1";
      });
    }

    var btnUnduhTransaksi = document.getElementById("btnUnduhTransaksi");
    if (btnUnduhTransaksi) {
      btnUnduhTransaksi.addEventListener("click", function () {
        var f = getFilters();
        var p = new URLSearchParams();
        p.set("tab", "transaksi");
        p.set("tipe", f.tipe);
        p.set("download", "1");
        if (f.tanggal_dari) p.set("tanggal_dari", f.tanggal_dari);
        if (f.tanggal_sampai) p.set("tanggal_sampai", f.tanggal_sampai);
        if (f.id_pelanggan) p.set("id_pelanggan", f.id_pelanggan);
        if (f.nm_supplier) p.set("nm_supplier", f.nm_supplier);
        if (f.id_kategori) p.set("id_kategori", f.id_kategori);
        if (f.nama_produk) p.set("nama_produk", f.nama_produk);
        if (f.payment_method) p.set("payment_method", f.payment_method);
        window.location.href = "/laporan/export?" + p.toString();
        closeModal(document.getElementById("cmExportTransaksi"));
      });
    }

    // Rugi Laba
    var filterTahunRugiLaba = document.getElementById("filterTahunRugiLaba");
    if (filterTahunRugiLaba) {
      filterTahunRugiLaba.addEventListener("change", function () {
        if (activeTab === "rugi-laba") initRugiLabaDatatable();
      });
    }

    var btnResetRugiLaba = document.getElementById("btnResetRugiLaba");
    if (btnResetRugiLaba) {
      btnResetRugiLaba.addEventListener("click", function () {
        document.getElementById("filterTahunRugiLaba").value =
          new Date().getFullYear();
        if (activeTab === "rugi-laba") initRugiLabaDatatable();
      });
    }

    var btnExportRugiLaba = document.getElementById("btnExportRugiLaba");
    if (btnExportRugiLaba) {
      btnExportRugiLaba.addEventListener("click", function () {
        var tahun = document.getElementById("filterTahunRugiLaba").value;
        currentExportUrl.rugiLaba =
          "/laporan/export?tab=rugi-laba&tahun=" + tahun;
        loadPreview("pdfPreviewRugiLaba", currentExportUrl.rugiLaba);
        openModal("cmExportRugiLaba");
      });
    }

    var btnCetakRugiLaba = document.getElementById("btnCetakRugiLaba");
    if (btnCetakRugiLaba) {
      btnCetakRugiLaba.addEventListener("click", function () {
        window.location.href = currentExportUrl.rugiLaba + "&print=1";
      });
    }

    var btnUnduhRugiLaba = document.getElementById("btnUnduhRugiLaba");
    if (btnUnduhRugiLaba) {
      btnUnduhRugiLaba.addEventListener("click", function () {
        var tahun = document.getElementById("filterTahunRugiLaba").value;
        window.location.href =
          "/laporan/export?tab=rugi-laba&tahun=" + tahun + "&download=1";
        closeModal(document.getElementById("cmExportRugiLaba"));
      });
    }

    // Keuangan filters
    [
      "filterKeuTanggalDari",
      "filterKeuTanggalSampai",
      "filterKeuTipeArus",
      "filterKeuAkun",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("change", function () {
          if (activeTab === "keuangan") initKeuanganDatatable();
        });
      }
    });

    var btnResetKeuangan = document.getElementById("btnResetKeuangan");
    if (btnResetKeuangan) {
      btnResetKeuangan.addEventListener("click", function () {
        document.getElementById("filterKeuTanggalDari").value = "";
        document.getElementById("filterKeuTanggalSampai").value = "";
        document.getElementById("filterKeuTipeArus").value = "";
        document.getElementById("filterKeuAkun").value = "";
        if (activeTab === "keuangan") initKeuanganDatatable();
      });
    }

    var btnExportKeuangan = document.getElementById("btnExportKeuangan");
    if (btnExportKeuangan) {
      btnExportKeuangan.addEventListener("click", function () {
        currentExportUrl.keuangan =
          "/laporan/export?" + getKeuParams().toString();
        loadPreview("pdfPreviewKeuangan", currentExportUrl.keuangan);
        openModal("cmExportKeuangan");
      });
    }

    var btnCetakKeuangan = document.getElementById("btnCetakKeuangan");
    if (btnCetakKeuangan) {
      btnCetakKeuangan.addEventListener("click", function () {
        window.location.href = currentExportUrl.keuangan + "&print=1";
      });
    }

    var btnUnduhKeuangan = document.getElementById("btnUnduhKeuangan");
    if (btnUnduhKeuangan) {
      btnUnduhKeuangan.addEventListener("click", function () {
        var p = getKeuParams();
        p.set("download", "1");
        window.location.href = "/laporan/export?" + p.toString();
        closeModal(document.getElementById("cmExportKeuangan"));
      });
    }
  }

  // INIT
  initEventListeners();
  applyPeriode("month");
  applyFilters();
})();
