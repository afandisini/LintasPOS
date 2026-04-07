<?php
// app/Views/auth/login.php
/** @var string $title */
/** @var string $message */
/** @var string $messageType */
/** @var string $oldIdentity */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? ('Login ' . brand_name())) ?></title>
    <script>
        (function () {
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
    <link href="<?= e(base_url('assets/css/login.css')) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/toast.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="shell">
    <?= raw(view('partials/auth/login_left')) ?>
    <?= raw(view('partials/auth/login_right', [
        'message' => (string) ($message ?? ''),
        'messageType' => (string) ($messageType ?? ''),
        'oldIdentity' => (string) ($oldIdentity ?? ''),
    ])) ?>
</div>

<?= raw(view('partials/shared/toast')) ?>

<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Auth/js/login.js')) ?>
</body>
</html>
