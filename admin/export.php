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

// 3. Tangkap Parameter Filter
$filter_status    = trim($_GET['status'] ?? '');
$filter_kecamatan = trim($_GET['kecamatan'] ?? '');
$search           = trim($_GET['q'] ?? '');

function normalize_name_for_match($value)
{
    if (!is_string($value)) {
        $value = (string) $value;
    }

    $value = trim($value);
    $value = strtolower($value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    $value = $value === false ? strtolower(trim($value)) : $value;
    $value = preg_replace('/[^a-z0-9\s]/', '', $value);
    $value = preg_replace('/\s+/', ' ', trim($value));

    return $value;
}

function format_people_with_phone($people_text, array $phone_map)
{
    $people_text = trim((string) $people_text);

    if ($people_text === '') {
        return '-';
    }

    $people = array_map('trim', preg_split('/\s*,\s*/', $people_text));
    $formatted = [];

    foreach ($people as $person) {
        if ($person === '') {
            continue;
        }

        $key = normalize_name_for_match($person);
        $phone = $phone_map[$key] ?? '';

        if ($phone !== '') {
            $formatted[] = $person . ' (' . $phone . ')';
        } else {
            $formatted[] = $person;
        }
    }

    return implode(', ', $formatted) ?: '-';
}

// 4. Header File CSV
$filename = "Laporan_Sensus_BPS_Manado_" . date('Ymd_His') . ".csv";
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
    'Latitude',
    'Longitude',
    'Status Tindak Lanjut'
], $delimiter);

// 6. Cek Keberadaan Tabel hasil normalisasi
$has_normalized_tables = false;
try {
    $check_table = $pdo->query("SHOW TABLES LIKE 'tbl_wilayah'")->fetch();
    $has_normalized_tables = !empty($check_table);
} catch (Exception $e) {
    $has_normalized_tables = false;
}

if ($has_normalized_tables) {
    $query = "SELECT 
                l.*,
                GROUP_CONCAT(DISTINCT p.nama_ppl ORDER BY p.nama_ppl SEPARATOR ', ') AS nama_ppl,
                GROUP_CONCAT(DISTINCT pm.nama_pml ORDER BY pm.nama_pml SEPARATOR ', ') AS nama_pml
              FROM tbl_laporan l
              LEFT JOIN tbl_wilayah w
                ON UPPER(TRIM(w.kecamatan)) = UPPER(TRIM(l.kecamatan))
               AND UPPER(TRIM(w.kelurahan)) = UPPER(TRIM(l.kelurahan))
               AND UPPER(TRIM(w.lingkungan)) = UPPER(TRIM(l.nomor_lingkungan))
              LEFT JOIN tbl_alokasi_wilayah aw
                ON aw.wilayah_id = w.id
              LEFT JOIN tbl_ppl p
                ON p.id = aw.ppl_id
              LEFT JOIN tbl_pml pm
                ON pm.id = p.pml_id
              WHERE 1=1";
    $group_by = " GROUP BY l.id";
} else {
    $query = "SELECT l.*, NULL as nama_ppl, NULL as nama_pml FROM tbl_laporan l WHERE 1=1";
    $group_by = "";
}

$params = [];

if (!empty($filter_status)) {
    $query .= " AND l.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_kecamatan)) {
    $query .= " AND UPPER(TRIM(l.kecamatan)) = UPPER(TRIM(:kecamatan))";
    $params[':kecamatan'] = $filter_kecamatan;
}

if (!empty($search)) {
    $query .= " AND (
        l.nama_pelapor LIKE :s OR 
        l.no_telepon LIKE :s OR 
        l.kecamatan LIKE :s OR 
        l.kelurahan LIKE :s OR 
        l.nomor_lingkungan LIKE :s OR 
        l.catatan LIKE :s
    )";
    $params[':s'] = "%$search%";
}

// GROUP BY harus berada SESUDAH seluruh klausa WHERE, bukan sebelumnya.
$query .= $group_by . " ORDER BY l.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$ppl_phone_map = [];
$ppl_phone_stmt = $pdo->query("SELECT nama_ppl, nomor_hp_ppl FROM tbl_ppl");
while ($ppl_phone_row = $ppl_phone_stmt->fetch(PDO::FETCH_ASSOC)) {
    $nama_ppl = trim((string)($ppl_phone_row['nama_ppl'] ?? ''));
    $no_hp = trim((string)($ppl_phone_row['nomor_hp_ppl'] ?? ''));

    if ($nama_ppl !== '' && $no_hp !== '') {
        $ppl_phone_map[normalize_name_for_match($nama_ppl)] = $no_hp;
    }
}

$no = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Solusi: Format formula teks agar angka 0 awal tidak hilang di Excel
    $no_hp = trim($row['no_telepon'] ?? '');
    $telepon = (!empty($no_hp) && $no_hp !== '-') ? '="' . $no_hp . '"' : '-';

    $catatan_bersih = !empty($row['catatan']) ? str_replace(["\r", "\n", ";"], [' ', ' ', ' '], $row['catatan']) : '-';

    fputcsv($output, [
        $no++,
        $row['created_at'] ?? '-',
        $row['nama_pelapor'] ?? '-',
        $telepon,
        $row['kecamatan'] ?? '-',
        $row['kelurahan'] ?? '-',
        $row['nomor_lingkungan'] ?? '-',
        $catatan_bersih,
        $row['waktu_pendataan'] ?? '-',
        !empty($row['nama_ppl']) ? format_people_with_phone($row['nama_ppl'], $ppl_phone_map) : '-',
        !empty($row['nama_pml']) ? $row['nama_pml'] : '-',
        $row['latitude'] ?? '-',
        $row['longitude'] ?? '-',
        $row['status'] ?? '-'
    ], $delimiter);
}

fclose($output);
exit;
