<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Ambil semua produk dari database
$query = "SELECT * FROM produk ORDER BY kategori ASC, nama_produk ASC";
$result = $conn->query($query);
$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
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
                            <li><a href="index.php#testimoni" class="dropdown-menu-item">Testimoni</a></li>
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
                    <li><a href="index.php#testimoni" class="nav-link">Testimoni</a></li>
                    <li><a href="index.php#hubungi" class="nav-link">Hubungi Kami</a></li>
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

            <!-- Tab Filter Kategori -->
            <div class="category-tabs-container">
                <ul class="category-tabs" id="category-tabs">
                    <li><button class="filter-btn active" onclick="selectCategory('Semua', this)">Semua Menu</button></li>
                    <li><button class="filter-btn" onclick="selectCategory('Bolu', this)">Bolu</button></li>
                    <li><button class="filter-btn" onclick="selectCategory('Kue Kering', this)">Kue Kering</button></li>
                    <li><button class="filter-btn" onclick="selectCategory('Kue Basah', this)">Kue Basah</button></li>
                </ul>
            </div>

            <!-- Grid Produk -->
            <div class="products-grid" id="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $row): ?>
                        <div class="product-card" data-nama="<?= strtolower(htmlspecialchars($row['nama_produk'])) ?>" data-kategori="<?= htmlspecialchars($row['kategori']) ?>">
                            <div class="product-img-wrapper">
                                <span class="product-badge"><?= htmlspecialchars($row['kategori']) ?></span>
                                <img src="assets/images/<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_produk']) ?>" class="product-img">
                            </div>
                            <div class="product-info">
                                <div class="product-header">
                                    <h3 class="product-title"><?= htmlspecialchars($row['nama_produk']) ?></h3>
                                    <span class="product-price">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                                </div>
                                <p class="product-desc"><?= htmlspecialchars($row['deskripsi']) ?></p>
                                <div class="product-footer">
                                    <div class="product-rating">
                                        <i class="fa-solid fa-star"></i>
                                        <i class="fa-solid fa-star"></i>
                                        <i class="fa-solid fa-star"></i>
                                        <i class="fa-solid fa-star"></i>
                                        <i class="fa-solid fa-star"></i>
                                        <span style="color: var(--text-muted); font-size: 0.8rem; margin-left: 4px;">(5.0)</span>
                                    </div>
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
            // Set active class tab
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            element.classList.add('active');

            // Simpan kategori
            selectedCategoryState = category;
            
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
