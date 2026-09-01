<?php
// diagnosa_fonnte.php
// Halaman diagnosa koneksi Fonnte. Menggantikan test_fontte.php.
// HANYA untuk admin yang sudah login.

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Akses ditolak. Silakan login sebagai admin terlebih dahulu.');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/fontte.php';

header('Content-Type: text/plain; charset=utf-8');

function fonnte_api_call($endpoint, array $fields = [])
{
    global $fontte_api_key, $fontte_base_url;

    $ch = curl_init($fontte_base_url . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $fontte_api_key,
        'Accept: application/json',
    ]);
    if ($fields) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['body' => $body, 'error' => $err, 'http' => $code, 'json' => json_decode($body, true)];
}

echo "=== DIAGNOSA FONNTE ===\n\n";

// --- 1. Token ---
echo "1. TOKEN\n";
if ($fontte_api_key === '') {
    echo "   GAGAL: token kosong. Isi FONTTE_API_KEY di config/fontte.php.\n";
    exit;
}
echo "   Terisi, panjang " . strlen($fontte_api_key) . " karakter";
echo ", diawali '" . substr($fontte_api_key, 0, 4) . "...'\n\n";

// --- 2. Ekstensi cURL ---
echo "2. EKSTENSI cURL\n";
echo function_exists('curl_init') ? "   Tersedia.\n\n" : "   GAGAL: ekstensi cURL tidak aktif.\n\n";

// --- 3. Status perangkat ---
echo "3. STATUS PERANGKAT (POST /device)\n";
$dev = fonnte_api_call('/device');

if ($dev['error'] !== '') {
    echo "   GAGAL menghubungi Fonnte: {$dev['error']}\n";
    echo "   Kemungkinan hosting memblokir koneksi keluar ke api.fonnte.com.\n\n";
} else {
    $d = $dev['json'];
    echo "   HTTP: {$dev['http']}\n";

    if (!is_array($d)) {
        echo "   Respons tidak berbentuk JSON:\n   " . substr((string) $dev['body'], 0, 300) . "\n\n";
    } elseif (empty($d['status'])) {
        echo "   DITOLAK. Alasan: " . ($d['reason'] ?? '(tidak disebutkan)') . "\n";
        echo "   -> Kalau alasannya 'token invalid', ambil ulang token di dashboard Fonnte.\n\n";
    } else {
        $status  = $d['device_status'] ?? '?';
        $quota   = $d['quota'] ?? '?';
        echo "   Nomor perangkat : " . ($d['device'] ?? '?') . "\n";
        echo "   Nama            : " . ($d['name'] ?? '?') . "\n";
        echo "   Paket           : " . ($d['package'] ?? '?') . "\n";
        echo "   Koneksi         : {$status}\n";
        echo "   Sisa kuota      : {$quota}\n";
        echo "   Kedaluwarsa     : " . ($d['expired'] ?? '?') . "\n\n";

        if ($status !== 'connect') {
            echo "   >>> INI PENYEBABNYA: perangkat TIDAK terhubung.\n";
            echo "       Scan ulang QR di dashboard Fonnte, pastikan HP pengirim\n";
            echo "       online dan WhatsApp-nya tidak logout.\n\n";
        }
        if (is_numeric($quota) && (int) $quota <= 0) {
            echo "   >>> INI PENYEBABNYA: kuota habis. Isi ulang kuota Fonnte.\n\n";
        }
    }
}

// --- 4. Uji kirim ke satu nomor ---
echo "4. UJI KIRIM\n";
if (!isset($_GET['nomor'])) {
    echo "   Dilewati. Untuk mencoba kirim satu pesan:\n";
    echo "   diagnosa_fonnte.php?nomor=08xxxxxxxxxx\n";
    echo "   (pakai nomor Anda sendiri, jangan nomor petugas)\n";
    exit;
}

$target = fonnte_normalize_phone($_GET['nomor']);
echo "   Nomor asli       : " . $_GET['nomor'] . "\n";
echo "   Setelah normalisasi: {$target}\n";

$res = fonnte_send_message($target, "Tes diagnosa sistem Lapor Belum Sensus BPS Kota Manado. Abaikan pesan ini.");
echo "   Hasil: " . ($res['ok'] ? 'BERHASIL' : 'GAGAL') . "\n";
if (!empty($res['reason'])) {
    echo "   Alasan: {$res['reason']}\n";
}
echo "   Respons mentah Fonnte:\n   " . substr((string) ($res['body'] ?? ''), 0, 500) . "\n";
