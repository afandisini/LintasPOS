(function () {
  var pendingForm = null;

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
    document.querySelectorAll("[data-cm-open]").forEach(function (button) {
      button.addEventListener("click", function () {
        var id = button.getAttribute("data-cm-open") || "";
        if (id !== "") {
          openModal(id);
        }
      });
    });

    document.querySelectorAll("[data-cm-close]").forEach(function (button) {
      button.addEventListener("click", function () {
        closeModal(button);
      });
    });
    document.querySelectorAll("[data-cm-bg]").forEach(function (bg) {
      bg.addEventListener("click", function (event) {
        if (event.target === bg) {
          closeModal(bg);
        }
      });
    });
  }

  function bindConfirmModal() {
    var confirmMessage = document.getElementById(
      "cmMenuGeneratorConfirmMessage",
    );
    var confirmButton = document.getElementById("cmMenuGeneratorConfirmBtn");
    if (!confirmMessage || !confirmButton) {
      return;
    }

    document.addEventListener("submit", function (event) {
      var form = event.target;
      if (
        !(form instanceof HTMLFormElement) ||
        !form.classList.contains("js-confirm-action")
      ) {
        return;
      }
      event.preventDefault();
      var message =
        form.getAttribute("data-confirm-message") ||
        "Yakin melanjutkan aksi ini?";
      pendingForm = form;
      confirmMessage.textContent = message;
      openModal("cmMenuGeneratorConfirm");
    });

    confirmButton.addEventListener("click", function () {
      if (!pendingForm) {
        closeModal(confirmButton);
        return;
      }
      var form = pendingForm;
      pendingForm = null;
      closeModal(confirmButton);
      form.submit();
    });
  }

  function badgeClass(status) {
    if (status === "generated") return "suc";
    if (status === "disabled") return "dng";
    return "wrn";
  }

  function statusLabel(status) {
    if (status === "generated") return "Generated";
    if (status === "disabled") return "Disabled";
    return "Draft";
  }

  function buildActionButtons(row, cfg) {
    var token = row.signed_token || "";
    var editUrl =
      (cfg.editBaseUrl || "/menu-generator/edit?id=") +
      encodeURIComponent(token);
    var csrf = cfg.csrfToken || "";

    return (
      "" +
      '<div class="d-flex gap-1 flex-wrap">' +
      '<a class="btn-g btn-sm" href="' +
      editUrl +
      '"><i class="bi bi-pencil-square me-1"></i><span>Edit</span></a>' +
      '<form method="post" action="' +
      cfg.generateUrl +
      '">' +
      '<input type="hidden" name="_token" value="' +
      csrf +
      '">' +
      '<input type="hidden" name="id" value="' +
      token +
      '">' +
      '<button type="submit" class="btn-a btn-sm"><i class="bi bi-gear-wide-connected me-1"></i><span>Generate</span></button>' +
      "</form>" +
      '<form method="post" action="' +
      cfg.deleteGeneratedUrl +
      '" class="js-confirm-action" data-confirm-message="Hapus file hasil generate modul ini?">' +
      '<input type="hidden" name="_token" value="' +
      csrf +
      '">' +
      '<input type="hidden" name="id" value="' +
      token +
      '">' +
      '<button type="submit" class="btn-g btn-sm"><i class="bi bi-trash3 me-1"></i><span>Delete Generated</span></button>' +
      "</form>" +
      '<form method="post" action="' +
      cfg.deleteUrl +
      '" class="js-confirm-action" data-confirm-message="Nonaktifkan konfigurasi ini?">' +
      '<input type="hidden" name="_token" value="' +
      csrf +
      '">' +
      '<input type="hidden" name="id" value="' +
      token +
      '">' +
      '<button type="submit" class="btn-g btn-sm"><i class="bi bi-slash-circle me-1"></i><span>Disable</span></button>' +
      "</form>" +
      "</div>"
    );
  }

  function initDatatable() {
    if (typeof window.menuGeneratorConfig === "undefined") return;
    if (
      typeof window.jQuery === "undefined" ||
      typeof window.jQuery.fn.DataTable === "undefined"
    )
      return;

    var cfg = window.menuGeneratorConfig;
    window.jQuery("#mgTable").DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ordering: true,
      lengthChange: true,
      pageLength: 10,
      language: {
        url: cfg.languageUrl || "",
      },
      ajax: {
        url: cfg.datatableUrl,
        type: "GET",
      },
      columns: [
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, row, meta) {
            var start = meta && meta.settings && meta.settings._iDisplayStart
              ? meta.settings._iDisplayStart
              : 0;
            return start + meta.row + 1;
          },
        },
        { data: "module_name" },
        { data: "table_name" },
        {
          data: "status",
          render: function (data) {
            var status = String(data || "draft");
            return (
              '<span class="sbadge ' +
              badgeClass(status) +
              '"><span class="sd"></span>' +
              statusLabel(status) +
              "</span>"
            );
          },
        },
        {
          data: "last_generated_at",
          render: function (data) {
            return data ? data : "-";
          },
        },
        {
          data: "updated_at",
          render: function (data) {
            return data ? data : "-";
          },
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, row) {
            return buildActionButtons(row, cfg);
          },
        },
      ],
      drawCallback: function () {
        // submit confirmation bindings work via delegated document listener.
      },
    });
  }

  function slugify(value) {
    return String(value || "")
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function toPascalCase(value) {
    var text = String(value || "")
      .replace(/[_-]+/g, " ")
      .trim();
    if (text === "") return "";
    return text
      .split(/\s+/)
      .map(function (part) {
        return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
      })
      .join("");
  }

  function updateFieldsPreview(fields) {
    var table = document.getElementById("mgFieldsPreviewTable");
    var info = document.getElementById("mgScanInfo");
    var hidden = document.getElementById("fields_json");
    if (!table || !hidden) return;

    var tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!Array.isArray(fields) || fields.length < 1) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-muted">Belum ada hasil scan.</td></tr>';
      if (info) info.textContent = "Belum ada hasil scan.";
      hidden.value = "[]";
      return;
    }

    fields.forEach(function (field, index) {
      var tr = document.createElement("tr");
      tr.innerHTML =
        "" +
        "<td>" +
        (index + 1) +
        "</td>" +
        "<td>" +
        (field.field_name || "") +
        "</td>" +
        "<td>" +
        (field.field_label || "") +
        "</td>" +
        "<td>" +
        (field.html_type || "text") +
        "</td>" +
        "<td>" +
        (field.auto_rule || "-") +
        "</td>" +
        "<td>" +
        (Number(field.is_system_field || 0) === 1 ? "Ya" : "Tidak") +
        "</td>";
      tbody.appendChild(tr);
    });

    if (info) info.textContent = "Total field: " + fields.length;
    hidden.value = JSON.stringify(fields);
  }

  function bindScanTable() {
    if (typeof window.menuGeneratorFormConfig === "undefined") return;
    var cfg = window.menuGeneratorFormConfig;
    var scanButton = document.getElementById("mgScanTableBtn");
    var tableInput = document.getElementById("table_name");
    var form = document.getElementById("mgConfigForm");

    if (!scanButton || !tableInput || !form) return;

    function autoFill() {
      var moduleName = document.getElementById("module_name");
      var moduleSlug = document.getElementById("module_slug");
      var controllerName = document.getElementById("controller_name");
      var viewFolder = document.getElementById("view_folder");
      var routePrefix = document.getElementById("route_prefix");
      var menuTitle = document.getElementById("menu_title");
      if (
        !moduleName ||
        !moduleSlug ||
        !controllerName ||
        !viewFolder ||
        !routePrefix ||
        !menuTitle
      ) {
        return;
      }

      var slug = slugify(
        moduleSlug.value || moduleName.value || tableInput.value,
      );
      if (moduleSlug.value.trim() === "") moduleSlug.value = slug;
      if (controllerName.value.trim() === "")
        controllerName.value = toPascalCase(slug) + "Controller";
      if (viewFolder.value.trim() === "") viewFolder.value = slug;
      if (routePrefix.value.trim() === "") routePrefix.value = slug;
      if (menuTitle.value.trim() === "")
        menuTitle.value = moduleName.value || toPascalCase(slug);
    }

    var moduleNameInput = document.getElementById("module_name");
    if (moduleNameInput) {
      moduleNameInput.addEventListener("blur", autoFill);
    }

    scanButton.addEventListener("click", function () {
      var tableName = String(tableInput.value || "").trim();
      if (tableName === "") {
        alert("Pilih nama tabel terlebih dahulu.");
        return;
      }

      scanButton.disabled = true;
      var originalHtml = scanButton.innerHTML;
      scanButton.innerHTML =
        '<i class="bi bi-arrow-repeat me-1"></i><span>Scanning...</span>';

      fetch(cfg.scanUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        body:
          "_token=" +
          encodeURIComponent(cfg.csrfToken || "") +
          "&table_name=" +
          encodeURIComponent(tableName),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (payload) {
          if (!payload || payload.ok !== true) {
            alert(
              payload && payload.message
                ? payload.message
                : "Scan tabel gagal.",
            );
            return;
          }
          updateFieldsPreview(payload.fields || []);
          autoFill();
        })
        .catch(function () {
          alert("Scan tabel gagal.");
        })
        .finally(function () {
          scanButton.disabled = false;
          scanButton.innerHTML =
            originalHtml ||
            '<i class="bi bi-search me-1"></i><span>Scan</span>';
        });
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      document.querySelectorAll(".cm-bg.show").forEach(function (modal) {
        modal.classList.remove("show");
      });
      document.body.style.overflow = "";
    }
  });

  document.addEventListener("DOMContentLoaded", function () {
    bindModalHandlers();
    bindConfirmModal();
    initDatatable();
    bindScanTable();
  });
})();
