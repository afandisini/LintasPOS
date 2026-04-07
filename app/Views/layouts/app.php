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
    <i class="bi bi-moon-stars-fill"></i>
    <i class="bi bi-sun-fill"></i>
  </button>

  <?= $content ?>

  <script defer src="<?= e(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script>
    (function () {
      const root = document.documentElement;
      const key = 'aiti_theme';
      const stored = localStorage.getItem(key);
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const initial = stored || (prefersDark ? 'dark' : 'light');
      root.setAttribute('data-theme', initial);

      const toggle = document.getElementById('themeToggle');
      if (!toggle) {
        return;
      }

      toggle.addEventListener('click', function () {
        const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        localStorage.setItem(key, next);
      });
    })();
  </script>
</body>
</html>
