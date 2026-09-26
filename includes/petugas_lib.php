<?php
// includes/petugas_lib.php
// Fungsi bersama untuk mencari petugas (PPL, PML, KOSEKA) sebuah wilayah,
// baik petugas pendataan Sensus Ekonomi (SENSUS) maupun petugas REVISIT.
// Dipakai oleh notifikasi WhatsApp, dashboard admin, dan export.

require_once __DIR__ . '/../config/periode.php';

/** Label tampilan periode. */
function periode_label($periode)
{
    return strtoupper((string) $periode) === 'REVISIT' ? 'Revisit SE2026' : 'Sensus Ekonomi 2026';
}

function periode_valid($periode)
{
    $periode = strtoupper(trim((string) $periode));
    return in_array($periode, ['SENSUS', 'REVISIT'], true) ? $periode : '';
}

/** Kunci pencocokan wilayah: KECAMATAN|KELURAHAN|LINGKUNGAN (huruf besar, spasi dirapikan). */
function wilayah_key($kecamatan, $kelurahan, $lingkungan)
{
    $norm = function ($v) {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)));
    };
    return $norm($kecamatan) . '|' . $norm($kelurahan) . '|' . $norm($lingkungan);
}

/**
 * Validasi & rapikan nomor HP Indonesia ke format 08xxxxxxxxxx.
 * Mengembalikan null bila nomor tidak meyakinkan.
 */
function nomor_hp_rapikan($raw)
{
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return null;
    }
    if (str_starts_with($digits, '62')) {
        $digits = substr($digits, 2);
        if (!str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }
    } elseif (str_starts_with($digits, '8')) {
        $digits = '0' . $digits;
    }

    if (!preg_match('/^08\d{8,11}$/', $digits)) {
        return null;
    }

    $prefix_seluler = [
        '0811', '0812', '0813', '0821', '0822', '0823', '0851', '0852', '0853',
        '0814', '0815', '0816', '0855', '0856', '0857', '0858',
        '0817', '0818', '0819', '0859', '0877', '0878', '0879',
        '0831', '0832', '0833', '0838',
        '0895', '0896', '0897', '0898', '0899',
        '0881', '0882', '0883', '0884', '0885', '0886', '0887', '0888', '0889',
    ];
    if (!in_array(substr($digits, 0, 4), $prefix_seluler, true)) {
        return null;
    }
    if (count(array_unique(str_split(substr($digits, 2)))) <= 2) {
        return null; // pola seperti 0811111111
    }

    return $digits;
}

function nomor_is_dummy($phone)
{
    return preg_replace('/\D+/', '', (string) $phone) === preg_replace('/\D+/', '', NOMOR_WA_DUMMY);
}

