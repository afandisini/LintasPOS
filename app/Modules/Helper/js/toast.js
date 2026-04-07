(function () {
    function resolveType(type) {
        var t = String(type || 'info').toLowerCase();
        if (['success', 'error', 'warning', 'info'].indexOf(t) === -1) return 'info';
        return t;
    }

    function theme(type) {
        return {
            success: { icon: 'bi-check-circle-fill', color: 'var(--success)', bg: 'var(--success-light)' },
            error: { icon: 'bi-x-circle-fill', color: 'var(--danger)', bg: 'var(--danger-light)' },
            warning: { icon: 'bi-exclamation-triangle-fill', color: 'var(--accent)', bg: 'var(--accent-light)' },
            info: { icon: 'bi-info-circle-fill', color: 'var(--info)', bg: 'var(--info-light)' },
        }[type];
    }

    function ensureWrap() {
        var wrap = document.getElementById('toastWrap');
        if (wrap) return wrap;
        wrap = document.createElement('div');
        wrap.id = 'toastWrap';
        wrap.className = 'toast-wrap';
        document.body.appendChild(wrap);
        return wrap;
    }

    function showToast(message, type, timeout) {
        var toastType = resolveType(type);
        var cfg = theme(toastType);
        var wrap = ensureWrap();

        var node = document.createElement('div');
        node.className = 'toast-i';
        node.innerHTML =
            '<span class="toast-icon" style="background:' + cfg.bg + ';color:' + cfg.color + '"><i class="bi ' + cfg.icon + '"></i></span>' +
            '<div class="toast-msg">' + String(message || '') + '</div>';

        wrap.appendChild(node);

        var delay = typeof timeout === 'number' ? timeout : 3000;
        setTimeout(function () {
            node.classList.add('out');
            setTimeout(function () {
                if (node.parentNode) node.parentNode.removeChild(node);
            }, 320);
        }, delay);
    }

    window.appToast = showToast;

    var queued = Array.isArray(window.__APP_TOASTS) ? window.__APP_TOASTS : [];
    queued.forEach(function (item, idx) {
        setTimeout(function () {
            showToast(item.message || '', item.type || 'info');
        }, idx * 220);
    });
})();
