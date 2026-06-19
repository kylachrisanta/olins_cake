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

// Ambil ID Pesanan dari URL
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_pesanan <= 0) {
    header("Location: pesanan_saya.php");
    exit;
}

// Ambil detail pesanan dan pastikan milik pelanggan aktif
$query = "SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id_pesanan, $id_pelanggan);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    // Pesanan tidak ditemukan atau bukan milik user
    header("Location: pesanan_saya.php");
    exit;
}

// Inisialisasi variabel status
$status_pesanan = $order['status_pesanan'];
$status_pembayaran = $order['status_pembayaran'];
$batas_pembayaran = $order['batas_pembayaran'];

// Logika Auto-Expiration: Cek apakah batas waktu pembayaran sudah lewat
$is_expired = false;
if ($status_pembayaran === 'Belum Bayar' && $status_pesanan === 'Menunggu Pembayaran') {
    $now_ts = time();
    $batas_ts = strtotime($batas_pembayaran);
    
    if ($now_ts > $batas_ts) {
        $is_expired = true;
        // Update status di database secara otomatis
        $up_stmt = $conn->prepare("UPDATE pesanan SET status_pesanan = 'Kedaluwarsa', status_pembayaran = 'Tidak Dibayar' WHERE id_pesanan = ?");
        $up_stmt->bind_param("i", $id_pesanan);
        $up_stmt->execute();
        $up_stmt->close();
        
        $status_pesanan = 'Kedaluwarsa';
        $status_pembayaran = 'Tidak Dibayar';
    }
}

if ($status_pesanan === 'Kedaluwarsa' || $status_pesanan === 'Dibatalkan' || $status_pembayaran === 'Tidak Dibayar') {
    $is_expired = true;
}

// Ambil rincian produk pesanan
$detail_query = "SELECT dp.id_produk, dp.jumlah, dp.harga_satuan, p.nama_produk, p.gambar, p.ukuran, p.kategori 
                 FROM detail_pesanan dp 
                 JOIN produk p ON dp.id_produk = p.id_produk 
                 WHERE dp.id_pesanan = ?";
