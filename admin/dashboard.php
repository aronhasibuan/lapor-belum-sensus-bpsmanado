<?php
// admin/dashboard.php
// Dashboard monitoring lengkap dengan kartu statistik metrik, WhatsApp chat, dan warna marker status

session_start();
require_once __DIR__ . '/../config/database.php';

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

function format_phone_for_wa($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '62')) {
        return $digits;
    }

    if (str_starts_with($digits, '0')) {
        return '62' . substr($digits, 1);
    }

    return $digits;
}

function format_person_phone_link($label, $phone)
{
    $phone = trim((string) $phone);
    $wa = format_phone_for_wa($phone);

    if ($wa === '') {
        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-wrap text-start d-block">' . htmlspecialchars($label) . '</span>';
    }

    return '<a href="https://wa.me/' . $wa . '" target="_blank" rel="noopener noreferrer" class="badge bg-primary-subtle text-primary border border-primary-subtle text-wrap text-start text-decoration-none d-block">' . htmlspecialchars($label) . ' (' . htmlspecialchars($phone) . ')</a>';
}

function format_people_with_phone_link($people_text, array $phone_map)
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
            $formatted[] = format_person_phone_link($person, $phone);
        } else {
            $formatted[] = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-wrap text-start d-block">' . htmlspecialchars($person) . '</span>';
        }
    }

    return implode('', $formatted) ?: '-';
}

function format_pml_with_phone_link($pml_text)
{
    $pml_text = trim((string) $pml_text);

    if ($pml_text === '') {
        return '-';
    }

    $items = array_map('trim', preg_split('/\s*,\s*/', $pml_text));
    $formatted = [];

    foreach ($items as $item) {
        if ($item === '') {
            continue;
        }

        if (preg_match('/^(.*?)(\(\s*([^\)]+?)\s*\))$/', $item, $matches)) {
            $label = trim($matches[1]);
            $phone = trim($matches[3]);
            $formatted[] = format_person_phone_link($label, $phone);
        } else {
            $formatted[] = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-wrap text-start d-block">' . htmlspecialchars($item) . '</span>';
        }
    }

    return implode('', $formatted) ?: '-';
}

function get_koseka_ids_for_kecamatan($kecamatan)
{
    $normalized = strtoupper(trim((string) $kecamatan));
    $kecamatan_map = [
        'MALALAYANG' => [1],
        'SARIO' => [2],
        'WANEA' => [3],
        'WENANG' => [4],
        'TIKALA' => [5],
        'MAPANGET' => [6, 7],
        'SINGKIL' => [8],
        'TUMINTING' => [9],
        'BUNAKEN' => [10],
        'BUNAKEN KEPULAUAN' => [10],
        'PAAL DUA' => [11],
    ];

    return $kecamatan_map[$normalized] ?? [];
}

function format_koseka_for_laporan($kecamatan, array $koseka_by_id)
{
    $ids = get_koseka_ids_for_kecamatan($kecamatan);

    if (empty($ids)) {
        return '<span class="text-muted fst-italic">-</span>';
    }

    $items = [];

    foreach ($ids as $id) {
        $data = $koseka_by_id[(int) $id] ?? null;
        $name = $data['nama_koseka'] ?? 'Koseka ID ' . $id;
        $phone = trim((string) ($data['nomor_hp_koseka'] ?? ''));

        if ($phone !== '') {
            $wa = format_phone_for_wa($phone);
            $items[] = '<a href="https://wa.me/' . $wa . '" target="_blank" rel="noopener noreferrer" class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-wrap text-start text-decoration-none d-block">Koseka: ' . htmlspecialchars($name) . ' (' . htmlspecialchars($phone) . ')</a>';
        } else {
            $items[] = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-wrap text-start d-block">Koseka: ' . htmlspecialchars($name) . '</span>';
        }
    }

    return implode('', $items) ?: '<span class="text-muted fst-italic">-</span>';
}

// Proteksi Autentikasi Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$filter_status    = trim($_GET['status'] ?? '');
$filter_kecamatan = trim($_GET['kecamatan'] ?? '');
$search           = trim($_GET['q'] ?? '');

