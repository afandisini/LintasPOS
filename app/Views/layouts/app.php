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
  <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle dark mode">
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
      var stored = localStorage.getItem(key);
      var prefersDark = !window.matchMedia || window.matchMedia('(prefers-color-scheme: dark)').matches;
      var initial = stored || (prefersDark ? 'dark' : 'light');
      if (initial === 'light') root.setAttribute('data-theme', 'light');

      var toggle = document.getElementById('themeToggle');
      if (!toggle) return;

      toggle.addEventListener('click', function () {
        var current = root.getAttribute('data-theme');
        var next = current === 'light' ? 'dark' : 'light';
        if (next === 'light') {
          root.setAttribute('data-theme', 'light');
        } else {
          root.removeAttribute('data-theme');
        }
        localStorage.setItem(key, next);
      });
    })();
  </script>
</body>
</html>
