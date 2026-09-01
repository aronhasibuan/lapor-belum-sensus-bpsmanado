<?php
// get_wilayah.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/database.php';

$type = $_GET['type'] ?? '';

if ($type === 'kecamatan') {
    $stmt = $pdo->query("SELECT DISTINCT kecamatan FROM tbl_wilayah ORDER BY kecamatan ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

if ($type === 'kelurahan') {
    $kecamatan = trim($_GET['kecamatan'] ?? '');
    $stmt = $pdo->prepare("SELECT DISTINCT kelurahan FROM tbl_wilayah WHERE kecamatan = :kec ORDER BY kelurahan ASC");
    $stmt->execute([':kec' => $kecamatan]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

if ($type === 'lingkungan') {
    $kecamatan = trim($_GET['kecamatan'] ?? '');
    $kelurahan = trim($_GET['kelurahan'] ?? '');
    $stmt = $pdo->prepare("SELECT DISTINCT lingkungan FROM tbl_wilayah WHERE kecamatan = :kec AND kelurahan = :kel ORDER BY CAST(SUBSTRING_INDEX(lingkungan, ' ', -1) AS UNSIGNED) ASC, lingkungan ASC");
    $stmt->execute([':kec' => $kecamatan, ':kel' => $kelurahan]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}

echo json_encode([]);
exit;