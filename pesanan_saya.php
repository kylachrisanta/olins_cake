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
$conn->query("UPDATE pesanan SET status_pesanan = 'Kedaluwarsa', status_pembayaran = 'Kedaluwarsa' WHERE id_pelanggan = $id_pelanggan AND status_pesanan = 'Menunggu Pembayaran' AND status_pembayaran = 'Belum Dibayar' AND batas_pembayaran < '$now_str'");

// Ambil Status Sukses/Notifikasi jika ada
$pesan_sukses = "";
$tipe_sukses = "checkout";
$pesan_error = "";

if (isset($_GET['status']) && $_GET['status'] === 'sukses') {
    $pesan_sukses = "Pesanan Pre-Order Anda berhasil dibuat! Admin kami akan segera memeriksa detail pesanan Anda.";
    $tipe_sukses = "checkout";
} elseif (isset($_SESSION['pesan_sukses'])) {
    $pesan_sukses = $_SESSION['pesan_sukses'];
    $tipe_sukses = "batal";
    unset($_SESSION['pesan_sukses']);
}

if (isset($_SESSION['pesan_error'])) {
    $pesan_error = $_SESSION['pesan_error'];
    unset($_SESSION['pesan_error']);
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
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
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
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link">Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Pesanan</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
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
                        <strong><?= $tipe_sukses === 'batal' ? 'Pesanan Dibatalkan' : 'Checkout Berhasil!' ?></strong>
                        <p><?= htmlspecialchars($pesan_sukses) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tampilkan Alert Error Jika Ada -->
            <?php if (!empty($pesan_error)): ?>
                <div class="orders-alert-danger" style="background-color: #fee2e2; border-left: 5px solid #ef4444; color: #991b1b; padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px; display: flex; align-items: center; gap: 14px;">
                    <div class="alert-icon-circle" style="background-color: #fca5a5; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #b91c1c;"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <div class="alert-text">
                        <strong>Terjadi Kesalahan</strong>
                        <p><?= htmlspecialchars($pesan_error) ?></p>
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
                            $wa_link = "https://wa.me/6289529236657?text=" . urlencode($wa_message);
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

                            <!-- Body Card: Rincian Kue & Status -->
                            <div class="order-card-body">

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
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openCancelModal(<?= $order['id_pesanan'] ?>, '<?= $kode_order ?>')">
                                            <i class="fa-solid fa-ban" style="margin-right: 6px;"></i> Batalkan Pesanan
                                        </button>
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
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Tambun Utara, Kabupaten Bekasi
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- Cancel Order Confirmation Modal -->
    <div id="cancelModal" class="cancel-modal">
        <div class="cancel-modal-content">
            <h4 style="color: var(--cowhide-cocoa); font-weight: 700; margin: 0; font-size: 1.15rem; width: 100%; border-bottom: 1px solid rgba(68, 45, 28, 0.08); padding-bottom: 12px; text-align: left;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #c93b2b; margin-right: 6px;"></i> Batalkan Pesanan
            </h4>
            <div style="width: 100%; text-align: left; margin: 8px 0;">
                <p style="font-weight: 700; margin-bottom: 8px; color: var(--text-main);">Apakah Anda yakin ingin membatalkan pesanan <span id="cancel-order-code" style="color: var(--spiced-wine);"></span> ini?</p>
                <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.4; margin: 0;">
                    <i class="fa-solid fa-circle-info" style="color: #c93b2b; margin-right: 4px;"></i> Pesanan yang dibatalkan tidak dapat dilanjutkan kembali.
                </p>
            </div>
            <form action="proses_batal.php" method="POST" style="width: 100%; display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; margin-bottom: 0;">
                <input type="hidden" name="id_pesanan" id="cancel-order-id" value="">
                <button type="button" onclick="closeCancelModal()" class="btn btn-outline" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 30px;">Kembali</button>
                <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 30px;">Ya, Batalkan Pesanan</button>
            </form>
        </div>
    </div>

    <!-- JavaScript Actions -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Cancel Modal Actions
        function openCancelModal(orderId, orderCode) {
            document.getElementById('cancel-order-id').value = orderId;
            document.getElementById('cancel-order-code').innerText = orderCode;
            document.getElementById('cancelModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                closeCancelModal();
            }
        });
    </script>
</body>
</html>
