<?php
session_start();

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
    $nama_lengkap_petugas = trim($_POST['nama_lengkap_petugas'] ?? '');
    $catatan_keterangan = trim($_POST['catatan_keterangan'] ?? '');

    if ($laporan_id <= 0 || $nama_lengkap_petugas === '' || $catatan_keterangan === '') {
        $_SESSION['petugas_error'] = 'Semua field wajib diisi sebelum submit hasil kunjungan.';
        header('Location: petugas.php');
        exit;
    }

    try {
        $cekStmt = $pdo->prepare("SELECT id FROM tbl_laporan WHERE id = :id AND status = 'Belum Ditindaklanjuti' LIMIT 1");
        $cekStmt->execute([':id' => $laporan_id]);

        if (!$cekStmt->fetch()) {
            $_SESSION['petugas_error'] = 'Laporan yang dipilih sudah tidak tersedia atau sudah ditindaklanjuti.';
            header('Location: petugas.php');
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO tbl_hasil_kunjungan (laporan_id, nama_lengkap_petugas, catatan_keterangan) VALUES (:laporan_id, :nama_lengkap_petugas, :catatan_keterangan)");
        $stmt->execute([
            ':laporan_id' => $laporan_id,
            ':nama_lengkap_petugas' => $nama_lengkap_petugas,
            ':catatan_keterangan' => $catatan_keterangan,
        ]);

        $updateStmt = $pdo->prepare("UPDATE tbl_laporan SET status = 'Sudah Ditindaklanjuti' WHERE id = :id");
        $updateStmt->execute([':id' => $laporan_id]);

        $_SESSION['petugas_success'] = 'Hasil kunjungan berhasil disimpan dan status laporan telah diperbarui.';
        header('Location: petugas.php');
        exit;
    } catch (Exception $e) {
        error_log('[petugas.php] Gagal menyimpan hasil kunjungan: ' . $e->getMessage());
        $_SESSION['petugas_error'] = 'Terjadi kesalahan saat menyimpan hasil kunjungan. Silakan coba lagi.';
        header('Location: petugas.php');
        exit;
    }
}

$page_title = "Laporan Follow Up Masyarakat Belum Didata";
require_once __DIR__ . '/includes/header.php';

$laporanStmt = $pdo->query("SELECT id, nama_pelapor, kecamatan, kelurahan, nomor_lingkungan, status, waktu_pendataan FROM tbl_laporan WHERE status = 'Belum Ditindaklanjuti' ORDER BY id DESC");
$laporanList = $laporanStmt->fetchAll();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="text-center mb-4">
                <div class="mb-3">
                    <img src="assets/img/logo-bps.png" alt="Logo BPS" style="height: 60px; width: auto;" class="mb-2">
                    <h6 class="fw-bold text-secondary tracking-wide mb-0">BPS KOTA MANADO</h6>
                </div>
                <h3 class="fw-bold text-dark">Laporan Follow Up Masyarakat Belum Didata</h3>
                <p class="text-muted small mb-0">Catat hasil kunjungan dan pendataan terhadap masyarakat yang masih berstatus belum ditindaklanjuti.</p>
            </div>

            <?php if (isset($_SESSION['petugas_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?= htmlspecialchars($_SESSION['petugas_success']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['petugas_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['petugas_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= htmlspecialchars($_SESSION['petugas_error']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['petugas_error']); ?>
            <?php endif; ?>

            <div class="card card-custom p-4">
                <?php if (empty($laporanList)): ?>
                    <div class="alert alert-info mb-0" role="alert">
                        <i class="bi bi-info-circle me-2"></i> Saat ini tidak ada laporan dengan status <strong>Belum Ditindaklanjuti</strong>.
                    </div>
                <?php else: ?>
                    <form action="petugas.php" method="POST">
                        <div class="mb-3">
                            <label for="laporan_id" class="form-label fw-semibold">Pilih Masyarakat / Laporan <span class="text-danger">*</span></label>
                            <select class="form-select" id="laporan_id" name="laporan_id" required>
                                <option value="">-- Pilih laporan belum ditindaklanjuti --</option>
                                <?php foreach ($laporanList as $laporan): ?>
                                    <option value="<?= (int)$laporan['id']; ?>">
                                        <?= htmlspecialchars('#' . $laporan['id'] . ' - ' . $laporan['nama_pelapor'] . ' | ' . $laporan['kecamatan'] . ' / ' . $laporan['kelurahan'] . ' / ' . $laporan['nomor_lingkungan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nama_lengkap_petugas" class="form-label fw-semibold">Nama Lengkap Petugas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap_petugas" name="nama_lengkap_petugas" placeholder="Contoh: Ahmad Pratama" required>
                        </div>

                        <div class="mb-4">
                            <label for="catatan_keterangan" class="form-label fw-semibold">Catatan / Keterangan Hasil Kunjungan dan Pendataan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="catatan_keterangan" name="catatan_keterangan" rows="5" placeholder="Contoh: Sudah dilakukan pengecekan langsung, rumah sudah terdata dan data sesuai dengan dokumen pendukung..." required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-semibold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Submit
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="text-center mt-4 border-top pt-3">
                <a href="index.php" class="text-decoration-none small text-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Formulir Pelaporan
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>