/** Pemetaan KOSEKA pendataan sensus (sama seperti versi sebelumnya). */
function koseka_sensus_ids_for_kecamatan($kecamatan)
{
    $map = [
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
    return $map[strtoupper(trim((string) $kecamatan))] ?? [];
}

/**
 * Memuat seluruh petugas satu periode sekaligus, dikelompokkan per wilayah.
 * Hasil: [
 *   'wilayah' => [wilayah_key => ['PPL' => [..], 'PML' => [..]]],
 *   'koseka'  => [KECAMATAN => [..]],
 * ]
 * Setiap petugas: ['name' => .., 'phone' => .., 'role' => .., 'dummy' => bool]
 * Hasil di-cache per request.
 */
function petugas_muat_periode(PDO $pdo, $periode)
{
    static $cache = [];
    $periode = periode_valid($periode) ?: PERIODE_AKTIF;
    if (isset($cache[$periode])) {
        return $cache[$periode];
    }

    $data = ['wilayah' => [], 'koseka' => []];

    if ($periode === 'REVISIT') {
        $sql = "SELECT w.kecamatan, w.kelurahan, w.lingkungan,
                       p.id AS ppl_id, p.nama_ppl, p.nomor_hp_ppl, p.is_dummy AS ppl_dummy,
                       pm.id AS pml_id, pm.nama_pml, pm.nomor_hp_pml, pm.is_dummy AS pml_dummy
                FROM tbl_alokasi_wilayah_revisit aw
                JOIN tbl_wilayah w ON w.id = aw.wilayah_id
                JOIN tbl_ppl_revisit p ON p.id = aw.ppl_id
                LEFT JOIN tbl_pml_revisit pm ON pm.id = p.pml_id
                ORDER BY p.is_dummy ASC, p.nama_ppl ASC";
    } else {
        $sql = "SELECT w.kecamatan, w.kelurahan, w.lingkungan,
                       p.id AS ppl_id, p.nama_ppl, p.nomor_hp_ppl, 0 AS ppl_dummy,
                       pm.id AS pml_id, pm.nama_pml, pm.nomor_hp_pml, 0 AS pml_dummy
                FROM tbl_alokasi_wilayah aw
                JOIN tbl_wilayah w ON w.id = aw.wilayah_id
                JOIN tbl_ppl p ON p.id = aw.ppl_id
                LEFT JOIN tbl_pml pm ON pm.id = p.pml_id
                ORDER BY p.nama_ppl ASC";
    }

    foreach ($pdo->query($sql) as $row) {
        $key = wilayah_key($row['kecamatan'], $row['kelurahan'], $row['lingkungan']);
        if (!isset($data['wilayah'][$key])) {
            $data['wilayah'][$key] = ['PPL' => [], 'PML' => []];
        }
        $data['wilayah'][$key]['PPL']['ppl' . $row['ppl_id']] = [
            'name' => trim((string) $row['nama_ppl']),
            'phone' => trim((string) $row['nomor_hp_ppl']),
            'role' => 'PPL',
            'dummy' => (bool) $row['ppl_dummy'] || nomor_is_dummy($row['nomor_hp_ppl']),
        ];
        if (!empty($row['pml_id'])) {
            $data['wilayah'][$key]['PML']['pml' . $row['pml_id']] = [
                'name' => trim((string) $row['nama_pml']),
                'phone' => trim((string) $row['nomor_hp_pml']),
                'role' => 'PML',
                'dummy' => (bool) $row['pml_dummy'] || nomor_is_dummy($row['nomor_hp_pml']),
            ];
        }
    }

    if ($periode === 'REVISIT') {
        $sql = "SELECT kk.kecamatan, k.nama_koseka, k.nomor_hp_koseka, k.is_dummy
                FROM tbl_koseka_revisit_kecamatan kk
                JOIN tbl_koseka_revisit k ON k.id = kk.koseka_id
                ORDER BY k.id ASC";
        foreach ($pdo->query($sql) as $row) {
            $data['koseka'][strtoupper(trim($row['kecamatan']))][] = [
                'name' => trim((string) $row['nama_koseka']),
                'phone' => trim((string) $row['nomor_hp_koseka']),
                'role' => 'KOSEKA',
                'dummy' => (bool) $row['is_dummy'] || nomor_is_dummy($row['nomor_hp_koseka']),
            ];
        }
    } else {
        $koseka_by_id = [];
        foreach ($pdo->query("SELECT id, nama_koseka, nomor_hp_koseka FROM tbl_koseka") as $row) {
            $koseka_by_id[(int) $row['id']] = $row;
        }
        $kecamatan_list = $pdo->query("SELECT DISTINCT kecamatan FROM tbl_wilayah")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($kecamatan_list as $kec) {
            foreach (koseka_sensus_ids_for_kecamatan($kec) as $id) {
                if (!isset($koseka_by_id[$id])) {
                    continue;
                }
                $data['koseka'][strtoupper(trim($kec))][] = [
                    'name' => trim((string) $koseka_by_id[$id]['nama_koseka']),
                    'phone' => trim((string) $koseka_by_id[$id]['nomor_hp_koseka']),
                    'role' => 'KOSEKA',
                    'dummy' => false,
                ];
            }
        }
    }

    foreach ($data['wilayah'] as $k => $v) {
        $data['wilayah'][$k]['PPL'] = array_values($v['PPL']);
        $data['wilayah'][$k]['PML'] = array_values($v['PML']);
    }

    return $cache[$periode] = $data;
}

/**
 * Petugas untuk satu wilayah pada periode tertentu.
 * Hasil: ['PPL' => [..], 'PML' => [..], 'KOSEKA' => [..]]
 */
function petugas_wilayah(PDO $pdo, $periode, $kecamatan, $kelurahan, $lingkungan)
{
    $data = petugas_muat_periode($pdo, $periode);
    $key = wilayah_key($kecamatan, $kelurahan, $lingkungan);
    $w = $data['wilayah'][$key] ?? ['PPL' => [], 'PML' => []];

    return [
        'PPL' => $w['PPL'],
        'PML' => $w['PML'],
        'KOSEKA' => $data['koseka'][strtoupper(trim((string) $kecamatan))] ?? [],
    ];
}

/** Petugas untuk satu baris tbl_laporan, sesuai periode laporan tersebut. */
function petugas_laporan(PDO $pdo, array $laporan)
{
    $periode = periode_valid($laporan['periode'] ?? '') ?: 'SENSUS';
    return petugas_wilayah($pdo, $periode, $laporan['kecamatan'] ?? '', $laporan['kelurahan'] ?? '', $laporan['nomor_lingkungan'] ?? '');
}

/** Ringkasan log notifikasi WA per laporan: [laporan_id => ['total','terkirim','dummy','detail'=>[...]]] */
function notifikasi_ringkasan(PDO $pdo, array $laporan_ids)
{
    $laporan_ids = array_values(array_filter(array_map('intval', $laporan_ids)));
    if (empty($laporan_ids)) {
        return [];
    }
    try {
        $ph = implode(',', array_fill(0, count($laporan_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM tbl_notifikasi_wa WHERE laporan_id IN ($ph) ORDER BY id ASC");
        $stmt->execute($laporan_ids);
    } catch (Exception $e) {
        return []; // tabel log belum dibuat
    }

    // Hanya proses kirim terakhir (batch terbaru) per laporan yang dihitung.
    $rows_by = [];
    foreach ($stmt as $row) {
        $rows_by[(int) $row['laporan_id']][] = $row;
    }
    $out = [];
    foreach ($rows_by as $id => $rows) {
        $last = end($rows);
        $batch = array_values(array_filter($rows, fn($r) => $r['batch'] === $last['batch']));
        $out[$id] = [
            'periode' => $last['periode'],
            'waktu' => $last['created_at'],
            'total' => count($batch),
            'terkirim' => count(array_filter($batch, fn($r) => (int) $r['terkirim'] === 1)),
            'dummy' => count(array_filter($batch, fn($r) => (int) $r['is_dummy'] === 1)),
            'jumlah_proses' => count(array_unique(array_column($rows, 'batch'))),
            'detail' => $batch,
        ];
    }
    return $out;
}

/** Simpan hasil pengiriman notifikasi ke tbl_notifikasi_wa (tidak pernah melempar error). */
function notifikasi_log_simpan(PDO $pdo, $laporan_id, $periode, array $notify_result)
{
    if (empty($notify_result['results']) || (int) $laporan_id <= 0) {
        return;
    }
    $batch = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    try {
        $stmt = $pdo->prepare("INSERT INTO tbl_notifikasi_wa
            (laporan_id, batch, periode, peran, nama_petugas, nomor_hp, is_dummy, terkirim, keterangan)
            VALUES (:laporan_id, :batch, :periode, :peran, :nama, :hp, :dummy, :terkirim, :ket)");
        foreach ($notify_result['results'] as $r) {
            $res = $r['result'] ?? [];
            $ket = !empty($r['untuk']) ? 'Nomor cadangan untuk: ' . implode(', ', $r['untuk']) : '';
            if (empty($res['ok']) && !empty($res['message'])) {
                $ket = trim($ket . ' ' . $res['message']);
            }
            $stmt->execute([
                ':laporan_id' => (int) $laporan_id,
                ':batch' => $batch,
                ':periode' => periode_valid($periode) ?: PERIODE_AKTIF,
                ':peran' => substr((string) ($r['role'] ?? ''), 0, 10),
                ':nama' => mb_substr((string) ($r['name'] ?? ''), 0, 150),
                ':hp' => substr((string) ($r['phone'] ?? ''), 0, 20),
                ':dummy' => !empty($r['dummy']) ? 1 : 0,
                ':terkirim' => !empty($res['ok']) ? 1 : 0,
                ':ket' => mb_substr($ket, 0, 255) ?: null,
            ]);
        }
    } catch (Exception $e) {
        error_log('[notifikasi_log_simpan] ' . $e->getMessage());
    }
}