// 1. Ambil Ringkasan Statistik Real-Time
$total_laporan = $pdo->query("SELECT COUNT(*) FROM tbl_laporan")->fetchColumn();
$total_belum   = $pdo->query("SELECT COUNT(*) FROM tbl_laporan WHERE status = 'Belum Ditindaklanjuti'")->fetchColumn();
$total_sudah   = $pdo->query("SELECT COUNT(*) FROM tbl_laporan WHERE status = 'Sudah Ditindaklanjuti'")->fetchColumn();
$total_kec     = $pdo->query("SELECT COUNT(DISTINCT kecamatan) FROM tbl_wilayah")->fetchColumn();

// 2. Ambil List Kecamatan untuk Dropdown Filter
$list_kecamatan = $pdo->query("SELECT DISTINCT kecamatan FROM tbl_wilayah ORDER BY kecamatan ASC")->fetchAll(PDO::FETCH_COLUMN);

// 3. Query Utama JOIN relasi wilayah dan petugas yang sudah dinormalisasi
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
        l.nama_pelapor LIKE :s_nama OR
        l.no_telepon LIKE :s_telp OR
        l.kecamatan LIKE :s_kec OR
        l.kelurahan LIKE :s_kel OR
        l.nomor_lingkungan LIKE :s_ling OR
        l.waktu_pendataan LIKE :s_waktu OR
        p.nama_ppl LIKE :s_ppl OR
        pm.nama_pml LIKE :s_pml
    )";
    $search_param = "%$search%";
    $params[':s_nama']  = $search_param;
    $params[':s_telp']  = $search_param;
    $params[':s_kec']   = $search_param;
    $params[':s_kel']   = $search_param;
    $params[':s_ling']  = $search_param;
    $params[':s_waktu'] = $search_param;
    $params[':s_ppl']   = $search_param;
    $params[':s_pml']   = $search_param;
}

// GROUP BY harus berada SESUDAH seluruh klausa WHERE, bukan sebelumnya.
$query .= " GROUP BY l.id ORDER BY l.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$laporan_list = $stmt->fetchAll();

$ppl_phone_map = [];
$ppl_phone_stmt = $pdo->query("SELECT nama_ppl, nomor_hp_ppl FROM tbl_ppl");
while ($ppl_phone_row = $ppl_phone_stmt->fetch(PDO::FETCH_ASSOC)) {
    $nama_ppl = trim((string)($ppl_phone_row['nama_ppl'] ?? ''));
    $no_hp = trim((string)($ppl_phone_row['nomor_hp_ppl'] ?? ''));

    if ($nama_ppl !== '' && $no_hp !== '') {
        $ppl_phone_map[normalize_name_for_match($nama_ppl)] = $no_hp;
    }
}

$pml_phone_map = [];
$pml_phone_stmt = $pdo->query("SELECT nama_pml, nomor_hp_pml FROM tbl_pml");
while ($pml_phone_row = $pml_phone_stmt->fetch(PDO::FETCH_ASSOC)) {
    $nama_pml = trim((string)($pml_phone_row['nama_pml'] ?? ''));
    $no_hp = trim((string)($pml_phone_row['nomor_hp_pml'] ?? ''));

    if ($nama_pml !== '' && $no_hp !== '') {
        $pml_phone_map[normalize_name_for_match($nama_pml)] = $no_hp;
    }
}

$koseka_by_id = [];
$koseka_stmt = $pdo->query("SELECT id, nama_koseka, nomor_hp_koseka FROM tbl_koseka ORDER BY id ASC");
while ($koseka_row = $koseka_stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = (int) ($koseka_row['id'] ?? 0);

    if ($id > 0) {
        $koseka_by_id[$id] = [
            'nama_koseka' => trim((string) ($koseka_row['nama_koseka'] ?? '')),
            'nomor_hp_koseka' => trim((string) ($koseka_row['nomor_hp_koseka'] ?? '')),
        ];
    }
}

