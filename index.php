<?php
// index.php
// Formulir pelaporan mandiri sensus warga dengan peta interaktif & validasi

session_start();

$page_title = "Formulir Pelaporan Sensus Mandiri";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">

            <div class="text-center mb-4">
                <!-- Logo Lokal di Form -->
                <div class="mb-3">
                    <img src="assets/img/logo-bps.png"
                        alt="Logo BPS"
                        style="height: 60px; width: auto;"
                        class="mb-2">
                    <h6 class="fw-bold text-secondary tracking-wide mb-0">BPS KOTA MANADO</h6>
                </div>
                <h3 class="fw-bold text-dark">Pelaporan Sensus Mandiri</h3>
                <p class="text-muted small">Laporkan data jika tempat tinggal atau usaha Anda belum terdata oleh petugas sensus lapangan.</p>
            </div>

            <!-- Notifikasi Pesan -->
            <?php if (isset($_SESSION['pesan_sukses'])): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?= htmlspecialchars($_SESSION['pesan_sukses']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['pesan_sukses']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['pesan_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= htmlspecialchars($_SESSION['pesan_error']); ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['pesan_error']); ?>
            <?php endif; ?>

            <form action="simpan.php" method="POST" id="formSensus">

                <!-- Honeypot Anti-Spam -->
                <div class="d-none" aria-hidden="true">
                    <input type="text" name="website_trap" tabindex="-1" autocomplete="off">
                </div>

                <!-- 1. Nama Pelapor & WhatsApp -->
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label for="nama_pelapor" class="form-label fw-semibold">Nama Kepala Keluarga / Usaha <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_pelapor" name="nama_pelapor" placeholder="Contoh: Anton Santoso / Toko Achilles" required>
                    </div>
                    <div class="col-md-5">
                        <label for="no_telepon" class="form-label fw-semibold">Nomor WhatsApp / HP <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-whatsapp text-success"></i></span>
                            <input type="tel" class="form-control" id="no_telepon" name="no_telepon" placeholder="Contoh: 085246773905" pattern="[0-9]{9,15}" required>
                        </div>
                    </div>
                </div>

                <!-- 2. Wilayah Berantai -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="kecamatan" class="form-label fw-semibold">Kecamatan <span class="text-danger">*</span></label>
                        <select class="form-select" id="kecamatan" name="kecamatan" required>
                            <option value="">-- Pilih Kecamatan --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="kelurahan" class="form-label fw-semibold">Kelurahan / Desa <span class="text-danger">*</span></label>
                        <select class="form-select" id="kelurahan" name="kelurahan" required disabled>
                            <option value="">-- Pilih Kecamatan Dulu --</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="nomor_lingkungan" class="form-label fw-semibold">Lingkungan / SLS <span class="text-danger">*</span></label>
                    <select class="form-select" id="nomor_lingkungan" name="nomor_lingkungan" required disabled>
                        <option value="">-- Pilih Kelurahan Dulu --</option>
                    </select>
                </div>

                <!-- 3. Jadwal Kunjungan (Tanggal & Jam) -->
                <!-- 3. Jadwal Kunjungan (Tanggal & Jam) -->
                <div class="mb-4 p-3 bg-light rounded border">
                    <label class="form-label fw-semibold text-primary mb-2">
                        <i class="bi bi-calendar-event me-1"></i> Waktu & Tanggal Siap Didata <span class="text-danger">*</span>
                    </label>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="tgl_kunjungan" class="form-label small text-muted">Tanggal Kunjungan</label>
                            <input type="date"
                                class="form-control"
                                id="tgl_kunjungan"
                                name="tgl_kunjungan"
                                required
                                value="<?= date('Y-m-d'); ?>"
                                min="<?= date('Y-m-d'); ?>"
                                max="2026-09-12">
                            <small class="text-danger d-block mt-1" style="font-size: 0.75rem;">
                                <i class="bi bi-info-circle me-1"></i><em>*Catatan: Pelaporan sensus mandiri hanya dapat dijadwalkan hingga tanggal 13 September 2026.</em>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label for="jam_kunjungan" class="form-label small text-muted">Rentang Waktu</label>
                            <select class="form-select" id="jam_kunjungan" name="jam_kunjungan" required>
                                <option value="">-- Pilih Waktu Kunjungan --</option>
                                <option value="08.00 - 09.00 WITA">08.00 - 09.00 WITA</option>
                                <option value="09.00 - 10.00 WITA">09.00 - 10.00 WITA</option>
                                <option value="10.00 - 11.00 WITA">10.00 - 11.00 WITA</option>
                                <option value="11.00 - 12.00 WITA">11.00 - 12.00 WITA</option>
                                <option value="12.00 - 13.00 WITA">12.00 - 13.00 WITA</option>
                                <option value="13.00 - 14.00 WITA">13.00 - 14.00 WITA</option>
                                <option value="14.00 - 15.00 WITA">14.00 - 15.00 WITA</option>
                                <option value="15.00 - 16.00 WITA">15.00 - 16.00 WITA</option>
                                <option value="16.00 - 17.00 WITA">16.00 - 17.00 WITA</option>
                                <option value="17.00 - 18.00 WITA">17.00 - 18.00 WITA</option>
                                <option value="18.00 - 19.00 WITA">18.00 - 19.00 WITA</option>
                                <option value="19.00 - 20.00 WITA">19.00 - 20.00 WITA</option>
                                <option value="20.00 - 21.00 WITA">20.00 - 21.00 WITA</option>
                                <option value="21.00 - 22.00 WITA">21.00 - 22.00 WITA</option>
                                <option value="Kapan Saja / Fleksibel">Kapan Saja / Fleksibel</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- 4. Peta Geospasial -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold mb-0">Titik Lokasi Rumah / Usaha <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnLokasi">
                            <i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saya (GPS)
                        </button>
                    </div>

                    <!-- Peta tampil dengan aman -->
                    <div id="map"></div>
                    <small class="text-muted d-block mt-2 mb-2">Klik pada peta atau geser pin marker merah ke posisi bangunan Anda.</small>

                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm bg-light" id="latitude" name="latitude" placeholder="Latitude" readonly required>
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm bg-light" id="longitude" name="longitude" placeholder="Longitude" readonly required>
                        </div>
                    </div>
                </div>
                <!-- 5. Catatan / Ciri-ciri Tempat Tinggal -->
                <div class="mb-4">
                    <label for="catatan" class="form-label fw-semibold">Catatan / Petunjuk Lokasi <span class="text-muted small fw-normal">(Opsional)</span></label>
                    <textarea class="form-control"
                        id="catatan"
                        name="catatan"
                        rows="3"
                        placeholder="Contoh: Rumah cat hijau pagar hitam, depan warung Madura, ada pohon mangga di halaman"></textarea>
                    <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">
                        <em>Ciri-ciri tempat tinggal atau keterangan lainnya yang perlu diketahui petugas pencacah lapangan saat berkunjung.</em>
                    </small>
                </div>


                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold shadow-sm">
                        <i class="bi bi-send me-1"></i> Kirim Laporan Sensus
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 border-top pt-3">
                <div class="d-grid gap-2">
                    <a href="admin/login.php" class="text-decoration-none small text-secondary">
                        <i class="bi bi-shield-lock me-1"></i> Akses Petugas / Admin &rarr;
                    </a>
                    <a href="petugas.php" class="text-decoration-none small text-secondary">
                        <i class="bi bi-person-check me-1"></i> Menu Petugas &rarr;
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Dropdown Wilayah Berantai
        const selKec = document.getElementById('kecamatan');
        const selKel = document.getElementById('kelurahan');
        const selLing = document.getElementById('nomor_lingkungan');

        fetch('get_wilayah.php?type=kecamatan')
            .then(r => r.json())
            .then(data => data.forEach(k => selKec.innerHTML += `<option value="${k}">${k}</option>`))
            .catch(err => console.error("Gagal memuat kecamatan:", err));

        selKec.addEventListener('change', function() {
            selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
            selLing.innerHTML = '<option value="">-- Pilih Kelurahan Dulu --</option>';
            selLing.disabled = true;
            if (!this.value) {
                selKel.disabled = true;
                return;
            }
            selKel.disabled = false;
            fetch(`get_wilayah.php?type=kelurahan&kecamatan=${encodeURIComponent(this.value)}`)
                .then(r => r.json())
                .then(data => data.forEach(k => selKel.innerHTML += `<option value="${k}">${k}</option>`));
        });

        selKel.addEventListener('change', function() {
            selLing.innerHTML = '<option value="">-- Pilih Lingkungan --</option>';
            if (!this.value) {
                selLing.disabled = true;
                return;
            }
            selLing.disabled = false;
            fetch(`get_wilayah.php?type=lingkungan&kecamatan=${encodeURIComponent(selKec.value)}&kelurahan=${encodeURIComponent(this.value)}`)
                .then(r => r.json())
                .then(data => data.forEach(l => selLing.innerHTML += `<option value="${l}">${l}</option>`));
        });

        // 2. Inisialisasi Peta Leaflet (Pusat Kota Manado)
        const defaultLat = 1.474830;
        const defaultLng = 124.842079;
        const map = L.map('map').setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Wajib panggil invalidateSize agar peta ter-render penuh tanpa terpotong abu-abu
        setTimeout(() => {
            map.invalidateSize();
        }, 350);

        let marker;

        function setLocation(lat, lng, zoom = 17) {
            if (!marker) {
                marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                marker.on('dragend', function(e) {
                    const pos = marker.getLatLng();
                    document.getElementById('latitude').value = pos.lat.toFixed(7);
                    document.getElementById('longitude').value = pos.lng.toFixed(7);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }
            map.setView([lat, lng], zoom);
            document.getElementById('latitude').value = parseFloat(lat).toFixed(7);
            document.getElementById('longitude').value = parseFloat(lng).toFixed(7);
        }

        map.on('click', function(e) {
            setLocation(e.latlng.lat, e.latlng.lng, map.getZoom());
        });

        // 3. Tombol GPS Ambil Lokasi
        const btnLokasi = document.getElementById('btnLokasi');
        btnLokasi.addEventListener('click', function() {
            btnLokasi.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengambil GPS...';
            btnLokasi.disabled = true;

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        setLocation(pos.coords.latitude, pos.coords.longitude, 17);
                        btnLokasi.innerHTML = '✅ Lokasi Ditemukan';
                        setTimeout(() => {
                            btnLokasi.innerHTML = '<i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saya (GPS)';
                            btnLokasi.disabled = false;
                        }, 2000);
                    },
                    function(err) {
                        alert('Gagal mendeteksi lokasi GPS. Pastikan izin lokasi aktif atau klik langsung pada peta.');
                        btnLokasi.innerHTML = '<i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saya (GPS)';
                        btnLokasi.disabled = false;
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                alert('Perangkat Anda tidak mendukung geolokasi.');
                btnLokasi.innerHTML = '<i class="bi bi-crosshair me-1"></i> Ambil Lokasi Saya (GPS)';
                btnLokasi.disabled = false;
            }
        });

        // 4. Validasi Form
        document.getElementById('formSensus').addEventListener('submit', function(e) {
            const lat = document.getElementById('latitude').value;
            if (!lat) {
                e.preventDefault();
                alert('Silakan tentukan titik lokasi pada peta terlebih dahulu!');
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>