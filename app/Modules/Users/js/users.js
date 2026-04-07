(function () {
  var rowCache = {};

  function val(el, fallback) {
    if (!el) return fallback || "";
    return el.value || fallback || "";
  }

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function safe(value, fallback) {
    var text = value == null ? "" : String(value);
    if (text !== "") return text;
    return fallback || "-";
  }

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  function closeModal(el) {
    var modal =
      el instanceof HTMLElement && el.classList.contains("cm-bg")
        ? el
        : el instanceof HTMLElement
          ? el.closest(".cm-bg")
          : null;

    if (!modal) return;
    modal.classList.remove("show");

    var remains = document.querySelector(".cm-bg.show");
    if (!remains) {
      document.body.style.overflow = "";
    }
  }

  function closeAllModals() {
    document.querySelectorAll(".cm-bg.show").forEach(function (m) {
      m.classList.remove("show");
    });
    document.body.style.overflow = "";
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
      bg.addEventListener("click", function (e) {
        if (e.target === bg) closeModal(bg);
      });
    });
  }

  function bindEditButtons() {
    var editForm = document.getElementById("formEditUser");
    document.addEventListener("click", function (event) {
      var btn =
        event.target instanceof Element
          ? event.target.closest(".btn-user-edit")
          : null;
      if (!(btn instanceof HTMLElement)) return;
      if (!editForm) return;

      var id = Number(btn.getAttribute("data-id") || "0");
      var row =
        Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : null;
      if (!row) return;

      editForm.action = "/users/" + id + "/update";

      var fName = document.getElementById("edit_name");
      var fUsername = document.getElementById("edit_username");
      var fEmail = document.getElementById("edit_email");
      var fTelepon = document.getElementById("edit_telepon");
      var fAlamat = document.getElementById("edit_alamat");
      var fAkses = document.getElementById("edit_akses");
      var fActive = document.getElementById("edit_active");
      var fPassword = document.getElementById("edit_password");

      var rawUsername = String(row.user || "");
      var rawEmail = String(row.email || "");
      var usernameUi = rawUsername;
      var emailUi = rawEmail;

      if (rawEmail === "" && rawUsername.indexOf("@") > 0) {
        emailUi = rawUsername;
        usernameUi = rawUsername.split("@")[0] || rawUsername;
      }

      if (fName) fName.value = String(row.name || "");
      if (fUsername) fUsername.value = usernameUi;
      if (fEmail) fEmail.value = emailUi;
      if (fTelepon) fTelepon.value = String(row.telepon || "");
      if (fAlamat) fAlamat.value = String(row.alamat || "");
      if (fAkses) fAkses.value = String(row.akses || val(fAkses, ""));
      if (fActive) fActive.value = String(row.active || "1");
      if (fPassword) fPassword.value = "";

      openModal("cmEditUser");
    });
  }

  function bindDeleteButtons() {
    var deleteForm = document.getElementById("formDeleteUser");
    var deleteName = document.getElementById("delete_user_name");

    document.addEventListener("click", function (event) {
      var btn =
        event.target instanceof Element
          ? event.target.closest(".btn-user-delete")
          : null;
      if (!(btn instanceof HTMLElement)) return;

      var id = Number(btn.getAttribute("data-id") || "0");
      var row =
        Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : null;
      if (!row) return;

      if (deleteForm) {
        deleteForm.action = "/users/" + id + "/delete";
      }
      if (deleteName) {
        deleteName.textContent = safe(row.name, "-");
      }

      openModal("cmDeleteUser");
    });
  }

  function initDatatable() {
    var cfg = window.usersCrudConfig || null;
    if (!cfg || !cfg.datatableUrl) return;
    if (
      typeof window.jQuery === "undefined" ||
      typeof window.jQuery.fn.DataTable === "undefined"
    )
      return;

    window.jQuery("#usersTable").DataTable({
      processing: true,
      serverSide: true,
      searching: true,
      ordering: true,
      lengthChange: true,
      pageLength: 10,
      language: { url: cfg.languageUrl || "" },
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
        {
          data: null,
          render: function (data, type, row) {
            var rawUsername = safe(row.user, "");
            var rawEmail = safe(row.email, "");
            var usernameUi = rawUsername;
            var emailUi = rawEmail;
            if (rawEmail === "" && rawUsername.indexOf("@") > 0) {
              emailUi = rawUsername;
              usernameUi = rawUsername.split("@")[0] || rawUsername;
            }
            return (
              "" +
              '<div class="fw-semibold">' +
              esc(safe(row.name, "-")) +
              "</div>" +
              '<div class="text-muted" style="font-size:11px">' +
              esc(safe(emailUi, "-")) +
              "</div>"
            );
          },
        },
        {
          data: "user",
          render: function (value) {
            var raw = safe(value, "");
            if (raw.indexOf("@") > 0) {
              raw = raw.split("@")[0] || raw;
            }
            return esc(safe(raw, "-"));
          },
        },
        {
          data: "role_name",
          render: function (value) {
            return esc(safe(value, "-"));
          },
        },
        {
          data: "active",
          render: function (value) {
            var active = String(value || "0") === "1";
            return (
              '<span class="sbadge ' +
              (active ? "suc" : "dng") +
              '"><span class="sd"></span>' +
              (active ? "Aktif" : "Nonaktif") +
              "</span>"
            );
          },
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, row) {
            var id = Number(row && row.id ? row.id : 0);
            if (!Number.isFinite(id) || id <= 0) return "-";
            rowCache[id] = row || {};
            return (
              "" +
              '<div class="d-flex gap-2">' +
              '<button type="button" class="btn-g btn-sm btn-user-edit" data-id="' +
              id +
              '">' +
              '<i class="bi bi-pencil-square"></i></button>' +
              '<button type="button" class="btn-a btn-sm btn-user-delete" data-id="' +
              id +
              '">' +
              '<i class="bi bi-trash3"></i></button>' +
              "</div>"
            );
          },
        },
      ],
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeAllModals();
    }
  });

  document.addEventListener("DOMContentLoaded", function () {
    initDatatable();
    bindGenericModalHandlers();
    bindEditButtons();
    bindDeleteButtons();
  });
})();
