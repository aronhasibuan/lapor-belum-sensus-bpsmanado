<?php
// config/fontte.php
// Template konfigurasi Fonnte untuk pengiriman WhatsApp otomatis dari sistem.
// 1. Isi token Fonnte Anda di bagian bawah file ini atau set environment variable FONTTE_API_KEY.
define('FONTTE_API_KEY', 'obmKHBsosnG9vxmj9oXk');
// 3. Atau jalankan: setx FONTTE_API_KEY "YOUR_FONNTE_API_KEY" di terminal Windows.

$fontte_api_key = getenv('FONTTE_API_KEY') ?: (defined('FONTTE_API_KEY') ? FONTTE_API_KEY : ($_ENV['FONTTE_API_KEY'] ?? ''));
$fontte_api_key = trim((string) $fontte_api_key);

$fontte_base_url = 'https://api.fonnte.com';
$fontte_enabled = $fontte_api_key !== '';

require_once __DIR__ . '/periode.php';
require_once __DIR__ . '/../includes/petugas_lib.php';

function fonnte_normalize_phone($phone)
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

/** Dipertahankan untuk kompatibilitas: pemetaan KOSEKA sensus per kecamatan. */
function fonnte_get_koseka_ids_for_kecamatan($kecamatan)
{
    return koseka_sensus_ids_for_kecamatan($kecamatan);
}

/**
 * Menghapus kontak ganda berdasarkan nomor HP.
 * Bila beberapa petugas berbagi nomor cadangan (dummy), pesan cukup dikirim
 * sekali, tetapi nama semua petugas yang dituju dicatat di 'untuk' supaya
 * pemegang nomor cadangan tahu harus meneruskan ke siapa.
 */
function fonnte_unique_contacts(array $contacts)
{
    $result = [];

    foreach ($contacts as $contact) {
        $phone = fonnte_normalize_phone($contact['phone'] ?? '');

        if ($phone === '') {
            continue;
        }

        $name = trim((string) ($contact['name'] ?? 'Petugas'));
        $role = trim((string) ($contact['role'] ?? ''));
        $dummy = !empty($contact['dummy']);

        if (!isset($result[$phone])) {
            $result[$phone] = [
                'name' => $name,
                'phone' => $phone,
                'role' => $role,
                'dummy' => $dummy,
                'untuk' => $dummy ? [$role . ' ' . $name] : [],
            ];
        } elseif ($dummy || $result[$phone]['dummy']) {
            $result[$phone]['dummy'] = true;
            $label = $role . ' ' . $name;
            if (!in_array($label, $result[$phone]['untuk'], true)) {
                $result[$phone]['untuk'][] = $label;
            }
        }
    }

    return array_values($result);
}

