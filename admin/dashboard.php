<?php
// admin/dashboard.php
// Dashboard monitoring laporan warga belum didata.
// Setiap laporan ditandai periodenya (SENSUS / REVISIT) dan menampilkan
// petugas sesuai periode tersebut, sehingga petugas pendataan Sensus Ekonomi
// dan petugas Revisit dapat dibedakan.

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
$stat = ['SENSUS' => ['total' => 0, 'belum' => 0, 'sudah' => 0], 'REVISIT' => ['total' => 0, 'belum' => 0, 'sudah' => 0]];
foreach ($pdo->query("SELECT periode, status, COUNT(*) AS n FROM tbl_laporan GROUP BY periode, status") as $r) {
    $p = periode_valid($r['periode']) ?: 'SENSUS';
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
        'total' => $stat['SENSUS']['total'] + $stat['REVISIT']['total'],
        'belum' => $stat['SENSUS']['belum'] + $stat['REVISIT']['belum'],
        'sudah' => $stat['SENSUS']['sudah'] + $stat['REVISIT']['sudah'],
    ];

$rev = $pdo->query("SELECT
        (SELECT COUNT(*) FROM tbl_ppl_revisit) AS ppl,
        (SELECT COUNT(*) FROM tbl_ppl_revisit WHERE is_dummy = 1) AS ppl_dummy,
        (SELECT COUNT(*) FROM tbl_pml_revisit) AS pml,
        (SELECT COUNT(*) FROM tbl_pml_revisit WHERE is_dummy = 1) AS pml_dummy")->fetch();

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
        $haystack = [$row['nama_pelapor'], $row['no_telepon'], $row['kecamatan'], $row['kelurahan'],
            $row['nomor_lingkungan'], $row['waktu_pendataan'], $row['catatan']];
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
                    Semua Laporan <span class="badge bg-light text-dark ms-1"><?= $stat['SENSUS']['total'] + $stat['REVISIT']['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link periode-sensus <?= $filter_periode === 'SENSUS' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => 'SENSUS']); ?>">
                    <i class="bi bi-clipboard-data me-1"></i>Petugas Sensus Ekonomi <span class="badge bg-light text-dark ms-1"><?= $stat['SENSUS']['total']; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link periode-revisit <?= $filter_periode === 'REVISIT' ? 'active' : ''; ?>" href="dashboard.php<?= $query_string(['periode' => 'REVISIT']); ?>">
                    <i class="bi bi-arrow-repeat me-1"></i>Petugas Revisit <span class="badge bg-light text-dark ms-1"><?= $stat['REVISIT']['total']; ?></span>
                </a>
            </li>
        </ul>
        <div class="small text-muted">
            Laporan baru dari warga otomatis diteruskan ke petugas
            <strong class="<?= PERIODE_AKTIF === 'REVISIT' ? 'text-revisit' : ''; ?>"><?= h(periode_label(PERIODE_AKTIF)); ?></strong>.
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
                        <div class="small text-muted">Sensus <?= $stat['SENSUS']['total']; ?> · <span class="text-revisit">Revisit <?= $stat['REVISIT']['total']; ?></span></div>
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
                        <div class="small text-muted">Sensus <?= $stat['SENSUS']['belum']; ?> · <span class="text-revisit">Revisit <?= $stat['REVISIT']['belum']; ?></span></div>
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
                        <div class="small text-muted">Sensus <?= $stat['SENSUS']['sudah']; ?> · <span class="text-revisit">Revisit <?= $stat['REVISIT']['sudah']; ?></span></div>
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
                            <div class="text-muted small fw-semibold">PETUGAS REVISIT</div>
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
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th>Periode</th>
                        <th>Waktu Lapor</th>
                        <th>Nama Pelapor</th>
                        <th>Kontak (WA)</th>
                        <th>Wilayah (Kec/Kel/Ling)</th>
                        <th>Catatan / Petunjuk</th>
                        <th>Jadwal Kunjungan</th>
                        <th style="min-width: 230px;">Petugas (PPL / PML)</th>
                        <th style="min-width: 200px;">Koseka</th>
                        <th class="text-center">Notifikasi WA</th>
                        <th class="text-center">Titik Lokasi</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan_list)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-4 text-muted small">Belum ada data laporan masuk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($laporan_list as $index => $row): ?>
                            <?php
                            $periode = periode_valid($row['periode']) ?: 'SENSUS';
                            $is_selesai = ($row['status'] === 'Sudah Ditindaklanjuti' || $row['status'] === 'Sudah Selesai / Didata');
                            $wa = wa_link_number($row['no_telepon'] ?? '');
                            $pt = $row['petugas'];
                            $nf = $notif[(int) $row['id']] ?? null;
                            ?>
                            <tr class="row-periode-<?= strtolower($periode); ?>">
                                <td class="text-center text-muted small"><?= $index + 1; ?></td>

                                <td><?= badge_periode($periode); ?><div class="small text-muted mt-1">#<?= (int) $row['id']; ?></div></td>

                                <td><small class="text-muted"><?= isset($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'; ?></small></td>

                                <td class="fw-semibold text-dark"><?= h($row['nama_pelapor'] ?? '-'); ?></td>

                                <td>
                                    <a href="https://wa.me/<?= h($wa); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-whatsapp"></i> <?= h($row['no_telepon'] ?? '-'); ?>
                                    </a>
                                </td>

                                <td>
                                    <div class="small">
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold"><?= h($row['kecamatan'] ?? '-'); ?></span><br>
                                        <span class="text-muted"><?= h($row['kelurahan'] ?? '-'); ?>, <?= h($row['nomor_lingkungan'] ?? '-'); ?></span>
                                    </div>
                                </td>

                                <td>
                                    <?php if (!empty($row['catatan'])): ?>
                                        <span class="badge bg-light text-dark border text-wrap text-start fst-italic" style="max-width: 160px;">
                                            <i class="bi bi-card-text me-1 text-primary"></i><?= h($row['catatan']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                                        <i class="bi bi-clock me-1"></i><?= h($row['waktu_pendataan'] ?? '-'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex flex-column gap-1 small" style="white-space: normal;">
                                        <?php foreach (array_merge($pt['PPL'], $pt['PML']) as $p): ?>
                                            <?= badge_petugas($p, $periode); ?>
                                        <?php endforeach; ?>
                                        <?php if (empty($pt['PPL']) && empty($pt['PML'])): ?>
                                            <span class="text-danger small fst-italic">Wilayah belum punya petugas</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex flex-column gap-1 small" style="white-space: normal;">
                                        <?php foreach ($pt['KOSEKA'] as $p): ?>
                                            <?= badge_petugas($p, $periode); ?>
                                        <?php endforeach; ?>
                                        <?php if (empty($pt['KOSEKA'])): ?><span class="text-muted fst-italic">-</span><?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-center small">
                                    <?php if ($nf): ?>
                                        <?php
                                        $ok = $nf['terkirim'] === $nf['total'];
                                        $tip = implode("\n", array_map(fn($d) => ($d['terkirim'] ? '✓ ' : '✗ ') . $d['peran'] . ' ' . $d['nama_petugas'] . ' (' . $d['nomor_hp'] . ')' . ($d['is_dummy'] ? ' [dummy]' : ''), $nf['detail']));
                                        ?>
                                        <span class="badge <?= $ok ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>" title="<?= h($tip); ?>" style="cursor: help;">
                                            <i class="bi bi-whatsapp me-1"></i><?= $nf['terkirim']; ?>/<?= $nf['total']; ?> terkirim
                                        </span>
                                        <div class="text-muted mt-1" style="font-size: .7rem;">
                                            ke petugas <?= $nf['periode'] === 'REVISIT' ? '<span class="text-revisit fw-semibold">revisit</span>' : 'sensus'; ?>
                                            <?= $nf['dummy'] ? '· <span class="text-danger">' . $nf['dummy'] . ' dummy</span>' : ''; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic" title="Laporan sebelum pencatatan log notifikasi diaktifkan">tidak tercatat</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <a href="https://www.google.com/maps?q=<?= h($row['latitude']); ?>,<?= h($row['longitude']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                        <i class="bi bi-geo-alt text-danger"></i> Peta
                                    </a>
                                </td>

                                <td class="text-center">
                                    <?php if ($is_selesai): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i> Sudah Ditindaklanjuti</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> Belum Ditindaklanjuti</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="update_status.php?id=<?= (int) $row['id']; ?>&status=Sudah+Ditindaklanjuti"
                                            class="btn <?= $is_selesai ? 'btn-success fw-semibold' : 'btn-outline-success'; ?>" title="Tandai Sudah Ditindaklanjuti">
                                            <i class="bi bi-check2"></i> Sudah
                                        </a>
                                        <a href="update_status.php?id=<?= (int) $row['id']; ?>&status=Belum+Ditindaklanjuti"
                                            class="btn <?= !$is_selesai ? 'btn-warning text-dark fw-semibold' : 'btn-outline-warning text-dark'; ?>" title="Tandai Belum Ditindaklanjuti">
                                            <i class="bi bi-hourglass-split"></i> Belum
                                        </a>
                                        <a href="hapus.php?id=<?= (int) $row['id']; ?>" class="btn btn-outline-danger"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus data laporan ini?')" title="Hapus Laporan">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                    <form method="POST" action="kirim_notifikasi.php" class="mt-1"
                                        onsubmit="return confirm(<?= h(json_encode($periode === 'SENSUS'
                                            ? 'Alihkan laporan #' . $row['id'] . ' ke petugas REVISIT dan kirim WhatsApp ke PPL, PML, dan Koseka revisit wilayah ini?'
                                            : 'Kirim ulang WhatsApp laporan #' . $row['id'] . ' ke petugas revisit wilayah ini?')); ?>)">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                        <input type="hidden" name="kembali" value="<?= h($query_string()); ?>">
                                        <?php if ($periode === 'SENSUS'): ?>
                                            <input type="hidden" name="aksi" value="alihkan">
                                            <button type="submit" class="btn btn-sm w-100 badge-ppl-revisit border-0" title="Alihkan ke petugas revisit & kirim WA">
                                                <i class="bi bi-arrow-repeat"></i> Alihkan ke Revisit
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="aksi" value="ulang">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100" title="Kirim ulang WA ke petugas revisit">
                                                <i class="bi bi-whatsapp"></i> Kirim Ulang WA
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
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const redIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });
        const greenIcon = new L.Icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
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
                const isRevisit = item.periode === 'REVISIT';
                const popup = `
                <div class="p-1" style="min-width: 220px;">
                    <span class="badge ${isRevisit ? 'badge-periode-revisit' : 'badge-periode-sensus'} mb-1">${isRevisit ? 'Revisit' : 'Sensus'} · #${item.id}</span>
                    <h6 class="fw-bold mb-1">${esc(item.nama)}</h6>
                    <small class="text-muted d-block mb-1">${esc(item.kecamatan)}, ${esc(item.kelurahan)} (${esc(item.lingk)})</small>
                    <div class="badge bg-light text-dark border mb-1 d-block text-start">📞 HP: ${esc(item.telepon)}</div>
                    <div class="badge bg-light text-dark border mb-2 d-block text-start">🗓️ ${esc(item.waktu)}</div>
                    <small class="d-block"><b>PPL ${isRevisit ? 'Revisit' : 'Sensus'}:</b> ${esc(item.ppl)}</small>
                    <small class="d-block mb-2"><b>PML ${isRevisit ? 'Revisit' : 'Sensus'}:</b> ${esc(item.pml)}</small>
                    <span class="badge ${isDone ? 'bg-success' : 'bg-danger'}">${esc(item.status)}</span>
                </div>`;
                L.marker([item.lat, item.lng], { icon: isDone ? greenIcon : redIcon }).addTo(map).bindPopup(popup);
                bounds.push([item.lat, item.lng]);
            });
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
        }
    </script>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
