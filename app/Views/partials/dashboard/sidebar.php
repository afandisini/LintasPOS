<?php
// app/Views/partials/dashboard/sidebar.php

/** @var array<string,mixed> $auth */
/** @var string $activeMenu */

$rawUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentPath = (string) (parse_url($rawUri, PHP_URL_PATH) ?: '/');
$isDashboard = $currentPath === '/dashboard';
$isUsers = $currentPath === '/users' || str_starts_with($currentPath, '/users/');
$isFilemanager = $currentPath === '/filemanager' || str_starts_with($currentPath, '/filemanager/');
$isToko = $currentPath === '/toko' || str_starts_with($currentPath, '/toko/');
$isMenuGenerator = $currentPath === '/menu-generator' || str_starts_with($currentPath, '/menu-generator/');

// Fallback to explicit flag from controller/view when needed.
if (($activeMenu ?? '') === 'dashboard') {
    $isDashboard = true;
}
if (($activeMenu ?? '') === 'users') {
    $isUsers = true;
}
if (($activeMenu ?? '') === 'filemanager') {
    $isFilemanager = true;
}
if (($activeMenu ?? '') === 'toko') {
    $isToko = true;
}
if (($activeMenu ?? '') === 'menu-generator') {
    $isMenuGenerator = true;
}

$generatedMenus = menu_generator_sidebar_items();
$generatedMenuGroups = [];
foreach ($generatedMenus as $menu) {
    if (!is_array($menu)) {
        continue;
    }
    $groupKey = trim((string) ($menu['parent_menu_key'] ?? ''));
    if ($groupKey === '') {
        $groupKey = 'modul-generator';
    }
    if (!isset($generatedMenuGroups[$groupKey]) || !is_array($generatedMenuGroups[$groupKey])) {
        $generatedMenuGroups[$groupKey] = [];
    }
    $generatedMenuGroups[$groupKey][] = $menu;
}
?>
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">{{brand_logo}}</div>
        <span class="brand-text">{{nama_toko}}</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Menu Utama</div>
        <a class="s-link <?= e($isDashboard ? 'active' : '') ?>" href="<?= e(site_url('dashboard')) ?>"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>

        <?php if ($generatedMenuGroups !== []): ?>
            <?php foreach ($generatedMenuGroups as $groupKey => $menus): ?>
                <?php
                $label = ucwords(str_replace(['-', '_'], ' ', (string) $groupKey));
                $groupKeyNormalized = strtolower(trim((string) $groupKey));
                $visibleMenus = [];
                $groupMenuCount = count($menus);
                foreach ($menus as $menu) {
                    if (!is_array($menu)) {
                        continue;
                    }
                    $routePrefix = trim((string) ($menu['route_prefix'] ?? ''), '/');
                    if ($routePrefix === '') {
                        continue;
                    }

                    // Hide parent container link when a group has children.
                    // Example: transaksi parent (/transaksi) + children (/transaksi/penjualan, /transaksi/pembelian)
                    if ($groupMenuCount > 1 && strtolower($routePrefix) === $groupKeyNormalized) {
                        continue;
                    }
                    $visibleMenus[] = $menu;
                }
                if ($visibleMenus === []) {
                    $visibleMenus = $menus;
                }
                ?>
                <div class="nav-label"><?= e($label) ?></div>
                <?php foreach ($visibleMenus as $menu): ?>
                    <?php
                    $routePrefix = trim((string) ($menu['route_prefix'] ?? ''), '/');
                    if ($routePrefix === '') {
                        continue;
                    }
                    $moduleName = (string) ($menu['module_name'] ?? $routePrefix);
                    $menuTitle = trim((string) ($menu['menu_title'] ?? ''));
                    $menuIcon = (string) ($menu['menu_icon'] ?? 'bi bi-grid-3x3-gap-fill');
                    $menuPath = '/' . $routePrefix;
                    $isGeneratedActive = $currentPath === $menuPath || str_starts_with($currentPath, $menuPath . '/');
                    $menuLabel = $menuTitle !== '' ? $menuTitle : $moduleName;
                    ?>
                    <a class="s-link <?= e($isGeneratedActive ? 'active' : '') ?>" href="<?= e(site_url($routePrefix)) ?>">
                        <i class="<?= e($menuIcon) ?>"></i><span><?= e($menuLabel) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="nav-label">Media</div>
        <a class="s-link <?= e($isFilemanager ? 'active' : '') ?>" href="<?= e(site_url('filemanager')) ?>"><i class="bi bi-folder2-open"></i><span>File Manager</span></a>

        <div class="nav-label">Pengaturan</div>
        <a class="s-link <?= e($isUsers ? 'active' : '') ?>" href="<?= e(site_url('users')) ?>"><i class="bi bi-people-fill"></i><span>Pengguna</span></a>
        <a class="s-link <?= e($isToko ? 'active' : '') ?>" href="<?= e(site_url('toko')) ?>"><i class="bi bi-shop"></i><span>Toko</span></a>
        <div class="sb-midline anim" aria-hidden="true">
            <span class="sb-midline-bar"></span>
            <span class="sb-midline-dot"><i class="bi bi-gift"></i></span>
            <span class="sb-midline-text">Spesial Fitur</span>
            <span class="sb-midline-bar"></span>
        </div>
        <a class="s-link <?= e($isMenuGenerator ? 'active' : '') ?>" href="<?= e(site_url('menu-generator')) ?>"><i class="bi bi-columns-gap"></i><span>Menu Generator</span></a>

    </nav>

    <div class="sidebar-footer">
        <div class="sf-meta">&copy; <?= date('Y') ?>. {{nama_toko}}</div>
        <div class="sf-store"><span class="fw-light">Powered by</span> <?= e(framework_credit()) ?></div>
    </div>
</aside>
