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



// Ambil Semua Testimoni dikelompokkan dengan Produk dan Pelanggan
$query_testi = "SELECT t.*, p.nama_produk, p.gambar AS gambar_produk, k.nama_kategori AS kategori, pl.foto_profil 
                FROM testimoni t 
                LEFT JOIN produk p ON t.id_produk = p.id_produk 
                LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                LEFT JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan 
                ORDER BY (p.id_produk IS NULL) ASC, p.nama_produk ASC, t.dibuat_pada DESC";
$list_testi = $conn->query($query_testi);

$grouped_testi = [];
if ($list_testi) {
    while ($row = $list_testi->fetch_assoc()) {
        $id_produk = $row['id_produk'] ?? 0;
        if (!isset($grouped_testi[$id_produk])) {
            $grouped_testi[$id_produk] = [
                'id_produk' => $id_produk,
                'nama_produk' => $row['nama_produk'] ?? 'Testimoni Umum',
                'gambar_produk' => $row['gambar_produk'] ?? '',
                'kategori' => $row['kategori'] ?? 'Lainnya',
                'testimoni' => [],
                'total_rating' => 0,
                'count_testimoni' => 0
            ];
        }
        $grouped_testi[$id_produk]['testimoni'][] = $row;
        $grouped_testi[$id_produk]['total_rating'] += $row['rating'];
        $grouped_testi[$id_produk]['count_testimoni']++;
    }
}
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

        <!-- Grid Daftar Terkelompok Berdasarkan Produk -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <?php if (!empty($grouped_testi)): ?>
                <?php foreach ($grouped_testi as $prod_id => $group): ?>
                    <div class="admin-panel-card">
                        <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background-color: rgba(210, 179, 140, 0.03); border-bottom: 1px solid var(--admin-border); padding: 15px 20px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <?php if (!empty($group['gambar_produk']) && file_exists('../assets/images/' . $group['gambar_produk'])): ?>
                                    <img src="../assets/images/<?= htmlspecialchars($group['gambar_produk']) ?>" alt="<?= htmlspecialchars($group['nama_produk']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background-color: rgba(210, 179, 140, 0.1); color: var(--admin-accent); display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                        <i class="fa-solid fa-cookie-bite" style="font-size: 1.5rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--admin-accent);"><?= htmlspecialchars($group['nama_produk']) ?></h3>
                                    <span class="admin-badge admin-badge-info" style="margin-top: 5px; font-size: 0.7rem;"><?= htmlspecialchars($group['kategori']) ?></span>
                                </div>
                            </div>
                            
                            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                <span style="font-size: 0.85rem; color: var(--admin-text-muted);">Ulasan: <strong><?= $group['count_testimoni'] ?></strong></span>
                                <?php if ($group['count_testimoni'] > 0): ?>
                                    <?php $avg_rating = $group['total_rating'] / $group['count_testimoni']; ?>
                                    <div style="color: #ffc107; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                        <div>
                                            <?php
                                            $rounded_stars = round($avg_rating);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rounded_stars ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <strong style="color: var(--admin-text-main); margin-left: 2px;"><?= number_format($avg_rating, 1) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Avatar</th>
                                        <th style="width: 180px;">Pelanggan</th>
                                        <th style="width: 120px;">Rating</th>
                                        <th>Ulasan</th>
                                        <th style="width: 150px;">Tanggal</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 240px; text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['testimoni'] as $testi): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($testi['foto_profil']) && file_exists('../assets/uploads/profil/' . $testi['foto_profil'])): ?>
                                                    <img src="../assets/uploads/profil/<?= htmlspecialchars($testi['foto_profil']) ?>" alt="<?= htmlspecialchars($testi['nama_lengkap']) ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 1px solid var(--admin-border);">
                                                <?php else: ?>
                                                    <div class="admin-avatar" style="width: 40px; height: 40px; background-color: var(--admin-border); color: var(--admin-text-main); border-radius: 50%; font-weight: bold; display: flex; align-items: center; justify-content: center;">
                                                        <?= htmlspecialchars($testi['avatar_initial']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($testi['nama_lengkap']) ?></strong>
                                                <p style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= htmlspecialchars($testi['pekerjaan']) ?></p>
                                            </td>
                                            <td>
                                                <div style="color: #ffc107; font-size: 0.8rem;">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?= $i <= $testi['rating'] ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <p style="font-size: 0.85rem; color: var(--admin-text-muted); line-height: 1.4;">
                                                    "<?= htmlspecialchars($testi['isi_testimoni']) ?>"
                                                </p>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.8rem; color: var(--admin-text-muted);"><?= date('d/m/Y H:i', strtotime($testi['dibuat_pada'])) ?> WIB</span>
                                            </td>
                                            <td>
                                                <a href="testimoni.php?action=toggle&id=<?= $testi['id_testimoni'] ?>" class="admin-badge <?= $testi['status'] === 'Aktif' ? 'admin-badge-success' : 'admin-badge-danger' ?>" style="text-decoration: none;" title="Klik untuk mengubah status">
                                                    <?= htmlspecialchars($testi['status']) ?>
                                                </a>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <?php if ($testi['status'] === 'Aktif'): ?>
                                                    <a href="testimoni.php?action=toggle&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Nonaktifkan" style="margin-right: 5px;">
                                                        <i class="fa-solid fa-ban"></i> Nonaktifkan
                                                    </a>
                                                <?php else: ?>
                                                    <a href="testimoni.php?action=toggle&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-success admin-btn-sm" title="Aktifkan" style="margin-right: 5px; color: white;">
                                                         <i class="fa-solid fa-check"></i> Aktifkan
                                                    </a>
                                                <?php endif; ?>
                                                <a href="testimoni.php?action=delete&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus ulasan ini?')">
                                                    <i class="fa-solid fa-trash"></i> Hapus
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="admin-panel-card" style="padding: 40px; text-align: center; color: var(--admin-text-light);">
                    <i class="fa-solid fa-comments-slash" style="font-size: 3rem; margin-bottom: 15px; color: var(--admin-border);"></i>
                    <p>Belum ada ulasan testimoni terdaftar.</p>
                </div>
            <?php endif; ?>

        </div>

    </div>

</body>
</html>
