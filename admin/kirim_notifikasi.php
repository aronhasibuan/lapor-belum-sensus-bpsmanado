<?php
// admin/kirim_notifikasi.php
// Mengalihkan laporan Pendataan ke Revisit 2 atau mengirim ulang notifikasi WhatsApp.
//   aksi=alihkan : ubah periode laporan Pendataan -> Revisit 2, lalu kirim WA
//   aksi=ulang   : kirim ulang WA sesuai fase laporan saat ini

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/fontte.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$kembali = 'dashboard.php' . (!empty($_POST['kembali']) && str_starts_with((string) $_POST['kembali'], '?') ? $_POST['kembali'] : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['admin_msg'] = 'Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    header("Location: $kembali");
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM tbl_laporan WHERE id = ?");
$stmt->execute([$id]);
$laporan = $stmt->fetch();

if (!$laporan) {
    $_SESSION['admin_msg'] = 'Laporan tidak ditemukan.';
    header("Location: $kembali");
    exit;
}

if ($aksi === 'alihkan') {
    $pdo->prepare("UPDATE tbl_laporan SET periode = 'REVISIT_2' WHERE id = ?")->execute([$id]);
    $laporan['periode'] = 'REVISIT_2';
}

$periode = periode_valid($laporan['periode']) ?: 'REVISIT_2';

try {
    $hasil = fonnte_notify_report_submission($pdo, $laporan, $periode);
    notifikasi_log_simpan($pdo, $id, $periode, $hasil);

    $prefix = ($aksi === 'alihkan' ? 'Laporan #' . $id . ' dialihkan ke petugas Revisit. ' : 'Laporan #' . $id . ': ');
    if (!empty($hasil['disabled'])) {
        $_SESSION['admin_msg'] = $prefix . 'Notifikasi WA tidak dikirim karena token Fonnte belum diatur.';
    } else {
        $_SESSION['admin_msg'] = $prefix . 'Notifikasi WA terkirim ke ' . $hasil['sent'] . ' dari ' . $hasil['total'] . ' nomor petugas ' . periode_label($periode) . '.'
            . (!empty($hasil['failed']) ? ' Gagal: ' . implode('; ', $hasil['failed']) : '');
    }
} catch (Exception $e) {
    error_log('[kirim_notifikasi.php] ' . $e->getMessage());
    $_SESSION['admin_msg'] = 'Periode laporan sudah diperbarui, tetapi pengiriman WA gagal: ' . $e->getMessage();
}

header("Location: $kembali");
exit;
