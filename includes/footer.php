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
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <img src="<?= $base_path; ?>assets/img/logo-bps.png" 
                     alt="Logo BPS" 
                     style="height: 24px; width: auto; object-fit: contain;"
                     onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/2/28/Badan_Pusat_Statistik.png'">
                <span class="fw-bold text-dark small" style="letter-spacing: 0.3px;">BADAN PUSAT STATISTIK KOTA MANADO</span>
            </div>
            <p class="text-muted small mb-0">
                &copy; <?= date('Y'); ?> Sistem Informasi Pelaporan Sensus Mandiri. Hak Cipta Dilindungi.
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>