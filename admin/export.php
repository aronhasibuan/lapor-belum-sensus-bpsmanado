<?php
// admin/export.php
// Skrip export Excel/CSV dengan format teks khusus agar angka 0 pada nomor HP tidak hilang

session_start();

// 1. Koneksi Database
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../koneksi.php')) {
    require_once __DIR__ . '/../koneksi.php';
}

// 2. Proteksi Akses Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

require_once __DIR__ . '/../includes/petugas_lib.php';

// 3. Tangkap Parameter Filter
$filter_periode   = periode_valid($_GET['periode'] ?? '');
$filter_status    = trim($_GET['status'] ?? '');
$filter_kecamatan = trim($_GET['kecamatan'] ?? '');
$search           = trim($_GET['q'] ?? '');

function format_petugas_csv(array $list)
{
    if (empty($list)) {
        return '-';
    }
    return implode(', ', array_map(function ($p) {
        return $p['name'] . ($p['phone'] !== '' ? ' (' . $p['phone'] . ')' : '') . (!empty($p['dummy']) ? ' [DUMMY]' : '');
    }, $list));
}

// 4. Header File CSV
$filename = "Laporan_Belum_Sensus_BPS_Manado_" . ($filter_periode ? $filter_periode . '_' : '') . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Pemisah kolom standar Excel Indonesia
fwrite($output, "sep=;\n");
$delimiter = ';';

// 5. Header Kolom
fputcsv($output, [
    'No',
    'ID Laporan',
    'Periode',
    'Waktu Lapor',
    'Nama Pelapor',
    'Nomor WhatsApp',
    'Kecamatan',
    'Kelurahan',
    'Lingkungan / SLS',
    'Catatan / Petunjuk Lokasi',
    'Jadwal Kunjungan',
    'Petugas PPL',
    'Petugas PML',
    'Koseka',
    'Ada Nomor Dummy',
    'Latitude',
    'Longitude',
    'Status Tindak Lanjut'
], $delimiter);

// 6. Ambil laporan
$query = "SELECT l.* FROM tbl_laporan l WHERE 1=1";
$params = [];

if ($filter_periode !== '') {
    $query .= " AND l.periode = :periode";
    $params[':periode'] = $filter_periode;
}

if (!empty($filter_status)) {
    $query .= " AND l.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_kecamatan)) {
    $query .= " AND UPPER(TRIM(l.kecamatan)) = UPPER(TRIM(:kecamatan))";
    $params[':kecamatan'] = $filter_kecamatan;
}

$query .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$no = 1;
$needle = mb_strtolower($search);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pt = petugas_laporan($pdo, $row);
    $ppl = format_petugas_csv($pt['PPL']);
    $pml = format_petugas_csv($pt['PML']);
    $kos = format_petugas_csv($pt['KOSEKA']);

    // Pencarian sama seperti dashboard (termasuk nama petugas)
    if ($needle !== '') {
        $hay = mb_strtolower(implode(' | ', [$row['nama_pelapor'], $row['no_telepon'], $row['kecamatan'], $row['kelurahan'],
            $row['nomor_lingkungan'], $row['waktu_pendataan'], $row['catatan'], $ppl, $pml, $kos]));
        if (mb_strpos($hay, $needle) === false) {
            continue;
        }
    }

    // Format formula teks agar angka 0 awal tidak hilang di Excel
    $no_hp = trim($row['no_telepon'] ?? '');
    $telepon = (!empty($no_hp) && $no_hp !== '-') ? '="' . $no_hp . '"' : '-';

    $catatan_bersih = !empty($row['catatan']) ? str_replace(["\r", "\n", ";"], [' ', ' ', ' '], $row['catatan']) : '-';
    $ada_dummy = (bool) array_filter(array_merge($pt['PPL'], $pt['PML'], $pt['KOSEKA']), fn($p) => !empty($p['dummy']));

    fputcsv($output, [
        $no++,
        $row['id'],
        periode_label($row['periode'] ?? 'SENSUS'),
        $row['created_at'] ?? '-',
        $row['nama_pelapor'] ?? '-',
        $telepon,
        $row['kecamatan'] ?? '-',
        $row['kelurahan'] ?? '-',
        $row['nomor_lingkungan'] ?? '-',
        $catatan_bersih,
        $row['waktu_pendataan'] ?? '-',
        $ppl,
        $pml,
        $kos,
        $ada_dummy ? 'Ya' : 'Tidak',
        $row['latitude'] ?? '-',
        $row['longitude'] ?? '-',
        $row['status'] ?? '-'
    ], $delimiter);
}

fclose($output);
exit;
