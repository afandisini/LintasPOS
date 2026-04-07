(function () {
  var rowCache = {};
  var tableInstance = null;
  var currentTab = "keuangan";

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  function closeModal(element) {
    var modal =
      element instanceof HTMLElement && element.classList.contains("cm-bg")
        ? element
        : element instanceof HTMLElement
          ? element.closest(".cm-bg")
          : null;
    if (!modal) return;
    modal.classList.remove("show");
    if (!document.querySelector(".cm-bg.show")) {
      document.body.style.overflow = "";
    }
  }

  function bindModalHandlers() {
    document.querySelectorAll("[data-cm-open]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-cm-open") || "";
        if (id !== "") openModal(id);
      });
    });
    document.addEventListener("click", function (e) {
      var closeBtn = e.target.closest("[data-cm-close]");
      if (closeBtn) closeModal(closeBtn);
    });
    document.querySelectorAll("[data-cm-bg]").forEach(function (bg) {
      bg.addEventListener("click", function (e) {
        if (e.target === bg) closeModal(bg);
      });
    });
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatRp(num) {
    var n = parseInt(String(num == null ? "0" : num), 10) || 0;
    return "Rp " + n.toLocaleString("id-ID");
  }

  function getTabMeta(tab) {
    if (tab === "akun") {
      return {
        desc: "Data master akun keuangan.",
        addText: "Tambah Akun",
        addTitle: "Tambah Akun",
        editTitle: "Edit Akun",
        addAction: "create_akun",
        editAction: "update_akun",
        deleteAction: "delete_akun",
        headers: ["Kode Akun", "Nama Akun", "Kategori", "Tipe Arus", "Status"],
      };
    }
    return {
      desc: "Mutasi pemasukan/pengeluaran.",
      addText: "Tambah Input Keuangan",
      addTitle: "Tambah Input Keuangan",
      editTitle: "Edit Input Keuangan",
      addAction: "create_keuangan",
      editAction: "update_keuangan",
      deleteAction: "delete_keuangan",
      headers: ["Tanggal", "No Ref", "Akun", "Tipe Arus", "Nominal"],
    };
  }

  function setInputValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    if (el.type === "checkbox") {
      el.checked = String(value) === "1";
      return;
    }
    el.value = String(value == null ? "" : value);
  }

  function setModeBlocks(root, modeClassToShow) {
    if (!root) return;
    root.querySelectorAll(".keu-mode").forEach(function (block) {
      var isActive = block.classList.contains(modeClassToShow);
      block.style.display = isActive ? "grid" : "none";
      block.querySelectorAll("input,select,textarea,button").forEach(function (el) {
        if (el.type === "hidden") return;
        el.disabled = !isActive;
      });
    });
  }

  function renderTableHead(headers) {
    var row = document.getElementById("keuanganTableHeadRow");
    if (!row) return;
    var html = "<th>No</th>";
    headers.forEach(function (label) {
      html += "<th>" + escapeHtml(label) + "</th>";
    });
    html += "<th>Aksi</th>";
    row.innerHTML = html;
  }

  function setMode(tab, cfg, pushUrl) {
    currentTab = tab === "akun" ? "akun" : "keuangan";
    var meta = getTabMeta(currentTab);

    document.querySelectorAll("[data-keu-tab]").forEach(function (el) {
      var isActive = el.getAttribute("data-keu-tab") === currentTab;
      el.classList.toggle("is-active", isActive);
    });

    var desc = document.getElementById("keuTabDesc");
    if (desc) desc.textContent = meta.desc;
    var addBtnText = document.getElementById("keuAddBtnText");
    if (addBtnText) addBtnText.textContent = meta.addText;
    var addTitle = document.getElementById("cmAddKeuanganTitle");
    if (addTitle) addTitle.textContent = meta.addTitle;
    var editTitle = document.getElementById("cmEditKeuanganTitle");
    if (editTitle) editTitle.textContent = meta.editTitle;

    setInputValue("add_action", meta.addAction);
    setInputValue("edit_action", meta.editAction);
    setInputValue("delete_action", meta.deleteAction);
    setInputValue("add_tab", currentTab);
    setInputValue("edit_tab", currentTab);
    setInputValue("delete_tab", currentTab);

    setModeBlocks(document.getElementById("formAddKeuangan"), currentTab === "akun" ? "keu-mode-akun" : "keu-mode-keuangan");
    setModeBlocks(document.getElementById("formEditKeuangan"), currentTab === "akun" ? "keu-mode-akun" : "keu-mode-keuangan");
    renderTableHead(meta.headers);

    if (pushUrl) {
      var nextUrl = cfg.inputPageUrl + "?tab=" + currentTab;
      window.history.replaceState({}, "", nextUrl);
    }
  }

  function buildColumns(tab) {
    var cols = [
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (data, type, row, meta) {
          var start =
            meta && meta.settings && meta.settings._iDisplayStart
              ? meta.settings._iDisplayStart
              : 0;
          return start + meta.row + 1;
        },
      },
    ];

    if (tab === "akun") {
      cols.push(
        { data: "kode_akun", defaultContent: "-" },
        { data: "nama_akun", defaultContent: "-" },
        { data: "kategori", defaultContent: "-" },
        { data: "tipe_arus", defaultContent: "-" },
        { data: "status_text", defaultContent: "-" },
      );
    } else {
      cols.push(
        { data: "tanggal", defaultContent: "-" },
        { data: "no_ref", defaultContent: "-" },
        {
          data: null,
          defaultContent: "-",
          render: function (data, type, row) {
            return (
              escapeHtml(row.kode_akun || "-") +
              " - " +
              escapeHtml(row.nama_akun || "-")
            );
          },
        },
        { data: "tipe_arus", defaultContent: "-" },
        {
          data: "nominal",
          defaultContent: "0",
          render: function (data) {
            return formatRp(data);
          },
        },
      );
    }

    cols.push({
      data: null,
      orderable: false,
      searchable: false,
      render: function (data, type, row) {
        var id = parseInt(String(row && row.id ? row.id : 0), 10) || 0;
        rowCache[id] = row || {};
        var label =
          tab === "akun"
            ? row.nama_akun || row.kode_akun || ("ID " + id)
            : row.no_ref || ("ID " + id);
        return (
          '<div class="d-flex gap-2">' +
          '<button type="button" class="btn-g btn-sm btn-keu-edit" data-id="' +
          id +
          '"><i class="bi bi-pencil-square"></i></button>' +
          '<button type="button" class="btn-a btn-sm btn-keu-delete" data-id="' +
          id +
          '" data-label="' +
          escapeHtml(label) +
          '"><i class="bi bi-trash3"></i></button>' +
          "</div>"
        );
      },
    });

    return cols;
  }

  function initDataTable(cfg) {
    if (
      typeof window.jQuery === "undefined" ||
      typeof window.jQuery.fn.DataTable === "undefined"
    )
      return;

    if (tableInstance) {
      tableInstance.destroy();
      window.jQuery("#keuanganTable tbody").html(
        '<tr><td colspan="7" class="text-muted">Memuat data...</td></tr>',
      );
    }

    rowCache = {};
    tableInstance = window.jQuery("#keuanganTable").DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ordering: true,
      lengthChange: true,
      pageLength: 10,
      scrollX: true,
      language: { url: cfg.languageUrl || "" },
      ajax: { url: cfg.datatableBaseUrl + "?tab=" + currentTab, type: "GET" },
      columns: buildColumns(currentTab),
    });
  }

  function bindActions() {
    document.addEventListener("click", function (e) {
      var editBtn = e.target.closest(".btn-keu-edit");
      if (editBtn) {
        var id = parseInt(editBtn.getAttribute("data-id") || "0", 10) || 0;
        var row = rowCache[id] || null;
        if (!row) return;

        setInputValue("edit_id", id);
        if (currentTab === "akun") {
          setInputValue("edit_kode_akun", row.kode_akun || "");
          setInputValue("edit_nama_akun", row.nama_akun || "");
          setInputValue("edit_kategori", row.kategori || "lainnya");
          setInputValue("edit_tipe_arus_akun", row.tipe_arus || "netral");
          setInputValue("edit_deskripsi_akun", row.deskripsi || "");
          setInputValue("edit_status", row.status || "1");
          setInputValue("edit_is_kas", row.is_kas || "0");
          setInputValue("edit_is_modal", row.is_modal || "0");
        } else {
          setInputValue("edit_tanggal", row.tanggal || "");
          setInputValue("edit_no_ref", row.no_ref || "");
          setInputValue("edit_akun_keuangan_id", row.akun_keuangan_id || "");
          setInputValue("edit_tipe_arus_keuangan", row.tipe_arus || "netral");
          setInputValue("edit_nominal", row.nominal || "0");
          setInputValue("edit_metode_pembayaran", row.metode_pembayaran || "");
          setInputValue("edit_deskripsi_keuangan", row.deskripsi || "");
        }
        openModal("cmEditKeuangan");
        return;
      }

      var delBtn = e.target.closest(".btn-keu-delete");
      if (delBtn) {
        var deleteId = parseInt(delBtn.getAttribute("data-id") || "0", 10) || 0;
        setInputValue("delete_id", deleteId);
        var label = delBtn.getAttribute("data-label") || "-";
        var targetLabel = document.getElementById("delete_label");
        if (targetLabel) targetLabel.textContent = label;
        openModal("cmDeleteKeuangan");
      }
    });
  }

  function bindTabs(cfg) {
    document.querySelectorAll("[data-keu-tab]").forEach(function (el) {
      el.addEventListener("click", function (e) {
        e.preventDefault();
        var next = el.getAttribute("data-keu-tab") || "keuangan";
        if (next === currentTab) return;
        setMode(next, cfg, true);
        initDataTable(cfg);
      });
    });
  }

  function init() {
    var cfg = window.keuanganCrudConfig || null;
    if (!cfg) return;
    bindModalHandlers();
    bindActions();
    bindTabs(cfg);

    var initial = cfg.activeTab === "akun" ? "akun" : "keuangan";
    var params = new URLSearchParams(window.location.search || "");
    var fromUrl = params.get("tab");
    if (fromUrl === "akun" || fromUrl === "keuangan") {
      initial = fromUrl;
    }
    setMode(initial, cfg, false);
    initDataTable(cfg);
  }

  init();
})();
