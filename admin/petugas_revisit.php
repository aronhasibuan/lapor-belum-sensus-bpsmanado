<?php
// admin/petugas_revisit.php
// Daftar petugas REVISIT (PPL, PML, KOSEKA) + alokasi wilayahnya.
// Admin dapat memperbarui nama dan nomor HP/WA, terutama untuk petugas
// yang masih memakai nomor dummy.

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$fase = strtoupper(trim($_REQUEST['f_fase'] ?? $_REQUEST['fase'] ?? 'REVISIT_2'));
if (!in_array($fase, ['REVISIT_1', 'REVISIT_2'], true)) {
    $fase = 'REVISIT_2';
}
$fase_label = $fase === 'REVISIT_2' ? 'Revisit 2' : 'Revisit 1';
$suffix_fase = $fase === 'REVISIT_2' ? '_2' : '';
$alokasi_table = 'tbl_alokasi_wilayah_revisit' . $suffix_fase;

$tabel = [
    'ppl' => ['table' => 'tbl_ppl_revisit' . $suffix_fase, 'nama' => 'nama_ppl', 'hp' => 'nomor_hp_ppl', 'label' => 'PPL'],
    'pml' => ['table' => 'tbl_pml_revisit' . $suffix_fase, 'nama' => 'nama_pml', 'hp' => 'nomor_hp_pml', 'label' => 'PML'],
    'koseka' => ['table' => 'tbl_koseka_revisit', 'nama' => 'nama_koseka', 'hp' => 'nomor_hp_koseka', 'label' => 'KOSEKA'],
];

$tab = $_GET['tab'] ?? 'pml';
if (!isset($tabel[$tab]) && $tab !== 'wilayah') {
    $tab = 'pml';
}
$hanya_dummy = !empty($_GET['dummy']);
$cari = trim($_GET['q'] ?? '');
$kec_filter = trim($_GET['kecamatan'] ?? '');

// ---- Simpan perubahan ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = $_POST['jenis'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nama'] ?? '')));
    $hp_input = trim((string) ($_POST['nomor_hp'] ?? ''));
    $redirect = 'petugas_revisit.php?' . http_build_query(array_filter([
        'fase' => $_POST['f_fase'] ?? $fase,
        'tab' => $jenis,
        'dummy' => $_POST['f_dummy'] ?? '',
        'q' => $_POST['f_q'] ?? '',
    ]));

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? '')) || !isset($tabel[$jenis]) || $id <= 0) {
        $_SESSION['petugas_msg'] = ['danger', 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.'];
        header("Location: $redirect");
        exit;
    }

    $hp = $hp_input === '' ? NOMOR_WA_DUMMY : nomor_hp_rapikan($hp_input);
    if ($hp === null) {
        $_SESSION['petugas_msg'] = ['danger', 'Nomor "' . $hp_input . '" tidak meyakinkan sebagai nomor HP/WA Indonesia. Contoh format yang benar: 081234567890. Kosongkan untuk memakai nomor dummy.'];
        header("Location: $redirect");
        exit;
    }
    if ($nama === '') {
        $_SESSION['petugas_msg'] = ['danger', 'Nama petugas tidak boleh kosong.'];
        header("Location: $redirect");
        exit;
    }

    $t = $tabel[$jenis];
    $is_dummy = nomor_is_dummy($hp) ? 1 : 0;
    $ket = 'Diperbarui admin ' . ($_SESSION['admin_nama'] ?? '') . ' pada ' . date('d/m/Y H:i') . ($is_dummy ? ' (nomor dummy)' : '');
    $stmt = $pdo->prepare("UPDATE {$t['table']} SET {$t['nama']} = :nama, {$t['hp']} = :hp, is_dummy = :dummy, keterangan = :ket WHERE id = :id");
    $stmt->execute([':nama' => $nama, ':hp' => $hp, ':dummy' => $is_dummy, ':ket' => $ket, ':id' => $id]);

    $_SESSION['petugas_msg'] = ['success', $t['label'] . ' "' . $nama . '" disimpan dengan nomor ' . $hp . ($is_dummy ? ' (dummy).' : '.')];
    header("Location: $redirect");
    exit;
}

$csrf = csrf_token();

// ---- Ringkasan ----
$ring = [];
foreach ($tabel as $k => $t) {
    $ring[$k] = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_dummy), 0) AS dummy FROM {$t['table']}")->fetch();
}

