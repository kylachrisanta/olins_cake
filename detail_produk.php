<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Ambil ID Produk dari URL
$id_produk = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data produk detail
$product = null;
if ($id_produk > 0) {
    $stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
    }
    $stmt->close();
}

// Jika produk tidak ditemukan, alihkan ke katalog produk
if (!$product) {
    header("Location: produk.php");
    exit;
}

// Query untuk rating rata-rata & jumlah ulasan produk ini
$prod_stats_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM testimoni WHERE id_produk = ? AND status = 'Aktif'";
$stmt_p_stats = $conn->prepare($prod_stats_query);
$stmt_p_stats->bind_param("i", $id_produk);
$stmt_p_stats->execute();
$prod_stats = $stmt_p_stats->get_result()->fetch_assoc();
$stmt_p_stats->close();

$prod_total_reviews = intval($prod_stats['total_reviews']);
$prod_avg_rating = $prod_total_reviews > 0 ? round(floatval($prod_stats['avg_rating']), 1) : 0.0;

// Ambil 6 foto testimoni terbaru untuk produk ini (Mini Gallery)
$gallery_query = "SELECT gambar FROM testimoni WHERE id_produk = ? AND status = 'Aktif' AND gambar IS NOT NULL AND gambar != '' ORDER BY dibuat_pada DESC LIMIT 6";
$stmt_g = $conn->prepare($gallery_query);
$stmt_g->bind_param("i", $id_produk);
$stmt_g->execute();
$gallery_res = $stmt_g->get_result();
$gallery_photos = [];
while ($g_row = $gallery_res->fetch_assoc()) {
    $gallery_photos[] = $g_row['gambar'];
}
$stmt_g->close();

// Ambil semua testimoni aktif untuk produk ini
$reviews_query = "SELECT * FROM testimoni WHERE id_produk = ? AND status = 'Aktif' ORDER BY dibuat_pada DESC";
$stmt_r = $conn->prepare($reviews_query);
$stmt_r->bind_param("i", $id_produk);
$stmt_r->execute();
$reviews_res = $stmt_r->get_result();
$product_reviews = [];
while ($r_row = $reviews_res->fetch_assoc()) {
    $product_reviews[] = $r_row;
}
$stmt_r->close();

// Helper untuk bintang
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

