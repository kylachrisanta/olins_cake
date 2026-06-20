<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['pelanggan_id'])) {
    header("Location: masuk.php");
    exit;
}

$id_pelanggan = $_SESSION['pelanggan_id'];

// Ambil Data Profil Pelanggan untuk Prefill Form
$cust_stmt = $conn->prepare("SELECT nama_lengkap, nomor_wa FROM pelanggan WHERE id_pelanggan = ?");
$cust_stmt->bind_param("i", $id_pelanggan);
$cust_stmt->execute();
$cust_res = $cust_stmt->get_result();
$customer = $cust_res->fetch_assoc();
$cust_stmt->close();

// Ambil parameter items (ID Keranjang terpilih)
$items_str = isset($_GET['items']) ? $_GET['items'] : '';
if (empty($items_str)) {
    header("Location: keranjang.php");
    exit;
}

// Bersihkan data ID
$id_array = array_map('intval', explode(',', $items_str));
$placeholders = implode(',', array_fill(0, count($id_array), '?'));

// Ambil data produk di keranjang belanja yang terpilih
$query = "SELECT k.id_keranjang, k.jumlah, p.id_produk, p.nama_produk, p.harga, p.gambar, p.kategori, p.ukuran 
          FROM keranjang k 
          JOIN produk p ON k.id_produk = p.id_produk 
          WHERE k.id_pelanggan = ? AND k.id_keranjang IN ($placeholders)";
          
