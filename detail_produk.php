<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Ambil 3 produk dengan penjualan terbanyak (Best Seller)
$best_sellers = [];
$best_query = "SELECT id_produk, SUM(jumlah) as total_terjual 
               FROM detail_pesanan dp 
               JOIN pesanan p ON dp.id_pesanan = p.id_pesanan 
               WHERE p.status_pesanan NOT IN ('Dibatalkan', 'Kedaluwarsa') 
               GROUP BY id_produk 
               ORDER BY total_terjual DESC 
               LIMIT 3";
$best_res = $conn->query($best_query);
if ($best_res && $best_res->num_rows > 0) {
    while ($b_row = $best_res->fetch_assoc()) {
        if ($b_row['total_terjual'] > 0) {
            $best_sellers[] = (int)$b_row['id_produk'];
        }
    }
}
// Fallback jika belum ada data penjualan yang valid (gunakan ID: 1, 2, 3)
$fallback_ids = [1, 2, 3];
foreach ($fallback_ids as $fid) {
    if (count($best_sellers) >= 3) break;
    if (!in_array($fid, $best_sellers)) {
        $best_sellers[] = $fid;
    }
}

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
    <link rel="stylesheet" href="assets/css/style.css?v=1.1">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled"> <!-- Background solid untuk halaman detail -->
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
                    <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Produk</a></li>
                    <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                    <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                    <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
                <?php else: ?>
                    <!-- Menu Navigasi Sebelum Login -->
                    <li><a href="index.php" class="nav-link">Beranda</a></li>
                    <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                    <li><a href="produk.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Produk</a></li>
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

            <div class="profile-title-area">
                <h1>Detail Produk</h1>
                <p>Informasi detail dan spesifikasi dari <?= htmlspecialchars($product['nama_produk']) ?>.</p>
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
                    <div class="detail-tag-rating" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span class="detail-category-badge"><?= htmlspecialchars($product['kategori']) ?></span>
                        <?php if (in_array($product['id_produk'], $best_sellers)): ?>
                            <span class="detail-category-badge best-seller-badge" style="position: relative; left: auto; top: auto; right: auto; margin-left: 0; box-shadow: 0 4px 10px rgba(255, 77, 77, 0.2);"><i class="fa-solid fa-fire"></i> Best Seller</span>
                        <?php endif; ?>
                        <div class="product-rating-container" style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <div class="product-rating" style="display: inline-flex; align-items: center; gap: 4px;">
                                <?php if ($prod_total_reviews > 0): ?>
                                    <?= renderStars($prod_avg_rating) ?>
                                    <span style="color: var(--text-main); font-size: 0.95rem; font-weight: 700; margin-left: 4px;">
                                        <?= number_format($prod_avg_rating, 1, ',', '.') ?>/5
                                    </span>
                                <?php else: ?>
                                    <i class="fa-regular fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                    <i class="fa-regular fa-star"></i>
                                    <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; margin-left: 4px;">
                                        Belum ada ulasan
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($prod_total_reviews > 0): ?>
                                <span style="color: rgba(68, 45, 28, 0.2);">|</span>
                                <a href="testimoni.php?id_produk=<?= $product['id_produk'] ?>" style="color: var(--spiced-wine); font-size: 0.9rem; font-weight: 600; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">
                                    Lihat <?= $prod_total_reviews ?> Testimoni →
                                </a>
                            <?php endif; ?>
                        </div>
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
                    <?php if (isset($_SESSION['pelanggan_id'])): ?>
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
                    <?php else: ?>
                        <div class="detail-action-box" style="flex-direction: column; align-items: flex-start; gap: 15px; margin-top: 10px;">
                            <div class="preorder-notice-box" style="background-color: #fef2f2; border-color: #fca5a5; color: #991b1b; display: flex; width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid #fca5a5; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-circle-exclamation" style="color: #ef4444; font-size: 1.1rem;"></i>
                                <p style="margin: 0; font-weight: 500;">Silakan masuk terlebih dahulu untuk melakukan pemesanan.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div> <!-- Close detail-grid -->
        </div> <!-- Close container -->
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


    </script>
</body>
</html>
