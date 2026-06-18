<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'pembayaran';

$msg_success = "";
$msg_error = "";

// 1. VERIFIKASI PEMBAYARAN (APPROVE)
if (isset($_GET['action']) && $_GET['action'] === 'verify') {
    $id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_pesanan > 0) {
        $stmt = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'Sudah Bayar', status_pesanan = 'Diproses' WHERE id_pesanan = ?");
        $stmt->bind_param("i", $id_pesanan);
        if ($stmt->execute()) {
            $msg_success = "Pembayaran untuk Pesanan OLN-" . (10000 + $id_pesanan) . " berhasil diverifikasi. Status diperbarui ke 'Diproses'.";
        } else {
            $msg_error = "Gagal memverifikasi pembayaran: " . $conn->error;
        }
        $stmt->close();
    }
}

// 2. TOLAK PEMBAYARAN (REJECT - Hapus bukti lama agar pelanggan bisa upload ulang)
if (isset($_GET['action']) && $_GET['action'] === 'reject') {
    $id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_pesanan > 0) {
        // Ambil nama file bukti lama untuk dihapus
        $res = $conn->query("SELECT bukti_pembayaran FROM pesanan WHERE id_pesanan = $id_pesanan");
        if ($res && $res->num_rows > 0) {
            $bukti_old = $res->fetch_assoc()['bukti_pembayaran'];
            
            // Hapus file
            if (!empty($bukti_old) && file_exists('../assets/uploads/bukti_pembayaran/' . $bukti_old)) {
                unlink('../assets/uploads/bukti_pembayaran/' . $bukti_old);
            }
            
            // Reset status di database
            $stmt = $conn->prepare("UPDATE pesanan SET status_pembayaran = 'Belum Bayar', status_pesanan = 'Menunggu Pembayaran', bukti_pembayaran = NULL, metode_pembayaran = NULL WHERE id_pesanan = ?");
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

// Ambil Daftar Pesanan yang Menunggu Verifikasi Pembayaran
$query_waiting = "SELECT p.*, pl.nama_lengkap as nama_pelanggan 
                 FROM pesanan p 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                 WHERE p.status_pembayaran = 'Menunggu Verifikasi' OR p.bukti_pembayaran IS NOT NULL
                 ORDER BY p.dibuat_pada DESC";
$list_pembayaran = $conn->query($query_waiting);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pembayaran - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css">
</head>
<body>

    <!-- Include Sidebar -->
    <?php require_once 'sidebar.php'; ?>

    <div class="admin-layout">
        
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-title">
                <h1>Kelola Pembayaran</h1>
                <p>Verifikasi bukti transfer bank atau QRIS dari pelanggan sebelum memproses pembuatan kue.</p>
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

        <!-- Panel Pembayaran -->
        <div class="admin-panel-card">
            <div class="panel-card-header">
                <h3><i class="fa-solid fa-credit-card"></i> Daftar Verifikasi Bukti Pembayaran</h3>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Metode Bayar</th>
                            <th>Bukti Transfer</th>
                            <th>Total Tagihan</th>
                            <th>Status Bayar</th>
                            <th>Status Pesanan</th>
                            <th style="width: 280px; text-align: right;">Aksi Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($list_pembayaran && $list_pembayaran->num_rows > 0): ?>
                            <?php while($row = $list_pembayaran->fetch_assoc()): ?>
                                <?php
                                $kode_order = "OLN-" . (10000 + $row['id_pesanan']);
                                $status_pembayaran = $row['status_pembayaran'];
                                $status_pesanan = $row['status_pesanan'];
                                
                                $badge_bayar = 'admin-badge-info';
                                if ($status_pembayaran === 'Belum Bayar') $badge_bayar = 'admin-badge-waiting';
                                elseif ($status_pembayaran === 'Sudah Bayar') $badge_bayar = 'admin-badge-success';
                                elseif ($status_pembayaran === 'Menunggu Verifikasi') $badge_bayar = 'admin-badge-waiting';
                                ?>
                                <tr>
                                    <td><strong><?= $kode_order ?></strong></td>
                                    <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['metode_pembayaran']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['bukti_pembayaran'])): ?>
                                            <a href="../assets/uploads/bukti_pembayaran/<?= htmlspecialchars($row['bukti_pembayaran']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; color: var(--admin-accent); font-weight: 700; text-decoration: underline; font-size: 0.85rem;">
                                                <i class="fa-regular fa-image"></i> Lihat Bukti
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--admin-text-light); font-size: 0.85rem;">Belum Upload</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong style="color: var(--admin-accent);">Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?></strong></td>
                                    <td><span class="admin-badge <?= $badge_bayar ?>"><?= htmlspecialchars($status_pembayaran) ?></span></td>
                                    <td>
                                        <span class="admin-badge <?= $status_pesanan === 'Selesai' ? 'admin-badge-success' : 'admin-badge-info' ?>">
                                            <?= htmlspecialchars($status_pesanan) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($status_pembayaran === 'Menunggu Verifikasi'): ?>
                                            <a href="pembayaran.php?action=verify&id=<?= $row['id_pesanan'] ?>" class="admin-btn admin-btn-success admin-btn-sm" style="margin-right: 4px;" onclick="return confirm('Konfirmasi bahwa dana transfer telah masuk ke rekening?')">
                                                <i class="fa-solid fa-check"></i> Setujui
                                            </a>
                                            <a href="pembayaran.php?action=reject&id=<?= $row['id_pesanan'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" style="margin-right: 4px;" onclick="return confirm('Tolak bukti pembayaran ini? Klien harus mengunggah ulang.')">
                                                <i class="fa-solid fa-xmark"></i> Tolak
                                            </a>
                                        <?php endif; ?>
                                        <a href="pesanan.php?action=view&id=<?= $row['id_pesanan'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Kelola">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--admin-text-light); padding: 40px 0;">Tidak ada pembayaran menunggu verifikasi saat ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
