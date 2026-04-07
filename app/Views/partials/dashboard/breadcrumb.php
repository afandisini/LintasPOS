<?php
// app/Views/partials/dashboard/breadcrumb.php

/** @var array<int,array<string,string>> $items */
/** @var string $current */

$items = is_array($items ?? null) ? $items : [];
$current = trim((string) ($current ?? ''));
?>
<nav class="app-breadcrumb anim mb-0" aria-label="Breadcrumb">
    <ol class="app-breadcrumb-list">
        <?php foreach ($items as $item): ?>
            <?php
            $label = trim((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($label === '') {
                continue;
            }
            ?>
            <li class="app-breadcrumb-item">
                <?php if ($url !== ''): ?>
                    <a href="<?= e($url) ?>"><?= e($label) ?></a>
                <?php else: ?>
                    <span><?= e($label) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if ($current !== ''): ?>
            <li class="app-breadcrumb-item is-current" aria-current="page"><?= e($current) ?></li>
        <?php endif; ?>
    </ol>
</nav>