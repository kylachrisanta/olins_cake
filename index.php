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

// Fetch Favorite Products (limit 3)
$products_query = "SELECT * FROM produk LIMIT 3";
$products_result = $conn->query($products_query);


// Fetch up to 6 Latest Active Testimonials (from completed orders or seeded)
$testi_query = "SELECT t.*, p.nama_produk 
                FROM testimoni t
                LEFT JOIN produk p ON t.id_produk = p.id_produk
                LEFT JOIN pesanan o ON t.id_pesanan = o.id_pesanan
                WHERE t.status = 'Aktif' AND (t.id_pesanan IS NULL OR o.status_pesanan = 'Selesai')
                ORDER BY t.rating DESC, t.dibuat_pada DESC
                LIMIT 6";
$testi_result = $conn->query($testi_query);

// Helper for date formatting
if (!function_exists('formatIndonesianDate')) {
    function formatIndonesianDate($dateStr) {
        $timestamp = strtotime($dateStr);
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $day = date('j', $timestamp);
        $month = date('n', $timestamp);
        $year = date('Y', $timestamp);
        return "$day " . $months[$month] . " $year";
    }
}


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
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
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
                        <span class="dropdown-trigger">
                            Beranda <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                        </span>
                        <ul class="dropdown-menu-list">
                            <li><a href="index.php#tentang" class="dropdown-menu-item">Tentang Kami</a></li>
                            <li><a href="index.php#produk" class="dropdown-menu-item">Produk Favorit</a></li>
                            <li><a href="index.php#cara-pesan" class="dropdown-menu-item">Cara Pesan</a></li>
                            <li><a href="#testimoni" class="dropdown-menu-item">Testimoni</a></li>
                            <li><a href="index.php#hubungi" class="dropdown-menu-item">Hubungi Kami</a></li>
                        </ul>
                    </li>
                    <li><a href="produk.php" class="nav-link">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="#tentang" class="nav-link">Tentang Kami</a></li>
                    <li><a href="#produk" class="nav-link">Produk Favorit</a></li>
                    <li><a href="#cara-pesan" class="nav-link">Cara Pesan</a></li>
                    <li><a href="#testimoni" class="nav-link">Testimoni</a></li>
                    <li><a href="#hubungi" class="nav-link">Hubungi Kami</a></li>
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
                <div class="hero-actions">
                    <a href="#produk" class="btn btn-primary">Lihat Produk Favorit</a>
                    <a href="#tentang" class="btn btn-outline">Tentang Kami</a>
                </div>
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
                <h2>Kisah Manis Olin's Cake</h2>
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
                    <a href="https://wa.me/6281234567890?text=Halo%20Olin's%20Cake,%20saya%20ingin%20tanya%20mengenai%20kue..." target="_blank" class="btn btn-accent">
                        <i class="fa-brands fa-whatsapp" style="margin-right: 8px;"></i> Hubungi via WhatsApp
                    </a>
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
    <section class="section" id="produk">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Menu Terlaris</span>
                <h2>Produk Favorit</h2>
                <p>Intip tiga varian kue signature kami yang paling sering dipesan oleh pelanggan setia.</p>
            </div>

            <div class="products-grid">
                <?php if ($products_result && $products_result->num_rows > 0): ?>
                    <?php while($row = $products_result->fetch_assoc()): ?>
                        <div class="product-card">
                            <div class="product-img-wrapper">
                                <span class="product-badge">Pre-Order</span>
                                <img src="assets/images/<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_produk']) ?>" class="product-img">
                            </div>
                            <div class="product-info">
                                <div class="product-header">
                                    <h3 class="product-title"><?= htmlspecialchars($row['nama_produk']) ?></h3>
                                    <span class="product-price">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                                </div>
                                <p class="product-desc"><?= htmlspecialchars($row['deskripsi']) ?></p>
                                <div class="product-footer">
                                    <?php
                                    $p_id = $row['id_produk'];
                                    $avg_rat = isset($product_ratings[$p_id]) ? $product_ratings[$p_id]['avg_rating'] : 0.0;
                                    $tot_rev = isset($product_ratings[$p_id]) ? $product_ratings[$p_id]['total_reviews'] : 0;
                                    ?>
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
                                    <span class="product-status">Hanya Preview</span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">Belum ada produk favorit saat ini.</p>
                <?php endif; ?>
            </div>

            <div class="product-preview-note">
                Catatan: Saat ini kami hanya menampilkan preview produk terpopuler. <br>Untuk memesan, silakan <a href="<?php echo isset($_SESSION['pelanggan_id']) ? '#hubungi' : 'masuk.php' ?>">masuk ke akun Anda</a> atau hubungi WhatsApp kami.
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

    <!-- Testimoni Section -->
    <section class="section" id="testimoni">
        <div class="container">
            <div class="section-header">
                <span class="subtitle">Ulasan Pelanggan</span>
                <h2>Apa Kata Mereka?</h2>
                <p>Ulasan tulus dari para pelanggan yang telah mempercayakan perayaan mereka kepada Olin's Cake.</p>
            </div>


            <!-- Testimonials Slider -->
            <div class="testimonials-slider-container">
                <button class="slider-btn prev-btn" id="testi-prev" aria-label="Sebelumnya"><i class="fa-solid fa-chevron-left"></i></button>
                <div class="testimonials-slider-viewport">
                    <div class="testimonials-slider-track" id="testi-track">
                        <?php if ($testi_result && $testi_result->num_rows > 0): ?>
                            <?php while($row = $testi_result->fetch_assoc()): ?>
                                <div class="testi-card-premium" data-rating="<?= intval($row['rating']) ?>" data-photo="<?= !empty($row['gambar']) ? 'true' : 'false' ?>">
                                    <div>
                                        <div class="testi-card-header">
                                            <div class="testi-card-stars">
                                                <?= renderStars($row['rating']) ?>
                                            </div>
                                            <div class="testi-card-date">
                                                <?= formatIndonesianDate($row['dibuat_pada']) ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($row['nama_produk'])): ?>
                                            <span class="testi-card-product-badge"><?= htmlspecialchars($row['nama_produk']) ?></span>
                                        <?php endif; ?>

                                        <p class="testi-card-text" style="margin-top: 6px;">
                                            "<?= htmlspecialchars($row['isi_testimoni']) ?>"
                                        </p>
                                        
                                        <?php if (!empty($row['gambar'])): ?>
                                            <div class="testi-card-img-container" onclick="openLightbox('assets/uploads/testimoni/<?= htmlspecialchars($row['gambar']) ?>')">
                                                <img src="assets/uploads/testimoni/<?= htmlspecialchars($row['gambar']) ?>" alt="Foto ulasan pelanggan" class="testi-card-img" loading="lazy">
                                                <div class="testi-card-img-zoom-hint">
                                                    <i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="testi-profile" style="margin-top: 10px; border-top: 1px solid rgba(68, 45, 28, 0.05); padding-top: 14px; display: flex; gap: 12px; align-items: center;">
                                        <div class="testi-avatar" style="width: 40px; height: 40px; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; background-color: var(--cream-light); color: var(--spiced-wine); border-radius: 50%; font-weight: 700; flex-shrink: 0;"><?= htmlspecialchars($row['avatar_initial']) ?></div>
                                        <div>
                                            <h4 class="testi-name" style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-bottom: 2px; margin-top: 0; text-align: left;"><?= htmlspecialchars($row['nama_lengkap']) ?></h4>
                                            <span class="testi-role" style="font-size: 0.75rem; color: var(--text-light); display: block; text-align: left;"><?= htmlspecialchars($row['pekerjaan']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="width: 100%; text-align: center; padding: 40px 0; color: var(--text-muted);">
                                Belum ada ulasan testimoni dari pelanggan saat ini.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="slider-btn next-btn" id="testi-next" aria-label="Selanjutnya"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </section>

    <!-- LIGHTBOX OVERLAY FOR ZOOMING PHOTO -->
    <div class="lightbox-modal" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img src="" alt="Fullscreen image review" class="lightbox-content" id="lightbox-img">
    </div>

    <!-- Hubungi Kami Section -->
    <section class="section section-bg" id="hubungi">
        <div class="container contact-grid">
            <div class="contact-info">
                <span class="subtitle">Kontak & Lokasi</span>
                <h3>Ada Pertanyaan Khusus?</h3>
                <p>
                    Kami melayani pesanan khusus seperti kue ulang tahun custom, pesanan dalam jumlah besar untuk arisan, atau sekadar konsultasi rasa. Hubungi kami, dengan senang hati kami akan membantu Anda.
                </p>
                <div class="contact-details">
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="contact-text">
                            <h4>Lokasi Dapur Kami</h4>
                            <p>Jl. Melati Raya No. 45, Kebayoran Baru, Jakarta Selatan</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="contact-text">
                            <h4>Jam Operasional Pre-Order</h4>
                            <p>Senin - Sabtu: 08.00 - 17.00 WIB <br><span style="font-size: 0.85rem; color: var(--spiced-wine);">*Minggu libur (kecuali pengiriman pesanan khusus)</span></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
                        <div class="contact-text">
                            <h4>WhatsApp Hotline</h4>
                            <p>+62 812-3456-7890</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="contact-card">
                <h4>Kirim Pesan Cepat</h4>
                <form id="quick-message-form" onsubmit="sendWhatsAppMsg(event)">
                    <div class="contact-form-group">
                        <label for="msg-nama">Nama Lengkap</label>
                        <input type="text" id="msg-nama" class="contact-form-control" placeholder="Masukkan nama Anda" required>
                    </div>
                    <div class="contact-form-group">
                        <label for="msg-kue">Minat Kue</label>
                        <select id="msg-kue" class="contact-form-control">
                            <option value="Strawberry Shortcake">Strawberry Shortcake</option>
                            <option value="Signature Chocolate Fudge">Signature Chocolate Fudge</option>
                            <option value="Classic Pandan Cheese">Classic Pandan Cheese</option>
                            <option value="Custom Order / Lainnya">Custom Order / Lainnya</option>
                        </select>
                    </div>
                    <div class="contact-form-group">
                        <label for="msg-catatan">Pesan / Detail Khusus</label>
                        <textarea id="msg-catatan" class="contact-form-control" rows="4" placeholder="Tuliskan catatan pemesanan atau pertanyaan Anda..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-brands fa-whatsapp" style="margin-right: 8px;"></i> Kirim via WhatsApp
                    </button>
                </form>
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
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 812-3456-7890<br>
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
            const waUrl = `https://wa.me/6281234567890?text=${encodedText}`;

            window.open(waUrl, '_blank');
        }

        // ─── Testimonials Slider ────────────────────────────────────────────────
        (function () {
            const track      = document.getElementById('testi-track');
            const prevBtn    = document.getElementById('testi-prev');
            const nextBtn    = document.getElementById('testi-next');

            if (!track || !prevBtn || !nextBtn) return;

            const cards = track.querySelectorAll('.testi-card-premium');
            if (!cards.length) return;

            let currentIndex = 0;

            function getVisibleCount() {
                const vw = window.innerWidth;
                if (vw <= 650)  return 1;
                if (vw <= 992)  return 2;
                return 3;
            }

            function getCardWidthPercent() {
                const visible = getVisibleCount();
                const gapPx   = 24;
                // Card width as fraction of track width (same as CSS flex calc)
                return (100 / visible);
            }

            function maxIndex() {
                return Math.max(0, cards.length - getVisibleCount());
            }

            function slideTo(index) {
                currentIndex = Math.max(0, Math.min(index, maxIndex()));
                const visible  = getVisibleCount();
                const gapPx    = 24;
                // Calculate pixel offset for each step
                const cardEl      = cards[0];
                const cardWidth   = cardEl.getBoundingClientRect().width;
                const offset      = currentIndex * (cardWidth + gapPx);
                track.style.transform = `translateX(-${offset}px)`;

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

            // Auto-play every 5 seconds
            let autoPlay = setInterval(() => {
                if (currentIndex >= maxIndex()) {
                    slideTo(0);
                } else {
                    slideTo(currentIndex + 1);
                }
            }, 5000);

            // Pause auto-play on hover
            track.parentElement.addEventListener('mouseenter', () => clearInterval(autoPlay));
            track.parentElement.addEventListener('mouseleave', () => {
                autoPlay = setInterval(() => {
                    if (currentIndex >= maxIndex()) {
                        slideTo(0);
                    } else {
                        slideTo(currentIndex + 1);
                    }
                }, 5000);
            });
        })();

        // ─── Lightbox ───────────────────────────────────────────────────────────
        function openLightbox(imgUrl) {
            const lightbox    = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            if (!lightbox || !lightboxImg) return;
            lightboxImg.src              = imgUrl;
            lightbox.style.display       = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            if (!lightbox) return;
            lightbox.style.display       = 'none';
            document.body.style.overflow = '';
        }

        // Close lightbox with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeLightbox();
        });
    </script>
</body>
</html>
