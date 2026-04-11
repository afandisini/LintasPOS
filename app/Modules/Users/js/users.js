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
    document.addEventListener("click", function (event) {
      var openBtn =
        event.target instanceof Element
          ? event.target.closest("[data-cm-open]")
          : null;
      if (openBtn) {
        var id = openBtn.getAttribute("data-cm-open") || "";
        if (id !== "") openModal(id);
        return;
      }
      var closeBtn =
        event.target instanceof Element
          ? event.target.closest("[data-cm-close]")
          : null;
      if (closeBtn) {
        closeModal(closeBtn);
        return;
      }
      if (
        event.target instanceof Element &&
        event.target.hasAttribute("data-cm-bg")
      ) {
        closeModal(event.target);
      }
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

  // ── Hak Akses ──────────────────────────────────────────────────
  var hakAksesUserId = 0;

  function renderHakAkses(data) {
    var fiturs = data.fiturs || [];
    var isAdmin = !!data.is_admin;

    if (fiturs.length === 0) {
      return '<div style="padding:20px;text-align:center;color:var(--text-muted)">Tidak ada fitur tersedia.</div>';
    }

    // Kelompokkan per group
    var groups = {};
    fiturs.forEach(function (f) {
      var g = f.group || 'Umum';
      if (!groups[g]) groups[g] = [];
      groups[g].push(f);
    });

    var html = '<table style="width:100%;border-collapse:collapse;font-size:13px">';
    html += '<thead><tr style="border-bottom:1px solid var(--border-color);background:var(--bg-tertiary)">';
    html += '<th style="padding:10px 16px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700">Fitur</th>';
    html += '<th style="padding:10px 8px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;width:72px">Akses</th>';
    html += '<th style="padding:10px 8px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;width:72px">Tambah</th>';
    html += '<th style="padding:10px 8px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;width:72px">Edit</th>';
    html += '<th style="padding:10px 8px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);font-weight:700;width:72px">Hapus</th>';
    html += '</tr></thead><tbody>';

    Object.keys(groups).forEach(function (groupName) {
      html += '<tr><td colspan="5" style="padding:8px 16px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);background:var(--bg-primary)">' + esc(groupName) + '</td></tr>';

      groups[groupName].forEach(function (f) {
        var key = f.key;
        var isDashboard = key === 'dashboard';
        var disabled = isAdmin || isDashboard ? ' disabled' : '';
        var disabledAccess = isAdmin || isDashboard ? ' disabled' : '';

        function chk(name, val) {
          var checked = val ? ' checked' : '';
          return '<input type="checkbox" data-key="' + esc(key) + '" data-col="' + name + '"' + checked + disabled + ' style="width:16px;height:16px;accent-color:var(--accent);cursor:' + (disabled ? 'not-allowed' : 'pointer') + '">';
        }

        html += '<tr style="border-bottom:1px solid var(--border-color)">';
        html += '<td style="padding:10px 16px;font-weight:500">' + esc(f.label) + '</td>';
        html += '<td style="text-align:center;padding:10px 8px"><input type="checkbox" data-key="' + esc(key) + '" data-col="can_access"' + (f.can_access ? ' checked' : '') + disabledAccess + ' style="width:16px;height:16px;accent-color:var(--accent);cursor:' + (disabledAccess ? 'not-allowed' : 'pointer') + '"></td>';
        html += '<td style="text-align:center;padding:10px 8px">' + chk('can_create', f.can_create) + '</td>';
        html += '<td style="text-align:center;padding:10px 8px">' + chk('can_edit', f.can_edit) + '</td>';
        html += '<td style="text-align:center;padding:10px 8px">' + chk('can_delete', f.can_delete) + '</td>';
        html += '</tr>';
      });
    });

    html += '</tbody></table>';

    if (isAdmin) {
      html += '<div style="padding:10px 16px;font-size:12px;color:var(--success);background:var(--success-light);border-top:1px solid var(--border-color)"><i class="bi bi-shield-fill-check"></i> Administrator memiliki akses penuh ke semua fitur secara otomatis.</div>';
    }

    return html;
  }

  function bindHakAksesButtons() {
    document.addEventListener("click", function (event) {
      var btn = event.target instanceof Element ? event.target.closest(".btn-user-hak_akses") : null;
      if (!(btn instanceof HTMLElement)) return;

      var id = Number(btn.getAttribute("data-id") || "0");
      var row = Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : null;
      if (!row) return;

      hakAksesUserId = id;
      var nameEl = document.getElementById("hakAksesUserName");
      if (nameEl) nameEl.textContent = safe(row.name, "-");

      var body = document.getElementById("hakAksesBody");
      if (body) body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px"><i class="bi bi-arrow-repeat spin-icon"></i> Memuat...</div>';

      openModal("cmHakAkses");

      fetch("/users/" + id + "/hak-akses")
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (body) body.innerHTML = renderHakAkses(data);
        })
        .catch(function () {
          if (body) body.innerHTML = '<div style="padding:20px;text-align:center;color:var(--danger)">Gagal memuat data.</div>';
        });
    });

    var saveBtn = document.getElementById("btnSaveHakAkses");
    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        var body = document.getElementById("hakAksesBody");
        if (!body || hakAksesUserId <= 0) return;

        var checkboxes = body.querySelectorAll("input[type=checkbox][data-key]");
        var map = {};
        checkboxes.forEach(function (cb) {
          var key = cb.getAttribute("data-key") || "";
          var col = cb.getAttribute("data-col") || "";
          if (!key || !col) return;
          if (!map[key]) map[key] = { key: key, can_access: 0, can_create: 0, can_edit: 0, can_delete: 0 };
          map[key][col] = cb.checked ? 1 : 0;
        });

        var permissions = Object.values(map);
        saveBtn.disabled = true;
        saveBtn.textContent = 'Menyimpan...';

        fetch("/users/" + hakAksesUserId + "/hak-akses", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ permissions: permissions })
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.success) {
              closeAllModals();
            } else {
              alert("Gagal menyimpan: " + (res.error || 'Unknown error'));
            }
          })
          .catch(function () { alert("Gagal menyimpan hak akses."); })
          .finally(function () {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-shield-check"></i> Simpan Akses';
          });
      });
    }
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
              '<button type="button" class="qa-btn btn-sm btn-user-hak_akses" data-id="' +
              id +
              '">' +
              '<i class="bi bi-shield-check"></i></button>' +
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
    bindHakAksesButtons();
  });
})();
