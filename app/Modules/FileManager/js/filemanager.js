(function () {
    var pendingDeleteForm = null;

    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(el) {
        var modal = el instanceof HTMLElement && el.classList.contains('cm-bg')
            ? el
            : (el instanceof HTMLElement ? el.closest('.cm-bg') : null);

        if (!modal) return;
        modal.classList.remove('show');

        var remains = document.querySelector('.cm-bg.show');
        if (!remains) {
            document.body.style.overflow = '';
        }
    }

    function closeAllModals() {
        document.querySelectorAll('.cm-bg.show').forEach(function (m) {
            m.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    function bindGenericModalHandlers() {
        document.querySelectorAll('[data-cm-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-cm-open') || '';
                if (id !== '') openModal(id);
            });
        });

        document.querySelectorAll('[data-cm-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                closeModal(btn);
            });
        });

        document.querySelectorAll('[data-cm-bg]').forEach(function (bg) {
            bg.addEventListener('click', function (e) {
                if (e.target === bg) closeModal(bg);
            });
        });
    }

    function bindBulkDeleteHandlers() {
        var checkAll = document.getElementById('fmCheckAll');
        var checks = Array.prototype.slice.call(document.querySelectorAll('.fm-file-check'));
        var bulkBtn = document.getElementById('fmBulkDeleteBtn');
        var bulkForm = document.getElementById('fmBulkDeleteForm');

        if (!bulkBtn || !bulkForm) {
            return null;
        }

        var groupedChecks = {};
        checks.forEach(function (item) {
            var key = item.getAttribute('data-file-id') || item.value || '';
            if (key === '') return;
            if (!groupedChecks[key]) {
                groupedChecks[key] = [];
            }
            groupedChecks[key].push(item);
        });

        function syncGroup(source) {
            var key = source.getAttribute('data-file-id') || source.value || '';
            if (key === '' || !groupedChecks[key]) return;
            groupedChecks[key].forEach(function (item) {
                item.checked = source.checked;
            });
        }

        function syncState() {
            var checkedCount = 0;
            Object.keys(groupedChecks).forEach(function (key) {
                var rows = groupedChecks[key] || [];
                var selected = rows.some(function (item) { return item.checked; });
                if (selected) {
                    checkedCount++;
                }
            });
            bulkBtn.disabled = checkedCount < 1;

            if (checkAll) {
                var selectableChecks = checks.filter(function (item) { return !item.disabled; });
                var checkedSelectableCount = selectableChecks.filter(function (item) { return item.checked; }).length;
                checkAll.disabled = selectableChecks.length < 1;
                checkAll.checked = checkedSelectableCount > 0 && checkedSelectableCount === selectableChecks.length;
                checkAll.indeterminate = checkedSelectableCount > 0 && checkedSelectableCount < selectableChecks.length;
            }
        }

        checks.forEach(function (item) {
            item.addEventListener('change', function () {
                syncGroup(item);
                syncState();
            });
        });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                checks.forEach(function (item) {
                    if (item.disabled) return;
                    item.checked = checkAll.checked;
                    syncGroup(item);
                });
                syncState();
            });
        }

        syncState();
        return {
            syncState: syncState
        };
    }

    function bindDeleteConfirmModal() {
        var messageEl = document.getElementById('cmDeleteConfirmMessage');
        var confirmBtn = document.getElementById('cmDeleteConfirmBtn');
        if (!messageEl || !confirmBtn) return;

        function prepareMessage(form) {
            var isBulk = form.getAttribute('data-delete-bulk') === 'true';
            if (!isBulk) {
                return form.getAttribute('data-delete-message') || 'Yakin ingin menghapus data ini?';
            }

            var checks = Array.prototype.slice.call(document.querySelectorAll('.fm-file-check'));
            var checkedCount = checks.filter(function (item) { return item.checked; }).length;
            if (checkedCount < 1) {
                return '';
            }
            return 'Hapus ' + checkedCount + ' file terpilih?';
        }

        document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var msg = prepareMessage(form);
                if (msg === '') {
                    return;
                }

                pendingDeleteForm = form;
                messageEl.textContent = msg;
                openModal('cmDeleteConfirm');
            });
        });

        confirmBtn.addEventListener('click', function () {
            if (!pendingDeleteForm) {
                closeAllModals();
                return;
            }

            var form = pendingDeleteForm;
            pendingDeleteForm = null;
            closeAllModals();
            form.submit();
        });
    }

    function bindUploadFileInputLabel() {
        var input = document.getElementById('fm_upload_file');
        var label = document.getElementById('fm_upload_file_name');
        if (!input || !label) return;

        input.addEventListener('change', function () {
            var files = input.files;
            if (!files || files.length < 1) {
                label.textContent = 'Tidak ada file yang dipilih';
                return;
            }
            if (files.length === 1) {
                label.textContent = files[0].name || '1 file dipilih';
                return;
            }
            label.textContent = files.length + ' file dipilih';
        });
    }

    function bindViewSwitcher(onViewChanged) {
        var viewButtons = Array.prototype.slice.call(document.querySelectorAll('[data-fm-view]'));
        var viewPanels = Array.prototype.slice.call(document.querySelectorAll('[data-fm-view-panel]'));
        if (viewButtons.length < 1 || viewPanels.length < 1) return;

        function setViewMode(mode) {
            var activeMode = mode === 'grid' ? 'grid' : 'list';

            viewButtons.forEach(function (btn) {
                var isActive = btn.getAttribute('data-fm-view') === activeMode;
                btn.classList.toggle('is-active', isActive);
            });

            viewPanels.forEach(function (panel) {
                var panelMode = panel.getAttribute('data-fm-view-panel') || 'list';
                var isActive = panelMode === activeMode;
                panel.classList.toggle('is-hidden', !isActive);
                panel.querySelectorAll('.fm-file-check').forEach(function (check) {
                    check.disabled = !isActive;
                });
            });

            if (typeof onViewChanged === 'function') {
                onViewChanged();
            }

            try {
                window.localStorage.setItem('fm-view-mode', activeMode);
            } catch (err) {
                // no-op
            }
        }

        viewButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-fm-view') || 'list';
                setViewMode(mode);
            });
        });

        var initial = 'list';
        try {
            var saved = window.localStorage.getItem('fm-view-mode') || '';
            if (saved === 'grid' || saved === 'list') {
                initial = saved;
            }
        } catch (err) {
            // no-op
        }

        setViewMode(initial);
    }

    function bindImagePreviewModal() {
        var previewModal = document.getElementById('cmImagePreview');
        var previewImage = document.getElementById('fmPreviewImage');
        var previewTitle = document.getElementById('cmImagePreviewTitle');
        var previewName = document.getElementById('fmPreviewName');
        var previewMime = document.getElementById('fmPreviewMime');
        var previewSize = document.getElementById('fmPreviewSize');
        var previewPath = document.getElementById('fmPreviewPath');
        var previewModule = document.getElementById('fmPreviewModule');
        var previewRef = document.getElementById('fmPreviewRef');
        var previewCreated = document.getElementById('fmPreviewCreated');
        if (!previewModal || !previewImage || !previewTitle) return;

        document.querySelectorAll('.js-preview-image').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.classList.contains('is-disabled')) {
                    return;
                }

                var url = btn.getAttribute('data-preview-url') || '';
                var name = btn.getAttribute('data-preview-name') || 'Preview Gambar';
                var mime = btn.getAttribute('data-preview-mime') || '-';
                var size = btn.getAttribute('data-preview-size') || '-';
                var path = btn.getAttribute('data-preview-path') || '-';
                var module = btn.getAttribute('data-preview-module') || '-';
                var ref = btn.getAttribute('data-preview-ref') || '-';
                var created = btn.getAttribute('data-preview-created') || '-';
                if (url === '') {
                    return;
                }

                previewImage.src = url;
                previewImage.alt = name;
                previewTitle.textContent = name;
                if (previewName) previewName.textContent = name;
                if (previewMime) previewMime.textContent = mime;
                if (previewSize) previewSize.textContent = size;
                if (previewPath) previewPath.textContent = path;
                if (previewModule) previewModule.textContent = module;
                if (previewRef) previewRef.textContent = ref;
                if (previewCreated) previewCreated.textContent = created;
                openModal('cmImagePreview');
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        bindGenericModalHandlers();
        var bulkDeleteContext = bindBulkDeleteHandlers();
        bindDeleteConfirmModal();
        bindUploadFileInputLabel();
        bindImagePreviewModal();
        bindViewSwitcher(bulkDeleteContext ? bulkDeleteContext.syncState : null);
    });
})();
