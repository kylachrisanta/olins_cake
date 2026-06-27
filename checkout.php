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
    <style>
        /* ── Google Places Autocomplete Dropdown Fix ── */
        /* Pastikan dropdown saran alamat muncul di atas semua elemen */
        .pac-container {
            z-index: 99999 !important;
            border-radius: 8px !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important;
            border: 1px solid rgba(0,0,0,0.12) !important;
            font-family: inherit !important;
            margin-top: 4px !important;
        }
        .pac-item {
            padding: 8px 12px !important;
            cursor: pointer !important;
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
        }
        .pac-item:hover {
            background-color: #fdf6ee !important;
        }
        .pac-item-selected,
        .pac-item-selected:hover {
            background-color: #f5e6d0 !important;
        }
        .pac-matched {
            font-weight: 700 !important;
            color: var(--spiced-wine, #8b4513) !important;
        }
        /* Perbaikan radius sudut card karena overflow:visible */
        .checkout-card-header:first-child {
            border-radius: var(--radius-lg, 12px) var(--radius-lg, 12px) 0 0;
        }
        .checkout-card-body:last-child {
            border-radius: 0 0 var(--radius-lg, 12px) var(--radius-lg, 12px);
        }
        /* Override body overflow agar .pac-container tidak terpotong */
        body {
            overflow-x: clip !important;
        }
    </style>
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled">
        <div class="container navbar">
            <a href="index.php" class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 8px; margin-top: -3px;">
                    <circle cx="9" cy="7" r="2"/>
                    <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
                    <path d="M5.1 12.8 19 12"/>
                    <path d="M8.9 15.6 19 15"/>
                </svg> Olin's <span>Cake</span>
            </a>
            
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <ul class="nav-menu" id="nav-menu">
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
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
                <input type="hidden" id="input-lat" name="garis_lintang" value="">
                <input type="hidden" id="input-lng" name="garis_bujur" value="">

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
                                    <!-- Google Maps Container -->
                                    <div class="contact-form-group" style="margin-top: 15px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Pilih Lokasi Pengantaran di Peta <span class="text-danger">*</span></label>

                                        <!-- Geolocation Status Banner -->
                                        <div id="geo-status-banner" style="display: none; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; font-size: 0.875rem; font-weight: 500; border: 1px solid;">
                                            <i id="geo-status-icon" class="fa-solid fa-location-crosshairs"></i>
                                            <span id="geo-status-text"></span>
                                        </div>

                                        <div style="position: relative; margin-bottom: 8px;">
                                            <input type="text" id="map-search-input" class="contact-form-control" placeholder="Cari alamat atau lokasi di peta..." style="padding-right: 40px;">
                                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                                        </div>
                                        <div id="map" style="width: 100%; height: 350px; border-radius: 8px; border: 1px solid #ccc; overflow: hidden; margin-bottom: 8px;"></div>
                                        <small class="text-muted" style="display: block; line-height: 1.4;">
                                            <i class="fa-solid fa-map-pin" style="color: #ff4d4d; margin-right: 4px;"></i>
                                            Geser pin merah ke lokasi pengantaran atau klik pada peta untuk memindahkan pin.
                                        </small>

                                        <!-- Alamat Terdeteksi UI (Sekarang berupa Textarea yang bisa diedit) -->
                                        <div id="detected-address-box" style="margin-top: 15px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--cowhide-cocoa);">Detail Alamat Pengiriman & Patokan <span class="text-danger">*</span></label>
                                            <input type="text" id="input-alamat" name="alamat_pengiriman" class="contact-form-control" placeholder="Ketik nama jalan, kelurahan, kecamatan, atau kota..." required autocomplete="off" style="padding: 12px 14px;">
                                            <small class="text-muted" style="display: block; margin-top: 6px; line-height: 1.4;">
                                                * Alamat terisi otomatis dari peta. Anda juga dapat <strong>mengetik</strong> untuk mencari dan memilih saran alamat, atau <strong>geser pin</strong> di peta untuk menyesuaikan lokasi secara akurat.
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Informasi Hasil Ongkir & Jarak -->
                                    <div class="delivery-calc-results" id="delivery-results-box" style="display: none;">
                                        <div class="calc-row">
                                            <span>Jarak Pengiriman:</span>
                                            <strong id="display-jarak">0 km</strong>
                                        </div>
                                        <div class="calc-row">
                                            <span>Ongkos Kirim:</span>
                                            <strong id="display-ongkir" class="text-primary">Rp 0</strong>
                                        </div>
                                    </div>

                                    <!-- Alert Peringatan Jika > 20 km -->
                                    <div class="notice-warning" id="warning-distance-box" style="display: none;">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <div class="warning-text">
                                            <strong>Pengiriman di luar area layanan.</strong>
                                            <p>Jarak pengiriman melebihi batas maksimal 20 km dari toko kami di Karang Satria, Tambun Utara. Silakan pilih opsi alternatif:</p>
                                            <div class="warning-actions">
                                                <button type="button" class="btn btn-outline btn-sm" onclick="selectPickupOption()">Ambil di Toko</button>
                                                <button type="button" class="btn btn-primary btn-sm" onclick="focusSearchInput()">Ubah Alamat Pengiriman</button>
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
                                            <p class="store-address">Kp. Karang Jaya Blok D No.1, RT 002/RW 026, Karang Satria, Kec. Tambun Utara, Kabupaten Bekasi 17568</p>
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
                                Pembayaran <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 8px; margin-top: -3px;">
                        <circle cx="9" cy="7" r="2"/>
                        <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
                        <path d="M5.1 12.8 19 12"/>
                        <path d="M8.9 15.6 19 15"/>
                    </svg> Olin's <span>Cake</span>
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
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="index.php#tentang">Tentang Kami</a></li>
                    <li><a href="index.php#cara-pesan">Cara Pesan</a></li>
                    <li><a href="produk.php">Produk</a></li>
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
    <!-- Google Maps API and Libraries -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBnSaMaGbbQGbP_JB78HYxlxi9P1pPXwbc&libraries=places,geometry&callback=initMap" async defer></script>

    <!-- JavaScript Handling -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        const subtotalBelanja = <?= $subtotal_belanja ?>;
        let selectedMetode = 'Kirim ke Alamat';
        let currentJarak = 0;
        let currentOngkir = 0;

        // Google Maps Variables
        let map;
        let storeMarker;
        let customerMarker;
        let distanceMatrixService;
        let autocompleteSearch;
        let autocompleteAddress;
        let geocoder;

        const storeLatLng = { lat: -6.2215453, lng: 107.0463893 };

        // Initialize Map
        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: storeLatLng,
                zoom: 13,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            });

            // Store Marker
            storeMarker = new google.maps.Marker({
                position: storeLatLng,
                map: map,
                title: "Olin's Cake (Toko)",
                icon: {
                    url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                }
            });

            const storeInfoWindow = new google.maps.InfoWindow({
                content: "<strong>Olin's Cake (Toko)</strong><br>Kp. Karang Jaya Blok D No.1, Karang Satria, Tambun Utara, Bekasi"
            });
            storeMarker.addListener('click', () => {
                storeInfoWindow.open(map, storeMarker);
            });

            // Draggable Customer Marker (initially not placed)
            customerMarker = new google.maps.Marker({
                position: null,
                map: null,
                draggable: true,
                title: "Lokasi Pengiriman Anda",
                icon: {
                    url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
                }
            });

            // Distance Matrix & Geocoder Service
            distanceMatrixService = new google.maps.DistanceMatrixService();
            geocoder = new google.maps.Geocoder();

            // ── Places Autocomplete: Kolom Pencarian Peta ──────────────────────
            autocompleteSearch = new google.maps.places.Autocomplete(
                document.getElementById('map-search-input'), {
                    fields: ['geometry', 'formatted_address', 'name'],
                    componentRestrictions: { country: 'id' }
                }
            );
            autocompleteSearch.bindTo('bounds', map);
            autocompleteSearch.addListener('place_changed', () => {
                const place = autocompleteSearch.getPlace();
                if (!place.geometry || !place.geometry.location) {
                    console.warn('Tempat tidak memiliki koordinat.');
                    return;
                }
                handlePlaceSelected(place);
            });

            // ── Places Autocomplete: Kolom Alamat Pengiriman ────────────────────
            autocompleteAddress = new google.maps.places.Autocomplete(
                document.getElementById('input-alamat'), {
                    fields: ['geometry', 'formatted_address', 'name'],
                    componentRestrictions: { country: 'id' }
                }
            );
            autocompleteAddress.bindTo('bounds', map);
            autocompleteAddress.addListener('place_changed', () => {
                const place = autocompleteAddress.getPlace();
                if (!place.geometry || !place.geometry.location) {
                    // Tidak ada place terpilih dari saran — update state saja
                    updateFormState(currentJarak <= 20, currentJarak > 20 ? 'Pengiriman di luar area layanan.' : '');
                    return;
                }
                handlePlaceSelected(place);
            });

            // Click Map to position pin
            map.addListener('click', (event) => {
                if (selectedMetode !== 'Kirim ke Alamat') return;
                customerMarker.setPosition(event.latLng);
                customerMarker.setMap(map);
                calculateDistance(event.latLng);
                reverseGeocode(event.latLng);
            });

            // Drag pin end
            customerMarker.addListener('dragend', () => {
                if (selectedMetode !== 'Kirim ke Alamat') return;
                const pos = customerMarker.getPosition();
                calculateDistance(pos);
                reverseGeocode(pos);
            });
            
            // Initial call if delivery method requires it
            toggleDeliveryMethod(selectedMetode);
        }

        // ── Handler Terpadu: Saat Tempat Dipilih dari Autocomplete ────────────
        // Dipanggil oleh autocompleteSearch DAN autocompleteAddress
        function handlePlaceSelected(place) {
            const location = place.geometry.location;

            // 1. Pindahkan peta ke lokasi terpilih
            if (place.geometry.viewport) {
                map.fitBounds(place.geometry.viewport);
            } else {
                map.setCenter(location);
                map.setZoom(16);
            }

            // 2. Pindahkan marker ke lokasi terpilih
            customerMarker.setPosition(location);
            customerMarker.setMap(map);

            // 3. Isi alamat di kedua field sekaligus
            const address = place.formatted_address || place.name || '';
            document.getElementById('input-alamat').value = address;
            document.getElementById('map-search-input').value = '';  // kosongkan search bar setelah dipilih
            document.getElementById('detected-address-box').style.display = 'block';

            // 4. Simpan koordinat ke hidden inputs
            document.getElementById('input-lat').value = location.lat();
            document.getElementById('input-lng').value = location.lng();

            // 5. Hitung ulang jarak & ongkir
            calculateDistance(location);
        }

        // Show geolocation status banner
        function showGeoBanner(type, message) {
            const banner = document.getElementById('geo-status-banner');
            const icon = document.getElementById('geo-status-icon');
            const text = document.getElementById('geo-status-text');

            banner.style.display = 'flex';
            text.textContent = message;

            if (type === 'loading') {
                banner.style.backgroundColor = '#eff6ff';
                banner.style.borderColor = '#93c5fd';
                banner.style.color = '#1d4ed8';
                icon.className = 'fa-solid fa-spinner fa-spin';
            } else if (type === 'success') {
                banner.style.backgroundColor = '#f0fdf4';
                banner.style.borderColor = '#86efac';
                banner.style.color = '#15803d';
                icon.className = 'fa-solid fa-circle-check';
            } else if (type === 'warning') {
                banner.style.backgroundColor = '#fffbeb';
                banner.style.borderColor = '#fcd34d';
                banner.style.color = '#92400e';
                icon.className = 'fa-solid fa-triangle-exclamation';
            } else if (type === 'error') {
                banner.style.backgroundColor = '#fef2f2';
                banner.style.borderColor = '#fca5a5';
                banner.style.color = '#991b1b';
                icon.className = 'fa-solid fa-circle-xmark';
            }
        }

        function hideGeoBanner() {
            document.getElementById('geo-status-banner').style.display = 'none';
        }

        // Auto-detect user location via browser Geolocation API
        function detectUserLocation() {
            if (!navigator.geolocation) {
                showGeoBanner('warning', 'Browser Anda tidak mendukung deteksi lokasi otomatis. Silakan pilih lokasi pada peta secara manual.');
                return;
            }

            showGeoBanner('loading', 'Mendeteksi lokasi Anda secara otomatis...');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Success: got user location
                    const userLatLng = new google.maps.LatLng(
                        position.coords.latitude,
                        position.coords.longitude
                    );

                    // Center map on user location
                    map.setCenter(userLatLng);
                    map.setZoom(16);

                    // Place/move customer marker to user location
                    customerMarker.setPosition(userLatLng);
                    customerMarker.setMap(map);

                    // Save coordinates to hidden inputs
                    document.getElementById('input-lat').value = position.coords.latitude;
                    document.getElementById('input-lng').value = position.coords.longitude;

                    showGeoBanner('success', 'Lokasi Anda berhasil terdeteksi! Pin telah ditempatkan. Geser pin jika perlu penyesuaian.');

                    // Reverse geocode to fill address
                    reverseGeocode(userLatLng);

                    // Calculate distance
                    calculateDistance(userLatLng);

                    // Auto-hide banner after 5 seconds
                    setTimeout(hideGeoBanner, 5000);
                },
                function(error) {
                    // Error / permission denied
                    let msg = 'Izin lokasi ditolak.';
                    if (error.code === error.PERMISSION_DENIED) {
                        msg = 'Izin akses lokasi ditolak. Silakan pilih lokasi pada peta secara manual atau aktifkan izin lokasi di browser Anda.';
                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                        msg = 'Informasi lokasi tidak tersedia. Silakan pilih lokasi secara manual pada peta.';
                    } else if (error.code === error.TIMEOUT) {
                        msg = 'Waktu deteksi lokasi habis. Silakan pilih lokasi secara manual pada peta.';
                    }
                    showGeoBanner('error', msg);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        // Reverse Geocode coordinates to address string
        function reverseGeocode(latLng) {
            if (!latLng) return;
            geocoder.geocode({ location: latLng }, (results, status) => {
                if (status === 'OK') {
                    if (results[0]) {
                        const address = results[0].formatted_address;
                        document.getElementById('input-alamat').value = address;
                        document.getElementById('detected-address-box').style.display = 'block';
                        
                        // Check state
                        updateFormState(currentJarak <= 20, currentJarak > 20 ? 'Pengiriman di luar area layanan.' : '');
                    }
                } else {
                    console.error("Geocoding failed due to: " + status);
                }
            });
        }

        // Calculate distance from store to location
        function calculateDistance(latLng) {
            if (!latLng) return;

            // Save coords to form inputs
            document.getElementById('input-lat').value = latLng.lat();
            document.getElementById('input-lng').value = latLng.lng();

            distanceMatrixService.getDistanceMatrix({
                origins: [storeLatLng],
                destinations: [latLng],
                travelMode: 'DRIVING'
            }, (response, status) => {
                let distanceInKm = 0;
                let success = false;

                if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                    const distanceValue = response.rows[0].elements[0].distance.value; // meters
                    distanceInKm = distanceValue / 1000;
                    success = true;
                } else {
                    // Fallback to straight-line distance if Directions service failed
                    if (google.maps.geometry && google.maps.geometry.spherical) {
                        const distanceInMeters = google.maps.geometry.spherical.computeDistanceBetween(
                            new google.maps.LatLng(storeLatLng.lat, storeLatLng.lng),
                            latLng
                        );
                        distanceInKm = distanceInMeters / 1000;
                        success = true;
                    }
                }

                if (success) {
                    processDistanceAndOngkir(distanceInKm);
                } else {
                    console.error("Gagal menghitung jarak.");
                }
            });
        }

        // Process distance and update shipping cost
        function processDistanceAndOngkir(distanceInKm) {
            currentJarak = distanceInKm;

            const resultsBox = document.getElementById('delivery-results-box');
            const warningBox = document.getElementById('warning-distance-box');
            const dispJarak = document.getElementById('display-jarak');
            const dispOngkir = document.getElementById('display-ongkir');

            // Format distance with comma separator for display (1 decimal place)
            const formattedDistance = distanceInKm.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 1 }) + " km";

            if (distanceInKm <= 20) {
                // Calculate ongkir using raw/exact distance * 3000 (rounded to nearest integer for DB/currency)
                currentOngkir = Math.round(distanceInKm * 3000);
                
                resultsBox.style.display = 'block';
                warningBox.style.display = 'none';
                dispJarak.innerText = formattedDistance;
                dispOngkir.innerText = "Rp " + currentOngkir.toLocaleString('id-ID');

                updateFormState(true, '');
            } else {
                // Out of service area
                currentOngkir = 0;
                resultsBox.style.display = 'none';
                warningBox.style.display = 'flex';

                updateFormState(false, 'Pengiriman di luar area layanan.');
            }
        }

        // Toggle Delivery Method Form Display
        function toggleDeliveryMethod(metode) {
            selectedMetode = metode;
            const deliveryBox = document.getElementById('delivery-fields');
            const pickupBox = document.getElementById('pickup-fields');
            const summaryOngkirRow = document.getElementById('summary-ongkir').closest('.price-row');

            if (metode === 'Kirim ke Alamat') {
                deliveryBox.style.display = 'block';
                pickupBox.style.display = 'none';
                if (summaryOngkirRow) summaryOngkirRow.style.display = 'flex';
                
                // Trigger maps search layout update (resize event helps maps load properly)
                if (map) {
                    google.maps.event.trigger(map, 'resize');
                    
                    // If marker is already set (user switched back), restore it
                    const lat = document.getElementById('input-lat').value;
                    const lng = document.getElementById('input-lng').value;
                    if (lat && lng) {
                        const latLng = new google.maps.LatLng(parseFloat(lat), parseFloat(lng));
                        customerMarker.setPosition(latLng);
                        customerMarker.setMap(map);
                        map.setCenter(latLng);
                        map.setZoom(16);
                        calculateDistance(latLng);
                    } else {
                        // No location set yet — auto-detect user location
                        detectUserLocation();
                        updateFormState(false, 'Tentukan lokasi pada peta terlebih dahulu.');
                    }
                }
            } else {
                deliveryBox.style.display = 'none';
                pickupBox.style.display = 'block';
                if (summaryOngkirRow) summaryOngkirRow.style.display = 'none';

                currentJarak = 0;
                currentOngkir = 0;
                
                updateFormState(true, '');
            }
        }

        // Form Submit State Handler
        function updateFormState(isValid, errorMsg) {
            const submitBtn = document.getElementById('btn-submit-checkout');
            const inputJarak = document.getElementById('input-jarak');
            const inputOngkir = document.getElementById('input-ongkir');
            const inputTotal = document.getElementById('input-total');
            
            const summaryOngkir = document.getElementById('summary-ongkir');
            const summaryTotal = document.getElementById('summary-total');

            // Save raw decimal distance to hidden input
            inputJarak.value = currentJarak;
            inputOngkir.value = currentOngkir;
            
            const totalBayar = subtotalBelanja + currentOngkir;
            inputTotal.value = totalBayar;

            // Update checkout summary values
            summaryOngkir.innerText = currentOngkir > 0 ? "Rp " + currentOngkir.toLocaleString('id-ID') : "Rp 0";
            summaryTotal.innerText = "Rp " + totalBayar.toLocaleString('id-ID');

            let finalValid = isValid;
            if (selectedMetode === 'Kirim ke Alamat') {
                const addressVal = document.getElementById('input-alamat').value;
                const latVal = document.getElementById('input-lat').value;
                const lngVal = document.getElementById('input-lng').value;
                if (!addressVal || !latVal || !lngVal) {
                    finalValid = false;
                }
            }

            // Toggle Submit Button state
            if (finalValid) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        // Action choices in Warning Box
        function selectPickupOption() {
            const radioAmbil = document.querySelector('input[name="metode_pengiriman"][value="Ambil Sendiri"]');
            if (radioAmbil) {
                radioAmbil.checked = true;
                toggleDeliveryMethod('Ambil Sendiri');
            }
        }

        // Focus search input on map
        function focusSearchInput() {
            const searchInput = document.getElementById('map-search-input');
            if (searchInput) {
                searchInput.focus();
                searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Pre-order date validation (min H+3)
        function validateDeliveryDate(selectedDateStr) {
            if (!selectedDateStr) return;

            const selectedDate = new Date(selectedDateStr + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const minDate = new Date(today);
            minDate.setDate(today.getDate() + 3);

            if (selectedDate < minDate) {
                alert("Minimal pemesanan adalah H-3 sebelum tanggal pengiriman. Silakan pilih tanggal lain.");
                document.getElementById('tanggal_pengiriman').value = '';
            }
        }
    </script>
</body>
</html>
