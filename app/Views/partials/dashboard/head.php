<?php
// app/Views/partials/dashboard/head.php

/** @var string $title */
/** @var string|null $extraHead */
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($title ?? 'Admin Panel') ?></title>
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('pos_theme');
                var preferDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', saved || (preferDark ? 'dark' : 'light'));
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <link href="<?= e(base_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/dashboard.css')) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/toast.css')) ?>" rel="stylesheet">
    <script>
        (function() {
            document.addEventListener('click', function(e) {
                var openBtn = e.target.closest('[data-cm-open]');
                if (openBtn) {
                    e.preventDefault();
                    var modalId = openBtn.getAttribute('data-cm-open');
                    var modal = document.getElementById(modalId);
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.classList.add('show');
                    }
                }
                var closeBtn = e.target.closest('[data-cm-close]');
                if (closeBtn) {
                    e.preventDefault();
                    var modal = closeBtn.closest('[data-cm-bg]');
                    if (modal) {
                        modal.classList.remove('show');
                        setTimeout(function() { modal.style.display = 'none'; }, 250);
                    }
                }
            });
            document.addEventListener('click', function(e) {
                if (e.target.hasAttribute('data-cm-bg') && e.target.classList.contains('show')) {
                    e.target.classList.remove('show');
                    setTimeout(function() { e.target.style.display = 'none'; }, 250);
                }
            });
        })();
    </script>
    <?php if (isset($extraHead) && trim((string) $extraHead) !== ''): ?>
        <?= raw((string) $extraHead) ?>
    <?php endif; ?>
</head>
