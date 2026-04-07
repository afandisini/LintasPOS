<?php
// app/Views/partials/auth/login_right.php

/** @var string $message */
/** @var string $messageType */
/** @var string $oldIdentity */
?>
<section class="right">
    <div class="card">
        <div class="card-head">
            <h2>Masuk</h2>
            <p>Gunakan username atau email yang terdaftar.</p>
        </div>
        <div class="card-body">
            <?php if ((string) $message !== ''): ?>
                <div class="alert-auth <?= e($messageType === 'success' ? 'success' : 'error') ?>">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(site_url('login')) ?>" autocomplete="off" novalidate>
                <?= raw(csrf_field()) ?>
                <div class="mb-3">
                    <label class="form-label">Username / Email</label>
                    <div class="input-wrap">
                        <i class="bi bi-person-fill"></i>
                        <input type="text" class="form-control" name="identity" value="<?= e($oldIdentity ?? '') ?>" placeholder="admin atau email" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" class="form-control" name="password" placeholder="Masukkan password" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login Dashboard
                </button>
            </form>

            <div class="divider">
                <span class="fw-bold"><?= e(brand_name()) ?></span>
                <div class="badge by-badge bg-danger">by</div>
                <span class="fw-bold">Aiti-Solutions.com</span>
            </div>
        </div>
    </div>
</section>