<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Olin's Cake</title>
    <meta name="description" content="Kelezatan sejati yang lahir dari dapur rumahan dengan dedikasi cita rasa tinggi.">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
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
                <?php if (isset($_SESSION['pelanggan_id'])): ?>
                    <!-- Menu Navigasi Setelah Pelanggan Login -->
                    <li><a href="index.php" class="nav-link">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php" class="nav-link">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li class="nav-auth">
                        <a href="masuk.php" class="btn btn-outline btn-sm">Masuk</a>
                        <a href="daftar.php" class="btn btn-primary btn-sm">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Tentang Kami Section -->
    <section class="section section-bg" id="tentang" style="padding-top: 130px; min-height: 100vh;">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Tentang Kami</span>
                <h2>Olin's Cake</h2>
            </div>
            
            <div class="about-grid">
                <div class="about-text">
                    <p>
                        Olin's Cake didirikan atas dasar kecintaan kami dalam menghadirkan kue yang tidak hanya cantik dipandang, tetapi juga memanjakan lidah. Kami percaya bahwa setiap perayaan, kecil maupun besar, berhak mendapatkan sentuhan manis yang sempurna.
                    </p>
                    <p>
                        Setiap resep telah kami uji secara mendalam demi mendapatkan kombinasi tekstur yang super lembut dan rasa manis yang pas, tidak membuat enek (less sweet), sehingga sangat disukai oleh anak-anak hingga orang tua.
                    </p>
                </div>
                <div class="about-cards">
                    <div class="about-card">
                        <div class="about-card-icon">🍃</div>
                        <div class="about-card-content">
                            <h4>Bahan Berkualitas</h4>
                            <p>Menggunakan mentega premium, cokelat impor asli, serta bahan organik pilihan tanpa pengawet.</p>
                        </div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon">🎂</div>
                        <div class="about-card-content">
                            <h4>Fresh Made by Order</h4>
                            <p>Kue hanya akan dipanggang setelah pesanan terkonfirmasi untuk menjamin kesegaran yang maksimal.</p>
                        </div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-icon">🚚</div>
                        <div class="about-card-content">
                            <h4>Pengiriman Tepat Waktu</h4>
                            <p>Dikirim dengan kurir khusus kue untuk memastikan produk sampai di meja Anda dalam kondisi sempurna.</p>
                        </div>
                    </div>
                </div>
            </div>
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
                    <li><a href="tentang.php">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php">Cara Pesan</a></li>
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
                    <i class="fa-solid fa-envelope" style="margin-right: 8px; color: var(--olive-harvest);"></i> carolinaberliana11@gmail.com<br>
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 895-2923-6657<br>
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Kp. Karang Jaya Blok D No.1 RT 002/RW 026, Karang Satria, Tambun Utara, Bekasi
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- JavaScript Interactions -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');

        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    </script>
</body>
</html>
