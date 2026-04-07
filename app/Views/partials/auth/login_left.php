<?php
// app/Views/partials/auth/login_left.php
?>
<section class="left">
    <div class="d-flex align-items-center justify-content-between">
        <div class="brand">
            <div class="icon"><i class="{{icons}}"></i></div>
            <div class="title">{{nama_toko}}</div>
        </div>
        <button class="theme-btn" id="themeBtn" type="button" aria-label="Toggle theme">
            <i class="bi bi-sun-fill"></i>
        </button>
    </div>

    <div class="hero">
        <div class="chip mb-3"><i class="bi bi-shield-lock-fill"></i> Secure Login</div>
        <h1>Masuk ke sistem {{nama_toko}}.</h1>
        <p>
            Akses dashboard untuk memantau transaksi, pelanggan, stok barang, dan performa bisnis
            secara real-time dengan autentikasi sesi yang aman.
        </p>
    </div>

    <div class="small text-secondary">&copy; <?= e(date('Y')) ?> {{nama_toko}} - {{alamat}}</div>
</section>