// Olah data JSON untuk marker Leaflet Peta
$map_data = array_map(function ($row) use ($ppl_phone_map, $pml_phone_map, $koseka_by_id) {
    $ppl_text = !empty($row['nama_ppl']) ? format_people_with_phone($row['nama_ppl'], $ppl_phone_map) : 'Belum ditugaskan';
    $pml_text = !empty($row['nama_pml']) ? format_people_with_phone($row['nama_pml'], $pml_phone_map) : 'Belum ditugaskan';

    return [
        'id'        => $row['id'],
        'nama'      => $row['nama_pelapor'],
        'telepon'   => $row['no_telepon'] ?: '-',
        'kecamatan' => $row['kecamatan'],
        'kelurahan' => $row['kelurahan'],
        'lingk'     => $row['nomor_lingkungan'],
        'waktu'     => $row['waktu_pendataan'] ?: 'Fleksibel',
        'lat'       => (float)$row['latitude'],
        'lng'       => (float)$row['longitude'],
        'status'    => $row['status'],
        'ppl'       => $ppl_text,
        'pml'       => $pml_text
    ];
}, $laporan_list);

$page_title = "Dashboard Admin Sensus";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container-fluid px-4 py-4">

    <!-- Notifikasi Flash Message -->
    <?php if (isset($_SESSION['admin_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($_SESSION['admin_msg']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['admin_msg']); ?>
    <?php endif; ?>

    <!-- Kartu Statistik Metrik Dashboard (Stat Cards) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">TOTAL LAPORAN</div>
                        <div class="fs-3 fw-bold text-dark"><?= number_format($total_laporan); ?></div>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-folder-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">BELUM DITINDAKLANJUTI</div>
                        <div class="fs-3 fw-bold text-warning-emphasis"><?= number_format($total_belum); ?></div>
                    </div>
                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-circle">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">SUDAH DITINDAKLANJUTI</div>
                        <div class="fs-3 fw-bold text-success"><?= number_format($total_sudah); ?></div>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-info border-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">CAKUPAN WILAYAH</div>
                        <div class="fs-3 fw-bold text-info"><?= $total_kec; ?> <span class="fs-6 text-muted">Kecamatan</span></div>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle">
                        <i class="bi bi-geo-alt-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Peta Sebaran Laporan -->
    <div class="card card-custom mb-4">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-map-fill text-primary"></i>
                <span>Peta Geospasial Laporan Masuk</span>
            </div>
            <!-- Legenda Warna Pin Peta -->
            <div class="small d-flex gap-3">
                <span><span class="badge bg-danger rounded-circle p-1">&nbsp;</span> Belum Ditindaklanjuti</span>
                <span><span class="badge bg-success rounded-circle p-1">&nbsp;</span> Sudah Ditindaklanjuti</span>
            </div>
        </div>
        <div class="card-body p-2">
            <div id="adminMap" class="map-container" style="height: 380px;"></div>
        </div>
    </div>

    <!-- Filter & Pencarian Lengkap -->
    <div class="card card-custom mb-4">
        <div class="card-body">
            <form method="GET" action="dashboard.php" class="row g-2">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control" name="q" placeholder="Cari nama, HP, kelurahan, petugas..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="kecamatan" class="form-select">
                        <option value="">-- Semua Kecamatan --</option>
                        <?php foreach ($list_kecamatan as $kec): ?>
                            <option value="<?= htmlspecialchars($kec); ?>" <?= $filter_kecamatan === $kec ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($kec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="Belum Ditindaklanjuti" <?= $filter_status === 'Belum Ditindaklanjuti' ? 'selected' : ''; ?>>Belum Ditindaklanjuti</option>
                        <option value="Sudah Ditindaklanjuti" <?= $filter_status === 'Sudah Ditindaklanjuti' ? 'selected' : ''; ?>>Sudah Ditindaklanjuti</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1 fw-semibold">Filter</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Laporan Masuk -->
    <div class="card card-custom">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Data Laporan Masuk (Total: <?= count($laporan_list); ?>)</span>
            <a href="export.php?status=<?= urlencode($filter_status); ?>&kecamatan=<?= urlencode($filter_kecamatan); ?>&q=<?= urlencode($search); ?>" class="btn btn-sm btn-success fw-semibold d-flex align-items-center gap-1">
                <i class="bi bi-file-earmark-excel"></i> Unduh Excel / CSV
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th>Waktu Lapor</th>
                        <th>Nama Pelapor</th>
                        <th>Kontak (WA)</th>
                        <th>Wilayah (Kec/Kel/Ling)</th>
                        <th>Catatan / Petunjuk</th>
                        <th>Jadwal Kunjungan</th>
                        <th>Petugas (PPL / PML)</th>
                        <th>Koseka</th>
                        <th class="text-center">Titik Lokasi</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan_list)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted small">Belum ada data laporan masuk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($laporan_list as $index => $row): ?>
                            <?php
                            $is_selesai = ($row['status'] === 'Sudah Ditindaklanjuti' || $row['status'] === 'Sudah Selesai / Didata');
                            $wa = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['no_telepon'] ?? ''));
                            $koseka_html = format_koseka_for_laporan($row['kecamatan'] ?? '', $koseka_by_id);
                            ?>
                            <tr>
                                <!-- 1. No -->
                                <td class="text-center text-muted small"><?= $index + 1; ?></td>

                                <!-- 2. Waktu Lapor -->
                                <td><small class="text-muted"><?= isset($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'; ?></small></td>

                                <!-- 3. Nama Pelapor -->
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_pelapor'] ?? '-'); ?></td>

                                <!-- 4. Kontak WA -->
                                <td>
                                    <a href="https://wa.me/<?= $wa; ?>" target="_blank" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($row['no_telepon'] ?? '-'); ?>
                                    </a>
                                </td>

                                <!-- 5. Wilayah -->
                                <td>
                                    <div class="small">
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold"><?= htmlspecialchars($row['kecamatan'] ?? '-'); ?></span><br>
                                        <span class="text-muted"><?= htmlspecialchars($row['kelurahan'] ?? '-'); ?>, <?= htmlspecialchars($row['nomor_lingkungan'] ?? '-'); ?></span>
                                    </div>
                                </td>

                                <!-- 7. Catatan / Petunjuk -->
                                <td>
                                    <?php if (!empty($row['catatan'])): ?>
                                        <span class="badge bg-light text-dark border text-wrap text-start fst-italic" style="max-width: 160px;">
                                            <i class="bi bi-card-text me-1 text-primary"></i><?= htmlspecialchars($row['catatan']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 8. Jadwal Kunjungan -->
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                                        <i class="bi bi-clock me-1"></i><?= htmlspecialchars($row['waktu_pendataan'] ?? '-'); ?>
                                    </span>
                                </td>

                                <!-- 9. Petugas PPL / PML -->
                                <td>
                                    <div class="d-flex flex-column gap-1 small">
                                        <?php if (!empty($row['nama_ppl'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach (preg_split('/\s*,\s*/', trim((string)$row['nama_ppl'])) as $ppl_name): ?>
                                                    <?php if (trim($ppl_name) === '') continue; ?>
                                                    <?php $ppl_phone = $ppl_phone_map[normalize_name_for_match($ppl_name)] ?? ''; ?>
                                                    <?php if ($ppl_phone !== ''): ?>
                                                        <a href="https://wa.me/<?= format_phone_for_wa($ppl_phone); ?>" target="_blank" rel="noopener noreferrer" class="badge bg-primary text-wrap text-start text-decoration-none d-block">
                                                            PPL: <?= htmlspecialchars($ppl_name); ?> (<?= htmlspecialchars($ppl_phone); ?>)
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary text-wrap text-start d-block">PPL: <?= htmlspecialchars($ppl_name); ?></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['nama_pml'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach (preg_split('/\s*,\s*/', trim((string)$row['nama_pml'])) as $pml_name): ?>
                                                    <?php if (trim($pml_name) === '') continue; ?>
                                                    <?php $pml_label = trim($pml_name);
                                                    $pml_phone = $pml_phone_map[normalize_name_for_match($pml_label)] ?? ''; ?>
                                                    <?php if ($pml_phone !== ''): ?>
                                                        <a href="https://wa.me/<?= format_phone_for_wa($pml_phone); ?>" target="_blank" rel="noopener noreferrer" class="badge bg-warning text-dark text-wrap text-start text-decoration-none d-block">
                                                            PML: <?= htmlspecialchars($pml_label); ?> (<?= htmlspecialchars($pml_phone); ?>)
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark text-wrap text-start d-block">PML: <?= htmlspecialchars($pml_name); ?></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($row['nama_ppl']) && empty($row['nama_pml'])): ?>
                                            <span class="text-muted fst-italic">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- 6. Koseka -->
                                <td>
                                    <div class="d-flex flex-column gap-1 small">
                                        <?= $koseka_html; ?>
                                    </div>
                                </td>

                                <!-- 10. Titik Lokasi Peta -->
                                <td class="text-center">
                                    <a href="https://www.google.com/maps?q=<?= $row['latitude']; ?>,<?= $row['longitude']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                        <i class="bi bi-geo-alt text-danger"></i> Peta
                                    </a>
                                </td>

                                <!-- 11. Status -->
                                <td class="text-center">
                                    <?php if ($is_selesai): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle me-1"></i> Sudah Ditindaklanjuti
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i> Belum Ditindaklanjuti
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Alternatif: 2 Tombol Status Langsung Berdampingan -->
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <!-- Pilihan 1: Sudah Ditindaklanjuti -->
                                        <a href="update_status.php?id=<?= $row['id']; ?>&status=Sudah+Ditindaklanjuti"
                                            class="btn <?= $is_selesai ? 'btn-success fw-semibold' : 'btn-outline-success'; ?>"
                                            title="Tandai Sudah Ditindaklanjuti">
                                            <i class="bi bi-check2"></i> Sudah
                                        </a>

                                        <!-- Pilihan 2: Belum Ditindaklanjuti -->
                                        <a href="update_status.php?id=<?= $row['id']; ?>&status=Belum+Ditindaklanjuti"
                                            class="btn <?= !$is_selesai ? 'btn-warning text-dark fw-semibold' : 'btn-outline-warning text-dark'; ?>"
                                            title="Tandai Belum Ditindaklanjuti">
                                            <i class="bi bi-hourglass-split"></i> Belum
                                        </a>

                                        <!-- Tombol Hapus -->
                                        <a href="hapus.php?id=<?= $row['id']; ?>"
                                            class="btn btn-outline-danger"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus data laporan ini?')"
                                            title="Hapus Laporan">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        // Ikon Pin Merah untuk Belum Ditindaklanjuti
        const redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Ikon Pin Hijau untuk Sudah Ditindaklanjuti
        const greenIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        const reports = <?= json_encode($map_data); ?>;
        const map = L.map('adminMap').setView([1.474830, 124.842079], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (reports.length > 0) {
            const bounds = [];
            reports.forEach(item => {
                const isDone = item.status === 'Sudah Ditindaklanjuti';
                const iconChoice = isDone ? greenIcon : redIcon;

                const popup = `
                <div class="p-1" style="min-width: 200px;">
                    <h6 class="fw-bold mb-1">${item.nama}</h6>
                    <small class="text-muted d-block mb-1">${item.kecamatan}, ${item.kelurahan} (${item.lingk})</small>
                    <div class="badge bg-light text-dark border mb-1 d-block text-start">📞 HP: ${item.telepon}</div>
                    <div class="badge bg-light text-dark border mb-2 d-block text-start">🗓️ ${item.waktu}</div>
                    <small class="d-block"><b>PPL:</b> ${item.ppl}</small>
                    <small class="d-block mb-2"><b>PML:</b> ${item.pml}</small>
                    <span class="badge ${isDone ? 'bg-success' : 'bg-danger'}">${item.status}</span>
                </div>
            `;
                L.marker([item.lat, item.lng], {
                    icon: iconChoice
                }).addTo(map).bindPopup(popup);
                bounds.push([item.lat, item.lng]);
            });
            map.fitBounds(bounds, {
                padding: [30, 30],
                maxZoom: 15
            });
        }
    </script>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>