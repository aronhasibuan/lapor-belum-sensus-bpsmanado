<?php
// includes/footer.php
// Komponen footer visual resmi BPS Kota Manado dan penutup skrip JavaScript

// Pastikan base_path terdefinisi jika footer dipanggil mandiri
if (!isset($base_path)) {
    $is_in_admin = (basename(dirname($_SERVER['PHP_SELF'])) === 'admin');
    $base_path   = $is_in_admin ? '../' : './';
}
?>
<!-- Footer Visual Resmi BPS -->
<footer class="bg-white border-top py-4 mt-auto">
    <div class="container text-center">
        <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
            <img src="<?= $base_path; ?>assets/img/logo-bps-manado.png"
                alt="Logo BPS Kota Manado"
                style="height: 26px; width: auto; object-fit: contain;">
            <span style="width:1px; height:20px; background-color:#dee2e6;"></span>
            <img src="<?= $base_path; ?>assets/img/logo-se2026.png"
                alt="Logo Sensus Ekonomi 2026"
                style="height: 24px; width: auto; object-fit: contain;">
        </div>
        <p class="text-muted small mb-0">
            &copy; <?= date('Y'); ?> Sistem Informasi Pelaporan Belum Sensus Ekonomi. Hak Cipta Dilindungi.
        </p>
    </div>
</footer>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>