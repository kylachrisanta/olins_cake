<?php
// Start Session
session_start();

// Include Database Connection
require_once 'config/database.php';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olin's Cake - Bakery Premium Rumahan</title>
    <meta name="description" content="Olin's Cake menyajikan kue premium buatan rumahan dengan bahan berkualitas terbaik. Freshly baked with love.">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=1.1">

</head>
<body>

    <!-- Floating Header -->
    <header id="header">
        <div class="container navbar">
            <a href="#" class="logo">
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
                    <li class="dropdown-container">
                        <a href="index.php" class="dropdown-trigger" style="text-decoration: none;">
                            Beranda <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                        </a>
                        <ul class="dropdown-menu-list">
                            <li><a href="index.php#tentang" class="dropdown-menu-item">Tentang Kami</a></li>
                            <li><a href="index.php#cara-pesan" class="dropdown-menu-item">Cara Pesan</a></li>
                        </ul>
                    </li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php" class="nav-link active">Beranda</a></li>
                    <li><a href="index.php#tentang" class="nav-link">Tentang Kami</a></li>
                    <li><a href="index.php#cara-pesan" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>

                    <li class="nav-auth">
                        <a href="masuk.php" class="btn btn-outline btn-sm">Masuk</a>
                        <a href="daftar.php" class="btn btn-primary btn-sm">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container hero-grid">
            <div class="hero-content">
                <div class="hero-tagline">
                    <i class="fa-solid fa-award"></i> Premium Home Bakery
                </div>
                <h1>Freshly Baked <span>with Love</span>, Made Special for You</h1>
                <p class="hero-slogan">
                    Menghadirkan kelezatan kue rumahan premium bertekstur lembut dengan cita rasa elegan, diracik khusus untuk melengkapi momen istimewa Anda.
                </p>

            </div>
            <div class="hero-img-container">
                <div class="hero-img-backdrop"></div>
                <img src="assets/images/hero_cake.png" alt="Olin's Cake Premium Cake" class="hero-img">
            </div>
        </div>
    </section>

    <!-- Tentang Kami Section -->
    <section class="section section-bg" id="tentang">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Tentang Kami</span>
                <h2>Olin's Cake</h2>
                <p>Kelezatan sejati yang lahir dari dapur rumahan dengan dedikasi cita rasa tinggi.</p>
            </div>
            
            <div class="about-grid">
                <div class="about-text">
                    <h3>Dibuat dengan Bahan Terbaik & Kasih Sayang</h3>
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



    <!-- Cara Pesan Section -->
    <section class="section" id="cara-pesan">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Langkah Pemesanan</span>
                <h2>Cara Mudah Memesan</h2>
                <p>Panduan praktis memesan kue pre-order Olin's Cake dari awal hingga sampai ke rumah Anda.</p>
            </div>

            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <h4 class="step-title">Pilih Produk</h4>
                    <p class="step-desc">Pilih varian rasa dan ukuran kue favorit Anda.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">2</div>
                    <h4 class="step-title">Ke Keranjang</h4>
                    <p class="step-desc">Masukkan detail pesanan ke keranjang belanja.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">3</div>
                    <h4 class="step-title">Checkout</h4>
                    <p class="step-desc">Tentukan tanggal kirim dan lengkapi alamat detail.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">4</div>
                    <h4 class="step-title">Bayar</h4>
                    <p class="step-desc">Lakukan pembayaran aman via transfer bank.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">5</div>
                    <h4 class="step-title">Diproses</h4>
                    <p class="step-desc">Kue dipanggang segar sesuai jadwal pengiriman.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">6</div>
                    <h4 class="step-title">Dikirim</h4>
                    <p class="step-desc">Kue cantik tiba dengan selamat dan siap dinikmati.</p>
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
                    <li><a href="index.php#tentang">Tentang Kami</a></li>
                    <li><a href="index.php#cara-pesan">Cara Pesan</a></li>
                    <li><a href="produk.php">Produk</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Bantuan</h4>
                <ul class="footer-links">
                    <li><a href="#hubungi">Kontak Kami</a></li>
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
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Kebayoran Baru, Jakarta Selatan
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- JavaScript Interactions -->
    <script>
        // Header Background on Scroll
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');

        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking nav link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });

        // Profile Dropdown Toggle
        const profileTrigger = document.getElementById('profile-trigger');
        const profileDropdown = document.getElementById('profile-dropdown');
        if (profileTrigger) {
            profileTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
            document.addEventListener('click', function() {
                profileDropdown.classList.remove('show');
            });
        }

        // Send WhatsApp Message from Quick Contact
        function sendWhatsAppMsg(event) {
            event.preventDefault();
            const nama = document.getElementById('msg-nama').value;
            const kue = document.getElementById('msg-kue').value;
            const catatan = document.getElementById('msg-catatan').value;

            const baseText = `Halo Olin's Cake,\n\nSaya ingin menanyakan detail pemesanan:\nNama: ${nama}\nMinat Kue: ${kue}\nCatatan Khusus: ${catatan}\n\nTerima kasih!`;
            const encodedText = encodeURIComponent(baseText);
            const waUrl = `https://wa.me/6289529236657?text=${encodedText}`;

            window.open(waUrl, '_blank');
        }




    </script>
</body>
</html>
