(function () {
    var html = document.documentElement;
    var btn = document.getElementById('themeBtn');
    if (!btn) return;

    var key = 'pos_theme';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function themeBackground(theme) {
        return theme === 'dark' ? 'rgba(11, 17, 32, 0.10)' : 'rgba(244, 248, 255, 0.10)';
    }

    function apply(theme) {
        html.setAttribute('data-theme', theme);
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
        localStorage.setItem(key, theme);
    }

    function revealTheme(theme, event, done) {
        if (reduceMotion) {
            done();
            return;
        }

        var rect = (event && event.currentTarget && event.currentTarget.getBoundingClientRect)
            ? event.currentTarget.getBoundingClientRect()
            : btn.getBoundingClientRect();
        var x = rect.left + rect.width / 2;
        var y = rect.top + rect.height / 2;
        var maxX = Math.max(x, window.innerWidth - x);
        var maxY = Math.max(y, window.innerHeight - y);
        var radius = Math.hypot(maxX, maxY);
        var overlay = document.createElement('div');

        overlay.setAttribute('aria-hidden', 'true');
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.zIndex = '9999';
        overlay.style.pointerEvents = 'none';
        overlay.style.background = themeBackground(theme);
        overlay.style.backdropFilter = 'blur(14px)';
        overlay.style.webkitBackdropFilter = 'blur(14px)';
        overlay.style.opacity = '1';
        overlay.style.webkitClipPath = 'circle(0px at ' + x + 'px ' + y + 'px)';
        overlay.style.clipPath = 'circle(0px at ' + x + 'px ' + y + 'px)';
        overlay.style.transition = 'clip-path 560ms cubic-bezier(0.22, 1, 0.36, 1), -webkit-clip-path 560ms cubic-bezier(0.22, 1, 0.36, 1), opacity 200ms ease-out';
        overlay.style.willChange = 'clip-path';

        document.body.appendChild(overlay);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                overlay.style.webkitClipPath = 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)';
                overlay.style.clipPath = 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)';
            });
        });

        var finished = false;
        var finish = function () {
            if (finished) return;
            finished = true;
            overlay.removeEventListener('transitionend', onTransitionEnd);
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            done();
        };

        var onTransitionEnd = function (e) {
            if (e && e.target === overlay && e.propertyName === 'clip-path') {
                finish();
            }
        };

        overlay.addEventListener('transitionend', onTransitionEnd);
        window.setTimeout(finish, 700);
    }

    apply(localStorage.getItem(key) || 'dark');
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        var next = (html.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
        revealTheme(next, e, function () {
            apply(next);
        });
    });
})();
