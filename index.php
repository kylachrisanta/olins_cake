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
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">

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
                    <li><a href="index.php" class="nav-link active">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php" class="nav-link active">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
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
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container hero-grid">
            <div class="hero-content">
                <div class="hero-tagline">
                    <i class="fa-solid fa-award"></i> Premium Home Bakery
                </div>
                <h1>Freshly Baked <span>with Love</span>, Made Special for You</h1>
                <p class="hero-slogan">
                    Menghadirkan kelezatan kue rumahan premium, diracik khusus untuk melengkapi momen istimewa Anda. Seluruh produk dibuat berdasarkan sistem pre-order minimal H-3 sebelum tanggal pengambilan atau pengiriman untuk memastikan kualitas dan kesegaran kue tetap terjaga.
                </p>

            </div>
            <div class="hero-img-container">
                <div class="hero-img-backdrop"></div>
                <img src="assets/images/hero_cake.png" alt="Olin's Cake Premium Cake" class="hero-img">
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
                    <a href="https://www.instagram.com/olinscake?igsh=MTgzeWszeGFxc2Iwag==" class="social-btn" aria-label="Instagram" target="_blank"><i class="fa-brands fa-instagram"></i></a>
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
                    <li><a href="#hubungi">Kontak Kami</a></li>
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