function fonnte_send_message($target_phone, $message)
{
    global $fontte_api_key, $fontte_base_url, $fontte_enabled;

    if (!$fontte_enabled) {
        return [
            'ok' => false,
            'status' => 'missing_api_key',
            'message' => 'Token Fonnte belum diatur. Pastikan FONTTE_API_KEY sudah diisi.',
        ];
    }

    $target = fonnte_normalize_phone($target_phone);

    // Mode uji: set environment variable FONNTE_DRY_RUN=1 supaya pesan
    // tidak benar-benar dikirim (dipakai saat pengujian di lokal).
    if (getenv('FONNTE_DRY_RUN') === '1' && $target !== '') {
        @file_put_contents(sys_get_temp_dir() . '/fonnte_dry_run.log', date('c') . " TO {$target}\n{$message}\n-----\n", FILE_APPEND);
        return ['ok' => true, 'status' => 'dry_run', 'message' => 'Dry run ke ' . $target, 'reason' => '', 'http_code' => 200, 'body' => ''];
    }

    if ($target === '') {
        return [
            'ok' => false,
            'status' => 'invalid_phone',
            'message' => 'Nomor WA invalid atau kosong.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 'curl_missing',
            'message' => 'PHP curl tidak tersedia. Aktifkan ekstensi cURL terlebih dahulu.',
        ];
    }

    // Endpoint resmi Fonnte adalah /send, BUKAN /send-message.
    $ch = curl_init($fontte_base_url . '/send');

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $fontte_api_key,
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'target' => $target,
        'message' => $message,
    ]);

    // Batas waktu wajib: tanpa ini satu kontak yang macet bisa menahan
    // seluruh proses submit laporan sampai PHP time-out.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    // curl_close() sengaja tidak dipanggil: deprecated sejak PHP 8.5
    // dan sudah tidak berefek sejak PHP 8.0.

    $body = is_string($response) ? $response : '';

    if ($curl_error !== '') {
        return [
            'ok' => false,
            'status' => 'curl_error',
            'message' => $curl_error,
            'http_code' => $http_code,
            'body' => $body,
        ];
    }

    // Fonnte tetap membalas HTTP 200 walaupun pengiriman ditolak, jadi HTTP code
    // saja tidak cukup. Status sebenarnya ada di field "status" pada body JSON.
    $http_ok = ($http_code >= 200 && $http_code < 300);
    $decoded = json_decode($body, true);
    $api_ok  = true;
    $reason  = '';

    if (is_array($decoded)) {
        if (array_key_exists('status', $decoded)) {
            $api_ok = filter_var($decoded['status'], FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['reason', 'detail', 'message'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                $reason = $decoded[$key];
                break;
            }
        }
    }

    $ok = $http_ok && $api_ok;

    return [
        'ok' => $ok,
        'status' => $ok ? 'sent' : ($http_ok ? 'api_rejected' : 'http_' . $http_code),
        'message' => $ok
            ? ('Pesan dikirim ke ' . $target)
            : ('Gagal kirim ke ' . $target . ($reason !== '' ? ' - ' . $reason : '')),
        'reason' => $reason,
        'http_code' => $http_code,
        'body' => $body,
    ];
}

/**
 * Menyusun isi pesan WhatsApp untuk satu petugas.
 * Isi inti sama untuk semua, tapi sapaan dan instruksi tindak lanjut
 * disesuaikan dengan peran (PPL / PML / KOSEKA).
 */