// Helper date format
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['nama_produk']) ?> - Olin's Cake</title>
    <meta name="description" content="<?= htmlspecialchars($product['deskripsi']) ?>">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled"> <!-- Background solid untuk halaman detail -->
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
                            <li><a href="testimoni.php" class="dropdown-menu-item">Testimoni</a></li>
                            <li><a href="index.php#hubungi" class="dropdown-menu-item">Hubungi Kami</a></li>
                        </ul>
                    </li>
                    <li><a href="produk.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php#tentang" class="nav-link">Tentang Kami</a></li>
                    <li><a href="index.php#produk" class="nav-link">Produk Favorit</a></li>
                    <li><a href="index.php#cara-pesan" class="nav-link">Cara Pesan</a></li>
                    <li><a href="testimoni.php" class="nav-link">Testimoni</a></li>
                    <li><a href="index.php#hubungi" class="nav-link">Hubungi Kami</a></li>
                    <li class="nav-auth">
                        <a href="masuk.php" class="btn btn-outline btn-sm">Masuk</a>
                        <a href="daftar.php" class="btn btn-primary btn-sm">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Detail Product Section -->
    <section class="detail-section">
        <div class="container">
            
            <!-- Breadcrumbs -->
            <div class="detail-breadcrumb">
                <a href="index.php">Beranda</a> <span>/</span> <a href="produk.php">Produk</a> <span>/</span> <span><?= htmlspecialchars($product['nama_produk']) ?></span>
            </div>

            <!-- Detail Grid Layout -->
            <div class="detail-grid">
                
                <!-- Sisi Kiri: Foto Besar -->
                <div class="detail-img-col">
                    <div class="detail-img-card">
                        <img src="assets/images/<?= htmlspecialchars($product['gambar']) ?>" alt="<?= htmlspecialchars($product['nama_produk']) ?>" class="detail-large-img">
                    </div>
                </div>

                <!-- Sisi Kanan: Detail Informasi -->
                <div class="detail-info-col">
                    <div class="detail-tag-rating">
                        <span class="detail-category-badge"><?= htmlspecialchars($product['kategori']) ?></span>
                        <a href="testimoni.php?id_produk=<?= $product['id_produk'] ?>" class="product-rating" style="cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                            <?php if ($prod_total_reviews > 0): ?>
                                <?= renderStars($prod_avg_rating) ?>
                                <span style="color: var(--spiced-wine); font-size: 0.9rem; margin-left: 6px; font-weight: 600; text-decoration: underline;">
                                    <?= number_format($prod_avg_rating, 1, ',', '.') ?> (<?= $prod_total_reviews ?> Ulasan)
                                </span>
                            <?php else: ?>
                                <i class="fa-regular fa-star"></i>
                                <i class="fa-regular fa-star"></i>
                                <i class="fa-regular fa-star"></i>
                                <i class="fa-regular fa-star"></i>
                                <i class="fa-regular fa-star"></i>
                                <span style="color: var(--text-muted); font-size: 0.9rem; margin-left: 6px; font-weight: 500;">
                                    Belum ada ulasan
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>

                    <h1 class="detail-title"><?= htmlspecialchars($product['nama_produk']) ?></h1>
                    
                    <div class="detail-price-box">
                        Rp <?= number_format($product['harga'], 0, ',', '.') ?>
                    </div>

                    <!-- Spesifikasi Ukuran & Masa Simpan -->
                    <ul class="detail-spec-list">
                        <li class="detail-spec-item">
                            <i class="fa-solid fa-ruler-combined"></i>
                            <div><strong>Ukuran:</strong> <?= htmlspecialchars($product['ukuran']) ?></div>
                        </li>
                        <li class="detail-spec-item">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <div><strong>Masa Simpan:</strong> <?= htmlspecialchars($product['masa_simpan']) ?></div>
                        </li>
                    </ul>

                    <div class="detail-desc-box">
                        <h4>Deskripsi Produk:</h4>
                        <p><?= htmlspecialchars($product['deskripsi']) ?></p>
                    </div>

                    <!-- Peringatan Batas Pre-order H-3 -->
                    <div class="preorder-notice-box">
                        <i class="fa-solid fa-circle-info"></i>
                        <p>Informasi: Minimal pre-order H-3 sebelum tanggal pengiriman.</p>
                    </div>

                    <!-- Jumlah Pemesanan & Tambah Ke Keranjang -->
                    <div class="detail-action-box">
                        <div class="qty-selector">
                            <button type="button" class="qty-btn" onclick="adjustQty(-1)" aria-label="Kurang Jumlah">
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <input type="text" id="qty-input" name="jumlah" class="qty-input" value="1" readonly>
                            <button type="button" class="qty-btn" onclick="adjustQty(1)" aria-label="Tambah Jumlah">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="addToCart(<?= $product['id_produk'] ?>)">
                            <i class="fa-solid fa-basket-shopping" style="margin-right: 8px;"></i> Tambah ke Keranjang
                        </button>
                    </div>
                </div>

        </div>
    </section>

    <!-- Section Ulasan Pelanggan -->
    <section class="detail-review-section" id="ulasan-produk">
        <div class="container">
            
            <div class="review-summary-row" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; border-bottom: 1px solid rgba(68, 45, 28, 0.05); padding-bottom: 20px; margin-bottom: 30px;">
                <div class="section-header" style="margin: 0; text-align: left; max-width: 100%; flex: 1;">
                    <span class="subtitle">Ulasan Produk</span>
                    <h2>Apa Kata Pembeli?</h2>
                    <p>Ulasan jujur dari pelanggan yang telah menikmati <?= htmlspecialchars($product['nama_produk']) ?>.</p>
                </div>
                <div>
                    <a href="testimoni.php?id_produk=<?= $product['id_produk'] ?>" class="btn btn-outline" style="border-radius: 30px; font-size: 0.95rem;">
                        <i class="fa-solid fa-comments" style="margin-right: 8px;"></i> Lihat Semua Ulasan (<?= $prod_total_reviews ?>)
                    </a>
                </div>
            </div>

            <!-- Mini Gallery Foto Ulasan Pelanggan -->
            <?php if (count($gallery_photos) > 0): ?>
                <div class="product-gallery-mini">
                    <h3 class="product-gallery-title">
                        <i class="fa-solid fa-images" style="color: var(--spiced-wine);"></i>
                        <span>Foto dari Pelanggan</span>
                    </h3>
                    <div class="product-gallery-grid">
                        <?php foreach ($gallery_photos as $g_photo): ?>
                            <img src="assets/uploads/testimoni/<?= htmlspecialchars($g_photo) ?>" alt="Foto ulasan pelanggan" class="product-gallery-thumb" onclick="openLightbox('assets/uploads/testimoni/<?= htmlspecialchars($g_photo) ?>')">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grid Card Review -->
            <div class="testi-grid-custom" style="margin-top: 30px;">
                <?php if (count($product_reviews) > 0): ?>
                    <?php foreach ($product_reviews as $rev): ?>
                        <div class="testi-card-premium">
                            <div>
                                <div class="testi-card-header">
                                    <div class="testi-card-stars">
                                        <?= renderStars($rev['rating']) ?>
                                    </div>
                                    <div class="testi-card-date">
                                        <?= formatIndonesianDate($rev['dibuat_pada']) ?>
                                    </div>
                                </div>

                                <p class="testi-card-text">
                                    "<?= htmlspecialchars($rev['isi_testimoni']) ?>"
                                </p>
                                
                                <?php if (!empty($rev['gambar'])): ?>
                                    <div class="testi-card-img-container" onclick="openLightbox('assets/uploads/testimoni/<?= htmlspecialchars($rev['gambar']) ?>')">
                                        <img src="assets/uploads/testimoni/<?= htmlspecialchars($rev['gambar']) ?>" alt="Foto ulasan pelanggan" class="testi-card-img" loading="lazy">
                                        <div class="testi-card-img-zoom-hint">
                                            <i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="testi-profile" style="margin-top: 10px; border-top: 1px solid rgba(68, 45, 28, 0.05); padding-top: 14px;">
                                <div class="testi-avatar" style="width: 40px; height: 40px; font-size: 0.95rem;"><?= htmlspecialchars($rev['avatar_initial']) ?></div>
                                <div>
                                    <h4 class="testi-name" style="font-size: 0.95rem;"><?= htmlspecialchars($rev['nama_lengkap']) ?></h4>
                                    <span class="testi-role" style="font-size: 0.75rem;"><?= htmlspecialchars($rev['pekerjaan']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 45px 0; color: var(--text-light); border: 1px dashed rgba(68, 45, 28, 0.15); border-radius: var(--radius-md); background: var(--cream-light);">
                        <i class="fa-regular fa-comment-dots" style="font-size: 2.5rem; color: var(--text-light); margin-bottom: 12px;"></i>
                        <h3>Belum Ada Ulasan</h3>
                        <p>Kue ini belum memiliki ulasan dari pelanggan. Jadilah yang pertama memberikan ulasan setelah pesanan Anda selesai!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- LIGHTBOX OVERLAY -->
    <div class="lightbox-modal" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img src="" alt="Fullscreen image review" class="lightbox-content" id="lightbox-img">
    </div>

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
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 812-3456-7890<br>
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Kebayoran Baru, Jakarta Selatan
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- JavaScript Actions -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Kuantitas Handler
        function adjustQty(amount) {
            const qtyInput = document.getElementById('qty-input');
            let currentVal = parseInt(qtyInput.value);
            
            if (isNaN(currentVal)) {
                currentVal = 1;
            }

            let newVal = currentVal + amount;
            
            // Batas minimal 1
            if (newVal < 1) {
                newVal = 1;
            }
            
            // Batas maksimal 99
            if (newVal > 99) {
                newVal = 99;
            }

            qtyInput.value = newVal;
        }

        // Tambah ke keranjang action via AJAX
        function addToCart(productId) {
            const qtyInput = document.getElementById('qty-input');
            const jumlah = qtyInput.value;

            <?php if (isset($_SESSION['pelanggan_id'])): ?>
                // Kirim request AJAX ke tambah_keranjang.php
                const formData = new FormData();
                formData.append('id_produk', productId);
                formData.append('jumlah', jumlah);

                fetch('tambah_keranjang.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        window.location.href = 'keranjang.php';
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan koneksi. Silakan coba lagi.');
                    console.error('Error:', error);
                });
            <?php else: ?>
                // Jika belum login, paksa login dulu
                alert("Silakan masuk (login) terlebih dahulu untuk mulai menambahkan kue ke keranjang.");
                window.location.href = "masuk.php";
            <?php endif; ?>
        }

        // Lightbox Functions
        function openLightbox(imgUrl) {
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            lightboxImg.src = imgUrl;
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.style.display = 'none';
            document.body.style.overflow = '';
        }
    </script>
</body>
</html>
