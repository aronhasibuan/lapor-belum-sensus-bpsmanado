<?php
// includes/header.php
// Header modular dengan logo resmi BPS & kustomisasi placeholder halus miring

if (!isset($page_title)) {
    $page_title = 'Badan Pusat Statistik Kota Manado - Sensus Mandiri';
}

// Deteksi apakah file dipanggil dari dalam folder admin
$is_in_admin = (basename(dirname($_SERVER['PHP_SELF'])) === 'admin');
$base_path   = $is_in_admin ? '../' : './';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    
    <!-- Favicon Logo BPS -->
    <link rel="icon" type="image/png" href="<?= $base_path; ?>assets/img/logo-bps.png">

    <!-- Google Fonts: Inter (Termasuk bobot 300 untuk teks tipis) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Leaflet.js CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            color: #333333;
        }

        /* Top Bar Header Resmi BPS */
        .bps-top-header {
            background-color: #ffffff;
            border-bottom: 3px solid #005596;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .bps-logo-img {
            height: 42px;
            width: auto;
            object-fit: contain;
        }

        .bps-brand-main {
            font-size: 0.95rem;
            font-weight: 800;
            color: #005596;
            letter-spacing: 0.5px;
        }

        .bps-brand-sub {
            font-size: 0.72rem;
            font-weight: 600;
            color: #6c757d;
        }

        /* Kartu Utama */
        .card-custom {
            background: #ffffff;
            border: none;
            border-radius: 14px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.06);
        }

        /* Kustomisasi Placeholder Kotak Input: Tipis, Halus, dan Miring */
        input::placeholder,
        textarea::placeholder {
            font-style: italic !important;
            font-weight: 300 !important;
            color: #94a3b8 !important;
            font-size: 0.9rem !important;
        }

        input::-webkit-input-placeholder { font-style: italic !important; font-weight: 300 !important; color: #94a3b8 !important; font-size: 0.9rem !important; }
        input::-moz-placeholder { font-style: italic !important; font-weight: 300 !important; color: #94a3b8 !important; font-size: 0.9rem !important; }
        input:-ms-input-placeholder { font-style: italic !important; font-weight: 300 !important; color: #94a3b8 !important; font-size: 0.9rem !important; }

        /* Opsi Dropdown Awal */
        select option[value=""] {
            font-style: italic;
            color: #94a3b8;
        }

        /* Tinggi Container Peta */
        #map, #adminMap {
            height: 350px !important;
            width: 100% !important;
            border-radius: 10px;
            border: 1px solid #ced4da;
            z-index: 1;
        }
    </style>
</head>
<body>

<?php if (!$is_in_admin): ?>
<!-- Header Putih Hanya Muncul di Formulir Warga -->
<header class="bps-top-header py-2">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
        <a href="<?= $base_path; ?>index.php" class="text-decoration-none d-flex align-items-center gap-3">
            <img src="<?= $base_path; ?>assets/img/logo-bps.png" 
                 alt="Logo BPS" 
                 class="bps-logo-img"
                 onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/2/28/Badan_Pusat_Statistik.png'">
            <div class="d-flex flex-column" style="line-height: 1.15;">
                <span class="bps-brand-main">BADAN PUSAT STATISTIK</span>
                <span class="bps-brand-sub">KOTA MANADO</span>
            </div>
        </a>
        <div class="d-none d-md-block">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold">
                <i class="bi bi-shield-check me-1"></i> Layanan Sensus Mandiri
            </span>
        </div>
    </div>
</header>
<?php endif; ?>