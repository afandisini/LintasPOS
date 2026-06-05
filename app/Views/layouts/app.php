<?php
// app/Views/layouts/app.php
/** @var string $title */
/** @var string $content */
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= e($title ?? brand_name()) ?></title>

  <!-- Bootstrap (local vendor after preset:bootstrap) -->
  <link rel="stylesheet" href="<?= e(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">

  <!-- Aiti theme -->
  <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">

  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
  <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle dark mode" onclick="toggleTheme(event)">
    <span class="theme-toggle-track">
      <i class="bi bi-moon-stars-fill theme-icon theme-icon-moon"></i>
      <i class="bi bi-sun-fill theme-icon theme-icon-sun"></i>
    </span>
  </button>

  <?= $content ?>

  <script defer src="<?= e(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script>
    (function () {
      var root = document.documentElement;
      var key = 'aiti_theme';
      var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function themeBackground(theme) {
        return theme === 'dark' ? 'rgba(11, 17, 32, 0.10)' : 'rgba(244, 248, 255, 0.10)';
      }

      function updateIcon(theme) {
        var moon = document.querySelector('.theme-icon-moon');
        var sun = document.querySelector('.theme-icon-sun');
        if (moon && sun) {
          if (theme === 'light') {
            moon.style.opacity = '1';
            moon.style.transform = 'rotate(0deg) scale(1)';
            sun.style.opacity = '0';
            sun.style.transform = 'rotate(-90deg) scale(0.5)';
          } else {
            moon.style.opacity = '0';
            moon.style.transform = 'rotate(90deg) scale(0.5)';
            sun.style.opacity = '1';
            sun.style.transform = 'rotate(0deg) scale(1)';
          }
        }
      }

      function applyTheme(theme) {
        if (theme === 'light') {
          root.setAttribute('data-theme', 'light');
        } else {
          root.removeAttribute('data-theme');
        }
        localStorage.setItem(key, theme);
        updateIcon(theme);
      }

      function revealTheme(theme, event, done) {
        if (reduceMotion) {
          done();
          return;
        }

        var button = (event && event.currentTarget && event.currentTarget.getBoundingClientRect)
          ? event.currentTarget
          : document.getElementById('themeToggle');
        var rect = button && button.getBoundingClientRect ? button.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2, width: 0, height: 0 };
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

      window.toggleTheme = function toggleTheme(event) {
        var current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        var next = current === 'light' ? 'dark' : 'light';
        revealTheme(next, event, function () {
          applyTheme(next);
        });
      };

      var stored = localStorage.getItem(key);
      var prefersDark = !window.matchMedia || window.matchMedia('(prefers-color-scheme: dark)').matches;
      var initial = stored || (prefersDark ? 'dark' : 'light');
      applyTheme(initial);

      var toggle = document.getElementById('themeToggle');
      if (!toggle) return;
    })();
  </script>
</body>
</html>
