(function () {
    var html = document.documentElement;
    var btn = document.getElementById('themeBtn');
    if (!btn) return;

    var key = 'pos_theme';

    function apply(theme) {
        html.setAttribute('data-theme', theme);
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
        localStorage.setItem(key, theme);
    }

    apply(localStorage.getItem(key) || 'dark');
    btn.addEventListener('click', function () {
        apply((html.getAttribute('data-theme') === 'dark') ? 'light' : 'dark');
    });
})();
