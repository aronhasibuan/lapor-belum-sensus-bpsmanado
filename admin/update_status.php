<?php
// admin/update_status.php
session_start();
require_once __DIR__ . '/../config/database.php';

// Proteksi Akses Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status_req = trim($_GET['status'] ?? '');

if ($id > 0) {
    $status_final = 'Belum Ditindaklanjuti';
    if ($status_req === 'Sudah Ditindaklanjuti' || $status_req === 'Sudah Selesai / Didata' || $status_req === 'Sudah') {
        $status_final = 'Sudah Ditindaklanjuti';
    } elseif ($status_req === 'Belum Ditindaklanjuti' || $status_req === 'Belum') {
        $status_final = 'Belum Ditindaklanjuti';
    } else {
        // Toggle otomatis jika parameter status kosong
        $stmt_cek = $pdo->prepare("SELECT status FROM tbl_laporan WHERE id = ?");
        $stmt_cek->execute([$id]);
        $cur = $stmt_cek->fetchColumn();
        $status_final = ($cur === 'Sudah Ditindaklanjuti' || $cur === 'Sudah Selesai / Didata') 
                        ? 'Belum Ditindaklanjuti' 
                        : 'Sudah Ditindaklanjuti';
    }

    try {
        $stmt = $pdo->prepare("UPDATE tbl_laporan SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $status_final,
            ':id'     => $id
        ]);

        // Kalimat notifikasi bersih dan rapi (tanpa kode HTML tag mentah)
        $_SESSION['admin_msg'] = "Status tindak lanjut laporan berhasil diperbarui menjadi: " . $status_final;
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Gagal memperbarui status: " . $e->getMessage();
    }
}

header("Location: dashboard.php");
exit;