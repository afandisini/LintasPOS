(function () {
    var html = document.documentElement;
    var key = 'pos_theme';

    function getTheme() {
        return localStorage.getItem(key) || 'light';
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem(key, theme);

        var icon = document.querySelector('#themeBtn i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }

        updateChartColors(theme);
    }

    window.toggleTheme = function toggleTheme() {
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    };

    window.toggleSidebar = function toggleSidebar() {
        var sb = document.getElementById('sidebar');
        var ov = document.getElementById('sbOverlay');
        if (!sb || !ov) return;
        sb.classList.toggle('open');
        ov.classList.toggle('show');
    };

    window.closeSidebar = function closeSidebar() {
        var sb = document.getElementById('sidebar');
        var ov = document.getElementById('sbOverlay');
        if (!sb || !ov) return;
        sb.classList.remove('open');
        ov.classList.remove('show');
    };

    var userMenu = document.getElementById('navUserMenu');
    var userToggle = document.getElementById('navUserToggle');

    function closeUserMenu() {
        if (!userMenu || !userToggle) return;
        userMenu.classList.remove('open');
        userToggle.setAttribute('aria-expanded', 'false');
    }

    function toggleUserMenu() {
        if (!userMenu || !userToggle) return;
        var open = userMenu.classList.toggle('open');
        userToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (userToggle) {
        userToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleUserMenu();
        });
    }

    document.addEventListener('click', function (e) {
        if (!userMenu) return;
        if (!userMenu.contains(e.target)) closeUserMenu();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
            closeUserMenu();
        }
    });

    var labels = window.dashboardChartLabels || [];
    var values = window.dashboardChartValues || [];
    var chart;

    function chartColors(theme) {
        var dark = theme === 'dark';
        return {
            line: '#d97706',
            fill: dark ? 'rgba(217,119,6,0.2)' : 'rgba(217,119,6,0.12)',
            grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
            text: dark ? '#a8a29e' : '#78716c'
        };
    }

    function initChart(theme) {
        var canvas = document.getElementById('revenueChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var c = chartColors(theme);
        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pendapatan',
                    data: values,
                    borderColor: c.line,
                    backgroundColor: c.fill,
                    borderWidth: 2.5,
                    fill: true,
                    tension: .35,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { color: c.text },
                        grid: { color: c.grid }
                    },
                    y: {
                        ticks: {
                            color: c.text,
                            callback: function (v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); }
                        },
                        grid: { color: c.grid }
                    }
                }
            }
        });
    }

    function updateChartColors(theme) {
        if (!chart) return;
        var c = chartColors(theme);
        chart.data.datasets[0].backgroundColor = c.fill;
        chart.data.datasets[0].borderColor = c.line;
        chart.options.scales.x.ticks.color = c.text;
        chart.options.scales.y.ticks.color = c.text;
        chart.options.scales.x.grid.color = c.grid;
        chart.options.scales.y.grid.color = c.grid;
        chart.update('none');
    }

    applyTheme(getTheme());
    initChart(getTheme());
})();
