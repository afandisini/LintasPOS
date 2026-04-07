(function () {
  var rowCache = {};

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

  function closeAllModals() {
    document.querySelectorAll(".cm-bg.show").forEach(function (m) {
      m.classList.remove("show");
    });
    document.body.style.overflow = "";
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function safeString(value) {
    var normalized = normalizeCellValue(value);
    var text = normalized == null ? "" : String(normalized);
    return text !== "" ? text : "-";
  }

  function normalizeCellValue(value) {
    if (value == null) return "";
    if (
      typeof value === "string" ||
      typeof value === "number" ||
      typeof value === "boolean"
    ) {
      return value;
    }
    if (Array.isArray(value)) {
      return value
        .map(function (item) {
          return normalizeCellValue(item);
        })
        .join(", ");
    }
    if (typeof value === "object") {
      if (Object.prototype.hasOwnProperty.call(value, "display"))
        return value.display;
      if (Object.prototype.hasOwnProperty.call(value, "label"))
        return value.label;
      if (Object.prototype.hasOwnProperty.call(value, "value"))
        return value.value;
      if (Object.prototype.hasOwnProperty.call(value, "text"))
        return value.text;
      return "";
    }
    return "";
  }

  function pickRowValue(row, key, fallback) {
    if (!row || typeof row !== "object") return fallback;
    if (Object.prototype.hasOwnProperty.call(row, key)) return row[key];
    var lower = String(key || "").toLowerCase();
    if (lower !== "" && Object.prototype.hasOwnProperty.call(row, lower))
      return row[lower];
    var upper = String(key || "").toUpperCase();
    if (upper !== "" && Object.prototype.hasOwnProperty.call(row, upper))
      return row[upper];
    return fallback;
  }

  function getDisplayValue(row, fieldName) {
    var relationLabelKey = String(fieldName || "") + "__label";
    var relationValue = pickRowValue(row, relationLabelKey, null);
    if (normalizeCellValue(relationValue) !== "") {
      return relationValue;
    }
    return pickRowValue(row, fieldName, "");
  }

  function buildActionButtons(row, cfg) {
    var id = Number(row && row.id ? row.id : 0);
    if (!Number.isFinite(id) || id <= 0) {
      return "-";
    }
    rowCache[id] = row || {};

    var firstColumnName = "";
    if (Array.isArray(cfg.displayColumns) && cfg.displayColumns.length > 0) {
      firstColumnName = String(cfg.displayColumns[0].name || "");
    }
    var label =
      firstColumnName !== ""
        ? safeString(getDisplayValue(row, firstColumnName))
        : "ID " + id;
    var updateUrl = "/" + cfg.routePrefix + "/" + id + "/update";
    var deleteUrl = "/" + cfg.routePrefix + "/" + id + "/delete";

    return (
      "" +
      '<div class="d-flex gap-2">' +
      '<button type="button" class="btn-g btn-sm btn-generated-edit" data-id="' +
      id +
      '" data-action="' +
      escapeHtml(updateUrl) +
      '">' +
      '<i class="bi bi-pencil-square"></i></button>' +
      '<button type="button" class="btn-a btn-sm btn-generated-delete" data-id="' +
      id +
      '" data-label="' +
      escapeHtml(label) +
      '" data-action="' +
      escapeHtml(deleteUrl) +
      '">' +
      '<i class="bi bi-trash3"></i></button>' +
      "</div>"
    );
  }

  function initDatatable() {
    var cfg = window.generatedCrudConfig || null;
    if (!cfg || !cfg.datatableUrl) return;
    if (
      typeof window.jQuery === "undefined" ||
      typeof window.jQuery.fn.DataTable === "undefined"
    )
      return;

    var columns = [
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

    var displayColumns = Array.isArray(cfg.displayColumns)
      ? cfg.displayColumns
      : [];
    displayColumns.forEach(function (column) {
      let name = String((column && column.name) || "").trim();
      if (name === "") return;
      columns.push({
        data: null,
        defaultContent: "",
        render: function (data, type, row) {
          var value = getDisplayValue(row, name);
          return escapeHtml(safeString(value));
        },
      });
    });

    columns.push({
      data: null,
      orderable: false,
      searchable: false,
      render: function (data, type, row) {
        return buildActionButtons(row, cfg);
      },
    });

    window.jQuery("#generatedTable").DataTable({
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
      columns: columns,
    });
  }

  function bindGenericModalHandlers() {
    document.querySelectorAll("[data-cm-open]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-cm-open") || "";
        if (id !== "") openModal(id);
      });
    });

    document.querySelectorAll("[data-cm-close]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        closeModal(btn);
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

  function bindEditButtons() {
    var editForm = document.getElementById("formEditGenerated");
    document.addEventListener("click", function (event) {
      var button =
        event.target instanceof Element
          ? event.target.closest(".btn-generated-edit")
          : null;
      if (!(button instanceof HTMLElement)) return;
      if (!editForm) return;

      var action = button.getAttribute("data-action") || "";
      if (action !== "") {
        editForm.setAttribute("action", action);
      }

      var id = Number(button.getAttribute("data-id") || "0");
      var row =
        Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : {};
      Object.keys(row).forEach(function (key) {
        var el = document.getElementById("edit_" + key);
        if (!el) return;
        var val = row[key] == null ? "" : String(row[key]);
        if (
          el.tagName === "TEXTAREA" ||
          el.tagName === "INPUT" ||
          el.tagName === "SELECT"
        ) {
          el.value = val;
        }
      });

      openModal("cmEditGenerated");
    });
  }

  function bindDeleteButtons() {
    var deleteForm = document.getElementById("formDeleteGenerated");
    var deleteLabel = document.getElementById("delete_generated_label");
    document.addEventListener("click", function (event) {
      var button =
        event.target instanceof Element
          ? event.target.closest(".btn-generated-delete")
          : null;
      if (!(button instanceof HTMLElement)) return;
      if (!deleteForm) return;

      var action = button.getAttribute("data-action") || "";
      var label = button.getAttribute("data-label") || "-";
      if (action !== "") {
        deleteForm.setAttribute("action", action);
      }
      if (deleteLabel) {
        deleteLabel.textContent = label;
      }
      openModal("cmDeleteGenerated");
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeAllModals();
    }
  });

  function init() {
    initDatatable();
    bindGenericModalHandlers();
    bindEditButtons();
    bindDeleteButtons();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
