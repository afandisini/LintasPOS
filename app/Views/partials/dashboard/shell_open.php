<?php
// app/Views/partials/dashboard/shell_open.php

/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
?>
<body>
    <?= raw(view('partials/dashboard/sidebar', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'dashboard'])) ?>
    <?= raw(view('partials/dashboard/navbar', ['auth' => $auth])) ?>
