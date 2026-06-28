<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'pengguna';

$msg_success = "";
$msg_error = "";
$current_admin_id = $_SESSION['admin_id'];

// 1. TAMBAH ADMINISTRATOR BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_admin'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $nama_lengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    
    if (empty($username) || empty($password) || empty($nama_lengkap)) {
        $msg_error = "Semua kolom wajib diisi.";
    } else {
        // Cek duplikasi username
        $check = $conn->prepare("SELECT id_admin FROM admin WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg_error = "Username '$username' sudah terdaftar.";
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed, $nama_lengkap);
            if ($stmt->execute()) {
                $msg_success = "Admin '$nama_lengkap' berhasil ditambahkan.";
            } else {
                $msg_error = "Gagal menambahkan admin: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// 2. EDIT ADMINISTRATOR (Ubah nama / password jika diisi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_admin'])) {
    $id_admin = isset($_POST['id_admin']) ? intval($_POST['id_admin']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $nama_lengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if ($id_admin <= 0 || empty($username) || empty($nama_lengkap)) {
        $msg_error = "Data admin tidak valid.";
    } else {
        // Cek duplikasi username lain
        $check = $conn->prepare("SELECT id_admin FROM admin WHERE username = ? AND id_admin != ?");
        $check->bind_param("si", $username, $id_admin);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $msg_error = "Username '$username' sudah digunakan oleh admin lain.";
        } else {
            if (!empty($password)) {
                // Update dengan password baru
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admin SET username = ?, password = ?, nama_lengkap = ? WHERE id_admin = ?");
                $stmt->bind_param("sssi", $username, $hashed, $nama_lengkap, $id_admin);
            } else {
                // Update tanpa ganti password
                $stmt = $conn->prepare("UPDATE admin SET username = ?, nama_lengkap = ? WHERE id_admin = ?");
                $stmt->bind_param("ssi", $username, $nama_lengkap, $id_admin);
            }
            
            if ($stmt->execute()) {
                $msg_success = "Profil admin berhasil diperbarui.";
                // Update session jika mengedit diri sendiri
                if ($id_admin === $current_admin_id) {
                    $_SESSION['admin_name'] = $nama_lengkap;
                    $_SESSION['admin_username'] = $username;
                }
            } else {
                $msg_error = "Gagal memperbarui admin: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// 3. HAPUS ADMINISTRATOR
if (isset($_GET['action']) && $_GET['action'] === 'delete_admin') {
    $id_del = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_del > 0) {
        if ($id_del === $current_admin_id) {
            $msg_error = "Gagal menghapus! Anda tidak dapat menghapus akun Anda sendiri saat sedang masuk.";
        } else {
            if ($conn->query("DELETE FROM admin WHERE id_admin = $id_del")) {
                $msg_success = "Admin berhasil dihapus.";
            } else {
                $msg_error = "Gagal menghapus admin: " . $conn->error;
            }
        }
    }
}

// Ambil admin untuk diedit jika diminta
$edit_admin = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_admin') {
    $id_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_edit > 0) {
        $res = $conn->query("SELECT * FROM admin WHERE id_admin = $id_edit");
        if ($res && $res->num_rows > 0) {
            $edit_admin = $res->fetch_assoc();
        }
    }
}

// Ambil Daftar Semua Admin
$list_admin = $conn->query("SELECT * FROM admin ORDER BY nama_lengkap ASC");

// Ambil Daftar Semua Pelanggan
$list_pelanggan = $conn->query("SELECT * FROM pelanggan ORDER BY dibuat_pada DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=<?= time(); ?>">
    <style>
        .users-row {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 24px;
        }
        @media (max-width: 992px) {
            .users-row {
                grid-template-columns: 1fr;
            }
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
                <h1>Kelola Pengguna</h1>
                <p>Manajemen akun administrator toko serta pemantauan pelanggan terdaftar.</p>
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

        <div class="users-row">
            
            <!-- Kolom Kiri: Kelola Akun Admin (CRUD) -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Form input -->
                <div class="admin-panel-card">
                    <?php if ($edit_admin): ?>
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-user-pen"></i> Edit Akun Admin</h3>
                        </div>
                        <form action="pengguna.php" method="POST">
                            <input type="hidden" name="action_edit_admin" value="1">
                            <input type="hidden" name="id_admin" value="<?= $edit_admin['id_admin'] ?>">
                            
                            <div class="admin-form-group">
                                <label for="nama_lengkap">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" class="admin-form-control" value="<?= htmlspecialchars($edit_admin['nama_lengkap']) ?>" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" class="admin-form-control" value="<?= htmlspecialchars($edit_admin['username']) ?>" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="password">Password Baru (Kosongkan jika tidak ingin diubah)</label>
                                <input type="password" id="password" name="password" class="admin-form-control" placeholder="Masukkan password baru">
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">Simpan</button>
                                <a href="pengguna.php" class="admin-btn admin-btn-secondary" style="flex: 1; justify-content: center; text-align: center;">Batal</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-user-plus"></i> Tambah Akun Admin</h3>
                        </div>
                        <form action="pengguna.php" method="POST">
                            <input type="hidden" name="action_add_admin" value="1">
                            
                            <div class="admin-form-group">
                                <label for="nama_lengkap">Nama Lengkap *</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" class="admin-form-control" placeholder="Contoh: Admin Baru" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" class="admin-form-control" placeholder="Masukkan username" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" class="admin-form-control" placeholder="Masukkan password" required>
                            </div>
                            
                            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Admin
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Tabel daftar admin -->
                <div class="admin-panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-user-shield"></i> Daftar Administrator</h3>
                    </div>
                    
                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th style="text-align: right; width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($list_admin && $list_admin->num_rows > 0): ?>
                                    <?php while($row = $list_admin->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                                                <?php if ($row['id_admin'] === $current_admin_id): ?>
                                                    <span style="font-size: 0.7rem; background-color: rgba(46, 196, 182, 0.15); color: var(--admin-success); padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 6px;">Aktif Anda</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($row['username']) ?></code></td>
                                            <td style="text-align: right;">
                                                <a href="pengguna.php?action=edit_admin&id=<?= $row['id_admin'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-right: 2px;" title="Ubah">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <?php if ($row['id_admin'] !== $current_admin_id): ?>
                                                    <a href="pengguna.php?action=delete_admin&id=<?= $row['id_admin'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus admin ini?')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Kolom Kanan: Lihat Daftar Pelanggan (Registered Customers) -->
            <div class="admin-panel-card" style="height: fit-content;">
                <div class="panel-card-header">
                    <h3><i class="fa-solid fa-users"></i> Daftar Pelanggan Terdaftar</h3>
                </div>
                
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>No. WhatsApp</th>
                                <th>Alamat Lengkap</th>
                                <th>Tgl Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list_pelanggan && $list_pelanggan->num_rows > 0): ?>
                                <?php while($row = $list_pelanggan->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($row['nama_pengguna']) ?></code></td>
                                        <td>
                                            <?= htmlspecialchars($row['nomor_wa']) ?>
                                            <?php
                                            $wa_msg = "Halo " . htmlspecialchars($row['nama_lengkap']) . ", ini dari Olin's Cake.";
                                            $wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $row['nomor_wa']) . "?text=" . urlencode($wa_msg);
                                            ?>
                                            <a href="<?= $wa_url ?>" target="_blank" style="color: var(--admin-success); margin-left: 4px;" title="Hubungi">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <p style="font-size: 0.85rem; color: var(--admin-text-muted); line-height: 1.4; max-width: 200px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($row['alamat']) ?>">
                                                <?= htmlspecialchars($row['alamat']) ?>
                                            </p>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--admin-text-muted);"><?= date('d/m/Y', strtotime($row['dibuat_pada'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--admin-text-light); padding: 40px 0;">Belum ada pelanggan terdaftar saat ini.</td>
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
