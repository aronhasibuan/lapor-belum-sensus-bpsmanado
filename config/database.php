<?php
// config/database.php
// Konfigurasi koneksi database PDO
// Ubah nilai di bawah sesuai database yang Anda pakai di lokal atau hosting.

$db_host = 'localhost';
$db_name = 'koth7791_lapor-belum-sensus';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}
