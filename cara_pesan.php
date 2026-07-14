<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cara Pesan - Olin's Cake</title>
    <meta name="description" content="Panduan praktis memesan kue pre-order Olin's Cake dari awal hingga sampai ke rumah Anda.">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
    <style>
        /* Custom Timeline Styles for Cara Pesan Halaman */
        .cara-pesan-section {
            width: 100%;
            padding: 130px 24px 80px 24px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: var(--warm-bg);
            overflow: hidden;
        }

        .timeline-header {
            text-align: center;
            margin-bottom: 64px;
            max-width: 600px;
            position: relative;
            z-index: 10;
        }

        .timeline-header h2 {
            font-size: 2.5rem;
            color: var(--cowhide-cocoa);
            margin-bottom: 16px;
        }

        .timeline-header h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background-color: var(--olive-harvest);
            margin: 16px auto 0 auto;
            border-radius: 2px;
        }

        .timeline-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .timeline-container {
            position: relative;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 10;
        }

        .timeline-svg {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .timeline-line-mobile {
            position: absolute;
            top: 0;
            bottom: 180px; /* stops before the bottom of the last card */
            left: 32px;
            width: 4px;
            background-color: rgba(116, 48, 20, 0.1);
            z-index: -1;
            border-radius: 4px;
        }

        .timeline-row {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-bottom: 96px;
        }

        /* Initial hidden state of cards for entry animation */
        .timeline-card {
            width: 85%;
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 32px;
            border: 1px solid rgba(68, 45, 28, 0.08);
            box-shadow: var(--shadow-md);
            position: relative;
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
        }

        /* Visible state triggered by Intersection Observer */
        .timeline-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .timeline-card.visible:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--spiced-wine);
        }

        .step-badge {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -32px;
            left: -32px;
            border: 4px solid var(--white);
            font-size: 1.5rem;
            font-weight: 800;
        }

        /* Color schemes for badges */
        .badge-primary {
            background-color: rgba(116, 48, 20, 0.08);
            color: var(--spiced-wine);
        }
        .badge-secondary {
            background-color: rgba(68, 45, 28, 0.08);
            color: var(--cowhide-cocoa);
        }
        .badge-tertiary {
            background-color: rgba(157, 145, 103, 0.15);
            color: var(--olive-harvest);
        }

        .step-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .step-card-header h3 {
            font-size: 1.5rem;
            color: var(--cowhide-cocoa);
            margin: 0;
        }

        .step-card-header .icon {
            font-size: 2rem;
            color: var(--spiced-wine);
        }

        .step-desc {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.6;
        }

        .deco-icon {
            position: absolute;
            opacity: 0.1;
            z-index: -1;
            font-size: 5rem;
            color: var(--cowhide-cocoa);
            pointer-events: none;
            transition: opacity 0.5s ease;
        }

        /* SVG timeline line draw effect */
        .path-line-active {
            stroke-dasharray: 120 1000;
            stroke-dashoffset: 1120;
            animation: drawPath 6s linear infinite;
        }

        @keyframes drawPath {
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Timeline alignment for desktop */
        @media (min-width: 768px) {
            .timeline-card {
                width: 384px;
            }
            .timeline-line-mobile {
                display: none;
            }
            .timeline-row {
                justify-content: center;
            }
            
            /* Initial translate offsets for winding entry animations */
            .row-1 .timeline-card {
                transform: translateX(230px) translateY(30px);
            }
            .row-1 .timeline-card.visible {
                transform: translateX(180px) translateY(0);
            }
            .row-1 .timeline-card.visible:hover {
                transform: translateX(180px) translateY(-8px);
            }

            .row-2 .timeline-card {
                transform: translateX(-230px) translateY(30px);
            }
            .row-2 .timeline-card.visible {
                transform: translateX(-180px) translateY(0);
            }
            .row-2 .timeline-card.visible:hover {
                transform: translateX(-180px) translateY(-8px);
            }
            .row-2 .step-badge {
                left: auto;
                right: -32px;
            }

            .row-3 .timeline-card {
                transform: translateX(190px) translateY(30px);
            }
            .row-3 .timeline-card.visible {
                transform: translateX(140px) translateY(0);
            }
            .row-3 .timeline-card.visible:hover {
                transform: translateX(140px) translateY(-8px);
            }

            .row-4 .timeline-card {
                transform: translateX(-190px) translateY(30px);
            }
            .row-4 .timeline-card.visible {
                transform: translateX(-140px) translateY(0);
            }
            .row-4 .timeline-card.visible:hover {
                transform: translateX(-140px) translateY(-8px);
            }
            .row-4 .step-badge {
                left: auto;
                right: -32px;
            }

            .row-5 .timeline-card {
                transform: translateX(130px) translateY(30px);
            }
            .row-5 .timeline-card.visible {
                transform: translateX(80px) translateY(0);
            }
            .row-5 .timeline-card.visible:hover {
                transform: translateX(80px) translateY(-8px);
            }

            .row-6 .timeline-card {
                transform: translateY(30px);
            }
            .row-6 .timeline-card.visible {
                transform: none;
            }
            .row-6 .timeline-card.visible:hover {
                transform: translateY(-8px);
            }
            .row-6 .step-badge {
                left: 50%;
                transform: translateX(-50%);
                top: -32px;
            }
            .row-6 .step-card-header {
                margin-top: 16px;
            }
        }

        @media (max-width: 767px) {
            .timeline-svg {
                display: none;
            }
            .timeline-line-mobile {
                left: 24px;
            }
            .timeline-row {
                justify-content: flex-start;
                padding-left: 56px;
                margin-bottom: 64px;
            }
            .timeline-card {
                width: 100%;
                padding: 24px;
            }
            .step-badge {
                width: 48px;
                height: 48px;
                top: -24px;
                left: -56px;
                font-size: 1.25rem;
            }
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
                <?php if (isset($_SESSION['pelanggan_id'])): ?>
                    <!-- Menu Navigasi Setelah Pelanggan Login -->
                    <li><a href="index.php" class="nav-link">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php" class="nav-link">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li class="nav-auth">
                        <a href="masuk.php" class="btn btn-outline btn-sm">Masuk</a>
                        <a href="daftar.php" class="btn btn-primary btn-sm">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Cara Pesan Section -->
    <section class="cara-pesan-section" id="cara-pesan">
        <div class="timeline-header">
            <h2>Cara Memesan</h2>
            <p>Ikuti perjalanan seru kue impianmu dari oven kami ke meja makanmu. Cukup beberapa langkah mudah!</p>
        </div>

        <div class="timeline-container">
            <!-- Curved path linking steps on desktop -->
            <svg class="timeline-svg hidden md:block" preserveAspectRatio="none" viewBox="0 0 1000 1200">
                <!-- Background static line -->
                <path class="path-line-bg" d="M 500,0 C 700,50 750,120 680,200 C 600,300 250,250 320,400 C 400,550 700,480 640,620 C 550,750 300,700 360,820 C 400,950 600,900 550,1020 C 500,1035 500,1045 500,1050" fill="none" stroke="rgba(116, 48, 20, 0.12)" stroke-linecap="round" stroke-width="8"></path>
                <!-- Animated active flow line -->
                <path class="path-line-active" d="M 500,0 C 700,50 750,120 680,200 C 600,300 250,250 320,400 C 400,550 700,480 640,620 C 550,750 300,700 360,820 C 400,950 600,900 550,1020 C 500,1035 500,1045 500,1050" fill="none" stroke="var(--spiced-wine)" stroke-linecap="round" stroke-width="8"></path>
            </svg>

            <!-- Line for mobile view -->
            <div class="timeline-line-mobile"></div>

            <!-- Step 1 -->
            <div class="timeline-row row-1">
                <div class="deco-icon hidden md:block" style="right: -48px; top: 48px; transform: rotate(12deg);">
                    <span class="material-symbols-outlined" style="font-size: 4.5rem; color: var(--text-light);">cookie</span>
                </div>
                <div class="timeline-card">
                    <div class="step-badge badge-primary">1</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">cake</span>
                        <h3>Pilih Produk</h3>
                    </div>
                    <p class="step-desc">Jelajahi katalog dan pilih produk kue premium favorit Anda.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="timeline-row row-2">
                <div class="deco-icon hidden md:block" style="left: -48px; top: 40px; transform: rotate(-12deg);">
                    <span class="material-symbols-outlined" style="font-size: 4rem; color: var(--text-light);">local_mall</span>
                </div>
                <div class="timeline-card">
                    <div class="step-badge badge-tertiary">2</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">shopping_cart</span>
                        <h3>Masukkan ke Keranjang</h3>
                    </div>
                    <p class="step-desc">Tambahkan kue pilihan Anda ke keranjang belanja dan tentukan jumlah pemesanan.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="timeline-row row-3">
                <div class="deco-icon hidden md:block" style="right: 40px; top: 80px; transform: rotate(45deg);">
                    <span class="material-symbols-outlined" style="font-size: 4rem; color: var(--text-light);">schedule</span>
                </div>
                <div class="timeline-card">
                    <div class="step-badge badge-secondary">3</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">pin_drop</span>
                        <h3>Checkout</h3>
                    </div>
                    <p class="step-desc">Lengkapi detail pengiriman Anda. Harap diperhatikan, minimal pre-order adalah H-3 sebelum tanggal pengiriman dan batas kuantitas kue tiap hari adalah 5 pcs.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="timeline-row row-4">
                <div class="deco-icon hidden md:block" style="left: 40px; top: -40px; transform: rotate(-6deg);">
                    <span class="material-symbols-outlined" style="font-size: 4.5rem; color: var(--text-light);">payments</span>
                </div>
                <div class="timeline-card">
                    <div class="step-badge badge-primary">4</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">credit_card</span>
                        <h3>Pembayaran</h3>
                    </div>
                    <p class="step-desc">Lakukan pembayaran melalui transfer bank dan segera unggah bukti pembayaran Anda.</p>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="timeline-row row-5">
                <div class="deco-icon hidden md:block" style="right: 80px; top: -40px; transform: rotate(12deg);">
                    <span class="material-symbols-outlined" style="font-size: 4rem; color: var(--text-light);">bakery_dining</span>
                </div>
                <div class="timeline-card">
                    <div class="step-badge badge-tertiary">5</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">oven_gen</span>
                        <h3>Pesanan Diproses</h3>
                    </div>
                    <p class="step-desc">Kue Anda akan dipanggang segar (freshly baked) menggunakan bahan-bahan premium oleh chef kami.</p>
                </div>
            </div>

            <!-- Step 6 -->
            <div class="timeline-row row-6" style="margin-bottom: 32px;">
                <div class="timeline-card">
                    <div class="step-badge badge-secondary">6</div>
                    <div class="step-card-header">
                        <span class="material-symbols-outlined icon" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
                        <h3>Dikirim/Ambil ke Toko</h3>
                    </div>
                    <p class="step-desc">Pilih pesanan Anda diantar langsung secara aman ke alamat tujuan atau diambil langsung di toko resmi kami. Jangan lupa untuk tunjukkan kwitansi pembayaran.</p>
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
                    <a href="https://www.instagram.com/olinscake?igsh=MTgzeWszeGFxc2Iwag==" class="social-btn" aria-label="Instagram" target="_blank"><i class="fa-brands fa-instagram"></i></a>
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
                    <li><a href="masuk.php">Masuk Akun</a></li>
                    <li><a href="daftar.php">Daftar Baru</a></li>
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

        // Intersection Observer for timeline cards entry animation
        document.addEventListener('DOMContentLoaded', () => {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.15
            };

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, observerOptions);

                document.querySelectorAll('.timeline-card').forEach(card => {
                    observer.observe(card);
                });
            } else {
                // Fallback for older browsers
                document.querySelectorAll('.timeline-card').forEach(card => {
                    card.classList.add('visible');
                });
            }
        });
    </script>
</body>
</html>
