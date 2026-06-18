<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'testimoni';

$msg_success = "";
$msg_error = "";

// 1. TAMBAH TESTIMONI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    $nama_lengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $pekerjaan = isset($_POST['pekerjaan']) ? trim($_POST['pekerjaan']) : '';
    $isi_testimoni = isset($_POST['isi_testimoni']) ? trim($_POST['isi_testimoni']) : '';
    $avatar_initial = isset($_POST['avatar_initial']) ? trim($_POST['avatar_initial']) : '';
    
    if (empty($nama_lengkap) || empty($pekerjaan) || empty($isi_testimoni) || empty($avatar_initial)) {
        $msg_error = "Harap isi semua kolom.";
    } else {
        $stmt = $conn->prepare("INSERT INTO testimoni (nama_lengkap, pekerjaan, isi_testimoni, avatar_initial, status) VALUES (?, ?, ?, ?, 'Aktif')");
        $stmt->bind_param("ssss", $nama_lengkap, $pekerjaan, $isi_testimoni, $avatar_initial);
        if ($stmt->execute()) {
            $msg_success = "Testimoni berhasil ditambahkan.";
        } else {
            $msg_error = "Gagal menambahkan testimoni: " . $conn->error;
        }
        $stmt->close();
    }
}

// 2. EDIT TESTIMONI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $id_testimoni = isset($_POST['id_testimoni']) ? intval($_POST['id_testimoni']) : 0;
    $nama_lengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $pekerjaan = isset($_POST['pekerjaan']) ? trim($_POST['pekerjaan']) : '';
    $isi_testimoni = isset($_POST['isi_testimoni']) ? trim($_POST['isi_testimoni']) : '';
    $avatar_initial = isset($_POST['avatar_initial']) ? trim($_POST['avatar_initial']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'Aktif';
    
    if ($id_testimoni <= 0 || empty($nama_lengkap) || empty($pekerjaan) || empty($isi_testimoni) || empty($avatar_initial)) {
        $msg_error = "Harap isi semua kolom wajib.";
    } else {
        $stmt = $conn->prepare("UPDATE testimoni SET nama_lengkap = ?, pekerjaan = ?, isi_testimoni = ?, avatar_initial = ?, status = ? WHERE id_testimoni = ?");
        $stmt->bind_param("sssssi", $nama_lengkap, $pekerjaan, $isi_testimoni, $avatar_initial, $status, $id_testimoni);
        if ($stmt->execute()) {
            $msg_success = "Testimoni berhasil diperbarui.";
        } else {
            $msg_error = "Gagal memperbarui testimoni: " . $conn->error;
        }
        $stmt->close();
    }
}

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

// Ambil Testimoni untuk diedit jika ada request
$edit_testi = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_edit > 0) {
        $res = $conn->query("SELECT * FROM testimoni WHERE id_testimoni = $id_edit");
        if ($res && $res->num_rows > 0) {
            $edit_testi = $res->fetch_assoc();
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
    <link rel="stylesheet" href="../assets/css/admin_style.css">
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

        <!-- Grid Input & Daftar -->
        <div class="admin-row" style="grid-template-columns: 0.7fr 1.3fr;">
            
            <!-- Kolom Form (Tambah/Edit) -->
            <div class="admin-panel-card" style="height: fit-content;">
                <?php if ($edit_testi): ?>
                    <!-- Form Edit Testimoni -->
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-pen-to-square"></i> Edit Testimoni</h3>
                    </div>
                    <form action="testimoni.php" method="POST">
                        <input type="hidden" name="action_edit" value="1">
                        <input type="hidden" name="id_testimoni" value="<?= $edit_testi['id_testimoni'] ?>">
                        
                        <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 10px;">
                            <div class="admin-form-group">
                                <label for="nama_lengkap">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" class="admin-form-control" value="<?= htmlspecialchars($edit_testi['nama_lengkap']) ?>" required autocomplete="off">
                            </div>
                            <div class="admin-form-group">
                                <label for="avatar_initial">Avatar Initials</label>
                                <input type="text" id="avatar_initial" name="avatar_initial" class="admin-form-control" value="<?= htmlspecialchars($edit_testi['avatar_initial']) ?>" maxlength="3" required placeholder="RN">
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label for="pekerjaan">Pekerjaan / Jabatan</label>
                            <input type="text" id="pekerjaan" name="pekerjaan" class="admin-form-control" value="<?= htmlspecialchars($edit_testi['pekerjaan']) ?>" required autocomplete="off">
                        </div>

                        <div class="admin-form-group">
                            <label for="status">Status Keaktifan</label>
                            <select id="status" name="status" class="admin-form-control" required>
                                <option value="Aktif" <?= $edit_testi['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif (Muncul di Web)</option>
                                <option value="Nonaktif" <?= $edit_testi['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif (Sembunyikan)</option>
                            </select>
                        </div>

                        <div class="admin-form-group">
                            <label for="isi_testimoni">Isi Testimoni</label>
                            <textarea id="isi_testimoni" name="isi_testimoni" class="admin-form-control" rows="5" required><?= htmlspecialchars($edit_testi['isi_testimoni']) ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">Simpan</button>
                            <a href="testimoni.php" class="admin-btn admin-btn-secondary" style="flex: 1; justify-content: center; text-align: center;">Batal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Form Tambah Testimoni -->
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-plus"></i> Tambah Testimoni</h3>
                    </div>
                    <form action="testimoni.php" method="POST">
                        <input type="hidden" name="action_add" value="1">
                        
                        <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 10px;">
                            <div class="admin-form-group">
                                <label for="nama_lengkap">Nama Lengkap *</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" class="admin-form-control" placeholder="Contoh: Budi Santoso" required autocomplete="off">
                            </div>
                            <div class="admin-form-group">
                                <label for="avatar_initial">Avatar *</label>
                                <input type="text" id="avatar_initial" name="avatar_initial" class="admin-form-control" placeholder="BS" maxlength="3" required>
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label for="pekerjaan">Pekerjaan / Jabatan *</label>
                            <input type="text" id="pekerjaan" name="pekerjaan" class="admin-form-control" placeholder="Contoh: Karyawan Swasta" required autocomplete="off">
                        </div>

                        <div class="admin-form-group">
                            <label for="isi_testimoni">Isi Ulasan Testimoni *</label>
                            <textarea id="isi_testimoni" name="isi_testimoni" class="admin-form-control" rows="5" placeholder="Tuliskan ulasan jujur mengenai cita rasa kue Olin's Cake..." required></textarea>
                        </div>
                        
                        <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Testimoni
                        </button>
                    </form>
                <?php endif; ?>
            </div>

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
                                            <a href="testimoni.php?action=edit&id=<?= $row['id_testimoni'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-right: 4px;" title="Ubah">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
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
