# Pemasangan Petugas Revisit SE2026

## Urutan (penting)
1. **Backup database** lewat phpMyAdmin (Export) terlebih dahulu.
2. Di phpMyAdmin, pilih database `koth7791_lapor-belum-sensus` → tab **Import** →
   unggah `sql/migrasi_petugas_revisit_se2026.sql` → Go.
3. Setelah itu baru unggah/timpa file PHP berikut ke hosting:
   - `config/fontte.php`, `config/periode.php` (baru)
   - `includes/petugas_lib.php` (baru), `includes/admin_ui.php` (baru), `includes/navbar.php`
   - `simpan.php`, `petugas.php`
   - `admin/dashboard.php`, `admin/export.php`, `admin/petugas_revisit.php` (baru), `admin/kirim_notifikasi.php` (baru)

   Kode baru membaca kolom `tbl_laporan.periode`, jadi SQL harus dijalankan lebih dulu.

## Setelah terpasang
- Laporan baru otomatis bertanda **Revisit 2** dan notifikasi WA memakai roster/alokasi khusus
  `tbl_*_revisit_2` dari sheet `Alokasi Revisit 2`; KOSEKA revisit tetap mengikuti kecamatan.
- Laporan lama bertanda **SENSUS** dipetakan menjadi **Pendataan**; laporan lama bertanda
  **REVISIT** dipetakan menjadi **Revisit 1**.
- Menu **Petugas Revisit** dipakai untuk mengisi nomor HP petugas yang masih dummy (08887654811).
- Tombol alihkan pada laporan **Pendataan** memindahkannya ke **Revisit 2** sekaligus mengirim WA.
- Untuk kembali memakai petugas pendataan lama, ubah `PERIODE_AKTIF` di `config/periode.php` menjadi `'PENDATAAN'`.

## Catatan
- Skrip SQL kompatibel dengan **MySQL 5.7/8.x** dan **MariaDB** (sudah diuji di MySQL 8.0 dan MariaDB).
- Bila impor sebelumnya gagal di tengah jalan, cukup impor ulang skrip ini. Tidak perlu menghapus apa pun.
- Menjalankan ulang skrip SQL hanya mengisi ulang tabel `*_revisit_2` dari sheet **Alokasi Revisit 2**.
  Data dan nomor petugas **Revisit 1** tidak dihapus atau ditimpa. Nomor Revisit 2 yang sudah diedit
  lewat admin akan kembali ke nomor sumber atau nomor dummy.
- Satu PML tidak memiliki nomor terverifikasi di sumber/database lama dan memakai nomor dummy;
  lengkapi melalui menu **Petugas Revisit** sebelum mengandalkan notifikasi WhatsApp untuk PML itu.
- `daftar_petugas_revisit_hasil_olah.csv` berisi daftar PPL dan PML revisit beserta nomor yang dipakai,
  untuk dicek manual.
