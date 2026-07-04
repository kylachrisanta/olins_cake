<?php
// Start Session
session_start();

// Prevent browser caching for order detail and management actions
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'pesanan';

$msg_success = "";
$msg_error = "";

// 1. UPDATE STATUS PESANAN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_status'])) {
    // Proteksi: hanya admin yang login
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }

    $id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
    $status_pesanan = isset($_POST['status_pesanan']) ? trim($_POST['status_pesanan']) : '';

    if ($id_pesanan <= 0 || empty($status_pesanan)) {
        $msg_error = "Harap isi semua kolom status.";
    } else {
        // Tentukan status_pembayaran secara otomatis berdasarkan status_pesanan yang dipilih
        $status_pembayaran = 'Belum Dibayar'; // default fallback
        if (in_array($status_pesanan, ['Diproses', 'Siap Dikirim', 'Siap Diambil', 'Selesai'])) {
            $status_pembayaran = 'Sudah Dibayar';
        } elseif ($status_pesanan === 'Kedaluwarsa') {
            $status_pembayaran = 'Kedaluwarsa';
        } elseif ($status_pesanan === 'Dibatalkan') {
            $status_pembayaran = 'Belum Dibayar';
        }
        // Ambil data pelanggan dan status lama sebelum update
        $stmt_check = $conn->prepare("SELECT status_pesanan, nama_penerima, nomor_wa, metode_pengiriman FROM pesanan WHERE id_pesanan = ?");
        $stmt_check->bind_param("i", $id_pesanan);
        $stmt_check->execute();
        $order_info = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($order_info) {
            $old_status = $order_info['status_pesanan'];
            $nama_penerima = $order_info['nama_penerima'];
            $nomor_wa = $order_info['nomor_wa'];
            $metode_pengiriman_check = $order_info['metode_pengiriman'];

            // Whitelist status berdasarkan metode pengiriman
            $allowed_admin_statuses = ['Diproses', 'Selesai'];
            if ($metode_pengiriman_check === 'Kirim ke Alamat') {
                $allowed_admin_statuses[] = 'Siap Dikirim';
            } else {
                $allowed_admin_statuses[] = 'Siap Diambil';
            }

            // Tolak jika status bukan whitelist untuk metode ini
            if (!in_array($status_pesanan, $allowed_admin_statuses)) {
                $msg_error = "Status '{$status_pesanan}' tidak sesuai dengan metode pengiriman pesanan ini.";
            }

            // Validasi tambahan: pastikan status lama bukan status otomatis
            $auto_statuses = ['Menunggu Pembayaran', 'Menunggu Verifikasi', 'Dibatalkan', 'Kedaluwarsa'];
            if (empty($msg_error) && in_array($old_status, $auto_statuses)) {
                $msg_error = "Pesanan dengan status '{$old_status}' tidak dapat diubah secara manual melalui form ini.";
            }

            if (!empty($msg_error)) {
                // error sudah di-set, tidak perlu lanjut
            } else {
                // Update database
                $stmt = $conn->prepare("UPDATE pesanan SET status_pesanan = ?, status_pembayaran = ? WHERE id_pesanan = ?");
                $stmt->bind_param("ssi", $status_pesanan, $status_pembayaran, $id_pesanan);
                if ($stmt->execute()) {
                    $msg_success = "Status pesanan berhasil diperbarui.";

                    // Kirim Notifikasi WhatsApp jika status berubah
                    if ($status_pesanan !== $old_status) {
                        require_once '../config/fonnte_helper.php';
                        $kode_order = "OLN-" . (10000 + $id_pesanan);

                        $pesan_wa = "";
                        if ($status_pesanan === 'Diproses') {
                            $pesan_wa = "Halo *{$nama_penerima}*,\n\n"
                                      . "Pesanan Anda dengan nomor *{$kode_order}* saat ini sedang *Diproses* oleh tim dapur Olin's Cake.\n\n"
                                      . "Kami akan memastikan pesanan Anda dibuat dengan bahan berkualitas terbaik. Terima kasih telah memesan di Olin's Cake! ❤️";
                        } elseif ($status_pesanan === 'Siap Dikirim') {
                            $pesan_wa = "Halo *{$nama_penerima}*,\n\n"
                                      . "Pesanan Anda dengan nomor *{$kode_order}* sudah selesai dan saat ini berstatus *Siap Dikirim*.\n\n"
                                      . "Kurir kami akan segera mengantarkan pesanan Anda ke alamat tujuan. Harap pastikan nomor WhatsApp/telepon Anda aktif agar mudah dihubungi kurir. Terima kasih! 🚚";
                        } elseif ($status_pesanan === 'Siap Diambil') {
                            $pesan_wa = "Halo *{$nama_penerima}*,\n\n"
                                      . "Pesanan Anda dengan nomor *{$kode_order}* sudah selesai dan saat ini berstatus *Siap Diambil*.\n\n"
                                      . "Silakan datang ke toko Olin's Cake untuk mengambil pesanan Anda. Kami menantikan kedatangan Anda! 🍰";
                        } elseif ($status_pesanan === 'Selesai') {
                            $pesan_wa = "Halo *{$nama_penerima}*,\n\n"
                                      . "Pesanan Anda dengan nomor *{$kode_order}* telah selesai diserahkan dan berstatus *Selesai*.\n\n"
                                      . "Terima kasih banyak telah mempercayai Olin's Cake untuk menyajikan kelezatan di momen spesial Anda. Ditunggu pesanan berikutnya ya! 🥰";
                        }

                        if (!empty($pesan_wa)) {
                            $res_wa = kirimPesanWhatsApp($nomor_wa, $pesan_wa);
                            if ($res_wa['success']) {
                                $msg_success .= " Notifikasi WhatsApp berhasil dikirim ke pelanggan.";
                            } else {
                                $msg_error = "Status berhasil diperbarui, namun gagal mengirim WhatsApp: " . $res_wa['message'];
                            }
                        }
                    }
                } else {
                    $msg_error = "Gagal memperbarui status: " . $conn->error;
                }
                $stmt->close();
            }
        } else {
            $msg_error = "Pesanan tidak ditemukan.";
        }
    }
}

