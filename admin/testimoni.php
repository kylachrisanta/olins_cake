<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'testimoni';

$msg_success = "";
$msg_error = "";



// 3. TOGGLE STATUS (AKTIF / NONAKTIF)
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $id_toggle = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_toggle > 0) {
        $res = $conn->query("SELECT status FROM testimoni WHERE id_testimoni = $id_toggle");
        if ($res && $res->num_rows > 0) {
            $curr_status = $res->fetch_assoc()['status'];
            $new_status = ($curr_status === 'Aktif') ? 'Nonaktif' : 'Aktif';
            
            if ($conn->query("UPDATE testimoni SET status = '$new_status' WHERE id_testimoni = $id_toggle")) {
                $msg_success = "Status testimoni berhasil diubah menjadi '$new_status'.";
            } else {
                $msg_error = "Gagal mengubah status: " . $conn->error;
            }
        }
    }
}

// 4. HAPUS TESTIMONI
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id_del = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_del > 0) {
        if ($conn->query("DELETE FROM testimoni WHERE id_testimoni = $id_del")) {
            $msg_success = "Testimoni berhasil dihapus.";
        } else {
            $msg_error = "Gagal menghapus: " . $conn->error;
        }
    }
}



// Ambil Semua Testimoni
$list_testi = $conn->query("SELECT * FROM testimoni ORDER BY dibuat_pada DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Testimoni - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=<?= time(); ?>">
</head>
<body>

    <!-- Include Sidebar -->
    <?php require_once 'sidebar.php'; ?>

    <div class="admin-layout">
        
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-title">
                <h1>Kelola Testimoni</h1>
                <p>Kelola testimoni pelanggan yang ditampilkan di halaman utama website.</p>
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

        <!-- Grid Daftar -->
        <div class="admin-row" style="grid-template-columns: 1fr;">

            <!-- Kolom Tabel Daftar -->
            <div class="admin-panel-card">
                <div class="panel-card-header">
                    <h3><i class="fa-solid fa-comments"></i> Daftar Ulasan Testimoni</h3>
                </div>
                
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Avatar</th>
                                <th>Pelanggan</th>
                                <th>Ulasan</th>
                                <th>Status</th>
                                <th style="width: 180px; text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list_testi && $list_testi->num_rows > 0): ?>
                                <?php while($row = $list_testi->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="admin-avatar" style="width: 40px; height: 40px; background-color: var(--admin-border); color: var(--admin-text-main);">
                                                <?= htmlspecialchars($row['avatar_initial']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                                            <p style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= htmlspecialchars($row['pekerjaan']) ?></p>
                                        </td>
                                        <td>
                                            <p style="font-size: 0.85rem; color: var(--admin-text-muted); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($row['isi_testimoni']) ?>">
                                                "<?= htmlspecialchars($row['isi_testimoni']) ?>"
                                            </p>
                                        </td>
                                        <td>
                                            <a href="testimoni.php?action=toggle&id=<?= $row['id_testimoni'] ?>" class="admin-badge <?= $row['status'] === 'Aktif' ? 'admin-badge-success' : 'admin-badge-danger' ?>" style="text-decoration: none;" title="Klik untuk mengubah status">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </a>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="testimoni.php?action=delete&id=<?= $row['id_testimoni'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus ulasan ini?')">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--admin-text-light); padding: 30px 0;">Belum ada ulasan testimoni terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

</body>
</html>