// ---- Data tab ----
$rows = [];
if ($tab === 'ppl') {
    $sql = "SELECT p.id, p.nama_ppl AS nama, p.nomor_hp_ppl AS hp, p.nomor_hp_sumber AS sumber, p.is_dummy, p.keterangan,
                   pm.nama_pml AS atasan, pm.is_dummy AS atasan_dummy,
                   COUNT(DISTINCT a.wilayah_id) AS jml_wilayah, COALESCE(SUM(a.jumlah_subsls), 0) AS jml_subsls,
                   GROUP_CONCAT(DISTINCT w.kecamatan ORDER BY w.kecamatan SEPARATOR ', ') AS kecamatan,
                   GROUP_CONCAT(DISTINCT CONCAT(w.kelurahan, ' ', REPLACE(w.lingkungan, 'LINGKUNGAN ', 'L.')) ORDER BY w.kelurahan, w.lingkungan SEPARATOR ', ') AS wilayah
            FROM {$tabel['ppl']['table']} p
            LEFT JOIN {$tabel['pml']['table']} pm ON pm.id = p.pml_id
            LEFT JOIN {$alokasi_table} a ON a.ppl_id = p.id
            LEFT JOIN tbl_wilayah w ON w.id = a.wilayah_id
            GROUP BY p.id ORDER BY p.is_dummy DESC, p.nama_ppl ASC";
    $rows = $pdo->query($sql)->fetchAll();
} elseif ($tab === 'pml') {
    $sql = "SELECT pm.id, pm.nama_pml AS nama, pm.nomor_hp_pml AS hp, pm.nomor_hp_sumber AS sumber, pm.is_dummy, pm.keterangan,
                   GROUP_CONCAT(DISTINCT p.nama_ppl ORDER BY p.nama_ppl SEPARATOR ', ') AS bawahan,
                   COUNT(DISTINCT a.wilayah_id) AS jml_wilayah,
                   GROUP_CONCAT(DISTINCT w.kecamatan ORDER BY w.kecamatan SEPARATOR ', ') AS kecamatan
            FROM {$tabel['pml']['table']} pm
            LEFT JOIN {$tabel['ppl']['table']} p ON p.pml_id = pm.id
            LEFT JOIN {$alokasi_table} a ON a.ppl_id = p.id
            LEFT JOIN tbl_wilayah w ON w.id = a.wilayah_id
            GROUP BY pm.id ORDER BY pm.is_dummy DESC, pm.nama_pml ASC";
    $rows = $pdo->query($sql)->fetchAll();
} elseif ($tab === 'koseka') {
    $sql = "SELECT k.id, k.nama_koseka AS nama, k.nomor_hp_koseka AS hp, NULL AS sumber, k.is_dummy, k.keterangan,
                   GROUP_CONCAT(kk.kecamatan ORDER BY kk.kecamatan SEPARATOR ', ') AS kecamatan
            FROM tbl_koseka_revisit k
            LEFT JOIN tbl_koseka_revisit_kecamatan kk ON kk.koseka_id = k.id
            GROUP BY k.id ORDER BY k.id ASC";
    $rows = $pdo->query($sql)->fetchAll();
} else {
    $data_rev = petugas_muat_periode($pdo, $fase);
    $wil = $pdo->query("SELECT id, kecamatan, kelurahan, lingkungan FROM tbl_wilayah
                        ORDER BY kecamatan, kelurahan, CAST(SUBSTRING_INDEX(lingkungan, ' ', -1) AS UNSIGNED), lingkungan")->fetchAll();
    foreach ($wil as $w) {
        if ($kec_filter !== '' && strcasecmp($w['kecamatan'], $kec_filter) !== 0) {
            continue;
        }
        $p = $data_rev['wilayah'][wilayah_key($w['kecamatan'], $w['kelurahan'], $w['lingkungan'])] ?? ['PPL' => [], 'PML' => []];
        $w['PPL'] = $p['PPL'];
        $w['PML'] = $p['PML'];
        $w['KOSEKA'] = $data_rev['koseka'][strtoupper($w['kecamatan'])] ?? [];
        $w['ada_dummy'] = (bool) array_filter(array_merge($w['PPL'], $w['PML'], $w['KOSEKA']), fn($x) => $x['dummy']);
        $rows[] = $w;
    }
}

// Filter dummy & pencarian
if ($hanya_dummy) {
    $rows = array_values(array_filter($rows, fn($r) => $tab === 'wilayah' ? $r['ada_dummy'] : (int) $r['is_dummy'] === 1));
}
if ($cari !== '') {
    $needle = mb_strtolower($cari);
    $rows = array_values(array_filter($rows, function ($r) use ($needle) {
        $txt = [];
        array_walk_recursive($r, function ($v) use (&$txt) {
            $txt[] = (string) $v;
        });
        return mb_strpos(mb_strtolower(implode(' ', $txt)), $needle) !== false;
    }));
}

$kecamatan_list = $pdo->query("SELECT DISTINCT kecamatan FROM tbl_wilayah ORDER BY kecamatan")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Petugas {$fase_label} SE2026";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
admin_ui_styles();
?>

<div class="container-fluid px-4 py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1 text-revisit"><i class="bi bi-arrow-repeat me-1"></i> Petugas <?= h($fase_label); ?> Sensus Ekonomi 2026</h4>
            <div class="text-muted small">
                Notifikasi WA laporan warga dikirim ke petugas di halaman ini. Petugas bertanda
                <span class="tag-dummy">DUMMY</span> masih memakai nomor cadangan <strong><?= h(NOMOR_WA_DUMMY); ?></strong>.
            </div>
        </div>
        <a href="dashboard.php?periode=<?= h($fase); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Fase revisit">
        <a href="?fase=REVISIT_1&tab=<?= h($tab); ?>" class="btn <?= $fase === 'REVISIT_1' ? 'btn-success' : 'btn-outline-success'; ?>">Revisit 1</a>
        <a href="?fase=REVISIT_2&tab=<?= h($tab); ?>" class="btn <?= $fase === 'REVISIT_2' ? 'btn-primary' : 'btn-outline-primary'; ?>">Revisit 2</a>
    </div>

    <?php if (!empty($_SESSION['petugas_msg'])): ?>
        <div class="alert alert-<?= h($_SESSION['petugas_msg'][0]); ?> alert-dismissible fade show py-2" role="alert">
            <?= h($_SESSION['petugas_msg'][1]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['petugas_msg']); ?>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach (['pml' => 'PML ' . $fase_label, 'ppl' => 'PPL ' . $fase_label, 'koseka' => 'Koseka Revisit'] as $k => $lbl): ?>
            <div class="col-md-4">
                <div class="card card-custom p-3 h-100">
                    <div class="text-muted small fw-semibold"><?= $lbl; ?></div>
                    <div class="fs-4 fw-bold"><?= (int) $ring[$k]['total']; ?> <span class="fs-6 text-muted">orang</span></div>
                    <div class="small <?= $ring[$k]['dummy'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?= $ring[$k]['dummy'] > 0
                            ? (int) $ring[$k]['dummy'] . ' masih nomor dummy — ' . '<a class="text-danger" href="?fase=' . h($fase) . '&tab=' . $k . '&dummy=1">lengkapi</a>'
                            : 'Semua sudah memakai nomor asli'; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <ul class="nav nav-tabs mb-0">
        <?php foreach (['pml' => 'PML', 'ppl' => 'PPL', 'koseka' => 'Koseka', 'wilayah' => 'Alokasi Wilayah'] as $k => $lbl): ?>
            <li class="nav-item">
                <a class="nav-link <?= $tab === $k ? 'active fw-semibold' : ''; ?>" href="?fase=<?= h($fase); ?>&tab=<?= $k; ?>"><?= $lbl; ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="card card-custom" style="border-top-left-radius: 0;">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= h($tab); ?>">
                <input type="hidden" name="fase" value="<?= h($fase); ?>">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control" placeholder="Cari nama, nomor, wilayah..." value="<?= h($cari); ?>">
                </div>
                <?php if ($tab === 'wilayah'): ?>
                    <div class="col-md-3">
                        <select name="kecamatan" class="form-select">
                            <option value="">-- Semua Kecamatan --</option>
                            <?php foreach ($kecamatan_list as $k): ?>
                                <option value="<?= h($k); ?>" <?= strcasecmp($k, $kec_filter) === 0 ? 'selected' : ''; ?>><?= h($k); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-auto form-check ms-2">
                    <input class="form-check-input" type="checkbox" name="dummy" value="1" id="fdummy" <?= $hanya_dummy ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="fdummy">Hanya yang masih dummy</label>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm">Tampilkan</button>
                    <a href="?fase=<?= h($fase); ?>&tab=<?= h($tab); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
                <div class="col text-end small text-muted"><?= count($rows); ?> baris</div>
            </form>
        </div>

        <div class="table-responsive">
            <?php if ($tab === 'wilayah'): ?>
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th>Kecamatan</th>
                            <th>Kelurahan</th>
                            <th>Lingkungan</th>
                            <th style="min-width: 260px;">PPL <?= h($fase_label); ?></th>
                            <th style="min-width: 260px;">PML <?= h($fase_label); ?></th>
                            <th style="min-width: 200px;">Koseka Revisit</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= h($r['kecamatan']); ?></td>
                                <td><?= h($r['kelurahan']); ?></td>
                                <td><?= h($r['lingkungan']); ?></td>
                                <?php foreach (['PPL', 'PML', 'KOSEKA'] as $role): ?>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <?php foreach ($r[$role] as $p): ?><?= badge_petugas($p, $fase); ?><?php endforeach; ?>
                                            <?php if (empty($r[$role])): ?><span class="text-danger fst-italic">belum ada</span><?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th style="min-width: 260px;">Nama <?= $tabel[$tab]['label']; ?></th>
                            <th style="min-width: 190px;">Nomor HP / WA</th>
                            <th style="width: 90px;"></th>
                            <?php if ($tab === 'ppl'): ?><th>PML</th>
                                <th>Wilayah Tugas</th><?php endif; ?>
                            <?php if ($tab === 'pml'): ?><th>PPL yang Diawasi</th>
                                <th>Kecamatan</th><?php endif; ?>
                            <?php if ($tab === 'koseka'): ?><th>Kecamatan</th><?php endif; ?>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($rows as $i => $r): ?>
                            <?php $form_id = 'f-' . $tab . '-' . (int) $r['id']; ?>
                            <tr class="<?= $r['is_dummy'] ? 'table-danger' : ''; ?>">
                                <td class="text-muted"><?= $i + 1; ?></td>
                                <td>
                                    <input form="<?= $form_id; ?>" type="text" name="nama" class="form-control form-control-sm" value="<?= h($r['nama']); ?>" required>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input form="<?= $form_id; ?>" type="text" name="nomor_hp" class="form-control" inputmode="tel"
                                            value="<?= $r['is_dummy'] ? '' : h($r['hp']); ?>"
                                            placeholder="<?= $r['is_dummy'] ? 'dummy ' . h(NOMOR_WA_DUMMY) : '08xxxxxxxxxx'; ?>">
                                        <?php if (!$r['is_dummy'] && wa_link_number($r['hp']) !== ''): ?>
                                            <a class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" href="https://wa.me/<?= h(wa_link_number($r['hp'])); ?>" title="Buka WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($r['sumber'])): ?>
                                        <div class="text-muted mt-1" style="font-size: .7rem;">Di Excel: <?= h($r['sumber']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form id="<?= $form_id; ?>" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf); ?>">
                                        <input type="hidden" name="jenis" value="<?= h($tab); ?>">
                                        <input type="hidden" name="f_fase" value="<?= h($fase); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id']; ?>">
                                        <input type="hidden" name="f_dummy" value="<?= $hanya_dummy ? '1' : ''; ?>">
                                        <input type="hidden" name="f_q" value="<?= h($cari); ?>">
                                        <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-save"></i> Simpan</button>
                                    </form>
                                    <?php if ($r['is_dummy']): ?><span class="tag-dummy d-inline-block mt-1">DUMMY</span><?php endif; ?>
                                </td>
                                <?php if ($tab === 'ppl'): ?>
                                    <td><?= h($r['atasan'] ?? '-'); ?><?= !empty($r['atasan_dummy']) ? ' <span class="tag-dummy">DUMMY</span>' : ''; ?></td>
                                    <td style="max-width: 360px; white-space: normal;">
                                        <span class="fw-semibold"><?= h($r['kecamatan']); ?></span>
                                        <span class="text-muted">(<?= (int) $r['jml_wilayah']; ?> lingkungan, <?= (int) $r['jml_subsls']; ?> sub-SLS)</span><br>
                                        <span class="text-muted"><?= h($r['wilayah']); ?></span>
                                    </td>
                                <?php elseif ($tab === 'pml'): ?>
                                    <td style="max-width: 360px; white-space: normal;"><?= h($r['bawahan'] ?: '-'); ?></td>
                                    <td><?= h($r['kecamatan'] ?: '-'); ?> <span class="text-muted">(<?= (int) $r['jml_wilayah']; ?> lingk.)</span></td>
                                <?php else: ?>
                                    <td><?= h($r['kecamatan'] ?: '-'); ?></td>
                                <?php endif; ?>
                                <td class="text-muted" style="max-width: 280px; white-space: normal; font-size: .75rem;"><?= h($r['keterangan'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <p class="small text-muted mt-2 mb-0">
        Kosongkan kolom nomor lalu Simpan untuk kembali ke nomor dummy. Nomor dengan format 62… atau +62… otomatis dirapikan menjadi 08….
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>