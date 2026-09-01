<?php
// admin/hapus.php
// Pemrosesan hapus baris laporan warga

session_start();
require_once __DIR__ . '/../config/database.php';

// Cek autentikasi admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_laporan WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['admin_msg'] = "Laporan #$id berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Gagal menghapus data laporan: " . $e->getMessage();
    }
}

header("Location: dashboard.php");
exit;