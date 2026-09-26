<?php
// config/periode.php
// Pengaturan periode kegiatan yang sedang berjalan.
//
// PERIODE_AKTIF menentukan:
//   - label periode untuk setiap laporan baru dari warga (kolom tbl_laporan.periode)
//   - petugas yang menerima notifikasi WhatsApp
//       'REVISIT' -> tbl_ppl_revisit / tbl_pml_revisit / tbl_koseka_revisit
//       'SENSUS'  -> tbl_ppl / tbl_pml / tbl_koseka (petugas pendataan SE2026)
//
// Ubah ke 'SENSUS' hanya jika ingin kembali memakai petugas pendataan lama.

if (!defined('PERIODE_AKTIF')) {
    define('PERIODE_AKTIF', 'REVISIT');
}

// Nomor cadangan untuk petugas yang belum punya nomor HP/WA yang meyakinkan.
if (!defined('NOMOR_WA_DUMMY')) {
    define('NOMOR_WA_DUMMY', '08887654811');
}

// Jam pada pesan WhatsApp ditulis "WITA", jadi zona waktu PHP disamakan.
date_default_timezone_set('Asia/Makassar');