function fonnte_build_report_message(array $contact, array $d)
{
    $role = strtoupper((string) ($contact['role'] ?? ''));
    $nama_petugas = trim((string) ($contact['name'] ?? ''));
    $sapaan = $nama_petugas !== '' ? $nama_petugas : 'Bapak/Ibu';

    $pembuka = [
        'PPL' => "Halo {$sapaan}, ada warga di wilayah tugas Anda yang melapor belum didata.",
        'PML' => "Halo {$sapaan}, masuk laporan warga belum didata di wilayah tim Anda.",
        'KOSEKA' => "Halo {$sapaan}, tembusan laporan warga belum didata di wilayah koordinasi Anda.",
    ];

    $penutup = [
        'PPL' => "*TINDAK LANJUT*\n"
            . "1. Hubungi pelapor lebih dulu untuk memastikan waktu kunjungan.\n"
            . "2. Lakukan " . (periode_is_revisit($d['periode']) ? 'kunjungan revisit' : 'pendataan') . " sesuai prosedur SE2026.\n"
            . "3. Laporkan hasilnya ke PML setelah selesai.\n\n"
            . "Mohon ditindaklanjuti maksimal 2x24 jam sejak pesan ini diterima.",
        'PML' => "*TINDAK LANJUT*\n"
            . "Mohon dipastikan PPL yang bersangkutan menindaklanjuti maksimal 2x24 jam, "
            . "lalu perbarui statusnya pada dashboard.",
        'KOSEKA' => "*TINDAK LANJUT*\n"
            . "Pesan ini bersifat pemberitahuan untuk monitoring wilayah. "
            . "Penanganan di lapangan dilakukan oleh PPL dengan pengawasan PML.",
    ];

    $lines = [];
    $lines[] = "*LAPORAN WARGA BELUM TERSENSUS*";
    $lines[] = '_' . periode_label($d['periode']) . ' - BPS Kota Manado_';
    $lines[] = "";
    if (!empty($contact['dummy'])) {
        $lines[] = "Halo, pesan ini dikirim ke nomor cadangan karena nomor WA petugas berikut belum tersedia:";
        foreach ((array) ($contact['untuk'] ?? []) as $untuk) {
            $lines[] = "- " . $untuk;
        }
        $lines[] = "Mohon diteruskan ke petugas yang bersangkutan.";
    } else {
        $lines[] = $pembuka[$role] ?? "Halo {$sapaan}, ada laporan warga belum didata.";
    }
    $lines[] = "";

    $lines[] = "*DATA LAPORAN*";
    $lines[] = "No. Laporan : " . $d['no_laporan'];
    $lines[] = "Masuk : " . $d['waktu_masuk'] . " WITA";
    $lines[] = "Nama Kepala Keluarga/Usaha : " . ($d['nama_pelapor'] !== '' ? $d['nama_pelapor'] : '-');
    $lines[] = "Kontak Pelapor : " . ($d['no_telepon'] !== '' ? $d['no_telepon'] : '-');
    if ($d['wa_pelapor'] !== '') {
        $lines[] = "Chat Pelapor : " . $d['wa_pelapor'];
    }
    $lines[] = "";

    $lines[] = "*LOKASI*";
    $lines[] = "Kecamatan : " . ($d['kecamatan'] !== '' ? $d['kecamatan'] : '-');
    $lines[] = "Kelurahan : " . ($d['kelurahan'] !== '' ? $d['kelurahan'] : '-');
    $lines[] = "Lingkungan : " . ($d['lingkungan'] !== '' ? $d['lingkungan'] : '-');
    $lines[] = "Titik Lokasi : " . ($d['peta'] !== '' ? $d['peta'] : 'Koordinat tidak dikirim pelapor');
    $lines[] = "";

    $lines[] = "*WAKTU YANG TERSEDIA*";
    $lines[] = $d['waktu_lapor'] !== '' ? $d['waktu_lapor'] : 'Tidak disebutkan, mohon dikonfirmasi ke pelapor.';

    if ($d['catatan'] !== '') {
        $lines[] = "";
        $lines[] = "*CATATAN / PETUNJUK LOKASI*";
        $lines[] = $d['catatan'];
    }

    $lines[] = "";
    $lines[] = $penutup[$role] ?? "Mohon ditindaklanjuti sesuai kewenangan masing-masing.";
    $lines[] = "";
    $lines[] = "_Pesan otomatis dari sistem Lapor Belum Sensus. Mohon tidak membalas pesan ini._";

    return implode("\n", $lines);
}

/**
 * Mengirim notifikasi laporan ke petugas wilayah.
 * $periode: 'REVISIT' atau 'SENSUS'. Default mengikuti PERIODE_AKTIF (config/periode.php).
 * Bila wilayah tidak punya petugas sama sekali, pesan dikirim ke nomor cadangan.
 */
