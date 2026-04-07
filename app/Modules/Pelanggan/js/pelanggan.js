(function () {
    var rowCache = {};

    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(element) {
        var modal = element instanceof HTMLElement && element.classList.contains('cm-bg')
            ? element
            : (element instanceof HTMLElement ? element.closest('.cm-bg') : null);
        if (!modal) return;
        modal.classList.remove('show');
        if (!document.querySelector('.cm-bg.show')) {
            document.body.style.overflow = '';
        }
    }

    function closeAllModals() {
        document.querySelectorAll('.cm-bg.show').forEach(function (m) {
            m.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getInitials(value) {
        var text = String(value == null ? '' : value).trim();
        if (text === '') return '?';
        var parts = text.split(/\s+/).filter(Boolean);
        var picked = [];
        if (parts.length > 0) picked.push(parts[0].charAt(0));
        if (parts.length > 1) picked.push(parts[parts.length - 1].charAt(0));
        var initials = picked.join('').toUpperCase();
        if (initials !== '') return initials;
        return text.charAt(0).toUpperCase();
    }

    function stripTags(value) {
        return String(value == null ? '' : value).replace(/<[^>]*>/g, '').trim();
    }

    function looksLikeImageFieldName(name) {
        return /(gambar|image|img|foto|photo|thumbnail|thumb|avatar|logo)/i.test(String(name || ''));
    }

    function looksLikeImageUrl(url) {
        var text = String(url == null ? '' : url).trim();
        if (text === '') return false;
        if (/^data:image\//i.test(text)) return true;
        return /\.(png|jpe?g|gif|webp|svg|bmp|ico)(\?.*)?$/i.test(text);
    }

    function getRowDisplayName(row) {
        var preferredKeys = ['nama_pelanggan', 'nama', 'name', 'judul', 'title', 'kode_pelanggan', 'kode', 'id'];
        for (var i = 0; i < preferredKeys.length; i += 1) {
            var candidate = safeString(getDisplayValue(row, preferredKeys[i]));
            if (candidate !== '-') return stripTags(candidate);
        }
        var keys = Object.keys(row || {});
        for (var j = 0; j < keys.length; j += 1) {
            var raw = normalizeCellValue(row[keys[j]]);
            var text = String(raw == null ? '' : raw).trim();
            if (text !== '') return stripTags(text);
        }
        return 'Tanpa Nama';
    }

    function extractImageUrlFromHtml(html) {
        var text = String(html == null ? '' : html).trim();
        if (text === '') return '';
        var match = text.match(/<img[^>]+src\s*=\s*["']?([^"' >]+)["']?[^>]*>/i);
        return match && match[1] ? String(match[1]).trim() : '';
    }

    function createImageFallbackHtml(imageUrl, initials, alt, cssClass) {
        var safeInitials = escapeHtml(initials || '?');
        var safeAlt = escapeHtml(alt || 'Gambar');
        var fallback = '<span class="generated-avatar-fallback" aria-hidden="true">' + safeInitials + '</span>';
        if (String(imageUrl || '').trim() === '') {
            return '<div class="' + cssClass + '">' + fallback + '</div>';
        }
        return '<div class="' + cssClass + '">' +
            '<img src="' + escapeHtml(imageUrl) + '" alt="' + safeAlt + '" onerror="this.remove();">' +
            fallback +
            '</div>';
    }

    function resolveImagePreview(row, cfg) {
        var displayName = getRowDisplayName(row);
        var initials = getInitials(displayName);
        var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];

        for (var i = 0; i < displayColumns.length; i += 1) {
            var fieldName = String((displayColumns[i] && displayColumns[i].name) || '').trim();
            if (fieldName === '' || !looksLikeImageFieldName(fieldName)) continue;

            var helperHtml = getDisplayHtml(row, fieldName);
            var imageFromHtml = extractImageUrlFromHtml(helperHtml);
            if (imageFromHtml !== '') {
                return { url: imageFromHtml, initials: initials, alt: displayName };
            }

            var rawValue = normalizeCellValue(getDisplayValue(row, fieldName));
            if (looksLikeImageUrl(rawValue)) {
                return { url: String(rawValue).trim(), initials: initials, alt: displayName };
            }
        }

        var keys = Object.keys(row || {});
        for (var j = 0; j < keys.length; j += 1) {
            var key = String(keys[j] || '');
            if (!looksLikeImageFieldName(key)) continue;
            var directRaw = normalizeCellValue(row[key]);
            if (looksLikeImageUrl(directRaw)) {
                return { url: String(directRaw).trim(), initials: initials, alt: displayName };
            }
        }

        return { url: '', initials: initials, alt: displayName };
    }

    function safeString(value) {
        var normalized = normalizeCellValue(value);
        var text = normalized == null ? '' : String(normalized);
        return text !== '' ? text : '-';
    }

    function normalizeCellValue(value) {
        if (value == null) return '';
        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
            return value;
        }
        if (Array.isArray(value)) {
            return value.map(function (item) { return normalizeCellValue(item); }).join(', ');
        }
        if (typeof value === 'object') {
            if (Object.prototype.hasOwnProperty.call(value, 'display')) return value.display;
            if (Object.prototype.hasOwnProperty.call(value, 'label')) return value.label;
            if (Object.prototype.hasOwnProperty.call(value, 'value')) return value.value;
            if (Object.prototype.hasOwnProperty.call(value, 'text')) return value.text;
            return '';
        }
        return '';
    }

    function pickRowValue(row, key, fallback) {
        if (!row || typeof row !== 'object') return fallback;
        if (Object.prototype.hasOwnProperty.call(row, key)) return row[key];
        var lower = String(key || '').toLowerCase();
        if (lower !== '' && Object.prototype.hasOwnProperty.call(row, lower)) return row[lower];
        var upper = String(key || '').toUpperCase();
        if (upper !== '' && Object.prototype.hasOwnProperty.call(row, upper)) return row[upper];
        return fallback;
    }

    function getDisplayValue(row, fieldName) {
        var helperDisplayKey = String(fieldName || '') + '__display';
        var helperDisplayValue = pickRowValue(row, helperDisplayKey, null);
        if (normalizeCellValue(helperDisplayValue) !== '') {
            return helperDisplayValue;
        }
        var relationLabelKey = String(fieldName || '') + '__label';
        var relationValue = pickRowValue(row, relationLabelKey, null);
        if (normalizeCellValue(relationValue) !== '') {
            return relationValue;
        }
        return pickRowValue(row, fieldName, '');
    }

    function getDisplayHtml(row, fieldName) {
        var helperHtmlKey = String(fieldName || '') + '__html';
        var helperHtmlValue = pickRowValue(row, helperHtmlKey, '');
        var html = String(helperHtmlValue == null ? '' : helperHtmlValue).trim();
        return html !== '' ? html : '';
    }

    function buildActionButtons(row, cfg) {
        var id = Number(row && row.id ? row.id : 0);
        if (!Number.isFinite(id) || id <= 0) {
            return '-';
        }
        rowCache[id] = row || {};

        var firstColumnName = '';
        if (Array.isArray(cfg.displayColumns) && cfg.displayColumns.length > 0) {
            firstColumnName = String(cfg.displayColumns[0].name || '');
        }
        var label = firstColumnName !== '' ? safeString(getDisplayValue(row, firstColumnName)) : ('ID ' + id);
        var updateUrl = '/' + cfg.routePrefix + '/' + id + '/update';
        var deleteUrl = '/' + cfg.routePrefix + '/' + id + '/delete';

        return '' +
            '<div class="d-flex gap-2">' +
            '<button type="button" class="btn-g btn-sm btn-generated-detail" data-id="' + id + '" title="Detail">' +
            '<i class="bi bi-eye"></i></button>' +
            '<button type="button" class="btn-g btn-sm btn-generated-edit" data-id="' + id + '" data-action="' + escapeHtml(updateUrl) + '">' +
            '<i class="bi bi-pencil-square"></i></button>' +
            '<button type="button" class="btn-a btn-sm btn-generated-delete" data-id="' + id + '" data-label="' + escapeHtml(label) + '" data-action="' + escapeHtml(deleteUrl) + '">' +
            '<i class="bi bi-trash3"></i></button>' +
            '</div>';
    }

    function initDatatable() {
        var cfg = window.generatedCrudConfig || null;
        if (!cfg || !cfg.datatableUrl) return;
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') return;

        var columns = [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row, meta) {
                    var start = meta && meta.settings && meta.settings._iDisplayStart ? meta.settings._iDisplayStart : 0;
                    return start + meta.row + 1;
                }
            }
        ];

        var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];
        displayColumns.forEach(function (column) {
            let name = String((column && column.name) || '').trim();
            if (name === '') return;
            columns.push({
                data: null,
                defaultContent: '',
                render: function (data, type, row) {
                    var helperHtml = getDisplayHtml(row, name);
                    if (helperHtml !== '') {
                        return helperHtml;
                    }
                    var value = getDisplayValue(row, name);
                    return escapeHtml(safeString(value));
                }
            });
        });

        columns.push({
            data: null,
            orderable: false,
            searchable: false,
            render: function (data, type, row) {
                return buildActionButtons(row, cfg);
            }
        });

        window.jQuery('#generatedTable').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            lengthChange: true,
            pageLength: 10,
            scrollX: true,
            language: {
                url: cfg.languageUrl || ''
            },
            ajax: {
                url: cfg.datatableUrl,
                type: 'GET'
            },
            columns: columns
        });
    }

    function bindGenericModalHandlers() {
        document.addEventListener('click', function (event) {
            var openBtn = event.target instanceof Element ? event.target.closest('[data-cm-open]') : null;
            if (openBtn) {
                var id = openBtn.getAttribute('data-cm-open') || '';
                if (id !== '') openModal(id);
                return;
            }
            var closeBtn = event.target instanceof Element ? event.target.closest('[data-cm-close]') : null;
            if (closeBtn) {
                closeModal(closeBtn);
                return;
            }
            if (event.target instanceof Element && event.target.hasAttribute('data-cm-bg')) {
                closeModal(event.target);
            }
        });
    }

    function bindEditButtons() {
        var editForm = document.getElementById('formEditGenerated');
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-edit') : null;
            if (!(button instanceof HTMLElement)) return;
            if (!editForm) return;

            var action = button.getAttribute('data-action') || '';
            if (action !== '') {
                editForm.setAttribute('action', action);
            }

            var id = Number(button.getAttribute('data-id') || '0');
            var row = Number.isFinite(id) && id > 0 && rowCache[id] ? rowCache[id] : {};
            Object.keys(row).forEach(function (key) {
                var el = document.getElementById('edit_' + key);
                if (!el) return;
                var val = row[key] == null ? '' : String(row[key]);
                if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                    el.value = val;
                }
            });

            openModal('cmEditGenerated');
        });
    }

    function bindDeleteButtons() {
        var deleteForm = document.getElementById('formDeleteGenerated');
        var deleteLabel = document.getElementById('delete_generated_label');
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-delete') : null;
            if (!(button instanceof HTMLElement)) return;
            if (!deleteForm) return;

            var action = button.getAttribute('data-action') || '';
            var label = button.getAttribute('data-label') || '-';
            if (action !== '') {
                deleteForm.setAttribute('action', action);
            }
            if (deleteLabel) {
                deleteLabel.textContent = label;
            }
            openModal('cmDeleteGenerated');
        });
    }

    function bindDetailButtons() {
        var titleEl = document.getElementById('cmDetailGeneratedTitle');
        var contentEl = document.getElementById('generated_detail_content');
        var cfg = window.generatedCrudConfig || {};
        document.addEventListener('click', function (event) {
            var button = event.target instanceof Element ? event.target.closest('.btn-generated-detail') : null;
            if (!(button instanceof HTMLElement)) return;

            var id = Number(button.getAttribute('data-id') || '0');
            if (!Number.isFinite(id) || id <= 0) return;
            var row = rowCache[id] || null;
            if (!row || typeof row !== 'object') return;

            if (titleEl) {
                var nama = getRowDisplayName(row);
                titleEl.textContent = 'Detail Data - ' + nama;
            }

            if (contentEl) {
                var preview = resolveImagePreview(row, cfg);
                var html = '<div class="generated-detail-layout">';
                html += '<div class="panel generated-detail-media">';
                html += '<div class="panel-head"><span class="panel-title">Gambar</span></div>';
                html += '<div class="panel-body">';
                html += createImageFallbackHtml(preview.url, preview.initials, preview.alt, 'generated-detail-preview');
                html += '</div></div>';
                html += '<div class="panel generated-detail-info">';
                html += '<div class="panel-head"><span class="panel-title">Detail</span></div>';
                html += '<div class="panel-body"><div class="table-responsive"><table class="dtable table-sm align-middle mb-0 generated-detail-table"><tbody>';
                var displayColumns = Array.isArray(cfg.displayColumns) ? cfg.displayColumns : [];
                displayColumns.forEach(function (column) {
                    var name = String((column && column.name) || '').trim();
                    if (name === '') return;
                    if (looksLikeImageFieldName(name)) return;
                    var label = String((column && column.label) || name);
                    var helperHtml = getDisplayHtml(row, name);
                    var displayValue = helperHtml !== '' ? helperHtml : escapeHtml(safeString(getDisplayValue(row, name)));
                    html += '<tr>';
                    html += '<th>' + escapeHtml(label) + '</th>';
                    html += '<td>' + displayValue + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div></div>';
                html += '</div>';
                html += '</div>';
                contentEl.innerHTML = html;
            }

            openModal('cmDetailGenerated');
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    function init() {
        initDatatable();
        bindGenericModalHandlers();
        bindEditButtons();
        bindDeleteButtons();
        bindDetailButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
