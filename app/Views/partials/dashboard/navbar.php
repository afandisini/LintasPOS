<?php
// app/Views/partials/dashboard/navbar.php

/** @var array<string,mixed> $auth */

$avatar = avatar_meta($auth['avatar'] ?? null, (string) ($auth['name'] ?? 'User'));
?>
<header class="topnav" id="topnav">
    <div class="t-left">
        <button class="btn-sb" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="bi bi-list"></i></button>
        <div class="search-box" id="globalSearchBox">
            <i class="bi bi-search"></i>
            <input type="text" id="globalSearchInput" placeholder="Cari Barang, Jasa, Pelanggan, Supplier..." autocomplete="off">
            <div class="gs-dropdown" id="gsDropdown"></div>
        </div>
    </div>

    <div class="t-right">
        <button class="n-btn theme-btn" onclick="toggleTheme()" aria-label="Toggle theme" id="themeBtn">
            <i class="bi bi-moon-fill"></i>
        </button>

        <div class="nav-user-menu" id="navUserMenu">
            <button type="button" class="nav-pro" id="navUserToggle" aria-haspopup="true" aria-expanded="false">
                <?php if ($avatar['has_image']): ?>
                    <img
                        class="nav-pro-avatar"
                        src="<?= e($avatar['url']) ?>"
                        alt="<?= e((string) ($auth['name'] ?? 'User')) ?>"
                        onerror="this.style.display='none';var fb=this.nextElementSibling;if(fb){fb.style.display='inline-flex';}">
                    <span class="nav-pro-avatar is-initials" style="display:none" aria-hidden="true"><?= e($avatar['initials']) ?></span>
                <?php else: ?>
                    <span class="nav-pro-avatar is-initials" aria-hidden="true"><?= e($avatar['initials']) ?></span>
                <?php endif; ?>
                <span><?= e((string) ($auth['name'] ?? 'User')) ?></span>
                <i class="bi bi-chevron-down nav-pro-caret"></i>
            </button>

            <div class="user-dd" id="navUserDropdown" role="menu" aria-labelledby="navUserToggle">
                <a href="<?= e(site_url('profile')) ?>" class="user-dd-item">
                    <i class="bi bi-person-gear"></i>
                    <span>Edit Profile</span>
                </a>
                <form method="post" action="<?= e(site_url('logout')) ?>" class="user-dd-form">
                    <?= raw(csrf_field()) ?>
                    <button type="submit" class="user-dd-item is-danger">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>