function fonnte_notify_report_submission(PDO $pdo, array $report_data, $periode = null)
{
    global $fontte_enabled;

    $periode = periode_valid($periode ?? '') ?: PERIODE_AKTIF;

    if (!$fontte_enabled) {
        return [
            'sent' => 0,
            'total' => 0,
            'disabled' => true,
            'periode' => $periode,
            'message' => 'Fonnte belum aktif karena token API belum diisi.',
        ];
    }

    $kecamatan = trim((string) ($report_data['kecamatan'] ?? ''));
    $kelurahan = trim((string) ($report_data['kelurahan'] ?? ''));
    $lingkungan = trim((string) ($report_data['nomor_lingkungan'] ?? ''));
    $nama_pelapor = trim((string) ($report_data['nama_pelapor'] ?? ''));
    $no_telepon = trim((string) ($report_data['no_telepon'] ?? ''));
    $waktu_lapor = trim((string) ($report_data['waktu_pendataan'] ?? ''));
    $laporan_id  = (int) ($report_data['id'] ?? 0);
    $catatan     = trim((string) ($report_data['catatan'] ?? ''));
    $latitude    = trim((string) ($report_data['latitude'] ?? ''));
    $longitude   = trim((string) ($report_data['longitude'] ?? ''));

    $petugas = petugas_wilayah($pdo, $periode, $kecamatan, $kelurahan, $lingkungan);

    // Wilayah tanpa PPL/PML (mis. lingkungan baru): tetap kirim ke nomor cadangan.
    if (empty($petugas['PPL'])) {
        $petugas['PPL'][] = ['name' => 'Wilayah ' . $kelurahan . ' ' . $lingkungan, 'phone' => NOMOR_WA_DUMMY, 'role' => 'PPL', 'dummy' => true];
    }
    if (empty($petugas['PML'])) {
        $petugas['PML'][] = ['name' => 'Wilayah ' . $kelurahan . ' ' . $lingkungan, 'phone' => NOMOR_WA_DUMMY, 'role' => 'PML', 'dummy' => true];
    }

    // Urutan penting: PPL dulu, lalu PML, lalu KOSEKA.
    $contacts = array_merge($petugas['PPL'], $petugas['PML'], $petugas['KOSEKA']);
    $unique_contacts = fonnte_unique_contacts($contacts);

    if (empty($unique_contacts)) {
        return [
            'sent' => 0,
            'total' => 0,
            'disabled' => false,
            'periode' => $periode,
            'message' => 'Tidak ada kontak PPL, PML, atau KOSEKA yang cocok untuk wilayah ini.',
        ];
    }

    // ---- Bahan pesan ----
    $no_laporan = $laporan_id > 0 ? '#' . str_pad((string) $laporan_id, 4, '0', STR_PAD_LEFT) : '-';
    $waktu_masuk = date('d/m/Y H:i');

    $peta = '';
    if (is_numeric($latitude) && is_numeric($longitude)) {
        $peta = 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude;
    }

    $wa_pelapor = '';
    if ($no_telepon !== '') {
        $wa_target = fonnte_normalize_phone($no_telepon);
        if ($wa_target !== '') {
            $wa_pelapor = 'https://wa.me/' . $wa_target;
        }
    }

    $sent = 0;
    $results = [];
    $failed = [];

    foreach ($unique_contacts as $contact) {
        $message = fonnte_build_report_message($contact, [
            'periode'      => $periode,
            'no_laporan'   => $no_laporan,
            'waktu_masuk'  => $waktu_masuk,
            'nama_pelapor' => $nama_pelapor,
            'no_telepon'   => $no_telepon,
            'wa_pelapor'   => $wa_pelapor,
            'kecamatan'    => $kecamatan,
            'kelurahan'    => $kelurahan,
            'lingkungan'   => $lingkungan,
            'peta'         => $peta,
            'waktu_lapor'  => $waktu_lapor,
            'catatan'      => $catatan,
        ]);

        $result = fonnte_send_message($contact['phone'], $message);
        $results[] = [
            'name' => $contact['name'],
            'role' => $contact['role'],
            'phone' => $contact['phone'],
            'dummy' => $contact['dummy'],
            'untuk' => $contact['untuk'],
            'result' => $result,
        ];

        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $failed[] = $contact['role'] . ' ' . $contact['name']
                . (!empty($result['reason']) ? ' (' . $result['reason'] . ')' : '');
        }
    }

    return [
        'sent' => $sent,
        'total' => count($unique_contacts),
        'disabled' => false,
        'periode' => $periode,
        'results' => $results,
        'failed' => $failed,
        'message' => 'Pengiriman notifikasi selesai.',
    ];
}
