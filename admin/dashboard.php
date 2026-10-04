<?php
// admin/dashboard.php
// Dashboard monitoring laporan warga belum didata.
// Setiap laporan ditandai fase kegiatan dan menampilkan petugas sesuai fasenya.

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin_ui.php';

// Proteksi Autentikasi Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$filter_periode   = periode_valid($_GET['periode'] ?? '');
$filter_status    = trim($_GET['status'] ?? '');
$filter_kecamatan = trim($_GET['kecamatan'] ?? '');
$search           = trim($_GET['q'] ?? '');

$query_string = function (array $override = []) use ($filter_periode, $filter_status, $filter_kecamatan, $search) {
    $params = array_filter(array_merge([
        'periode' => $filter_periode,
        'status' => $filter_status,
        'kecamatan' => $filter_kecamatan,
        'q' => $search,
    ], $override), fn($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
};

// 1. Ringkasan statistik per periode & status
$stat = [
    'PENDATAAN' => ['total' => 0, 'belum' => 0, 'sudah' => 0],
    'REVISIT_1' => ['total' => 0, 'belum' => 0, 'sudah' => 0],
    'REVISIT_2' => ['total' => 0, 'belum' => 0, 'sudah' => 0],
];
foreach ($pdo->query("SELECT periode, status, COUNT(*) AS n FROM tbl_laporan GROUP BY periode, status") as $r) {
    $p = periode_valid($r['periode']) ?: 'PENDATAAN';
    $stat[$p]['total'] += (int) $r['n'];
    if ($r['status'] === 'Sudah Ditindaklanjuti') {
        $stat[$p]['sudah'] += (int) $r['n'];
    } else {
        $stat[$p]['belum'] += (int) $r['n'];
    }
}
$stat_view = $filter_periode !== ''
    ? $stat[$filter_periode]
    : [
        'total' => array_sum(array_column($stat, 'total')),
        'belum' => array_sum(array_column($stat, 'belum')),
        'sudah' => array_sum(array_column($stat, 'sudah')),
    ];

$rev = $pdo->query("SELECT
    (SELECT COUNT(*) FROM tbl_ppl_revisit_2) AS ppl,
    (SELECT COUNT(*) FROM tbl_ppl_revisit WHERE is_dummy = 1) + (SELECT COUNT(*) FROM tbl_ppl_revisit_2 WHERE is_dummy = 1) AS ppl_dummy,
    (SELECT COUNT(*) FROM tbl_pml_revisit_2) AS pml,
    (SELECT COUNT(*) FROM tbl_pml_revisit WHERE is_dummy = 1) + (SELECT COUNT(*) FROM tbl_pml_revisit_2 WHERE is_dummy = 1) AS pml_dummy")->fetch();

// 2. List kecamatan untuk filter
$list_kecamatan = $pdo->query("SELECT DISTINCT kecamatan FROM tbl_wilayah ORDER BY kecamatan ASC")->fetchAll(PDO::FETCH_COLUMN);

// 3. Ambil laporan
$query = "SELECT l.* FROM tbl_laporan l WHERE 1=1";
$params = [];
if ($filter_periode !== '') {
    $query .= " AND l.periode = :periode";
    $params[':periode'] = $filter_periode;
}
if ($filter_status !== '') {
    $query .= " AND l.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_kecamatan !== '') {
    $query .= " AND UPPER(TRIM(l.kecamatan)) = UPPER(TRIM(:kecamatan))";
    $params[':kecamatan'] = $filter_kecamatan;
}
$query .= " ORDER BY l.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);

$laporan_list = [];
$needle = mb_strtolower($search);
foreach ($stmt->fetchAll() as $row) {
    $row['petugas'] = petugas_laporan($pdo, $row);

    if ($needle !== '') {
        $haystack = [
            $row['nama_pelapor'],
            $row['no_telepon'],
            $row['kecamatan'],
            $row['kelurahan'],
            $row['nomor_lingkungan'],
            $row['waktu_pendataan'],
            $row['catatan']
        ];
        foreach ($row['petugas'] as $list) {
            foreach ($list as $p) {
                $haystack[] = $p['name'];
                $haystack[] = $p['phone'];
            }
        }
        if (mb_strpos(mb_strtolower(implode(' | ', array_map('strval', $haystack))), $needle) === false) {
            continue;
        }
    }
    $laporan_list[] = $row;
}

$notif = notifikasi_ringkasan($pdo, array_column($laporan_list, 'id'));
$csrf = csrf_token();

// Data untuk peta
$map_data = array_map(function ($row) {
    $fmt = function ($list) {
        if (empty($list)) {
            return 'Belum ditugaskan';
        }
        return implode(', ', array_map(fn($p) => $p['name'] . ($p['phone'] ? ' (' . $p['phone'] . ')' : '') . (!empty($p['dummy']) ? ' [DUMMY]' : ''), $list));
    };
    return [
        'id'        => (int) $row['id'],
        'nama'      => $row['nama_pelapor'],
        'telepon'   => $row['no_telepon'] ?: '-',
        'kecamatan' => $row['kecamatan'],
        'kelurahan' => $row['kelurahan'],
        'lingk'     => $row['nomor_lingkungan'],
        'waktu'     => $row['waktu_pendataan'] ?: 'Fleksibel',
        'lat'       => (float) $row['latitude'],
        'lng'       => (float) $row['longitude'],
        'status'    => $row['status'],
        'periode'   => $row['periode'],
        'periode_label' => periode_label($row['periode']),
        'ppl'       => $fmt($row['petugas']['PPL']),
        'pml'       => $fmt($row['petugas']['PML']),
    ];
}, $laporan_list);

$page_title = "Dashboard Admin Sensus";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
admin_ui_styles();
?>

<div class="container-fluid px-4 py-4">

    <?php if (isset($_SESSION['admin_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= h($_SESSION['admin_msg']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['admin_msg']); ?>
    <?php endif; ?>

    <!-- Pilihan periode -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <ul class="nav nav-pills nav-periode gap-1">
            <li class="nav-item">
                <a class="nav-link periode-semua <?= $filter_periode === '' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => '']); ?>">
                    Semua Laporan <span class="badge bg-light text-dark ms-1"><?= array_sum(array_column($stat, 'total')); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link periode-sensus <?= $filter_periode === 'PENDATAAN' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => 'PENDATAAN']); ?>">
                    <i class="bi bi-clipboard-data me-1"></i>Pendataan <span class="badge bg-light text-dark ms-1"><?= $stat['PENDATAAN']['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link periode-revisit <?= $filter_periode === 'REVISIT_1' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => 'REVISIT_1']); ?>">
                    <i class="bi bi-arrow-repeat me-1"></i>Revisit 1 <span class="badge bg-light text-dark ms-1"><?= $stat['REVISIT_1']['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link periode-revisit <?= $filter_periode === 'REVISIT_2' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => 'REVISIT_2']); ?>">
                    <i class="bi bi-arrow-repeat me-1"></i>Revisit 2 <span class="badge bg-light text-dark ms-1"><?= $stat['REVISIT_2']['total']; ?></span>
                </a>
            </li>
        </ul>
        <div class="small text-muted">
            Laporan baru dari warga otomatis diteruskan ke petugas
            <strong class="<?= periode_is_revisit(PERIODE_AKTIF) ? 'text-revisit' : ''; ?>"><?= h(periode_label(PERIODE_AKTIF)); ?></strong>.
        </div>
    </div>

    <!-- Kartu Statistik -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-primary border-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">TOTAL LAPORAN<?= $filter_periode ? ' ' . strtoupper($filter_periode) : ''; ?></div>
                        <div class="fs-3 fw-bold text-dark"><?= number_format($stat_view['total']); ?></div>
                        <div class="small text-muted">Pendataan <?= $stat['PENDATAAN']['total']; ?> · Revisit 1 <?= $stat['REVISIT_1']['total']; ?> · <span class="text-revisit">Revisit 2 <?= $stat['REVISIT_2']['total']; ?></span></div>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle"><i class="bi bi-folder-fill fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-warning border-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">BELUM DITINDAKLANJUTI</div>
                        <div class="fs-3 fw-bold text-warning-emphasis"><?= number_format($stat_view['belum']); ?></div>
                        <div class="small text-muted">Pendataan <?= $stat['PENDATAAN']['belum']; ?> · Revisit 1 <?= $stat['REVISIT_1']['belum']; ?> · <span class="text-revisit">Revisit 2 <?= $stat['REVISIT_2']['belum']; ?></span></div>
                    </div>
                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-circle"><i class="bi bi-hourglass-split fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-custom p-3 border-start border-success border-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">SUDAH DITINDAKLANJUTI</div>
                        <div class="fs-3 fw-bold text-success"><?= number_format($stat_view['sudah']); ?></div>
                        <div class="small text-muted">Pendataan <?= $stat['PENDATAAN']['sudah']; ?> · Revisit 1 <?= $stat['REVISIT_1']['sudah']; ?> · <span class="text-revisit">Revisit 2 <?= $stat['REVISIT_2']['sudah']; ?></span></div>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle"><i class="bi bi-check2-circle fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="petugas_revisit.php" class="text-decoration-none">
                <div class="card card-custom p-3 border-start border-4 border-revisit h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-semibold">PETUGAS REVISIT-2</div>
                            <div class="fs-3 fw-bold text-revisit"><?= (int) $rev['ppl']; ?> <span class="fs-6 text-muted">PPL</span> · <?= (int) $rev['pml']; ?> <span class="fs-6 text-muted">PML</span></div>
                            <div class="small <?= ($rev['ppl_dummy'] + $rev['pml_dummy']) > 0 ? 'text-danger' : 'text-muted'; ?>">
                                <?= (int) $rev['ppl_dummy'] + (int) $rev['pml_dummy']; ?> petugas masih nomor dummy &rsaquo;
                            </div>
                        </div>
                        <div class="bg-revisit-subtle text-revisit p-3 rounded-circle"><i class="bi bi-people-fill fs-4"></i></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Peta -->
    <div class="card card-custom mb-4">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-map-fill text-primary"></i>
                <span>Peta Geospasial Laporan Masuk</span>
            </div>
            <div class="small d-flex gap-3">
                <span><span class="badge bg-danger rounded-circle p-1">&nbsp;</span> Belum Ditindaklanjuti</span>
                <span><span class="badge bg-success rounded-circle p-1">&nbsp;</span> Sudah Ditindaklanjuti</span>
            </div>
        </div>
        <div class="card-body p-2">
            <div id="adminMap" class="map-container" style="height: 380px;"></div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card card-custom mb-4">
        <div class="card-body">
            <form method="GET" action="dashboard.php" class="row g-2">
                <input type="hidden" name="periode" value="<?= h($filter_periode); ?>">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control" name="q" placeholder="Cari nama, HP, kelurahan, petugas..." value="<?= h($search); ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select name="kecamatan" class="form-select">
                        <option value="">-- Semua Kecamatan --</option>
                        <?php foreach ($list_kecamatan as $kec): ?>
                            <option value="<?= h($kec); ?>" <?= $filter_kecamatan === $kec ? 'selected' : ''; ?>><?= h($kec); ?></option>
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
                    <a href="dashboard.php<?= $filter_periode ? '?periode=' . h($filter_periode) : ''; ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Laporan -->
    <style>
        /* Tabel ringkas: muat selebar layar, detail lengkap ada di baris "Detail" */
        .lap-wrap {
            overflow-x: auto;
        }

        .table-lap {
            table-layout: fixed;
            width: 100%;
            min-width: 960px;
            font-size: .875rem;
        }

        .table-lap th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .table-lap td {
            vertical-align: top;
            padding-top: .6rem;
            padding-bottom: .6rem;
        }

        .table-lap .lap-sub {
            font-size: .75rem;
            color: #6c757d;
            line-height: 1.35;
        }

        .table-lap .lap-catatan {
            font-size: .75rem;
            color: #6c757d;
            font-style: italic;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chip-petugas {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            max-width: 100%;
            padding: 1px 7px 1px 2px;
            margin: 0 4px 3px 0;
            border-radius: 999px;
            font-size: .75rem;
            line-height: 1.5;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chip-petugas b {
            flex: none;
            font-size: .62rem;
            padding: 0 5px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .35);
        }

        .chip-petugas:hover {
            filter: brightness(.93);
        }
    </style>
    <?php
    // Pecah "dd/mm/yyyy - 08.00 - 09.00 WITA" menjadi tanggal & jam
    $pecah_jadwal = function ($w) {
        $w = trim((string) $w);
        $pos = strpos($w, ' - ');
        return $pos === false ? [$w, ''] : [substr($w, 0, $pos), substr($w, $pos + 3)];
    };
    $chip_petugas = function (array $p, $periode) {
        $role = strtoupper($p['role'] ?? '');
        $cls = 'chip-petugas badge-' . strtolower($role) . '-' . (periode_is_revisit($periode) ? 'revisit' : 'sensus') . (!empty($p['dummy']) ? ' badge-dummy' : '');
        $wa = wa_link_number($p['phone'] ?? '');
        $tip = $role . ' ' . ($p['name'] ?? '') . ($wa ? ' · ' . $p['phone'] : '') . (!empty($p['dummy']) ? ' (nomor dummy)' : '');
        $inner = '<b>' . h($role === 'KOSEKA' ? 'KSK' : $role) . '</b>' . h($p['name'] ?? '-');
        return $wa
            ? '<a class="' . $cls . '" href="https://wa.me/' . h($wa) . '" target="_blank" rel="noopener noreferrer" title="' . h($tip) . '">' . $inner . '</a>'
            : '<span class="' . $cls . '" title="' . h($tip) . '">' . $inner . '</span>';
    };
    ?>
    <div class="card card-custom">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span>Data Laporan Masuk (Total: <?= count($laporan_list); ?>)</span>
                <div class="small fw-normal text-muted mt-1 d-flex flex-wrap gap-3">
                    <span><span class="legend-swatch badge-ppl-sensus"></span>PPL Sensus</span>
                    <span><span class="legend-swatch badge-pml-sensus"></span>PML Sensus</span>
                    <span><span class="legend-swatch badge-koseka-sensus"></span>Koseka Sensus</span>
                    <span><span class="legend-swatch badge-ppl-revisit"></span>PPL Revisit</span>
                    <span><span class="legend-swatch badge-pml-revisit"></span>PML Revisit</span>
                    <span><span class="legend-swatch badge-koseka-revisit"></span>Koseka Revisit</span>
                    <span><span class="tag-dummy">DUMMY</span> nomor cadangan <?= h(NOMOR_WA_DUMMY); ?></span>
                </div>
            </div>
            <a href="export.php<?= $query_string(); ?>" class="btn btn-sm btn-success fw-semibold d-flex align-items-center gap-1">
                <i class="bi bi-file-earmark-excel"></i> Unduh Excel / CSV
            </a>
        </div>
        <div class="lap-wrap">
            <table class="table table-hover mb-0 table-lap">
                <colgroup>
                    <col style="width: 70px;">
                    <col style="width: 20%;">
                    <col style="width: 19%;">
                    <col style="width: 115px;">
                    <col>
                    <col style="width: 150px;">
                    <col style="width: 118px;">
                </colgroup>
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="text-center">No</th>
                        <th>Pelapor</th>
                        <th>Wilayah</th>
                        <th>Jadwal</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted small">Belum ada data laporan masuk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($laporan_list as $index => $row): ?>
                            <?php
                            $periode = periode_valid($row['periode']) ?: 'PENDATAAN';
                            $is_revisit = periode_is_revisit($periode);
                            $is_selesai = ($row['status'] === 'Sudah Ditindaklanjuti' || $row['status'] === 'Sudah Selesai / Didata');
                            $wa = wa_link_number($row['no_telepon'] ?? '');
                            $pt = $row['petugas'];
                            $nf = $notif[(int) $row['id']] ?? null;
                            $id = (int) $row['id'];
                            [$jadwal_tgl, $jadwal_jam] = $pecah_jadwal($row['waktu_pendataan'] ?? '');
                            $ling = preg_replace('/^LINGKUNGAN\s*/i', 'Ling. ', (string) ($row['nomor_lingkungan'] ?? '-'));
                            $semua_petugas = array_merge($pt['PPL'], $pt['PML'], $pt['KOSEKA']);
                            ?>
                            <tr class="row-periode-<?= strtolower($periode); ?>">
                                <td class="text-center">
                                    <div class="fw-semibold"><?= $index + 1; ?></div>
                                    <div class="lap-sub">#<?= $id; ?></div>
                                    <span class="badge <?= $is_revisit ? 'badge-periode-revisit' : 'badge-periode-sensus'; ?> mt-1" style="font-size: .6rem;"><?= h(periode_label($periode)); ?></span>
                                </td>

                                <td>
                                    <div class="fw-semibold text-dark text-break"><?= h($row['nama_pelapor'] ?? '-'); ?></div>
                                    <?php if ($wa !== ''): ?>
                                        <a href="https://wa.me/<?= h($wa); ?>" target="_blank" rel="noopener noreferrer" class="text-success text-decoration-none small fw-semibold">
                                            <i class="bi bi-whatsapp"></i> <?= h($row['no_telepon']); ?>
                                        </a>
                                    <?php endif; ?>
                                    <div class="lap-sub">Lapor <?= isset($row['created_at']) ? date('d/m/y H:i', strtotime($row['created_at'])) : '-'; ?></div>
                                </td>

                                <td>
                                    <div class="fw-semibold text-break"><?= h($row['kelurahan'] ?? '-'); ?> <span class="fw-normal text-muted">· <?= h($ling); ?></span></div>
                                    <div class="lap-sub"><?= h($row['kecamatan'] ?? '-'); ?></div>
                                    <?php if (!empty($row['catatan'])): ?>
                                        <div class="lap-catatan" title="<?= h($row['catatan']); ?>"><i class="bi bi-card-text"></i> <?= h($row['catatan']); ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="fw-semibold"><?= h($jadwal_tgl ?: '-'); ?></div>
                                    <div class="lap-sub"><?= h($jadwal_jam); ?></div>
                                </td>

                                <td>
                                    <?php foreach ($semua_petugas as $p): ?><?= $chip_petugas($p, $periode); ?><?php endforeach; ?>
                                    <?php if (empty($pt['PPL']) && empty($pt['PML'])): ?>
                                        <div class="text-danger small fst-italic">Wilayah belum punya petugas</div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($is_selesai): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Sudah</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-hourglass-split me-1"></i>Belum</span>
                                    <?php endif; ?>

                                </td>

                                <td class="text-center">
                                    <div class="btn-group btn-group-sm mb-1" role="group">
                                        <?php if ($is_selesai): ?>
                                            <a href="update_status.php?id=<?= $id; ?>&status=Belum+Ditindaklanjuti" class="btn btn-outline-warning text-dark" title="Kembalikan ke Belum Ditindaklanjuti"><i class="bi bi-arrow-counterclockwise"></i></a>
                                        <?php else: ?>
                                            <a href="update_status.php?id=<?= $id; ?>&status=Sudah+Ditindaklanjuti" class="btn btn-success" title="Tandai Sudah Ditindaklanjuti"><i class="bi bi-check2"></i></a>
                                        <?php endif; ?>
                                        <a href="https://www.google.com/maps?q=<?= h($row['latitude']); ?>,<?= h($row['longitude']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary" title="Lihat titik lokasi"><i class="bi bi-geo-alt text-danger"></i></a>
                                        <a href="hapus.php?id=<?= $id; ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data laporan ini?')" title="Hapus Laporan"><i class="bi bi-trash"></i></a>
                                    </div>
                                    <form method="POST" action="kirim_notifikasi.php"
                                        onsubmit="return confirm(<?= h(json_encode(!$is_revisit
                                                                        ? 'Alihkan laporan #' . $id . ' ke fase Revisit 2 dan kirim WhatsApp ke petugas wilayah ini?'
                                                                        : 'Kirim ulang WhatsApp laporan #' . $id . ' ke petugas revisit wilayah ini?')); ?>)">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf); ?>">
                                        <input type="hidden" name="id" value="<?= $id; ?>">
                                        <input type="hidden" name="kembali" value="<?= h($query_string()); ?>">
                                        <?php if (!$is_revisit): ?>
                                            <input type="hidden" name="aksi" value="alihkan">
                                            <button type="submit" class="btn btn-sm w-100 badge-ppl-revisit border-0" style="font-size: .72rem;" title="Alihkan ke petugas revisit & kirim WA">
                                                <i class="bi bi-arrow-repeat"></i> Ke Revisit
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="aksi" value="ulang">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100" style="font-size: .72rem;" title="Kirim ulang WA ke petugas revisit">
                                                <i class="bi bi-whatsapp"></i> Kirim Ulang
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));

        const redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
        const greenIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        const reports = <?= json_encode($map_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const map = L.map('adminMap').setView([1.474830, 124.842079], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (reports.length > 0) {
            const bounds = [];
            reports.forEach(item => {
                const isDone = item.status === 'Sudah Ditindaklanjuti';
                const isRevisit = item.periode !== 'PENDATAAN';
                const popup = `
                <div class="p-1" style="min-width: 220px;">
                    <span class="badge ${isRevisit ? 'badge-periode-revisit' : 'badge-periode-sensus'} mb-1">${esc(item.periode_label)} · #${item.id}</span>
                    <h6 class="fw-bold mb-1">${esc(item.nama)}</h6>
                    <small class="text-muted d-block mb-1">${esc(item.kecamatan)}, ${esc(item.kelurahan)} (${esc(item.lingk)})</small>
                    <div class="badge bg-light text-dark border mb-1 d-block text-start">📞 HP: ${esc(item.telepon)}</div>
                    <div class="badge bg-light text-dark border mb-2 d-block text-start">🗓️ ${esc(item.waktu)}</div>
                    <small class="d-block"><b>PPL ${isRevisit ? 'Revisit' : 'Sensus'}:</b> ${esc(item.ppl)}</small>
                    <small class="d-block mb-2"><b>PML ${isRevisit ? 'Revisit' : 'Sensus'}:</b> ${esc(item.pml)}</small>
                    <span class="badge ${isDone ? 'bg-success' : 'bg-danger'}">${esc(item.status)}</span>
                </div>`;
                L.marker([item.lat, item.lng], {
                    icon: isDone ? greenIcon : redIcon
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