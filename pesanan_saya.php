<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['pelanggan_id'])) {
    header("Location: masuk.php");
    exit;
}

$id_pelanggan = $_SESSION['pelanggan_id'];

// Auto-Expiration: Batalkan pesanan otomatis jika melewati batas 24 jam dan belum bayar
$now_str = date('Y-m-d H:i:s');
$conn->query("UPDATE pesanan SET status_pesanan = 'Kedaluwarsa', status_pembayaran = 'Tidak Dibayar' WHERE id_pelanggan = $id_pelanggan AND status_pesanan = 'Menunggu Pembayaran' AND status_pembayaran = 'Belum Bayar' AND batas_pembayaran < '$now_str'");

// Ambil Status Sukses/Notifikasi jika ada
$pesan_sukses = "";
if (isset($_GET['status']) && $_GET['status'] === 'sukses') {
    $pesan_sukses = "Pesanan Pre-Order Anda berhasil dibuat! Admin kami akan segera memeriksa detail pesanan Anda.";
}

// Ambil Riwayat Pesanan Pelanggan Aktif
$query = "SELECT * FROM pesanan WHERE id_pelanggan = ? ORDER BY dibuat_pada DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_pelanggan);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $id_pesanan = $row['id_pesanan'];
    
    // Ambil detail produk untuk pesanan ini
    $detail_query = "SELECT dp.id_produk, dp.jumlah, dp.harga_satuan, p.nama_produk, p.gambar, p.ukuran, p.kategori 
                     FROM detail_pesanan dp 
                     JOIN produk p ON dp.id_produk = p.id_produk 
                     WHERE dp.id_pesanan = ?";
    $stmt_d = $conn->prepare($detail_query);
    $stmt_d->bind_param("i", $id_pesanan);
    $stmt_d->execute();
    $d_result = $stmt_d->get_result();
    
    $items = [];
    while ($d_row = $d_result->fetch_assoc()) {
        $items[] = $d_row;
    }
    $stmt_d->close();
    
    $row['items'] = $items;
    $orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled">
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
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Pesanan Saya</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- My Orders Section -->
    <section class="orders-section">
        <div class="container">
            
            <div class="orders-title-area">
                <h1>Pesanan Saya</h1>
                <p>Pantau status pre-order kue lezat Anda secara berkala.</p>
            </div>

            <!-- Tampilkan Alert Sukses Jika Ada Transaksi Baru -->
            <?php if (!empty($pesan_sukses)): ?>
                <div class="orders-alert-success">
                    <div class="alert-icon-circle"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="alert-text">
                        <strong>Checkout Berhasil!</strong>
                        <p><?= htmlspecialchars($pesan_sukses) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($orders) > 0): ?>
                <div class="orders-list-wrapper">
                    
                    <?php foreach ($orders as $order): ?>
                        <?php 
                            // Penentuan warna lencana status dan icon dinamis
                            $status_class = '';
                            $status_text = htmlspecialchars($order['status_pesanan']);
                            $icon_class = 'fa-solid fa-circle-notch fa-spin-custom';
                            
                            switch ($order['status_pesanan']) {
                                case 'Menunggu Pembayaran':
                                    $status_class = 'status-waiting';
                                    $icon_class = 'fa-regular fa-clock';
                                    break;
                                case 'Menunggu Verifikasi':
                                    $status_class = 'status-verifying';
                                    $icon_class = 'fa-solid fa-spinner fa-spin';
                                    break;
                                case 'Menunggu Konfirmasi':
                                    $status_class = 'status-waiting';
                                    $icon_class = 'fa-solid fa-circle-notch fa-spin-custom';
                                    break;
                                case 'Diproses':
                                    $status_class = 'status-processing';
                                    $icon_class = 'fa-solid fa-cookie-bite';
                                    break;
                                case 'Siap Dikirim':
                                case 'Siap Diambil':
                                    $status_class = 'status-ready';
                                    $icon_class = ($order['status_pesanan'] === 'Siap Diambil') ? 'fa-solid fa-store' : 'fa-solid fa-truck-fast';
                                    break;
                                case 'Selesai':
                                    $status_class = 'status-completed';
                                    $icon_class = 'fa-solid fa-circle-check';
                                    break;
                                case 'Dibatalkan':
                                case 'Kedaluwarsa':
                                    $status_class = 'status-cancelled';
                                    $icon_class = 'fa-solid fa-circle-xmark';
                                    break;
                            }
                            
                            // Generate kode pesanan unik (contoh: OLN-10023)
                            $kode_order = "OLN-" . (10000 + $order['id_pesanan']);
                            
                            // Format Tanggal Pengantaran
                            $tgl_kirim_raw = strtotime($order['tanggal_pengiriman']);
                            $hari_indonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                            $bulan_indonesia = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            
                            $format_hari = $hari_indonesia[date('w', $tgl_kirim_raw)];
                            $format_tgl = date('j', $tgl_kirim_raw);
                            $format_bln = $bulan_indonesia[date('n', $tgl_kirim_raw)];
                            $format_thn = date('Y', $tgl_kirim_raw);
                            
                            $tgl_kirim_formatted = "$format_hari, $format_tgl $format_bln $format_thn";
                            
                            // Membuat pesan WA dinamis
                            $wa_message = "Halo Olin's Cake, saya ingin menanyakan status pesanan pre-order saya dengan nomor *" . $kode_order . "* atas nama *" . htmlspecialchars($order['nama_penerima']) . "*. Terima kasih!";
                            $wa_link = "https://wa.me/6281234567890?text=" . urlencode($wa_message);
                        ?>
                        
                        <!-- Card Order -->
                        <div class="order-card-wrapper">
                            
                            <!-- Header Card: Info Kode & Status -->
                            <div class="order-card-header">
                                <div class="order-info-left">
                                    <span class="order-code"><?= $kode_order ?></span>
                                    <span class="order-date">Tanggal Pemesanan: <?= date('d/m/Y H:i', strtotime($order['dibuat_pada'])) ?></span>
                                </div>
                                <div class="order-info-right">
                                    <span class="order-status-badge <?= $status_class ?>">
                                        <i class="<?= $icon_class ?>"></i> <?= $status_text ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Body Card: Detail Pengiriman & Jadwal -->
                            <div class="order-card-body">
                                <div class="order-body-grid">
                                    
                                    <!-- Info Pengiriman -->
                                    <div class="order-delivery-info">
                                        <h4><i class="fa-solid fa-truck"></i> Detail Penerimaan</h4>
                                        <p><strong>Penerima:</strong> <?= htmlspecialchars($order['nama_penerima']) ?></p>
                                        <p><strong>WhatsApp:</strong> <?= htmlspecialchars($order['nomor_wa']) ?></p>
                                        <p><strong>Metode:</strong> <?= htmlspecialchars($order['metode_pengiriman']) ?></p>
                                        <p class="address-paragraph"><strong>Alamat/Lokasi:</strong> <?= htmlspecialchars($order['alamat_pengiriman']) ?></p>
                                    </div>
                                    
                                    <!-- Info Jadwal -->
                                    <div class="order-schedule-info">
                                        <h4><i class="fa-solid fa-calendar-check"></i> Jadwal Pre-Order</h4>
                                        <p><strong>Tanggal Pengiriman:</strong></p>
                                        <p class="highlight-date"><i class="fa-regular fa-calendar"></i> <?= $tgl_kirim_formatted ?></p>
                                        <p><strong>Waktu Antar/Ambil:</strong></p>
                                        <p class="highlight-time"><i class="fa-regular fa-clock"></i> Jam <?= htmlspecialchars($order['waktu_pengiriman']) ?> WIB</p>
                                        
                                        <?php if (!empty($order['catatan'])): ?>
                                            <div class="order-card-notes">
                                                <strong>Catatan:</strong>
                                                <p>"<?= nl2br(htmlspecialchars($order['catatan'])) ?>"</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>

                                <!-- Rincian Kue yang Dipesan -->
                                <div class="order-items-list-area">
                                    <h5>Rincian Kue Dipesan:</h5>
                                    <div class="order-items-scrollable">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <?php 
                                                // Cek apakah tombol Beri Testimoni harus ditampilkan
                                                $show_review_btn = false;
                                                if ($order['status_pesanan'] === 'Selesai') {
                                                    $reviewed_stmt = $conn->prepare("SELECT 1 FROM testimoni WHERE id_pelanggan = ? AND id_pesanan = ? AND id_produk = ?");
                                                    $reviewed_stmt->bind_param("iii", $id_pelanggan, $order['id_pesanan'], $item['id_produk']);
                                                    $reviewed_stmt->execute();
                                                    $has_reviewed = $reviewed_stmt->get_result()->num_rows > 0;
                                                    $reviewed_stmt->close();
                                                    
                                                    if (!$has_reviewed) {
                                                        $show_review_btn = true;
                                                    }
                                                }
                                            ?>
                                            <div class="order-item-row-detail">
                                                <div class="item-img-circle">
                                                    <img src="assets/images/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                                                </div>
                                                <div class="item-name-spec">
                                                    <span class="name"><?= htmlspecialchars($item['nama_produk']) ?></span>
                                                    <span class="category"><?= htmlspecialchars($item['kategori']) ?> (<?= htmlspecialchars($item['ukuran']) ?>)</span>
                                                    <?php if ($show_review_btn): ?>
                                                        <a href="testimoni.php?id_produk=<?= $item['id_produk'] ?>&id_pesanan=<?= $order['id_pesanan'] ?>" class="btn btn-accent btn-sm" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 4px; display: inline-flex; width: fit-content; margin-top: 6px; height: auto;"><i class="fa-solid fa-star" style="margin-right: 4px; font-size: 0.7rem;"></i> Beri Testimoni</a>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="item-qty-price">
                                                    <span class="qty">Jumlah: <strong><?= $item['jumlah'] ?>x</strong></span>
                                                    <span class="price">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?> / pcs</span>
                                                </div>
                                                <div class="item-row-subtotal">
                                                    Rp <?= number_format($item['harga_satuan'] * $item['jumlah'], 0, ',', '.') ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Card: Total & Aksi -->
                            <div class="order-card-footer">
                                <div class="footer-totals">
                                    <div class="total-row">
                                        <span>Ongkos Kirim:</span>
                                        <strong>Rp <?= number_format($order['ongkos_kirim'], 0, ',', '.') ?></strong>
                                    </div>
                                    <div class="total-row main-total">
                                        <span>Total Pembayaran:</span>
                                        <span class="price">Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                                <div class="footer-actions" style="display: flex; gap: 12px; flex-wrap: wrap;">
                                    <?php if ($order['status_pesanan'] === 'Menunggu Pembayaran'): ?>
                                        <a href="pembayaran.php?id=<?= $order['id_pesanan'] ?>" class="btn btn-accent btn-sm">
                                            <i class="fa-solid fa-wallet" style="margin-right: 6px;"></i> Bayar Sekarang
                                        </a>
                                    <?php endif; ?>
                                    <a href="detail_pesanan.php?id=<?= $order['id_pesanan'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-circle-info" style="margin-right: 6px;"></i> Detail Pesanan
                                    </a>
                                    <a href="<?= $wa_link ?>" target="_blank" class="btn btn-outline btn-sm btn-wa">
                                        <i class="fa-brands fa-whatsapp" style="margin-right: 6px;"></i> Hubungi Admin
                                    </a>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            <?php else: ?>
                <!-- State Belum Ada Transaksi -->
                <div class="order-card-empty">
                    <div class="empty-icon"><i class="fa-solid fa-receipt"></i></div>
                    <h2>Belum Ada Riwayat Pesanan</h2>
                    <p>Anda belum pernah melakukan pemesanan kue pre-order di dapur Olin's Cake.</p>
                    <a href="produk.php" class="btn btn-primary">Mulai Belanja Kue</a>
                </div>
            <?php endif; ?>

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
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Tambun Utara, Kabupaten Bekasi
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
    </script>
</body>
</html>