// VERIFIKASI / TOLAK PEMBAYARAN (GET)
if (isset($_GET['action_payment'])) {
    // Proteksi halaman admin
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
    
    $id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $action_payment = $_GET['action_payment'];
    
    if ($id_pesanan > 0) {
        if ($action_payment === 'verify') {
            // Ambil data pelanggan dan status lama sebelum update
            $stmt_check = $conn->prepare("SELECT status_pesanan, nama_penerima, nomor_wa FROM pesanan WHERE id_pesanan = ?");
            $stmt_check->bind_param("i", $id_pesanan);
            $stmt_check->execute();
            $order_info = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();
            
            if ($order_info) {
                $old_status = $order_info['status_pesanan'];
                $nama_penerima = $order_info['nama_penerima'];
                $nomor_wa = $order_info['nomor_wa'];
                
                $stmt = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'Sudah Dibayar', status_pesanan = 'Diproses' WHERE id_pesanan = ?");
                $stmt->bind_param("i", $id_pesanan);
                if ($stmt->execute()) {
                    $msg_success = "Pembayaran untuk Pesanan OLN-" . (10000 + $id_pesanan) . " berhasil diverifikasi. Status diperbarui ke 'Diproses'.";
                    
                    // Kirim Notifikasi WhatsApp jika status berubah ke 'Diproses'
                    if ($old_status !== 'Diproses') {
                        require_once '../config/fonnte_helper.php';
                        $kode_order = "OLN-" . (10000 + $id_pesanan);
                        $pesan_wa = "Halo *{$nama_penerima}*,\n\n"
                                  . "Pesanan Anda dengan nomor *{$kode_order}* saat ini sedang *Diproses* oleh tim dapur Olin's Cake.\n\n"
                                  . "Kami akan memastikan pesanan Anda dibuat dengan bahan berkualitas terbaik. Terima kasih telah memesan di Olin's Cake! ❤️";
                        
                        $res_wa = kirimPesanWhatsApp($nomor_wa, $pesan_wa);
                        if ($res_wa['success']) {
                            $msg_success .= " Notifikasi WhatsApp berhasil dikirim ke pelanggan.";
                        } else {
                            $msg_error = "Pembayaran diverifikasi, namun gagal mengirim WhatsApp: " . $res_wa['message'];
                        }
                    }
                } else {
                    $msg_error = "Gagal memverifikasi pembayaran: " . $conn->error;
                }
                $stmt->close();
            } else {
                $msg_error = "Pesanan tidak ditemukan.";
            }
        } elseif ($action_payment === 'reject') {
            // Ambil nama file bukti lama untuk dihapus
            $res = $conn->query("SELECT bukti_pembayaran FROM pesanan WHERE id_pesanan = $id_pesanan");
            if ($res && $res->num_rows > 0) {
                $bukti_old = $res->fetch_assoc()['bukti_pembayaran'];
                
                // Hapus file
                if (!empty($bukti_old) && file_exists('../assets/uploads/bukti_pembayaran/' . $bukti_old)) {
                    unlink('../assets/uploads/bukti_pembayaran/' . $bukti_old);
                }
                
                // Reset status di database
                $stmt = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'Belum Dibayar', status_pesanan = 'Menunggu Pembayaran', bukti_pembayaran = NULL, metode_pembayaran = NULL WHERE id_pesanan = ?");
                $stmt->bind_param("i", $id_pesanan);
                if ($stmt->execute()) {
                    $msg_success = "Bukti pembayaran ditolak. Status dikembalikan ke 'Menunggu Pembayaran' agar pelanggan dapat mengunggah bukti baru.";
                } else {
                    $msg_error = "Gagal memproses penolakan: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// 2. DETAIL PESANAN VIEW (GET)
$view_order = null;
$order_items = [];
if (isset($_GET['action']) && $_GET['action'] === 'view') {
    $id_view = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_view > 0) {
        // Ambil data pesanan utama
        $stmt = $conn->prepare("SELECT p.*, pl.nama_lengkap as nama_pelanggan, pl.nama_pengguna 
                                 FROM pesanan p 
                                 JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                                 WHERE p.id_pesanan = ?");
        $stmt->bind_param("i", $id_view);
        $stmt->execute();
        $view_order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($view_order) {
            // Ambil rincian produk pesanan
            $detail_query = "SELECT dp.jumlah, dp.harga_satuan, p.nama_produk, p.gambar, p.ukuran 
                             FROM detail_pesanan dp 
                             JOIN produk p ON dp.id_produk = p.id_produk 
                             WHERE dp.id_pesanan = ?";
            $stmt_d = $conn->prepare($detail_query);
            $stmt_d->bind_param("i", $id_view);
            $stmt_d->execute();
            $d_res = $stmt_d->get_result();
            while($r = $d_res->fetch_assoc()) {
                $order_items[] = $r;
            }
            $stmt_d->close();
        }
    }
}

// 3. DAFTAR SEMUA PESANAN
$query_all = "SELECT p.*, pl.nama_lengkap as nama_pelanggan 
              FROM pesanan p 
              JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
              ORDER BY p.dibuat_pada DESC";
$list_pesanan = $conn->query($query_all);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view_order ? 'Detail Pesanan' : 'Kelola Pesanan' ?> - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=<?= time(); ?>">
    <style>
        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 992px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        .detail-row-item {
            margin-bottom: 16px;
        }
        .detail-row-item label {
            display: block;
            font-size: 0.8rem;
            color: var(--admin-text-light);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .detail-row-item span {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .detail-row-item blockquote {
            background-color: rgba(210, 179, 140, 0.05);
            border-left: 3px solid var(--admin-accent);
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-style: italic;
            margin-top: 4px;
        }
        .item-list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--admin-border);
        }
        .item-list-row:last-child {
            border-bottom: none;
        }
        .total-summary-box {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--admin-border);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-top: 16px;
            display: flex;
            flex-direction: column;
        }

        /* Modal Bukti Pembayaran Admin */
        .admin-proof-modal {
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

        .admin-proof-modal-content {
            background-color: var(--admin-card-bg);
            border-radius: var(--radius-md);
            max-width: 500px;
            width: 100%;
            padding: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--admin-border);
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

    <!-- Include Sidebar -->
    <?php require_once 'sidebar.php'; ?>

    <div class="admin-layout">
        
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-title">
                <h1><?= $view_order ? 'Detail Pesanan' : 'Kelola Pesanan' ?></h1>
                <p>Pantau detail transaksi masuk dan perbarui status pesanan serta pengiriman.</p>
            </div>
            <div class="admin-header-actions">
                <?php if ($view_order): ?>
                    <a href="pesanan.php" class="admin-btn admin-btn-secondary">
                        <i class="fa-solid fa-chevron-left"></i> Kembali ke Daftar Pesanan
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if (!empty($msg_success)): ?>
            <div class="admin-banner" style="background-color: rgba(46, 196, 182, 0.1); color: var(--admin-success); border-left: 4px solid var(--admin-success);">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($msg_success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($msg_error)): ?>
            <div class="admin-banner admin-banner-danger">
                <i class="fa-solid fa-circle-xmark"></i>
                <span><?= htmlspecialchars($msg_error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($view_order): ?>
            <!-- ================= VIEW DETAIL PESANAN ================= -->
            <?php
            $kode_order = "OLN-" . (10000 + $view_order['id_pesanan']);
            
            // Format WhatsApp Link
            $wa_message = "Halo " . htmlspecialchars($view_order['nama_penerima']) . ", ini dari Admin Olin's Cake mengenai pesanan Anda *" . $kode_order . "*.";
            $wa_link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $view_order['nomor_wa']) . "?text=" . urlencode($wa_message);
            ?>
            <div class="details-grid">
                
                <!-- Kolom Kiri: Rincian Produk, Pengiriman, dan Pembayaran -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <!-- 1. Ringkasan Pengantaran -->
                    <div class="admin-panel-card">
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-truck"></i> Informasi Pengantaran / Pengambilan</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="detail-row-item">
                                <label>Penerima</label>
                                <span><?= htmlspecialchars($view_order['nama_penerima']) ?></span>
                            </div>
                            <div class="detail-row-item">
                                <label>No. WhatsApp</label>
                                <span>
                                    <?= htmlspecialchars($view_order['nomor_wa']) ?>
                                    <a href="<?= $wa_link ?>" target="_blank" style="color: var(--admin-success); margin-left: 8px;" title="Chat WhatsApp">
                                        <i class="fa-brands fa-whatsapp"></i> Chat WhatsApp
                                    </a>
                                </span>
                            </div>
                            <div class="detail-row-item">
                                <label>Metode Pengiriman</label>
                                <span><?= htmlspecialchars($view_order['metode_pengiriman']) ?></span>
                            </div>
                            <?php if ($view_order['metode_pengiriman'] === 'Kirim ke Alamat'): ?>
                                <div class="detail-row-item">
                                    <label>Jarak Pengiriman</label>
                                    <span><?= number_format($view_order['jarak_km'], 1, ',', '.') ?> km</span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row-item" style="grid-column: 1 / -1;">
                                <label>Alamat Pengiriman</label>
                                <span><?= nl2br(htmlspecialchars($view_order['alamat_pengiriman'])) ?></span>
                            </div>
                            <?php if ($view_order['metode_pengiriman'] === 'Kirim ke Alamat' && !empty($view_order['garis_lintang']) && !empty($view_order['garis_bujur'])): ?>
                                <div class="detail-row-item">
                                    <label>Koordinat Pelanggan</label>
                                    <span>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode("{$view_order['garis_lintang']},{$view_order['garis_bujur']}") ?>" target="_blank" style="color: var(--admin-accent); font-weight: bold; text-decoration: underline;">
                                            <i class="fa-solid fa-map-location-dot" style="color: #ff4d4d;"></i> Buka di Google Maps
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row-item">
                                <label>Tanggal Pengiriman</label>
                                <span><?= date('d M Y', strtotime($view_order['tanggal_pengiriman'])) ?></span>
                            </div>
                            <div class="detail-row-item">
                                <label>Waktu Pengiriman</label>
                                <span>Jam <?= htmlspecialchars($view_order['waktu_pengiriman']) ?> WIB</span>
                            </div>
                            <?php if (!empty($view_order['catatan'])): ?>
                                <div class="detail-row-item" style="grid-column: 1 / -1;">
                                    <label>Catatan Tambahan</label>
                                    <blockquote>"<?= htmlspecialchars($view_order['catatan']) ?>"</blockquote>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Rincian Kue Dipesan -->
                    <div class="admin-panel-card">
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-basket-shopping"></i> Rincian Produk Kue</h3>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <?php foreach ($order_items as $item): ?>
                                <div class="item-list-row">
                                    <div style="display: flex; align-items: center; gap: 14px;">
                                        <img src="../assets/images/<?= htmlspecialchars($item['gambar']) ?>" alt="Kue" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                        <div>
                                            <strong style="color: var(--admin-text-main); font-size: 0.95rem;"><?= htmlspecialchars($item['nama_produk']) ?></strong>
                                            <span style="display: block; font-size: 0.75rem; color: var(--admin-text-muted);"><?= htmlspecialchars($item['ukuran']) ?></span>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="font-size: 0.85rem; color: var(--admin-text-muted); margin-right: 20px;"><?= $item['jumlah'] ?> &times; Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></span>
                                        <strong style="color: var(--admin-accent);">Rp <?= number_format($item['harga_satuan'] * $item['jumlah'], 0, ',', '.') ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="total-summary-box">
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--admin-text-muted);">
                                <span>Total Belanja</span>
                                <span>Rp <?= number_format($view_order['total_bayar'] - $view_order['ongkos_kirim'], 0, ',', '.') ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--admin-text-muted);">
                                <span>Ongkos Kirim</span>
                                <span>Rp <?= number_format($view_order['ongkos_kirim'], 0, ',', '.') ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 1.1rem; border-top: 1px solid var(--admin-border); padding-top: 8px; margin-top: 4px;">
                                <span>Total Bayar</span>
                                <span style="color: var(--admin-accent);">Rp <?= number_format($view_order['total_bayar'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Pengaturan Status & Info Pembayaran -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <!-- 3. Panel Update Status -->
                    <div class="admin-panel-card" style="height: fit-content;">
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-gears"></i> Kelola Status Pesanan</h3>
                        </div>
                        <?php
                        $auto_statuses = ['Menunggu Pembayaran', 'Menunggu Verifikasi', 'Dibatalkan', 'Kedaluwarsa'];
                        $is_auto_status = in_array($view_order['status_pesanan'], $auto_statuses);
                        ?>
                        <?php if ($is_auto_status): ?>
                            <div class="admin-form-group">
                                <label>Status Pesanan</label>
                                <div style="margin-top: 8px;">
                                    <?php
                                    $badge_class = 'admin-badge-info';
                                    if ($view_order['status_pesanan'] === 'Menunggu Pembayaran') $badge_class = 'admin-badge-waiting';
                                    elseif ($view_order['status_pesanan'] === 'Kedaluwarsa' || $view_order['status_pesanan'] === 'Dibatalkan') $badge_class = 'admin-badge-danger';
                                    ?>
                                    <span class="admin-badge <?= $badge_class ?>" style="font-size: 0.95rem; padding: 6px 12px; display: inline-block;"><?= htmlspecialchars($view_order['status_pesanan']) ?></span>
                                </div>
                                <small class="admin-text-muted" style="display: block; margin-top: 8px; font-style: italic;">
                                    Status ini dikelola otomatis oleh sistem/aksi pelanggan dan tidak dapat diubah secara manual.
                                </small>
                            </div>

                            <div class="admin-form-group" style="margin-top: 16px;">
                                <label>Status Pembayaran</label>
                                <div style="margin-top: 8px;">
                                    <?php
                                    $badge_pay_class = 'admin-badge-info';
                                    if ($view_order['status_pembayaran'] === 'Belum Dibayar') $badge_pay_class = 'admin-badge-waiting';
                                    elseif ($view_order['status_pembayaran'] === 'Sudah Dibayar') $badge_pay_class = 'admin-badge-success';
                                    elseif ($view_order['status_pembayaran'] === 'Kedaluwarsa') $badge_pay_class = 'admin-badge-danger';
                                    ?>
                                    <span class="admin-badge <?= $badge_pay_class ?>" style="font-size: 0.95rem; padding: 6px 12px; display: inline-block;"><?= htmlspecialchars($view_order['status_pembayaran']) ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <form action="pesanan.php?action=view&id=<?= $view_order['id_pesanan'] ?>" method="POST">
                                <input type="hidden" name="action_update_status" value="1">
                                <input type="hidden" name="id_pesanan" value="<?= $view_order['id_pesanan'] ?>">
                                
                                <div class="admin-form-group">
                                    <label for="status_pesanan">Status Pesanan</label>
                                    <select id="status_pesanan" name="status_pesanan" class="admin-form-control" required>
                                        <option value="Diproses" <?= $view_order['status_pesanan'] === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                                        <?php if ($view_order['metode_pengiriman'] === 'Kirim ke Alamat'): ?>
                                            <option value="Siap Dikirim" <?= $view_order['status_pesanan'] === 'Siap Dikirim' ? 'selected' : '' ?>>Siap Dikirim</option>
                                        <?php else: ?>
                                            <option value="Siap Diambil" <?= $view_order['status_pesanan'] === 'Siap Diambil' ? 'selected' : '' ?>>Siap Diambil</option>
                                        <?php endif; ?>
                                        <option value="Selesai" <?= $view_order['status_pesanan'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>

                                <div class="admin-form-group">
                                     <label>Status Pembayaran</label>
                                     <input type="text" class="admin-form-control" value="<?= htmlspecialchars($view_order['status_pembayaran']) ?>" disabled style="background-color: #f5f5f5; cursor: not-allowed;">
                                     <small class="text-muted">* Status pembayaran otomatis mengikuti status pesanan.</small>
                                 </div>
                                
                                <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fa-solid fa-check"></i> Perbarui Status
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($view_order['status_pembayaran'] === 'Sudah Dibayar'): ?>
                            <div style="margin-top: 16px; border-top: 1px dashed var(--admin-border); padding-top: 16px;">
                                <a href="../unduh_kwitansi.php?id=<?= $view_order['id_pesanan'] ?>" target="_blank" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center; gap: 8px; text-decoration: none;">
                                    <i class="fa-solid fa-file-invoice"></i> Unduh Kwitansi Pembayaran
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Detail Info Pembayaran -->
                    <div class="admin-panel-card" style="height: fit-content;">
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-wallet"></i> Bukti & Metode Pembayaran</h3>
                        </div>
                        <div class="info-details" style="gap: 12px;">
                            <div class="detail-row-item">
                                <label>Metode Pembayaran</label>
                                <span><?= !empty($view_order['metode_pembayaran']) ? htmlspecialchars($view_order['metode_pembayaran']) : 'Belum Memilih' ?></span>
                            </div>
                            <div class="detail-row-item">
                                <label>Batas Waktu</label>
                                <span style="font-size: 0.85rem; color: var(--admin-warning);"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($view_order['batas_pembayaran'])) ?> WIB</span>
                            </div>
                            <?php if (!empty($view_order['bukti_pembayaran'])): ?>
                                <div class="detail-row-item">
                                    <label>Bukti Transfer</label>
                                    <div style="margin-top: 8px;">
                                        <a href="javascript:void(0);" onclick="openAdminProofModal('../assets/uploads/bukti_pembayaran/<?= htmlspecialchars($view_order['bukti_pembayaran']) ?>')" style="display: block; width: 100%; text-align: center; border-radius: var(--radius-sm); border: 1px solid var(--admin-border); overflow: hidden;">
                                            <img src="../assets/uploads/bukti_pembayaran/<?= htmlspecialchars($view_order['bukti_pembayaran']) ?>" alt="Bukti Transfer" style="max-width: 100%; max-height: 250px; object-fit: contain; padding: 4px;">
                                            <span style="display: block; background-color: rgba(0,0,0,0.05); padding: 8px; font-size: 0.8rem; color: var(--admin-accent); font-weight: 700;">
                                                <i class="fa-solid fa-magnifying-glass"></i> Lihat Gambar Penuh
                                            </span>
                                        </a>
                                    </div>
                                    <?php if ($view_order['status_pesanan'] === 'Menunggu Verifikasi'): ?>
                                        <div style="display: flex; gap: 10px; margin-top: 12px;">
                                            <a href="pesanan.php?action=view&id=<?= $view_order['id_pesanan'] ?>&action_payment=verify" class="admin-btn admin-btn-success admin-btn-sm" style="flex: 1; justify-content: center; text-align: center;" onclick="return confirm('Konfirmasi bahwa dana transfer telah masuk?')">
                                                <i class="fa-solid fa-check"></i> Setujui
                                            </a>
                                            <a href="pesanan.php?action=view&id=<?= $view_order['id_pesanan'] ?>&action_payment=reject" class="admin-btn admin-btn-danger admin-btn-sm" style="flex: 1; justify-content: center; text-align: center;" onclick="return confirm('Tolak bukti pembayaran ini? Pelanggan harus mengunggah ulang.')">
                                                <i class="fa-solid fa-xmark"></i> Tolak
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="admin-banner admin-banner-danger" style="margin-bottom: 0; padding: 12px; font-size: 0.85rem;">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span>Belum mengunggah bukti pembayaran.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

        <?php else: ?>
            <!-- ================= DAFTAR SEMUA PESANAN ================= -->
            <div class="admin-panel-card">
                <div class="panel-card-header">
                    <h3><i class="fa-solid fa-receipt"></i> Daftar Pesanan Pre-Order</h3>
                </div>

                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>No. Order</th>
                                <th>Nama Pelanggan</th>
                                <th>Tanggal Pemesanan</th>
                                <th>Tanggal Pengiriman</th>
                                <th>Total Bayar</th>
                                <th>Status Bayar</th>
                                <th>Status Pesanan</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list_pesanan && $list_pesanan->num_rows > 0): ?>
                                <?php while($row = $list_pesanan->fetch_assoc()): ?>
                                    <?php
                                    $kode_order = "OLN-" . (10000 + $row['id_pesanan']);
                                    $status_pesanan = $row['status_pesanan'];
                                    $status_pembayaran = $row['status_pembayaran'];
                                    
                                    // Klasifikasi badge status pesanan
                                    $badge_pesanan = 'admin-badge-info';
                                    if ($status_pesanan === 'Menunggu Pembayaran') $badge_pesanan = 'admin-badge-waiting';
                                    elseif ($status_pesanan === 'Selesai') $badge_pesanan = 'admin-badge-success';
                                    elseif ($status_pesanan === 'Kedaluwarsa' || $status_pesanan === 'Dibatalkan') $badge_pesanan = 'admin-badge-danger';
                                    
                                    // Klasifikasi badge status pembayaran
                                    $badge_bayar = 'admin-badge-info';
                                    if ($status_pembayaran === 'Belum Dibayar') $badge_bayar = 'admin-badge-waiting';
                                    elseif ($status_pembayaran === 'Sudah Dibayar') $badge_bayar = 'admin-badge-success';
                                    elseif ($status_pembayaran === 'Kedaluwarsa') $badge_bayar = 'admin-badge-danger';
                                    ?>
                                    <tr>
                                        <td><strong><?= $kode_order ?></strong></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['dibuat_pada'])) ?> WIB</td>
                                        <td>
                                            <span style="font-size: 0.85rem; font-weight: 600; display: block;"><?= date('d M Y', strtotime($row['tanggal_pengiriman'])) ?></span>
                                            <span style="font-size: 0.75rem; color: var(--admin-text-muted);">Jam <?= htmlspecialchars($row['waktu_pengiriman']) ?> WIB</span>
                                        </td>
                                        <td><strong>Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?></strong></td>
                                        <td><span class="admin-badge <?= $badge_bayar ?>"><?= htmlspecialchars($status_pembayaran) ?></span></td>
                                        <td><span class="admin-badge <?= $badge_pesanan ?>"><?= htmlspecialchars($status_pesanan) ?></span></td>
                                         <td style="text-align: right; white-space: nowrap;">
                                             <?php if ($status_pembayaran === 'Sudah Dibayar'): ?>
                                                 <a href="../unduh_kwitansi.php?id=<?= $row['id_pesanan'] ?>" target="_blank" class="admin-btn admin-btn-sm" style="background-color: var(--admin-accent); color: white; border-color: var(--admin-accent); display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="Unduh Kwitansi">
                                                     <i class="fa-solid fa-file-invoice"></i> Kwitansi
                                                 </a>
                                             <?php endif; ?>
                                             <a href="pesanan.php?action=view&id=<?= $row['id_pesanan'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Kelola" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                                 <i class="fa-solid fa-gears"></i> Kelola
                                             </a>
                                         </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--admin-text-light); padding: 40px 0;">Belum ada pesanan masuk.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function openAdminProofModal(imgSrc) {
            document.getElementById('adminProofImg').src = imgSrc;
            document.getElementById('adminProofModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAdminProofModal() {
            document.getElementById('adminProofModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('adminProofModal');
            if (event.target === modal) {
                closeAdminProofModal();
            }
        });
    </script>

    <!-- Modal Bukti Pembayaran Admin -->
    <div id="adminProofModal" class="admin-proof-modal">
        <div class="admin-proof-modal-content">
            <h4 style="color: var(--admin-text-main); font-weight: 700; margin: 0; font-size: 1.15rem; width: 100%; border-bottom: 1px solid var(--admin-border); padding-bottom: 12px; text-align: left;">
                <i class="fa-solid fa-receipt" style="color: var(--admin-accent); margin-right: 6px;"></i> Bukti Transfer Pembayaran
            </h4>
            <img id="adminProofImg" src="" alt="Bukti Transfer" style="max-width: 100%; max-height: 400px; object-fit: contain; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
            <div style="width: 100%; display: flex; justify-content: center; margin-top: 8px;">
                <button onclick="closeAdminProofModal()" class="admin-btn admin-btn-secondary" style="min-width: 120px; justify-content: center; cursor: pointer;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>
    </div>
</body>
</html>
