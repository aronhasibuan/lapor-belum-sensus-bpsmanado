<?php
// config/database.php
// Konfigurasi koneksi database PDO

// Deteksi environment (Localhost vs Live Hosting)
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);

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
    // Jangan tampilkan pesan detail database ke pengunjung umum jika di hosting
    if ($is_localhost) {
        die("Koneksi Database Gagal: " . $e->getMessage());
    } else {
        die("Terjadi gangguan koneksi ke server basis data. Silakan coba beberapa saat lagi.");
    }
}
