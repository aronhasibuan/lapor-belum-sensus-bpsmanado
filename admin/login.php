<?php
// admin/login.php
// Halaman login petugas admin sistem sensus

session_start();

// Jika admin sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Login Petugas Admin - BPS Kota Manado";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card card-custom p-4 p-md-5" style="max-width: 420px; width: 100%;">

        <!-- Header Kartu Login dengan Logo BPS -->
        <div class="text-center mb-4">
            <div class="mb-4 d-flex align-items-center justify-content-center gap-3">
                <img src="../assets/img/logo-bps-manado.png"
                    alt="Logo BPS Kota Manado"
                    style="height: 46px; width: auto; object-fit: contain;">
                <span style="width:1px; height:34px; background-color:#dee2e6;"></span>
                <img src="../assets/img/logo-se2026.png"
                    alt="Logo Sensus Ekonomi 2026"
                    style="height: 46px; width: auto; object-fit: contain;">
            </div>
            <h5 class="fw-bold text-dark mb-1">Masuk Panel Admin</h5>
            <p class="text-muted small mb-0">Sistem Pelaporan Belum Sensus</p>
        </div>

        <!-- Notifikasi Error Login -->
        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-exclamation-circle-fill fs-6"></i>
                <div><?= htmlspecialchars($_SESSION['login_error']); ?></div>
            </div>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <form action="auth.php?action=login" method="POST">
            <!-- Input Username -->
            <div class="mb-3">
                <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username admin" required autofocus>
                </div>
            </div>

            <!-- Input Password -->
            <div class="mb-4">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted">
                        <i class="bi bi-key"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    <button class="btn btn-light border text-muted" type="button" id="togglePassword">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="../index.php" class="text-decoration-none small text-muted">
                &larr; Kembali ke Formulir Warga
            </a>
        </div>

    </div>
</div>

<script>
    // Fitur Tampilkan/Sembunyikan Password
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwdInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            pwdInput.type = 'password';
            eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>