$stmt = $conn->prepare($query);
$types = 'i' . str_repeat('i', count($id_array));
$bind_params = array_merge([$id_pelanggan], $id_array);
$stmt->bind_param($types, ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

$checkout_items = [];
$subtotal_belanja = 0;

while ($row = $result->fetch_assoc()) {
    $checkout_items[] = $row;
    $subtotal_belanja += ($row['harga'] * $row['jumlah']);
}
$stmt->close();

// Jika tidak ada item yang cocok, alihkan kembali ke keranjang
if (count($checkout_items) === 0) {
    header("Location: keranjang.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled">
        <div class="container navbar">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-cake-candles"></i> Olin's <span>Cake</span>
            </a>
            
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <ul class="nav-menu" id="nav-menu">
                <li class="dropdown-container">
                    <span class="dropdown-trigger">
                        Beranda <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </span>
                    <ul class="dropdown-menu-list">
                        <li><a href="index.php#tentang" class="dropdown-menu-item">Tentang Kami</a></li>
                        <li><a href="index.php#produk" class="dropdown-menu-item">Produk Favorit</a></li>
                        <li><a href="index.php#cara-pesan" class="dropdown-menu-item">Cara Pesan</a></li>
                        <li><a href="index.php#testimoni" class="dropdown-menu-item">Testimoni</a></li>
                        <li><a href="index.php#hubungi" class="dropdown-menu-item">Hubungi Kami</a></li>
                    </ul>
                </li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Checkout Section -->
    <section class="checkout-section">
        <div class="container">
            
            <div class="checkout-title-area">
                <a href="keranjang.php" class="back-to-cart-link">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Keranjang
                </a>
                <h1>Checkout Pemesanan</h1>
                <p>Lengkapi formulir penerimaan dan jadwal pre-order untuk menyelesaikan pesanan Anda.</p>
            </div>

            <form action="proses_checkout.php" method="POST" id="checkout-form">
                <!-- Kirim data ID keranjang terpilih -->
                <input type="hidden" name="items" value="<?= htmlspecialchars($items_str) ?>">
                <input type="hidden" id="input-subtotal" name="subtotal" value="<?= $subtotal_belanja ?>">
                <input type="hidden" id="input-jarak" name="jarak_km" value="0">
                <input type="hidden" id="input-ongkir" name="ongkos_kirim" value="0">
                <input type="hidden" id="input-total" name="total_bayar" value="<?= $subtotal_belanja ?>">

                <div class="checkout-grid">
                    
                    <!-- Kolom Kiri: Formulir Informasi -->
                    <div class="checkout-left-col">
                        
                        <!-- Card 1: Data Penerima -->
                        <div class="checkout-card">
                            <div class="checkout-card-header">
                                <i class="fa-solid fa-user-check"></i>
                                <h2>Data Penerima</h2>
                            </div>
                            <div class="checkout-card-body">
                                <div class="form-grid-2">
                                    <div class="contact-form-group">
                                        <label for="nama_penerima">Nama Penerima <span class="text-danger">*</span></label>
                                        <input type="text" id="nama_penerima" name="nama_penerima" class="contact-form-control" placeholder="Masukkan nama lengkap penerima" value="<?= htmlspecialchars($customer['nama_lengkap']) ?>" required>
                                    </div>
                                    <div class="contact-form-group">
                                        <label for="nomor_wa">Nomor WhatsApp <span class="text-muted">(Otomatis Terbaca)</span></label>
                                        <input type="text" id="nomor_wa" name="nomor_wa_display" class="contact-form-control" value="<?= htmlspecialchars($customer['nomor_wa']) ?>" readonly style="background-color: var(--warm-bg); cursor: not-allowed;">
                                        <input type="hidden" name="nomor_wa" value="<?= htmlspecialchars($customer['nomor_wa']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Metode Penerimaan Pesanan -->
                        <div class="checkout-card">
                            <div class="checkout-card-header">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                                <h2>Metode Penerimaan Pesanan</h2>
                            </div>
                            <div class="checkout-card-body">
                                <div class="method-selector-grid">
                                    <!-- Radio Diantar ke Alamat -->
                                    <label class="method-card" id="label-kirim">
                                        <input type="radio" name="metode_pengiriman" value="Kirim ke Alamat" class="method-radio" checked onchange="toggleDeliveryMethod(this.value)">
                                        <div class="method-card-content">
                                            <i class="fa-solid fa-truck-fast"></i>
                                            <span class="title">Diantar ke Alamat</span>
                                            <span class="desc">Kue diantar kurir langsung ke alamat tujuan</span>
                                        </div>
                                    </label>
                                    
                                    <!-- Radio Ambil di Toko -->
                                    <label class="method-card" id="label-ambil">
                                        <input type="radio" name="metode_pengiriman" value="Ambil Sendiri" class="method-radio" onchange="toggleDeliveryMethod(this.value)">
                                        <div class="method-card-content">
                                            <i class="fa-solid fa-store"></i>
                                            <span class="title">Ambil di Toko</span>
                                            <span class="desc">Ambil pesanan secara mandiri ke gerai kami</span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Bidang Input Pengiriman (Diantar ke Alamat) -->
                                <div id="delivery-fields" class="delivery-details-box">
                                    <div class="contact-form-group">
                                        <label for="alamat_pengiriman">Alamat Lengkap Pengiriman <span class="text-danger">*</span></label>
                                        <textarea id="alamat_pengiriman" name="alamat_pengiriman" class="contact-form-control" rows="3" placeholder="Contoh: Jl. Diponegoro No. 12, Perumahan Fajar Raya Blok C/3" required></textarea>
                                    </div>
                                    
                                    <div class="contact-form-group">
                                        <label for="kecamatan_pengiriman">Kecamatan Pengiriman <span class="text-danger">*</span></label>
                                        <select id="kecamatan_pengiriman" name="kecamatan_pengiriman" class="contact-form-control" onchange="updateDeliveryFee(this.value)" required>
                                            <option value="">-- Pilih Kecamatan Tujuan --</option>
                                            <!-- Jarak <= 20 km -->
                                            <option value="tambun_utara">Tambun Utara (3 km)</option>
                                            <option value="tambun_selatan">Tambun Selatan (9 km)</option>
                                            <option value="bekasi_utara">Bekasi Utara (6 km)</option>
                                            <option value="bekasi_timur">Bekasi Timur (8 km)</option>
                                            <option value="bekasi_barat">Bekasi Barat (13 km)</option>
                                            <option value="bekasi_selatan">Bekasi Selatan (11 km)</option>
                                            <option value="babelan">Babelan (7 km)</option>
                                            <option value="cibitung">Cibitung (12 km)</option>
                                            <option value="cikarang_utara">Cikarang Utara (18 km)</option>
                                            <!-- Jarak > 20 km -->
                                            <option value="cikarang_pusat">Cikarang Pusat (26 km - Di luar layanan)</option>
                                            <option value="cibarusah">Cibarusah (35 km - Di luar layanan)</option>
                                        </select>
                                    </div>

                                    <!-- Informasi Hasil Ongkir & Jarak -->
                                    <div class="delivery-calc-results" id="delivery-results-box" style="display: none;">
                                        <div class="calc-row">
                                            <span>Jarak Estimasi:</span>
                                            <strong id="display-jarak">0 km</strong>
                                        </div>
                                        <div class="calc-row">
                                            <span>Biaya Ongkos Kirim:</span>
                                            <strong id="display-ongkir" class="text-primary">Rp 0</strong>
                                        </div>
                                    </div>

                                    <!-- Alert Peringatan Jika > 20 km -->
                                    <div class="notice-warning" id="warning-distance-box" style="display: none;">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <div class="warning-text">
                                            <strong>Pengiriman di luar area layanan.</strong>
                                            <p>Jarak pengiriman melebihi batas maksimal 20 km dari toko kami di Tambun Utara. Silakan pilih opsi alternatif:</p>
                                            <div class="warning-actions">
                                                <button type="button" class="btn btn-outline btn-sm" onclick="selectPickupOption()">Ambil di Toko</button>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="focusKecamatan()">Ubah Kecamatan</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bidang Info Toko (Ambil di Toko) -->
                                <div id="pickup-fields" class="pickup-details-box" style="display: none;">
                                    <div class="pickup-info-card">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                        <div class="pickup-text">
                                            <strong>Lokasi Pengambilan Toko:</strong>
                                            <p class="store-name">Olin's Cake</p>
                                            <p class="store-address">Jl. Kemandoran No. 89, RT 006/RW 022, Pekayon Jaya, Kecamatan Tambun Utara, Kabupaten Bekasi</p>
                                            <small class="store-note">* Anda dapat mengambil pesanan secara mandiri sesuai dengan tanggal dan waktu yang ditentukan tanpa biaya ongkos kirim.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Jadwal Pengiriman & Catatan -->
                        <div class="checkout-card">
                            <div class="checkout-card-header">
                                <i class="fa-solid fa-calendar-days"></i>
                                <h2>Jadwal & Catatan Tambahan</h2>
                            </div>
                            <div class="checkout-card-body">
                                <div class="form-grid-2">
                                    <div class="contact-form-group">
                                        <label for="tanggal_pengiriman">Tanggal Pengiriman/Pengambilan <span class="text-danger">*</span></label>
                                        <!-- Batasi minimal H+3 di frontend -->
                                        <input type="date" id="tanggal_pengiriman" name="tanggal_pengiriman" class="contact-form-control" min="<?= date('Y-m-d', strtotime('+3 days')) ?>" required onchange="validateDeliveryDate(this.value)">
                                        <small class="text-muted"><i class="fa-solid fa-clock"></i> Minimal pre-order adalah H-3 sebelum tanggal pengiriman.</small>
                                    </div>
                                    <div class="contact-form-group">
                                        <label for="waktu_pengiriman">Waktu Pengiriman/Pengambilan <span class="text-danger">*</span></label>
                                        <select id="waktu_pengiriman" name="waktu_pengiriman" class="contact-form-control" required>
                                            <option value="">-- Pilih Jam Pengiriman --</option>
                                            <option value="08.00 - 12.00">Pagi (08.00 - 12.00)</option>
                                            <option value="12.00 - 16.00">Siang (12.00 - 16.00)</option>
                                            <option value="16.00 - 20.00">Sore/Malam (16.00 - 20.00)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="contact-form-group" style="margin-top: 16px;">
                                    <label for="catatan">Catatan Tambahan untuk Pembuat Kue <span class="text-muted">(Opsional)</span></label>
                                    <textarea id="catatan" name="catatan" class="contact-form-control" rows="3" placeholder="Tuliskan catatan khusus Anda (Contoh: Tulisan di kue: 'Selamat Ulang Tahun Ibu', kurangi rasa manis, dll)"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Kolom Kanan: Ringkasan Belanja -->
                    <div class="checkout-right-col">
                        <div class="checkout-card summary-card">
                            <div class="summary-header">
                                <h2>Ringkasan Pesanan</h2>
                            </div>
                            
                            <!-- Daftar Item Kue -->
                            <div class="summary-checkout-items">
                                <?php foreach ($checkout_items as $item): ?>
                                    <div class="summary-item-row">
                                        <div class="item-pic">
                                            <img src="assets/images/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                                        </div>
                                        <div class="item-name-qty">
                                            <span class="name"><?= htmlspecialchars($item['nama_produk']) ?></span>
                                            <span class="spec">Ukuran: <?= htmlspecialchars($item['ukuran']) ?></span>
                                            <span class="qty">Jumlah: <strong><?= $item['jumlah'] ?>x</strong></span>
                                        </div>
                                        <div class="item-sub">
                                            Rp <?= number_format($item['harga'] * $item['jumlah'], 0, ',', '.') ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Perincian Harga & Ongkir -->
                            <div class="summary-price-breakdown">
                                <div class="price-row">
                                    <span>Subtotal Produk</span>
                                    <span>Rp <?= number_format($subtotal_belanja, 0, ',', '.') ?></span>
                                </div>
                                <div class="price-row">
                                    <span>Ongkos Kirim</span>
                                    <span id="summary-ongkir">Rp 0</span>
                                </div>
                                <div class="price-row total-row">
                                    <span>Total Pembayaran</span>
                                    <span id="summary-total" class="total-price-text">Rp <?= number_format($subtotal_belanja, 0, ',', '.') ?></span>
                                </div>
                            </div>

                            <!-- Peringatan Batas H-3 Pesan -->
                            <div class="checkout-warning-box-inline">
                                <i class="fa-solid fa-calendar-check"></i>
                                <span>Kue dipanggang segar (Freshly Baked). Pemesanan dilakukan min. H-3 dari jadwal pengiriman.</span>
                            </div>

                            <!-- Tombol Final Checkout -->
                            <button type="submit" id="btn-submit-checkout" class="btn btn-primary btn-checkout">
                                Lanjut ke Pembayaran <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-grid">
            <div class="footer-col">
                <div class="footer-logo">
                    <i class="fa-solid fa-cake-candles"></i> Olin's <span>Cake</span>
                </div>
                <p>
                    Premium Home Bakery menyajikan kebahagiaan manis di setiap potongan kue. Dibuat fresh setiap hari dengan bahan kualitas premium dari dapur kami ke pintu rumah Anda.
                </p>
                <div class="social-links">
                    <a href="#" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-btn" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="social-btn" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>
            
            <div class="footer-col">
                <h4>Tautan Cepat</h4>
                <ul class="footer-links">
                    <li><a href="index.php#home">Beranda</a></li>
                    <li><a href="index.php#tentang">Tentang Kami</a></li>
                    <li><a href="index.php#produk">Produk Favorit</a></li>
                    <li><a href="index.php#cara-pesan">Cara Pesan</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Bantuan</h4>
                <ul class="footer-links">
                    <li><a href="index.php#hubungi">Kontak Kami</a></li>
                    <li><a href="masuk.php">Masuk Akun</a></li>
                    <li><a href="daftar.php">Daftar Baru</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <p>
                    <i class="fa-solid fa-envelope" style="margin-right: 8px; color: var(--olive-harvest);"></i> info@olinscake.com<br>
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 895-2923-6657<br>
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Tambun Utara, Kabupaten Bekasi
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- JavaScript Handling -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Dictionary Jarak dan Status Pengiriman per Kecamatan
        const kecamatanData = {
            'tambun_utara': { jarak: 3, aktif: true },
            'tambun_selatan': { jarak: 9, aktif: true },
            'bekasi_utara': { jarak: 6, aktif: true },
            'bekasi_timur': { jarak: 8, aktif: true },
            'bekasi_barat': { jarak: 13, aktif: true },
            'bekasi_selatan': { jarak: 11, aktif: true },
            'babelan': { jarak: 7, aktif: true },
            'cibitung': { jarak: 12, aktif: true },
            'cikarang_utara': { jarak: 18, aktif: true },
            'cikarang_pusat': { jarak: 26, aktif: false },
            'cibarusah': { jarak: 35, aktif: false }
        };

        const subtotalBelanja = <?= $subtotal_belanja ?>;
        let selectedMetode = 'Kirim ke Alamat';
        let currentJarak = 0;
        let currentOngkir = 0;

        // Toggle Tampilan Form Berdasarkan Metode
        function toggleDeliveryMethod(metode) {
            selectedMetode = metode;
            const deliveryBox = document.getElementById('delivery-fields');
            const pickupBox = document.getElementById('pickup-fields');
            
            const alamatInput = document.getElementById('alamat_pengiriman');
            const kecSelect = document.getElementById('kecamatan_pengiriman');

            if (metode === 'Kirim ke Alamat') {
                deliveryBox.style.display = 'block';
                pickupBox.style.display = 'none';
                
                // Aktifkan validasi required
                alamatInput.setAttribute('required', 'required');
                kecSelect.setAttribute('required', 'required');
                
                // Hitung ulang berdasarkan input kecamatan saat ini
                updateDeliveryFee(kecSelect.value);
            } else {
                deliveryBox.style.display = 'none';
                pickupBox.style.display = 'block';
                
                // Nonaktifkan validasi required
                alamatInput.removeAttribute('required');
                kecSelect.removeAttribute('required');

                // Set Ongkir & Jarak ke 0
                currentJarak = 0;
                currentOngkir = 0;
                
                updateFormState(true, ''); // Selalu aktif untuk Ambil di Toko
            }
        }

        // Update Ongkir Berdasarkan Pilihan Kecamatan
        function updateDeliveryFee(kecamatanKey) {
            if (selectedMetode !== 'Kirim ke Alamat') return;

            const resultsBox = document.getElementById('delivery-results-box');
            const warningBox = document.getElementById('warning-distance-box');
            const dispJarak = document.getElementById('display-jarak');
            const dispOngkir = document.getElementById('display-ongkir');

            if (!kecamatanKey) {
                resultsBox.style.display = 'none';
                warningBox.style.display = 'none';
                currentJarak = 0;
                currentOngkir = 0;
                updateFormState(false, 'Pilih kecamatan pengiriman terlebih dahulu.');
                return;
            }

            const data = kecamatanData[kecamatanKey];
            currentJarak = data.jarak;

            if (data.aktif) {
                // Kurang dari atau sama dengan 20 km -> Valid
                currentOngkir = currentJarak * 3000; // Tarif Rp 3.000 per km
                
                // Tampilkan info ongkir
                resultsBox.style.display = 'block';
                warningBox.style.display = 'none';
                dispJarak.innerText = currentJarak + " km";
                dispOngkir.innerText = "Rp " + currentOngkir.toLocaleString('id-ID');
                
                updateFormState(true, '');
            } else {
                // Lebih dari 20 km -> Pengiriman di luar layanan
                currentOngkir = 0;
                resultsBox.style.display = 'none';
                warningBox.style.display = 'flex';
                
                updateFormState(false, 'Pengiriman di luar area layanan. Silakan pilih ambil di toko.');
            }
        }

        // Kelola status aktif tombol submit dan input tersembunyi
        function updateFormState(isValid, errorMsg) {
            const submitBtn = document.getElementById('btn-submit-checkout');
            const inputJarak = document.getElementById('input-jarak');
            const inputOngkir = document.getElementById('input-ongkir');
            const inputTotal = document.getElementById('input-total');
            
            const summaryOngkir = document.getElementById('summary-ongkir');
            const summaryTotal = document.getElementById('summary-total');

            // Set values ke form hidden inputs
            inputJarak.value = currentJarak;
            inputOngkir.value = currentOngkir;
            
            const totalBayar = subtotalBelanja + currentOngkir;
            inputTotal.value = totalBayar;

            // Tampilkan ke ringkasan harga
            summaryOngkir.innerText = currentOngkir > 0 ? "Rp " + currentOngkir.toLocaleString('id-ID') : "Rp 0";
            summaryTotal.innerText = "Rp " + totalBayar.toLocaleString('id-ID');

            // Aktifkan / Nonaktifkan tombol submit
            if (isValid) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        // Pilihan Aksi Cepat pada Warning Box
        function selectPickupOption() {
            const radioAmbil = document.querySelector('input[name="metode_pengiriman"][value="Ambil Sendiri"]');
            if (radioAmbil) {
                radioAmbil.checked = true;
                toggleDeliveryMethod('Ambil Sendiri');
            }
        }

        function focusKecamatan() {
            const kecSelect = document.getElementById('kecamatan_pengiriman');
            if (kecSelect) {
                kecSelect.focus();
            }
        }

        // Validasi Tanggal Pre-order Minimal H-3
        function validateDeliveryDate(selectedDateStr) {
            if (!selectedDateStr) return;

            const selectedDate = new Date(selectedDateStr + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Hitung H+3 hari dari hari ini
            const minDate = new Date(today);
            minDate.setDate(today.getDate() + 3);

            if (selectedDate < minDate) {
                alert("Minimal pemesanan adalah H-3 sebelum tanggal pengiriman. Silakan pilih tanggal lain.");
                document.getElementById('tanggal_pengiriman').value = '';
            }
        }

        // Inisialisasi awal
        window.addEventListener('DOMContentLoaded', () => {
            toggleDeliveryMethod('Kirim ke Alamat');
        });
    </script>
</body>
</html>
