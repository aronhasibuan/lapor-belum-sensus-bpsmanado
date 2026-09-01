<?php
// simpan.php
session_start();

// Perbaikan jalur koneksi database (sesuaikan jika nama file Anda koneksi.php)
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/koneksi.php')) {
    require_once __DIR__ . '/koneksi.php';
} else {
    die("File koneksi database tidak ditemukan.");
}

require_once __DIR__ . '/config/fontte.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Filter Honeypot Anti-Bot
    if (!empty($_POST['website_trap'])) {
        header("Location: index.php");
        exit;
    }

    // 2. Ambil dan bersihkan data input
    $nama_pelapor     = trim($_POST['nama_pelapor'] ?? '');
    $no_telepon       = trim($_POST['no_telepon'] ?? '');
    $kecamatan        = trim($_POST['kecamatan'] ?? '');
    $kelurahan        = trim($_POST['kelurahan'] ?? '');
    $nomor_lingkungan = trim($_POST['nomor_lingkungan'] ?? '');
    $catatan          = trim($_POST['catatan'] ?? '');
    $tgl_kunjungan    = trim($_POST['tgl_kunjungan'] ?? '');
    $jam_kunjungan    = trim($_POST['jam_kunjungan'] ?? '');
    $latitude         = trim($_POST['latitude'] ?? '');
    $longitude        = trim($_POST['longitude'] ?? '');

    // 3. Validasi kelengkapan data wajib
    if (empty($nama_pelapor) || empty($no_telepon) || empty($kecamatan) || empty($kelurahan) || empty($nomor_lingkungan) || empty($latitude) || empty($longitude)) {
        $_SESSION['pesan_error'] = "Harap lengkapi seluruh kolom formulir dan tentukan titik lokasi pada peta!";
        header("Location: index.php");
        exit;
    }

    // 4. Format Jadwal Kunjungan
    if (!empty($tgl_kunjungan)) {
        $tgl_formatted = date('d/m/Y', strtotime($tgl_kunjungan));
        $waktu_pendataan = $tgl_formatted . ' - ' . ($jam_kunjungan ?: 'Fleksibel');
    } else {
        $waktu_pendataan = $jam_kunjungan ?: 'Kapan Saja / Fleksibel';
    }

    // 5. Simpan ke Database (Mendukung variabel $pdo atau $conn)
    try {
        $report_inserted_id = null;

        if (isset($pdo)) {
            // Jika koneksi menggunakan PDO
            $sql = "INSERT INTO tbl_laporan (nama_pelapor, no_telepon, kecamatan, kelurahan, nomor_lingkungan, catatan, waktu_pendataan, latitude, longitude, status) 
                    VALUES (:nama_pelapor, :no_telepon, :kecamatan, :kelurahan, :nomor_lingkungan, :catatan, :waktu_pendataan, :latitude, :longitude, 'Belum Ditindaklanjuti')";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama_pelapor'     => $nama_pelapor,
                ':no_telepon'       => $no_telepon,
                ':kecamatan'        => $kecamatan,
                ':kelurahan'        => $kelurahan,
                ':nomor_lingkungan' => $nomor_lingkungan,
                ':catatan'          => $catatan,
                ':waktu_pendataan'  => $waktu_pendataan,
                ':latitude'         => $latitude,
                ':longitude'        => $longitude
            ]);
            $report_inserted_id = (int) $pdo->lastInsertId();
        } elseif (isset($conn) || isset($koneksi)) {
            // Jika koneksi menggunakan MySQLi
            $db = isset($conn) ? $conn : $koneksi;
            $stmt = $db->prepare("INSERT INTO tbl_laporan (nama_pelapor, no_telepon, kecamatan, kelurahan, nomor_lingkungan, catatan, waktu_pendataan, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Belum Ditindaklanjuti')");
            $stmt->bind_param("sssssssss", $nama_pelapor, $no_telepon, $kecamatan, $kelurahan, $nomor_lingkungan, $catatan, $waktu_pendataan, $latitude, $longitude);
            $stmt->execute();
            $report_inserted_id = (int) $db->insert_id;
        }

        // Laporan sudah aman tersimpan di titik ini.
        $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim! Petugas sensus wilayah Anda akan segera menghubungi atau mendatangi lokasi sesuai jadwal.";
    } catch (Exception $e) {
        // Detail teknis tidak ditampilkan ke publik, cukup dicatat di log server.
        error_log('[simpan.php] Gagal menyimpan laporan: ' . $e->getMessage());
        $_SESSION['pesan_error'] = "Terjadi kesalahan sistem saat menyimpan laporan. Silakan coba beberapa saat lagi.";
        header("Location: index.php");
        exit;
    }

    // Notifikasi WhatsApp DILUAR try/catch penyimpanan.
    // Kalau Fonnte bermasalah, laporan warga tetap tersimpan dan pelapor
    // tidak boleh melihat pesan seolah-olah laporannya gagal.
    if ($report_inserted_id > 0 && isset($pdo)) {
        try {
            $notify_result = fonnte_notify_report_submission($pdo, [
                'id' => $report_inserted_id,
                'kecamatan' => $kecamatan,
                'kelurahan' => $kelurahan,
                'nomor_lingkungan' => $nomor_lingkungan,
                'nama_pelapor' => $nama_pelapor,
                'no_telepon' => $no_telepon,
                'waktu_pendataan' => $waktu_pendataan,
                'catatan' => $catatan,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            if (!empty($notify_result['disabled'])) {
                $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim. Notifikasi WhatsApp belum aktif karena token API belum diatur.";
            } elseif (empty($notify_result['total'])) {
                $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim. Tidak ada kontak petugas untuk wilayah ini, jadi notifikasi WhatsApp belum dikirim.";
            } elseif ($notify_result['sent'] === $notify_result['total']) {
                $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim dan notifikasi WhatsApp sudah diteruskan ke " . $notify_result['total'] . " petugas.";
            } else {
                // Sebagian gagal: catat siapa saja supaya bisa ditindaklanjuti manual.
                if (!empty($notify_result['failed'])) {
                    error_log('[simpan.php] Notifikasi gagal ke: ' . implode('; ', $notify_result['failed']));
                }
                $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim. Notifikasi WhatsApp baru sampai ke " . $notify_result['sent'] . " dari " . $notify_result['total'] . " petugas, sisanya akan ditindaklanjuti admin.";
            }
        } catch (Exception $e) {
            error_log('[simpan.php] Notifikasi WhatsApp gagal: ' . $e->getMessage());
            $_SESSION['pesan_sukses'] = "Laporan berhasil terkirim. Notifikasi WhatsApp sedang bermasalah, tetapi laporan Anda sudah tercatat.";
        }
    }
}

header("Location: index.php");
exit;
