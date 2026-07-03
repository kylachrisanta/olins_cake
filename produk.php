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

// Ambil semua produk dari database (hanya yang Aktif)
$query = "SELECT * FROM produk WHERE status_produk = 'Aktif' ORDER BY kategori ASC, nama_produk ASC";
$result = $conn->query($query);
$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Ambil daftar kategori dari tabel kategori yang memiliki minimal 1 produk Aktif
$categories = [];
$cat_query = "SELECT k.nama_kategori 
              FROM kategori k
              INNER JOIN produk p ON p.kategori = k.nama_kategori AND p.status_produk = 'Aktif'
              GROUP BY k.nama_kategori
              ORDER BY k.nama_kategori ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result && $cat_result->num_rows > 0) {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $categories[] = $cat_row['nama_kategori'];
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
    <title>Katalog Produk - Olin's Cake</title>
    <meta name="description" content="Pilih berbagai varian kue premium buatan rumah dari Olin's Cake. Pre-order sekarang untuk momen spesial Anda.">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled"> <!-- Set solid background from start for catalog -->
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

    <!-- Catalog Main Section -->
    <section class="catalog-section">
        <div class="container">
            
            <!-- Header Pencarian & Judul -->
            <div class="catalog-header">
                <div class="catalog-title">
                    <h1>Katalog Produk</h1>
                    <p>Temukan kue premium berkualitas terbaik untuk melengkapi kebahagiaan Anda.</p>
                </div>
                
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <input type="text" id="search-input" class="search-control" placeholder="Cari nama kue pilihan Anda..." oninput="filterProducts()">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                </div>
            </div>

            <!-- Tab Filter Kategori (Dinamis dari Database) -->
            <div class="category-tabs-container">
                <ul class="category-tabs" id="category-tabs">
                    <?php foreach ($categories as $cat): ?>
                        <li><button class="filter-btn" onclick="selectCategory('<?= htmlspecialchars($cat, ENT_QUOTES) ?>', this)"><?= htmlspecialchars($cat) ?></button></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Grid Produk -->
            <div class="products-grid" id="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $row): ?>
                        <div class="product-card" data-nama="<?= strtolower(htmlspecialchars($row['nama_produk'])) ?>" data-kategori="<?= htmlspecialchars($row['kategori']) ?>">
                            <div class="product-img-wrapper">
                                <span class="product-badge"><?= htmlspecialchars($row['kategori']) ?></span>
                                <?php if (in_array($row['id_produk'], $best_sellers)): ?>
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
                                     <a href="detail_produk.php?id=<?= $row['id_produk'] ?>" class="btn btn-outline btn-sm">Lihat Detail</a>
                                 </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <div class="empty-state-icon"><i class="fa-solid fa-cookie"></i></div>
                        <h3>Belum Ada Produk</h3>
                        <p>Maaf, saat ini belum ada daftar produk kue yang tersedia.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tampilan Pencarian Kosong (Hidden by default) -->
            <div class="empty-state" id="empty-search-state" style="display: none;">
                <div class="empty-state-icon"><i class="fa-solid fa-face-frown"></i></div>
                <h3>Kue Tidak Ditemukan</h3>
                <p>Maaf, kami tidak dapat menemukan kue dengan kata kunci tersebut. Coba kata kunci lainnya!</p>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer style="margin-top: 0;">
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

    <!-- JavaScript Filter & Pencarian Interaktif -->
    <script>
        // State Filter Aktif
        let selectedCategoryState = 'Semua';

        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Filter Kategori
        function selectCategory(category, element) {
            const isActive = element.classList.contains('active');

            // Set active class tab
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            if (isActive) {
                // Jika sudah aktif, matikan filternya
                selectedCategoryState = 'Semua';
            } else {
                // Jika belum aktif, aktifkan filternya
                element.classList.add('active');
                selectedCategoryState = category;
            }
            
            // Jalankan filter gabungan
            filterProducts();
        }

        // Fungsi Filter Gabungan (Pencarian & Kategori)
        function filterProducts() {
            const searchVal = document.getElementById('search-input').value.toLowerCase().trim();
            const productCards = document.querySelectorAll('.product-card');
            const emptyState = document.getElementById('empty-search-state');
            
            let visibleCount = 0;

            productCards.forEach(card => {
                const nama = card.getAttribute('data-nama');
                const kategori = card.getAttribute('data-kategori');

                // Cocok Kategori?
                const matchKategori = (selectedCategoryState === 'Semua' || kategori === selectedCategoryState);
                
                // Cocok Pencarian?
                const matchSearch = (searchVal === '' || nama.includes(searchVal));

                if (matchKategori && matchSearch) {
                    card.style.display = 'flex'; // Card uses flex column
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Tampilkan empty state jika tidak ada hasil
            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }
    </script>
</body>
</html>
