<?php
// app/Views/home.php
?>
<main class="d-flex align-items-center justify-content-center">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-9 col-xl-8 text-center">
        <div class="aiti-mark mx-auto mb-3 shadow" aria-hidden="true">
            <svg width="35" height="35" viewBox="0 0 192 192" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="96" cy="96" r="96" class="brand-logo-bg"></circle>
                <text x="96" y="90" class="brand-logo-text" font-family="Arial, Helvetica, sans-serif" font-weight="900" font-size="95" letter-spacing="-15" text-anchor="middle" dominant-baseline="central">
                    `Acf
                </text>
            </svg>
        </div>

        <h1 class="display-2 fw-black aiti-title mb-3">
          <?= e(brand_name()) ?>
        </h1>

        <p class="aiti-subtitle mx-auto mb-5">
          Bersiaplah untuk <?= e(brand_name()) ?>! Rasakan platform
          yang ramping, responsif, dan elegan yang dirancang
          untuk masa depan. Transformasi digital Anda dimulai
          sekarang.
        </p>

        <div class="aiti-section-title mb-3">CSRF Protected Form</div>

        <?php $flashMessage = trim((string) ($message ?? '')); ?>
        <?php $flashName = trim((string) ($messageName ?? '')); ?>
        <?php $flashType = trim((string) ($messageType ?? '')); ?>
        <?php if ($flashMessage !== ''): ?>
          <div class="alert <?= $flashType === 'success' ? 'alert-success' : 'alert-danger' ?> mb-3 py-4 rounded-4 text-start">
            <?= e($flashMessage) ?>
            <?php if ($flashType === 'success' && $flashName !== ''): ?>
              <span class="fw-bold"> <?= e($flashName) ?></span>.
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="/contact" class="aiti-form mx-auto">
          <?= csrf_field() ?>
          <div class="d-flex gap-3 flex-column flex-sm-row justify-content-center align-items-stretch">
            <input
              type="text"
              name="name"
              class="form-control form-control-lg aiti-input"
              placeholder="Nama Anda"
              autocomplete="name"
              required
            >
            <button type="submit" class="btn btn-dark btn-lg aiti-btn">
              Kirim
            </button>
          </div>

          <div class="aiti-hint mt-3">
            Endpoint submit: <code>/contact</code>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
