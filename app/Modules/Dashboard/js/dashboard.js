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

// ── Global Search ────────────────────────────────────────────────
(function () {
    var input    = document.getElementById('globalSearchInput');
    var dropdown = document.getElementById('gsDropdown');
    if (!input || !dropdown) return;

    var timer    = null;
    var activeIdx = -1;
    var items    = [];

    var typeLabel = { barang: 'Barang', jasa: 'Jasa', pelanggan: 'Pelanggan', supplier: 'Supplier' };
    var iconClass = { barang: '', jasa: 'gs-icon-jasa', pelanggan: 'gs-icon-pelanggan', supplier: 'gs-icon-supplier' };

    function open()  { dropdown.classList.add('gs-open'); }
    function close() { dropdown.classList.remove('gs-open'); activeIdx = -1; }

    function render(results) {
        if (!results.length) {
            dropdown.innerHTML = '<div class="gs-empty">Tidak ada hasil ditemukan.</div>';
            open(); return;
        }

        var grouped = {};
        results.forEach(function (r) {
            if (!grouped[r.type]) grouped[r.type] = [];
            grouped[r.type].push(r);
        });

        var html = '';
        Object.keys(grouped).forEach(function (type) {
            html += '<div class="gs-group-label">' + (typeLabel[type] || type) + '</div>';
            grouped[type].forEach(function (r) {
                html += '<a class="gs-item" href="' + r.url + '">'
                    + '<div class="gs-item-icon ' + (iconClass[type] || '') + '"><i class="bi ' + r.icon + '"></i></div>'
                    + '<div class="gs-item-body">'
                    + '<div class="gs-item-label">' + esc(r.label) + '</div>'
                    + (r.sub ? '<div class="gs-item-meta">' + esc(r.sub) + '</div>' : '')
                    + (r.meta ? '<div class="gs-item-meta">' + esc(r.meta) + '</div>' : '')
                    + '</div></a>';
            });
        });

        dropdown.innerHTML = html;
        items = dropdown.querySelectorAll('.gs-item');
        open();
    }

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function search(q) {
        dropdown.innerHTML = '<div class="gs-loading"><i class="bi bi-arrow-repeat spin-icon"></i> Mencari...</div>';
        open();
        fetch('/api/search?q=' + encodeURIComponent(q))
            .then(function (r) {
                if (!r.ok) throw new Error('status ' + r.status);
                return r.json();
            })
            .then(function (data) { render(data.results || []); })
            .catch(function () {
                dropdown.innerHTML = '<div class="gs-empty">Gagal memuat hasil pencarian.</div>';
            });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { close(); return; }
        timer = setTimeout(function () { search(q); }, 300);
    });

    input.addEventListener('keydown', function (e) {
        if (!dropdown.classList.contains('gs-open')) return;
        items = dropdown.querySelectorAll('.gs-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            items[activeIdx].click();
            return;
        } else if (e.key === 'Escape') {
            close(); return;
        }
        items.forEach(function (el, i) { el.classList.toggle('gs-active', i === activeIdx); });
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('globalSearchBox').contains(e.target)) close();
    });
})();
