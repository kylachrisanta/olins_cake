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

// Fetch All Products for Preview Carousel
$products_query = "SELECT * FROM produk ORDER BY id_produk ASC";
$products_result = $conn->query($products_query);





// Ambil data rating rata-rata & jumlah ulasan per produk
$product_ratings = [];
$ratings_res = $conn->query("SELECT id_produk, AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM testimoni WHERE status = 'Aktif' AND id_produk IS NOT NULL GROUP BY id_produk");
if ($ratings_res) {
    while ($rat_row = $ratings_res->fetch_assoc()) {
        $product_ratings[$rat_row['id_produk']] = [
            'avg_rating' => round(floatval($rat_row['avg_rating']), 1),
            'total_reviews' => intval($rat_row['total_reviews'])
        ];
    }
}

// Helper untuk render bintang
if (!function_exists('renderStars')) {
    function renderStars($rating) {
        $html = '';
        $floor = floor($rating);
        $diff = $rating - $floor;
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $floor) {
                $html .= '<i class="fa-solid fa-star"></i>';
            } elseif ($i == $floor + 1 && $diff >= 0.4) {
                $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
            } else {
                $html .= '<i class="fa-regular fa-star"></i>';
            }
        }
        return $html;
    }
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
    <!-- Inline styles to prevent stylesheet caching issues on the new carousel -->
    <style>
        .product-carousel-container {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            margin-top: 40px;
            padding: 0 50px;
        }
        .product-carousel-viewport {
            width: 100%;
            overflow: hidden;
        }
        .product-carousel-track {
            display: flex;
            align-items: stretch;
            gap: 30px;
            transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            padding: 15px 0;
        }
        .product-carousel-track .product-card {
            flex: 0 0 calc((100% - 60px) / 3);
            min-width: 280px;
            height: auto;
        }
        .product-carousel-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: var(--white);
            border: 1px solid rgba(68, 45, 28, 0.1);
            color: var(--spiced-wine);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            z-index: 10;
        }
        .product-carousel-nav-btn:hover {
            background-color: var(--spiced-wine);
            color: var(--white);
            border-color: var(--spiced-wine);
            box-shadow: var(--shadow-md);
        }
        .product-carousel-prev { left: -10px; }
        .product-carousel-next { right: -10px; }
        .best-seller-badge {
            left: 16px;
            right: auto !important;
            background: linear-gradient(135deg, #FF4D4D, #FF9900) !important;
            box-shadow: 0 4px 10px rgba(255, 77, 77, 0.3) !important;
        }
        @media (max-width: 992px) {
            .product-carousel-track .product-card {
                flex: 0 0 calc((100% - 30px) / 2);
            }
        }
        @media (max-width: 768px) {
            .product-carousel-container { padding: 0; }
            .product-carousel-viewport {
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                scrollbar-width: none;
            }
            .product-carousel-viewport::-webkit-scrollbar { display: none; }
            .product-carousel-track { gap: 20px; }
            .product-carousel-track .product-card {
                flex: 0 0 85%;
                scroll-snap-align: center;
            }
            .product-carousel-nav-btn { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Floating Header -->
    <header id="header">
        <div class="container navbar">
            <a href="#" class="logo">
                <i class="fa-solid fa-cake-candles"></i> Olin's <span>Cake</span>
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
                        <a href="index.php#home" class="dropdown-trigger" style="text-decoration: none;">
                            Beranda <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                        </a>
                        <ul class="dropdown-menu-list">
                            <li><a href="index.php#tentang" class="dropdown-menu-item">Tentang Kami</a></li>
                            <li><a href="index.php#produk" class="dropdown-menu-item">Produk Favorit</a></li>
                            <li><a href="index.php#cara-pesan" class="dropdown-menu-item">Cara Pesan</a></li>
                        </ul>
                    </li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php#home" class="nav-link">Beranda</a></li>
                    <li><a href="#tentang" class="nav-link">Tentang Kami</a></li>
                    <li><a href="#produk" class="nav-link">Produk Favorit</a></li>
                    <li><a href="#cara-pesan" class="nav-link">Cara Pesan</a></li>

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

    <!-- Produk Favorit Section -->
    <section class="section" id="produk" style="overflow: hidden;">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Menu Terlaris</span>
                <h2>Produk Favorit</h2>
                <p>Jelajahi seluruh varian kue premium signature kami yang paling sering dipesan oleh pelanggan setia.</p>
            </div>

            <div class="product-carousel-container">
                <button class="product-carousel-nav-btn product-carousel-prev" id="product-carousel-prev" aria-label="Geser Kiri">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                
                <div class="product-carousel-viewport">
                    <div class="product-carousel-track" id="product-carousel-track">
                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                            <?php 
                            $product_counter = 0;
                            while($row = $products_result->fetch_assoc()): 
                                $product_counter++;
                                $is_best_seller = ($product_counter <= 3);
                                $p_id = $row['id_produk'];
                                $avg_rat = isset($product_ratings[$p_id]) ? $product_ratings[$p_id]['avg_rating'] : 0.0;
                                $tot_rev = isset($product_ratings[$p_id]) ? $product_ratings[$p_id]['total_reviews'] : 0;
                            ?>
                                <div class="product-card">
                                    <div class="product-img-wrapper">
                                        <span class="product-badge"><?= htmlspecialchars($row['kategori']) ?></span>
                                        <?php if ($is_best_seller): ?>
                                            <span class="product-badge best-seller-badge"><i class="fa-solid fa-fire"></i> Best Seller</span>
                                        <?php endif; ?>
                                        <img src="assets/images/<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_produk']) ?>" class="product-img">
                                    </div>
                                    <div class="product-info">
                                        <div class="product-header">
                                            <h3 class="product-title"><?= htmlspecialchars($row['nama_produk']) ?></h3>
                                            <span class="product-price">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                                        </div>
                                        <p class="product-desc"><?= htmlspecialchars($row['deskripsi']) ?></p>
                                        <div class="product-footer">
                                            <a href="testimoni.php?id_produk=<?= $p_id ?>" class="product-rating" style="cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                <?php if ($tot_rev > 0): ?>
                                                    <?= renderStars($avg_rat) ?>
                                                    <span style="color: var(--text-muted); font-size: 0.8rem; margin-left: 4px; text-decoration: underline;">
                                                        <?= number_format($avg_rat, 1, ',', '.') ?> (<?= $tot_rev ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <i class="fa-regular fa-star"></i>
                                                    <i class="fa-regular fa-star"></i>
                                                    <i class="fa-regular fa-star"></i>
                                                    <i class="fa-regular fa-star"></i>
                                                    <i class="fa-regular fa-star"></i>
                                                    <span style="color: var(--text-light); font-size: 0.8rem; margin-left: 4px;">(0)</span>
                                                <?php endif; ?>
                                            </a>
                                            <a href="detail_produk.php?id=<?= $p_id ?>" class="btn btn-outline btn-sm">Lihat Detail</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-muted); width: 100%;">Belum ada produk saat ini.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="product-carousel-nav-btn product-carousel-next" id="product-carousel-next" aria-label="Geser Kanan">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

            <div class="product-preview-note">
                Catatan: Section ini hanya berfungsi sebagai preview produk. Untuk melakukan pemesanan kue, silakan kunjungi menu <a href="produk.php">Produk</a>.
            </div>
        </div>
    </section>

    <!-- Cara Pesan Section -->
    <section class="section section-bg" id="cara-pesan">
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
                    <li><a href="#home">Beranda</a></li>
                    <li><a href="#tentang">Tentang Kami</a></li>
                    <li><a href="#produk">Produk Favorit</a></li>
                    <li><a href="#cara-pesan">Cara Pesan</a></li>
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


        // ─── Product Carousel Slider ──────────────────────────────────────────
        (function () {
            const track = document.getElementById('product-carousel-track');
            const prevBtn = document.getElementById('product-carousel-prev');
            const nextBtn = document.getElementById('product-carousel-next');

            if (!track || !prevBtn || !nextBtn) return;

            const cards = track.querySelectorAll('.product-card');
            if (!cards.length) return;

            let currentIndex = 0;

            function getVisibleCount() {
                const vw = window.innerWidth;
                if (vw <= 768) return 1;
                if (vw <= 992) return 2;
                return 3;
            }

            function maxIndex() {
                return Math.max(0, cards.length - getVisibleCount());
            }

            function slideTo(index) {
                currentIndex = Math.max(0, Math.min(index, maxIndex()));
                const gapPx = 30; // gap in px
                const cardEl = cards[0];
                const cardWidth = cardEl.getBoundingClientRect().width;
                const offset = currentIndex * (cardWidth + gapPx);
                track.style.transform = `translateX(-${offset}px)`;

                // Update navigation button disabled states / opacity
                prevBtn.style.opacity = currentIndex === 0 ? '0.35' : '1';
                prevBtn.style.pointerEvents = currentIndex === 0 ? 'none' : 'auto';
                nextBtn.style.opacity = currentIndex >= maxIndex() ? '0.35' : '1';
                nextBtn.style.pointerEvents = currentIndex >= maxIndex() ? 'none' : 'auto';
            }

            prevBtn.addEventListener('click', () => slideTo(currentIndex - 1));
            nextBtn.addEventListener('click', () => slideTo(currentIndex + 1));

            // Re-calculate on resize
            window.addEventListener('resize', () => slideTo(currentIndex));

            // Initial state
            slideTo(0);
        })();

    </script>
</body>
</html>