$stmt_d = $conn->prepare($detail_query);
$stmt_d->bind_param("i", $id_pesanan);
$stmt_d->execute();
$d_result = $stmt_d->get_result();
$order_items = [];
while ($row = $d_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt_d->close();

// Generate kode order
$kode_order = "OLN-" . (10000 + $order['id_pesanan']);

// Logic Timeline
$failed_state = ($status_pesanan === 'Kedaluwarsa' || $status_pesanan === 'Dibatalkan');

// Normal timeline configuration
$normal_steps = [
    ['label' => 'Menunggu Pembayaran', 'desc' => 'Menunggu transfer dari Anda', 'icon' => 'fa-wallet'],
    ['label' => 'Verifikasi Pembayaran', 'desc' => 'Pengecekan bukti oleh admin', 'icon' => 'fa-receipt'],
    ['label' => 'Diproses', 'desc' => 'Kue sedang dibuat fresh', 'icon' => 'fa-cookie-bite'],
    ['label' => 'Dikirim / Siap Ambil', 'desc' => 'Pesanan dalam perjalanan / siap', 'icon' => 'fa-truck-fast'],
    ['label' => 'Selesai', 'desc' => 'Pesanan sukses diterima', 'icon' => 'fa-circle-check']
];

// Determine current step index
$current_step_index = 0;
if ($status_pesanan === 'Menunggu Pembayaran') {
    $current_step_index = 0;
} elseif ($status_pesanan === 'Menunggu Verifikasi' || $status_pesanan === 'Menunggu Konfirmasi') {
    $current_step_index = 1;
} elseif ($status_pesanan === 'Diproses') {
    $current_step_index = 2;
} elseif ($status_pesanan === 'Siap Dikirim' || $status_pesanan === 'Dikirim') {
    $current_step_index = 3;
} elseif ($status_pesanan === 'Selesai') {
    $current_step_index = 4;
}

// Generate WhatsApp Link
$wa_message = "Halo Olin's Cake, saya ingin menanyakan status pesanan saya dengan nomor *" . $kode_order . "* atas nama *" . htmlspecialchars($order['nama_penerima']) . "*. Terima kasih!";
$wa_link = "https://wa.me/6281234567890?text=" . urlencode($wa_message);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan <?= $kode_order ?> - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Custom Styling for Detail Pesanan Page */
        .detail-section {
            padding: 120px 0 80px 0;
            background-color: var(--warm-bg);
            min-height: 100vh;
        }

        .back-btn-area {
            margin-bottom: 24px;
        }

        .back-btn-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--spiced-wine);
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease;
        }

        .back-btn-link:hover {
            transform: translateX(-4px);
            color: var(--cowhide-cocoa);
        }

        .detail-header-card {
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(68, 45, 28, 0.05);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .detail-header-info h1 {
            font-size: 1.75rem;
            margin-bottom: 6px;
        }

        .detail-header-info p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        /* Status Colors mapping */
        .badge-waiting { background-color: #fef3c7; color: #d97706; }
        .badge-verifying { background-color: #e0f2fe; color: #0284c7; }
        .badge-processing { background-color: #fae8ff; color: #a21caf; }
        .badge-ready { background-color: #dcfce7; color: #15803d; }
        .badge-completed { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-cancelled { background-color: #fee2e2; color: #b91c1c; }

        /* Timeline Tracker Styles */
        .timeline-card {
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 32px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(68, 45, 28, 0.05);
            margin-bottom: 24px;
        }

        .timeline-title {
            font-size: 1.15rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        .timeline-wrapper {
            position: relative;
            margin: 20px 0;
        }

        /* Desktop Horizontal Timeline */
        .timeline-steps {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
        }

        .timeline-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 50px;
            right: 50px;
            height: 4px;
            background-color: #e9e5de;
            z-index: 1;
        }

        .timeline-progress-bar {
            position: absolute;
            top: 24px;
            left: 50px;
            height: 4px;
            background-color: var(--olive-harvest);
            z-index: 2;
            transition: width 0.4s ease;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 3;
            flex: 1;
        }

        .timeline-icon-box {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--white);
            border: 3px solid #e9e5de;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--text-light);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }

        .timeline-step.completed .timeline-icon-box {
            border-color: var(--olive-harvest);
            background-color: var(--olive-harvest);
            color: var(--white);
        }

        .timeline-step.active .timeline-icon-box {
            border-color: var(--spiced-wine);
            background-color: var(--white);
            color: var(--spiced-wine);
            box-shadow: 0 0 15px rgba(116, 48, 20, 0.2);
            transform: scale(1.1);
        }

        .timeline-step.failed .timeline-icon-box {
            border-color: #c93b2b;
            background-color: #c93b2b;
            color: var(--white);
        }

        .timeline-step-label {
            font-weight: 700;
            color: var(--cowhide-cocoa);
            margin-top: 14px;
            font-size: 0.95rem;
            line-height: 1.3;
        }

        .timeline-step-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
            padding: 0 8px;
        }

        /* Two-Column Grid Info */
        .info-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .info-card {
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(68, 45, 28, 0.05);
            height: 100%;
        }

        .info-card-header {
            border-bottom: 1px solid rgba(68, 45, 28, 0.05);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card-header h3 {
            font-size: 1.15rem;
            color: var(--cowhide-cocoa);
        }

        .info-card-header i {
            color: var(--spiced-wine);
            font-size: 1.1rem;
        }

        .info-details {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-item-label {
            color: var(--text-light);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-item-value {
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .info-item-value.highlight-price {
            color: var(--spiced-wine);
            font-size: 1.2rem;
            font-weight: 800;
        }

        /* Produk List Card */
        .products-card {
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(68, 45, 28, 0.05);
            margin-bottom: 24px;
        }

        .products-table-header {
            font-weight: 700;
            color: var(--cowhide-cocoa);
            border-bottom: 2px solid rgba(68, 45, 28, 0.08);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .product-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid rgba(68, 45, 28, 0.05);
        }

        .product-item-row:last-child {
            border-bottom: none;
        }

        .product-meta-col {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 2;
        }

        .product-img-thumb {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid rgba(68, 45, 28, 0.08);
        }

        .product-text-details {
            display: flex;
            flex-direction: column;
        }

        .product-name-title {
            font-weight: 700;
            color: var(--text-main);
            font-size: 1rem;
        }

        .product-spec-badge {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .product-qty-col {
            flex: 1;
            text-align: center;
            color: var(--text-main);
            font-weight: 600;
        }

        .product-price-col {
            flex: 1;
            text-align: right;
            font-weight: 700;
            color: var(--text-main);
        }

        .product-subtotal-col {
            flex: 1;
            text-align: right;
            font-weight: 700;
            color: var(--spiced-wine);
        }

        /* Order Totals Breakdown */
        .order-totals-card {
            background-color: var(--cream-light);
            border-radius: var(--radius-sm);
            padding: 20px;
            border: 1px solid rgba(157, 145, 103, 0.15);
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .total-breakdown-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .total-breakdown-row.grand-total {
            border-top: 1px solid rgba(68, 45, 28, 0.08);
            padding-top: 12px;
            font-weight: 800;
            color: var(--cowhide-cocoa);
            font-size: 1.15rem;
        }

        .total-breakdown-row.grand-total .grand-price {
            color: var(--spiced-wine);
            font-size: 1.3rem;
        }

        /* Expiration Banner */
        .alert-banner-detail {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border-left: 5px solid transparent;
        }

        .alert-banner-expired {
            background-color: #fee2e2;
            border-left-color: #ef4444;
            color: #991b1b;
        }

        .alert-banner-expired i {
            color: #ef4444;
            font-size: 1.4rem;
        }

        .alert-banner-warning {
            background-color: #fffbeb;
            border-left-color: #f59e0b;
            color: #92400e;
        }

        .alert-banner-warning i {
            color: #f59e0b;
            font-size: 1.4rem;
        }

        .alert-banner-success {
            background-color: #f0fdf4;
            border-left-color: #22c55e;
            color: #166534;
        }

        .alert-banner-success i {
            color: #22c55e;
            font-size: 1.4rem;
        }

        /* Action Buttons Area */
        .detail-actions-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 32px;
        }

        .right-actions {
            display: flex;
            gap: 12px;
        }

        /* Bank / QRIS detail inside payment card */
        .payment-method-box {
            background-color: var(--warm-bg);
            border-radius: var(--radius-sm);
            padding: 14px;
            border: 1px dashed rgba(68, 45, 28, 0.15);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-method-box i {
            font-size: 1.4rem;
            color: var(--spiced-wine);
        }

        .payment-proof-badge-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--spiced-wine);
            font-weight: 700;
            text-decoration: underline;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .payment-proof-badge-link:hover {
            color: var(--cowhide-cocoa);
        }

        /* CSS spin keyframe for loading status */
        .fa-spin-custom {
            animation: fa-spin 2s infinite linear;
        }

        /* Responsive Mobile Layout (max-width: 768px) */
        @media (max-width: 768px) {
            .info-cards-grid {
                grid-template-columns: 1fr;
            }

            .product-item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                position: relative;
            }

            .product-meta-col {
                width: 100%;
            }

            .product-qty-col {
                text-align: left;
                width: 100%;
                font-size: 0.9rem;
                padding-left: 80px; /* aligns with text details */
            }

            .product-price-col {
                text-align: left;
                width: 100%;
                font-size: 0.9rem;
                padding-left: 80px;
            }

            .product-subtotal-col {
                text-align: right;
                width: 100%;
                font-size: 1rem;
                border-top: 1px solid rgba(68, 45, 28, 0.03);
                padding-top: 8px;
            }

            /* Vertical Timeline */
            .timeline-steps {
                flex-direction: column;
                align-items: flex-start;
                padding-left: 10px;
            }

            .timeline-steps::before {
                top: 25px;
                left: 32px;
                bottom: 25px;
                width: 4px;
                height: calc(100% - 50px);
            }

            .timeline-progress-bar {
                left: 32px;
                width: 4px;
                height: 0; /* will be overridden dynamically via inline styles */
            }

            .timeline-step {
                flex-direction: row;
                text-align: left;
                align-items: center;
                width: 100%;
                margin-bottom: 24px;
            }

            .timeline-step:last-child {
                margin-bottom: 0;
            }

            .timeline-icon-box {
                width: 44px;
                height: 44px;
                font-size: 1.1rem;
                flex-shrink: 0;
            }

            .timeline-step-label {
                margin-top: 0;
                margin-left: 16px;
                font-size: 0.95rem;
            }

            .timeline-step-desc {
                margin-top: 2px;
                margin-left: 16px;
                padding: 0;
                font-size: 0.8rem;
            }
            
            .timeline-step-text-container {
                display: flex;
                flex-direction: column;
            }
        }

        /* Modal Bukti Pembayaran */
        .proof-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.75);
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }

        .proof-modal-content {
            background-color: var(--white);
            border-radius: var(--radius-md);
            max-width: 500px;
            width: 100%;
            padding: 24px;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
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
                        <li><a href="testimoni.php" class="dropdown-menu-item">Testimoni</a></li>
                        <li><a href="index.php#hubungi" class="dropdown-menu-item">Hubungi Kami</a></li>
                    </ul>
                </li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Detail Section -->
    <section class="detail-section">
        <div class="container detail-container">
            
            <!-- Back Button Link -->
            <div class="back-btn-area">
                <a href="pesanan_saya.php" class="back-btn-link">
                    <i class="fa-solid fa-chevron-left"></i> Kembali ke Riwayat Pesanan
                </a>
            </div>

            <!-- Expiration / Status Alert Banners -->
            <?php if ($status_pesanan === 'Kedaluwarsa'): ?>
                <div class="alert-banner-detail alert-banner-expired">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <div>
                        <strong>Pesanan Kedaluwarsa</strong>
                        <p>Batas waktu pembayaran 1×24 jam telah berakhir. Pesanan ini otomatis dibatalkan oleh sistem.</p>
                    </div>
                </div>
            <?php elseif ($status_pesanan === 'Dibatalkan'): ?>
                <div class="alert-banner-detail alert-banner-expired">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <div>
                        <strong>Pesanan Dibatalkan</strong>
                        <p>Pesanan ini telah dibatalkan atas permintaan Anda atau kebijakan toko.</p>
                    </div>
                </div>
            <?php elseif ($status_pesanan === 'Menunggu Pembayaran'): ?>
                <div class="alert-banner-detail alert-banner-warning">
                    <i class="fa-regular fa-clock"></i>
                    <div>
                        <strong>Menunggu Pembayaran</strong>
                        <p>Segera lakukan pembayaran sebelum batas waktu berakhir pada <strong><?= date('d M Y, H:i', strtotime($batas_pembayaran)) ?> WIB</strong>.</p>
                    </div>
                </div>
            <?php elseif ($status_pesanan === 'Menunggu Verifikasi'): ?>
                <div class="alert-banner-detail alert-banner-warning">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <div>
                        <strong>Bukti Pembayaran Sedang Diverifikasi</strong>
                        <p>Terima kasih. Admin kami sedang meninjau bukti pembayaran yang Anda kirimkan.</p>
                    </div>
                </div>
            <?php elseif ($status_pesanan === 'Selesai'): ?>
                <div class="alert-banner-detail alert-banner-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Pesanan Selesai</strong>
                        <p>Kue lezat Anda telah berhasil diterima. Terima kasih telah memesan di Olin's Cake!</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Header Card Detail -->
            <div class="detail-header-card">
                <div class="detail-header-info">
                    <h1>Detail Pesanan <?= $kode_order ?></h1>
                    <p>Dibuat pada: <strong><?= date('d M Y, H:i', strtotime($order['dibuat_pada'])) ?> WIB</strong></p>
                </div>
                <div>
                    <?php
                    $status_badge_class = '';
                    switch ($status_pesanan) {
                        case 'Menunggu Pembayaran': $status_badge_class = 'badge-waiting'; break;
                        case 'Menunggu Verifikasi':
                        case 'Menunggu Konfirmasi': $status_badge_class = 'badge-verifying'; break;
                        case 'Diproses': $status_badge_class = 'badge-processing'; break;
                        case 'Siap Dikirim':
                        case 'Dikirim': $status_badge_class = 'badge-ready'; break;
                        case 'Selesai': $status_badge_class = 'badge-completed'; break;
                        case 'Dibatalkan':
                        case 'Kedaluwarsa': $status_badge_class = 'badge-cancelled'; break;
                    }
                    ?>
                    <span class="badge-status <?= $status_badge_class ?>">
                        <?php if ($status_pesanan === 'Menunggu Pembayaran'): ?>
                            <i class="fa-regular fa-clock"></i>
                        <?php elseif ($status_pesanan === 'Menunggu Verifikasi'): ?>
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        <?php elseif ($status_pesanan === 'Diproses'): ?>
                            <i class="fa-solid fa-cookie-bite"></i>
                        <?php elseif ($status_pesanan === 'Siap Dikirim' || $status_pesanan === 'Dikirim'): ?>
                            <i class="fa-solid fa-truck-fast"></i>
                        <?php elseif ($status_pesanan === 'Selesai'): ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-xmark"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($status_pesanan) ?>
                    </span>
                </div>
            </div>

            <!-- Status Timeline Tracker Card -->
            <div class="timeline-card">
                <div class="timeline-title">
                    <i class="fa-solid fa-map-location-dot" style="color: var(--spiced-wine);"></i>
                    Status Alur Pesanan
                </div>
                
                <div class="timeline-wrapper">
                    <?php if ($failed_state): ?>
                        <!-- Failed Timeline Layout -->
                        <div class="timeline-steps" style="justify-content: space-around;">
                            <!-- Step 1: Menunggu Pembayaran (Failed) -->
                            <div class="timeline-step completed failed">
                                <div class="timeline-icon-box">
                                    <i class="fa-solid fa-wallet"></i>
                                </div>
                                <div class="timeline-step-text-container">
                                    <span class="timeline-step-label">Menunggu Pembayaran</span>
                                    <span class="timeline-step-desc">Tidak Terbayar</span>
                                </div>
                            </div>
                            
                            <!-- Connecting Line -->
                            <div class="timeline-progress-bar" style="width: 100%; background-color: #c93b2b;"></div>
                            
                            <!-- Step 2: Failed State -->
                            <div class="timeline-step completed failed">
                                <div class="timeline-icon-box">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </div>
                                <div class="timeline-step-text-container">
                                    <span class="timeline-step-label"><?= htmlspecialchars($status_pesanan) ?></span>
                                    <span class="timeline-step-desc">Transaksi Dibatalkan</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Normal Process Timeline Layout -->
                        <?php
                        // Calculate percentage progress line for horizontal view
                        $desktop_progress_percent = $current_step_index * 25;
                        // Calculate percentage progress height for vertical mobile view
                        $mobile_progress_percent = $current_step_index * 25;
                        ?>
                        <div class="timeline-steps">
                            <!-- Progress Lines -->
                            <div class="timeline-progress-bar" id="desktop-progress" style="width: <?= $desktop_progress_percent ?>%;"></div>
                            <div class="timeline-progress-bar" id="mobile-progress" style="height: <?= $mobile_progress_percent ?>%; display: none;"></div>

                            <?php foreach ($normal_steps as $idx => $step): ?>
                                <?php
                                $step_class = '';
                                if ($idx < $current_step_index) {
                                    $step_class = 'completed';
                                } elseif ($idx === $current_step_index) {
                                    $step_class = 'active';
                                }
                                ?>
                                <div class="timeline-step <?= $step_class ?>">
                                    <div class="timeline-icon-box">
                                        <?php if ($idx < $current_step_index): ?>
                                            <i class="fa-solid fa-check"></i>
                                        <?php else: ?>
                                            <i class="fa-solid <?= $step['icon'] ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-step-text-container">
                                        <span class="timeline-step-label"><?= $step['label'] ?></span>
                                        <span class="timeline-step-desc"><?= $step['desc'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Two Column Info Grid (Pengiriman & Pembayaran) -->
            <div class="info-cards-grid">
                
                <!-- 1. Informasi Pengiriman -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fa-solid fa-truck"></i>
                        <h3>Informasi Pengantaran / Pengambilan</h3>
                    </div>
                    <div class="info-details">
                        <div class="info-item">
                            <span class="info-item-label">Nama Penerima</span>
                            <span class="info-item-value"><?= htmlspecialchars($order['nama_penerima']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-item-label">Nomor WhatsApp</span>
                            <span class="info-item-value"><?= htmlspecialchars($order['nomor_wa']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-item-label">Metode Pengiriman</span>
                            <span class="info-item-value"><?= htmlspecialchars($order['metode_pengiriman']) ?></span>
                        </div>
                        <?php if ($order['metode_pengiriman'] === 'Kirim ke Alamat'): ?>
                            <div class="info-item">
                                <span class="info-item-label">Jarak Pengantaran</span>
                                <span class="info-item-value"><?= number_format($order['jarak_km'], 1, ',', '.') ?> km</span>
                            </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-item-label">Alamat Lengkap</span>
                            <span class="info-item-value"><?= nl2br(htmlspecialchars($order['alamat_pengiriman'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-item-label">Estimasi Waktu</span>
                            <?php
                            $tgl_kirim_raw = strtotime($order['tanggal_pengiriman']);
                            $hari_indonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                            $bulan_indonesia = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            
                            $format_hari = $hari_indonesia[date('w', $tgl_kirim_raw)];
                            $format_tgl = date('j', $tgl_kirim_raw);
                            $format_bln = $bulan_indonesia[date('n', $tgl_kirim_raw)];
                            $format_thn = date('Y', $tgl_kirim_raw);
                            
                            $tgl_kirim_formatted = "$format_hari, $format_tgl $format_bln $format_thn";
                            ?>
                            <span class="info-item-value">
                                <?= $tgl_kirim_formatted ?> (Jam <?= htmlspecialchars($order['waktu_pengiriman']) ?> WIB)
                            </span>
                        </div>
                        <?php if (!empty($order['catatan'])): ?>
                            <div class="info-item">
                                <span class="info-item-label">Catatan Tambahan</span>
                                <span class="info-item-value" style="font-weight: 400; font-style: italic;">
                                    "<?= htmlspecialchars($order['catatan']) ?>"
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Informasi Pembayaran -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fa-solid fa-credit-card"></i>
                        <h3>Informasi Status Pembayaran</h3>
                    </div>
                    <div class="info-details">
                        <div class="info-item">
                            <span class="info-item-label">Metode Pembayaran</span>
                            <span class="info-item-value">
                                <?= !empty($order['metode_pembayaran']) ? htmlspecialchars($order['metode_pembayaran']) : 'Belum Memilih' ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-item-label">Status Verifikasi</span>
                            <span class="info-item-value"><?= htmlspecialchars($order['status_pembayaran']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-item-label">Total yang Harus Dibayar</span>
                            <span class="info-item-value highlight-price">Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></span>
                        </div>
                        
                        <?php if (!empty($order['metode_pembayaran'])): ?>
                            <div class="info-item" style="margin-top: 8px;">
                                <span class="info-item-label">Detail Akun Tujuan</span>
                                <?php if ($order['metode_pembayaran'] === 'Transfer Bank'): ?>
                                    <div class="payment-method-box">
                                        <i class="fa-solid fa-building-columns"></i>
                                        <div>
                                            <strong>Bank BCA - Rekening: 1234567890</strong><br>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);">a.n. Olin's Cake</span>
                                        </div>
                                    </div>
                                <?php elseif ($order['metode_pembayaran'] === 'QRIS'): ?>
                                    <div class="payment-method-box">
                                        <i class="fa-solid fa-qrcode"></i>
                                        <div>
                                            <strong>QRIS E-Wallet Olin's Cake</strong><br>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);">Scan via GoPay/OVO/Dana</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['bukti_pembayaran'])): ?>
                            <div class="info-item" style="margin-top: 10px;">
                                <span class="info-item-label">Bukti Pembayaran Anda</span>
                                <a href="javascript:void(0);" onclick="openProofModal('assets/uploads/bukti_pembayaran/<?= htmlspecialchars($order['bukti_pembayaran']) ?>')" class="payment-proof-badge-link">
                                    <i class="fa-solid fa-image"></i> Lihat Bukti Terkirim
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rincian Produk Belanjaan -->
            <div class="products-card">
                <div class="info-card-header" style="border-bottom: none; margin-bottom: 0;">
                    <i class="fa-solid fa-basket-shopping"></i>
                    <h3>Daftar Produk yang Dipesan</h3>
                </div>
                
                <!-- Table Headers for Desktop -->
                <div class="products-table-header" style="display: flex;">
                    <div style="flex: 2;">Produk</div>
                    <div style="flex: 1; text-align: center;">Jumlah</div>
                    <div style="flex: 1; text-align: right;">Harga Satuan</div>
                    <div style="flex: 1; text-align: right;">Subtotal</div>
                </div>

                <!-- Product Items Rows -->
                <?php foreach ($order_items as $item): ?>
                    <?php 
                        // Cek apakah tombol Beri Testimoni harus ditampilkan
                        $show_review_btn = false;
                        if ($status_pesanan === 'Selesai') {
                            $reviewed_stmt = $conn->prepare("SELECT 1 FROM testimoni WHERE id_pelanggan = ? AND id_pesanan = ? AND id_produk = ?");
                            $reviewed_stmt->bind_param("iii", $id_pelanggan, $id_pesanan, $item['id_produk']);
                            $reviewed_stmt->execute();
                            $has_reviewed = $reviewed_stmt->get_result()->num_rows > 0;
                            $reviewed_stmt->close();
                            
                            if (!$has_reviewed) {
                                $show_review_btn = true;
                            }
                        }
                    ?>
                    <div class="product-item-row">
                        <div class="product-meta-col">
                            <img src="assets/images/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" class="product-img-thumb">
                            <div class="product-text-details">
                                <span class="product-name-title"><?= htmlspecialchars($item['nama_produk']) ?></span>
                                <span class="product-spec-badge"><?= htmlspecialchars($item['kategori']) ?> &bull; <?= htmlspecialchars($item['ukuran']) ?></span>
                                <?php if ($show_review_btn): ?>
                                    <a href="testimoni.php?id_produk=<?= $item['id_produk'] ?>&id_pesanan=<?= $id_pesanan ?>" class="btn btn-accent btn-sm" style="padding: 4px 10px; font-size: 0.75rem; border-radius: 4px; display: inline-flex; width: fit-content; margin-top: 6px; height: auto;"><i class="fa-solid fa-star" style="margin-right: 4px; font-size: 0.75rem;"></i> Beri Testimoni</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="product-qty-col">
                            <?= $item['jumlah'] ?> pcs
                        </div>
                        <div class="product-price-col">
                            Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?>
                        </div>
                        <div class="product-subtotal-col">
                            Rp <?= number_format($item['harga_satuan'] * $item['jumlah'], 0, ',', '.') ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Totals Breakdown area -->
                <div class="order-totals-card">
                    <div class="total-breakdown-row">
                        <span>Total Produk</span>
                        <span>Rp <?= number_format($order['total_bayar'] - $order['ongkos_kirim'], 0, ',', '.') ?></span>
                    </div>
                    <div class="total-breakdown-row">
                        <span>Ongkos Kirim</span>
                        <span>Rp <?= number_format($order['ongkos_kirim'], 0, ',', '.') ?></span>
                    </div>
                    <div class="total-breakdown-row grand-total">
                        <span>Total Pembayaran</span>
                        <span class="grand-price">Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Area Buttons -->
            <div class="detail-actions-area">
                <a href="pesanan_saya.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Riwayat Pesanan
                </a>
                
                <div class="right-actions">
                    <?php if ($status_pesanan === 'Menunggu Pembayaran'): ?>
                        <a href="pembayaran.php?id=<?= $id_pesanan ?>" class="btn btn-accent">
                            <i class="fa-solid fa-wallet" style="margin-right: 8px;"></i> Bayar Sekarang
                        </a>
                    <?php endif; ?>
                    <a href="<?= $wa_link ?>" target="_blank" class="btn btn-primary">
                        <i class="fa-brands fa-whatsapp" style="margin-right: 8px;"></i> Hubungi Admin
                    </a>
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

        // Adjust mobile progress bar height on load and resize
        function adjustMobileProgress() {
            const isMobile = window.innerWidth <= 768;
            const desktopBar = document.getElementById('desktop-progress');
            const mobileBar = document.getElementById('mobile-progress');
            
            if (desktopBar && mobileBar) {
                if (isMobile) {
                    desktopBar.style.display = 'none';
                    mobileBar.style.display = 'block';
                } else {
                    desktopBar.style.display = 'block';
                    mobileBar.style.display = 'none';
                }
            }
        }
        
        window.addEventListener('resize', adjustMobileProgress);
        window.addEventListener('DOMContentLoaded', adjustMobileProgress);

        // Modal Bukti Pembayaran Actions
        function openProofModal(imgSrc) {
            document.getElementById('proofImg').src = imgSrc;
            document.getElementById('proofModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeProofModal() {
            document.getElementById('proofModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('proofModal');
            if (event.target === modal) {
                closeProofModal();
            }
        });
    </script>

    <!-- Modal Bukti Pembayaran -->
    <div id="proofModal" class="proof-modal">
        <div class="proof-modal-content">
            <h4 style="color: var(--cowhide-cocoa); font-weight: 700; margin: 0; font-size: 1.15rem; width: 100%; border-bottom: 1px solid rgba(68, 45, 28, 0.08); padding-bottom: 12px; text-align: left;">
                <i class="fa-solid fa-receipt" style="color: var(--spiced-wine); margin-right: 6px;"></i> Bukti Pembayaran Terkirim
            </h4>
            <img id="proofImg" src="" alt="Bukti Pembayaran" style="max-width: 100%; max-height: 400px; object-fit: contain; border-radius: var(--radius-sm); border: 1px solid rgba(68, 45, 28, 0.08);">
            <div style="width: 100%; display: flex; justify-content: center; margin-top: 8px;">
                <button onclick="closeProofModal()" class="btn btn-outline" style="min-width: 120px; justify-content: center; display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>
    </div>
</body>
</html>
