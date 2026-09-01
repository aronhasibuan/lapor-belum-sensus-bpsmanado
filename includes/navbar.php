<?php
// includes/navbar.php
// Navigasi Bar Admin Resmi BPS Kota Manado

$admin_nama = $_SESSION['admin_nama'] ?? 'Petugas Admin';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2" style="background-color: #0b1f33 !important; border-bottom: 2px solid #005596;">
    <div class="container-fluid px-4">
        
        <!-- Logo BPS & Nama Instansi -->
        <a class="navbar-brand d-flex align-items-center gap-3 py-1 text-decoration-none" href="dashboard.php">
            <img src="../assets/img/logo-bps.png" 
                 alt="Logo BPS" 
                 class="bps-logo-img" 
                 style="height: 38px; width: auto; object-fit: contain;"
                 onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/2/28/Badan_Pusat_Statistik.png'">
            <div class="d-flex flex-column" style="line-height: 1.2;">
                <span class="fw-bold text-white" style="font-size: 0.95rem; letter-spacing: 0.5px;">BPS KOTA MANADO</span>
                <span class="text-white-50" style="font-size: 0.75rem;">Panel Dashboard Sensus Mandiri</span>
            </div>
        </a>

        <!-- Tombol Toggle Mobile -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarContent" aria-controls="adminNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu Kanan (Navigasi & User Info) -->
        <div class="collapse navbar-collapse" id="adminNavbarContent">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-3 mt-3 mt-lg-0">
                
                <!-- Pintasan Lihat Form Warga -->
                <li class="nav-item">
                    <a href="../index.php" target="_blank" class="nav-link text-white-50 small d-flex align-items-center gap-1 hover-link">
                        <i class="bi bi-box-arrow-up-right text-info"></i>
                        <span>Lihat Form Warga</span>
                    </a>
                </li>

                <!-- Separator Desktop -->
                <li class="nav-item d-none d-lg-block text-secondary">|</li>

                <!-- Informasi User Admin -->
                <li class="nav-item">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-person-fill fs-6"></i>
                        </div>
                        <div class="d-flex flex-column text-start" style="line-height: 1.1;">
                            <span class="text-white small fw-semibold"><?= htmlspecialchars($admin_nama); ?></span>
                            <span class="text-success small" style="font-size: 0.7rem;">● Online</span>
                        </div>
                    </div>
                </li>

                <!-- Tombol Logout -->
                <li class="nav-item">
                    <a href="auth.php?action=logout" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 px-3 py-1 rounded-pill" onclick="return confirm('Apakah Anda yakin ingin keluar dari panel admin?')">
                        <i class="bi bi-power"></i> Keluar
                    </a>
                </li>

            </ul>
        </div>

    </div>
</nav>