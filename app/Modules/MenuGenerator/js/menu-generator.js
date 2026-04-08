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
      '<div class="d-flex gap-1">' +
      '<a class="btn-g btn-sm" title="Edit" href="' +
      editUrl +
      '"><i class="bi bi-pencil-square me-1"></i></a>' +
      '<form method="post" action="' +
      cfg.generateUrl +
      '">' +
      '<input type="hidden" name="_token" value="' +
      csrf +
      '">' +
      '<input type="hidden" name="id" value="' +
      token +
      '">' +
      '<button type="submit" title="Generate" class="btn-a btn-sm"><i class="bi bi-gear-wide-connected"></i></button>' +
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
      '<button type="submit" title="Hapus Fitur" class="btn-g btn-sm"><i class="bi bi-trash3"></i></button>' +
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
      '<button type="submit" title="Nonaktifkan" class="btn-g btn-sm"><i class="bi bi-slash-circle"></i></button>' +
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
      scrollX: true,
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
            var start =
              meta && meta.settings && meta.settings._iDisplayStart
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
              '<span class="sbadge small ' +
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

  function bindMenuGeneratorTabs() {
    var tabButtons = document.querySelectorAll("[data-mg-tab]");
    if (!tabButtons || tabButtons.length < 1) {
      return;
    }

    document.addEventListener("click", function (event) {
      var button = event.target.closest("[data-mg-tab]");
      if (!button) {
        return;
      }
      event.preventDefault();
      var tab = button.getAttribute("data-mg-tab") || "config";

      tabButtons.forEach(function (btn) {
        btn.classList.toggle(
          "is-active",
          (btn.getAttribute("data-mg-tab") || "") === tab,
        );
      });
      document
        .querySelectorAll("[data-mg-content]")
        .forEach(function (content) {
          var active = (content.getAttribute("data-mg-content") || "") === tab;
          content.style.display = active ? "" : "none";
          content.classList.toggle("active", active);
        });

      if (
        tab === "config" &&
        typeof window.jQuery !== "undefined" &&
        typeof window.jQuery.fn.DataTable !== "undefined" &&
        window.jQuery.fn.dataTable.isDataTable("#mgTable")
      ) {
        window.jQuery("#mgTable").DataTable().columns.adjust();
      }

      try {
        var url = new URL(window.location.href);
        if (tab === "config") {
          url.searchParams.delete("tab");
        } else {
          url.searchParams.set("tab", tab);
        }
        window.history.replaceState({}, "", url.toString());
      } catch (error) {
        // no-op
      }
    });
  }

  function syncOrderJson(orderList, orderInput) {
    if (!orderList || !orderInput) return;
    var ids = [];
    orderList.querySelectorAll("[data-menu-id]").forEach(function (item, idx) {
      var id = Number(item.getAttribute("data-menu-id") || "0");
      if (id > 0) {
        ids.push(id);
      }
      var pos = item.querySelector(".mg-order-pos");
      if (pos) {
        pos.textContent = String(idx + 1);
      }
    });
    orderInput.value = JSON.stringify(ids);
  }

  function moveOrderItem(item, direction) {
    if (!(item instanceof HTMLElement)) return;
    if (direction === "up" && item.previousElementSibling) {
      item.parentNode.insertBefore(item, item.previousElementSibling);
      return;
    }
    if (direction === "down" && item.nextElementSibling) {
      item.parentNode.insertBefore(item.nextElementSibling, item);
    }
  }

  function bindMenuOrder() {
    var orderList = document.getElementById("mgOrderList");
    var orderInput = document.getElementById("mgOrderJson");
    var orderForm = document.getElementById("mgMenuOrderForm");
    if (!orderList || !orderInput || !orderForm) {
      return;
    }

    syncOrderJson(orderList, orderInput);

    var dragItem = null;

    orderList.addEventListener("dragstart", function (event) {
      var item = event.target.closest(".mg-order-item");
      if (!(item instanceof HTMLElement)) {
        return;
      }
      dragItem = item;
      item.classList.add("is-dragging");
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
      }
    });

    orderList.addEventListener("dragend", function (event) {
      var item = event.target.closest(".mg-order-item");
      if (item) {
        item.classList.remove("is-dragging");
      }
      dragItem = null;
      syncOrderJson(orderList, orderInput);
    });

    orderList.addEventListener("dragover", function (event) {
      if (!dragItem) return;
      event.preventDefault();
      var target = event.target.closest(".mg-order-item");
      if (!(target instanceof HTMLElement) || target === dragItem) {
        return;
      }
      var rect = target.getBoundingClientRect();
      var shouldInsertBefore = event.clientY < rect.top + rect.height / 2;
      if (shouldInsertBefore) {
        orderList.insertBefore(dragItem, target);
      } else {
        orderList.insertBefore(dragItem, target.nextElementSibling);
      }
      syncOrderJson(orderList, orderInput);
    });

    orderList.addEventListener("click", function (event) {
      var actionButton = event.target.closest("[data-order-action]");
      if (!actionButton) return;
      var item = actionButton.closest(".mg-order-item");
      if (!item) return;
      var action = actionButton.getAttribute("data-order-action") || "";
      if (action === "up" || action === "down") {
        moveOrderItem(item, action);
        syncOrderJson(orderList, orderInput);
      }
    });

    orderForm.addEventListener("submit", function () {
      syncOrderJson(orderList, orderInput);
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
    bindMenuGeneratorTabs();
    bindModalHandlers();
    bindConfirmModal();
    initDatatable();
    bindScanTable();
    bindMenuOrder();
  });
})();
