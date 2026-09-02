<?php
// config/database.php
// Konfigurasi koneksi database PDO

// Deteksi environment (Localhost vs Live Hosting)
$host_candidates = [
    $_SERVER['HTTP_HOST'] ?? '',
    $_SERVER['SERVER_NAME'] ?? '',
    $_SERVER['REQUEST_URI'] ?? '',
];

$is_localhost = false;
foreach ($host_candidates as $candidate) {
    $host = strtolower(trim((string) $candidate));
    $host = preg_replace('#^https?://#i', '', $host);
    $host = preg_replace('#/.*$#', '', $host);

    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        $is_localhost = true;
        break;
    }

    if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains($host, '::1')) {
        $is_localhost = true;
        break;
    }

    if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
        $is_localhost = true;
        break;
    }
}

if ($is_localhost) {
    // Pengaturan Database Lokal (XAMPP / Laragon)
    $db_host = 'localhost';
    $db_name = 'koth7791_lapor-belum-sensus';
    $db_user = 'root';
    $db_pass = '';
} else {
    // Pengaturan Database Live Hosting (Sesuaikan dengan data cPanel Anda nanti)
    $db_host = 'localhost';
    $db_name = 'koth7791_lapor-belum-sensus';
    $db_user = 'koth7791_humasbpsmanado';
    $db_pass = 'WebHumas7171!';
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Tampilkan detail di lokal agar debugging lebih cepat
    if ($is_localhost) {
        die("Koneksi Database Gagal: " . $e->getMessage());
    }

    die("Terjadi gangguan koneksi ke server basis data. Silakan coba beberapa saat lagi.");
}
