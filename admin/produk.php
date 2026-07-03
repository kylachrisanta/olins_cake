<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'produk';

// Ambil tab aktif (default: produk)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'produk';

$msg_success = isset($_SESSION['msg_success']) ? $_SESSION['msg_success'] : "";
$msg_error = isset($_SESSION['msg_error']) ? $_SESSION['msg_error'] : "";
unset($_SESSION['msg_success']);
unset($_SESSION['msg_error']);

// ==========================================
// 1. LOGIKA CRUD KATEGORI
// ==========================================

// A. TAMBAH KATEGORI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_kategori'])) {
    $nama_kategori = isset($_POST['nama_kategori']) ? trim($_POST['nama_kategori']) : '';
    
    if (empty($nama_kategori)) {
        $_SESSION['msg_error'] = "Nama kategori tidak boleh kosong.";
    } else {
        // Cek duplikasi
        $check = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ?");
        $check->bind_param("s", $nama_kategori);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['msg_error'] = "Kategori dengan nama '$nama_kategori' sudah ada.";
        } else {
            $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->bind_param("s", $nama_kategori);
            if ($stmt->execute()) {
                $_SESSION['msg_success'] = "Kategori '$nama_kategori' berhasil ditambahkan.";
            } else {
                $_SESSION['msg_error'] = "Gagal menambahkan kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
    header("Location: produk.php?tab=kategori");
    exit;
}

// B. EDIT KATEGORI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_kategori'])) {
    $id_kategori = isset($_POST['id_kategori']) ? intval($_POST['id_kategori']) : 0;
    $nama_kategori = isset($_POST['nama_kategori']) ? trim($_POST['nama_kategori']) : '';
    
    if ($id_kategori <= 0 || empty($nama_kategori)) {
        $_SESSION['msg_error'] = "Data kategori tidak valid.";
    } else {
        // Cek duplikasi nama lain
        $check = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ? AND id_kategori != ?");
        $check->bind_param("si", $nama_kategori, $id_kategori);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['msg_error'] = "Kategori dengan nama '$nama_kategori' sudah ada.";
        } else {
            $stmt = $conn->prepare("UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?");
            $stmt->bind_param("si", $nama_kategori, $id_kategori);
            if ($stmt->execute()) {
                $_SESSION['msg_success'] = "Kategori berhasil diperbarui.";
            } else {
                $_SESSION['msg_error'] = "Gagal memperbarui kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
    header("Location: produk.php?tab=kategori");
    exit;
}

// C. HAPUS KATEGORI
if (isset($_GET['action_kategori']) && $_GET['action_kategori'] === 'delete_kategori') {
    $id_del = isset($_GET['id_kategori']) ? intval($_GET['id_kategori']) : 0;
    if ($id_del > 0) {
        // Cek apakah ada produk yang sedang menggunakan kategori ini
        $kat_res = $conn->query("SELECT nama_kategori FROM kategori WHERE id_kategori = $id_del");
        if ($kat_res && $kat_res->num_rows > 0) {
            $nama_kat = $kat_res->fetch_assoc()['nama_kategori'];
            
            $prod_check = $conn->prepare("SELECT COUNT(*) as count FROM produk WHERE kategori = ?");
            $prod_check->bind_param("s", $nama_kat);
            $prod_check->execute();
            $p_count = $prod_check->get_result()->fetch_assoc()['count'];
            $prod_check->close();
            
            if ($p_count > 0) {
                $_SESSION['msg_error'] = "Gagal menghapus! Kategori '$nama_kat' sedang digunakan oleh $p_count produk.";
            } else {
                $conn->query("DELETE FROM kategori WHERE id_kategori = $id_del");
                $_SESSION['msg_success'] = "Kategori berhasil dihapus.";
            }
        }
    }
    header("Location: produk.php?tab=kategori");
    exit;
}

// ==========================================
// 2. LOGIKA CRUD PRODUK
// ==========================================

// A. TAMBAH PRODUK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_produk'])) {
    $nama_produk = isset($_POST['nama_produk']) ? trim($_POST['nama_produk']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $harga = isset($_POST['harga']) ? intval($_POST['harga']) : 0;
    $kategori = isset($_POST['kategori']) ? trim($_POST['kategori']) : '';
    $ukuran = isset($_POST['ukuran']) ? trim($_POST['ukuran']) : '';
    $masa_simpan = isset($_POST['masa_simpan']) ? trim($_POST['masa_simpan']) : '';
    
    $redirect_url = "produk.php?tab=produk";
    
    if (empty($nama_produk) || empty($kategori) || $harga <= 0 || empty($ukuran) || empty($masa_simpan)) {
        $_SESSION['msg_error'] = "Harap isi semua kolom wajib.";
        $redirect_url = "produk.php?tab=produk&action=add";
    } elseif (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['msg_error'] = "Gambar produk wajib diunggah.";
        $redirect_url = "produk.php?tab=produk&action=add";
    } else {
        $file = $_FILES['gambar'];
        $filename = $file['name'];
        $filetmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_exts)) {
            $_SESSION['msg_error'] = "Format gambar tidak valid. Hanya JPG, JPEG, PNG, dan WEBP yang diperbolehkan.";
            $redirect_url = "produk.php?tab=produk&action=add";
        } else {
            $new_filename = 'produk_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest_path = '../assets/images/' . $new_filename;
            
            if (!is_dir('../assets/images/')) {
                mkdir('../assets/images/', 0755, true);
            }
            
            if (move_uploaded_file($filetmp, $dest_path)) {
                $stmt = $conn->prepare("INSERT INTO produk (nama_produk, deskripsi, harga, gambar, kategori, ukuran, masa_simpan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissss", $nama_produk, $deskripsi, $harga, $new_filename, $kategori, $ukuran, $masa_simpan);
                
                if ($stmt->execute()) {
                    $_SESSION['msg_success'] = "Produk '$nama_produk' berhasil ditambahkan.";
                    $redirect_url = "produk.php?tab=produk";
                } else {
                    $_SESSION['msg_error'] = "Gagal menyimpan ke database: " . $conn->error;
                    unlink($dest_path);
                    $redirect_url = "produk.php?tab=produk&action=add";
                }
                $stmt->close();
            } else {
                $_SESSION['msg_error'] = "Gagal mengunggah gambar ke server.";
                $redirect_url = "produk.php?tab=produk&action=add";
            }
        }
    }
    header("Location: " . $redirect_url);
    exit;
}

// B. EDIT PRODUK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_produk'])) {
    $id_produk = isset($_POST['id_produk']) ? intval($_POST['id_produk']) : 0;
    $nama_produk = isset($_POST['nama_produk']) ? trim($_POST['nama_produk']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
    $harga = isset($_POST['harga']) ? intval($_POST['harga']) : 0;
    $kategori = isset($_POST['kategori']) ? trim($_POST['kategori']) : '';
    $ukuran = isset($_POST['ukuran']) ? trim($_POST['ukuran']) : '';
    $masa_simpan = isset($_POST['masa_simpan']) ? trim($_POST['masa_simpan']) : '';
    $old_gambar = isset($_POST['old_gambar']) ? trim($_POST['old_gambar']) : '';
    
    $redirect_url = "produk.php?tab=produk";
    
    if ($id_produk <= 0 || empty($nama_produk) || empty($kategori) || $harga <= 0 || empty($ukuran) || empty($masa_simpan)) {
        $_SESSION['msg_error'] = "Harap isi semua kolom wajib.";
        $redirect_url = "produk.php?tab=produk&action=edit&id=" . $id_produk;
    } else {
        $new_filename = $old_gambar;
        $upload_ok = true;
        
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['gambar'];
            $filename = $file['name'];
            $filetmp = $file['tmp_name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed_exts)) {
                $_SESSION['msg_error'] = "Format gambar tidak valid. Hanya JPG, JPEG, PNG, dan WEBP yang diperbolehkan.";
                $upload_ok = false;
                $redirect_url = "produk.php?tab=produk&action=edit&id=" . $id_produk;
            } else {
                $new_filename = 'produk_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest_path = '../assets/images/' . $new_filename;
                
                if (move_uploaded_file($filetmp, $dest_path)) {
                    if (!empty($old_gambar) && file_exists('../assets/images/' . $old_gambar)) {
                        unlink('../assets/images/' . $old_gambar);
                    }
                } else {
                    $_SESSION['msg_error'] = "Gagal mengunggah gambar baru ke server.";
                    $upload_ok = false;
                    $redirect_url = "produk.php?tab=produk&action=edit&id=" . $id_produk;
                }
            }
        }
        
        if ($upload_ok) {
            $stmt = $conn->prepare("UPDATE produk SET nama_produk = ?, deskripsi = ?, harga = ?, gambar = ?, kategori = ?, ukuran = ?, masa_simpan = ? WHERE id_produk = ?");
            $stmt->bind_param("ssissssi", $nama_produk, $deskripsi, $harga, $new_filename, $kategori, $ukuran, $masa_simpan, $id_produk);
            
            if ($stmt->execute()) {
                $_SESSION['msg_success'] = "Produk berhasil diperbarui.";
                $redirect_url = "produk.php?tab=produk";
            } else {
                $_SESSION['msg_error'] = "Gagal memperbarui database: " . $conn->error;
                $redirect_url = "produk.php?tab=produk&action=edit&id=" . $id_produk;
            }
            $stmt->close();
        }
    }
    header("Location: " . $redirect_url);
    exit;
}

// C. ARSIPKAN / HAPUS PRODUK
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id_del = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_del > 0) {
        // Cek apakah produk memiliki riwayat transaksi
        $cek_trans = $conn->prepare("SELECT COUNT(*) as total FROM detail_pesanan WHERE id_produk = ?");
        $cek_trans->bind_param("i", $id_del);
        $cek_trans->execute();
        $total_trans = $cek_trans->get_result()->fetch_assoc()['total'];
        $cek_trans->close();

        if ($total_trans > 0) {
            // Produk pernah digunakan dalam transaksi → Arsipkan (soft delete)
            $stmt_arsip = $conn->prepare("UPDATE produk SET status_produk = 'Diarsipkan' WHERE id_produk = ?");
            $stmt_arsip->bind_param("i", $id_del);
            if ($stmt_arsip->execute()) {
                $_SESSION['msg_success'] = "Produk tidak dapat dihapus karena memiliki riwayat transaksi. Produk telah diarsipkan dan tidak akan muncul di katalog pelanggan.";
            } else {
                $_SESSION['msg_error'] = "Gagal mengarsipkan produk: " . $conn->error;
            }
            $stmt_arsip->close();
        } else {
            // Belum pernah bertransaksi → Hapus permanen
            $res = $conn->query("SELECT gambar FROM produk WHERE id_produk = $id_del");
            if ($res && $res->num_rows > 0) {
                $gambar_del = $res->fetch_assoc()['gambar'];
                if ($conn->query("DELETE FROM produk WHERE id_produk = $id_del")) {
                    $_SESSION['msg_success'] = "Produk berhasil dihapus secara permanen.";
                    if (!empty($gambar_del) && file_exists('../assets/images/' . $gambar_del)) {
                        unlink('../assets/images/' . $gambar_del);
                    }
                } else {
                    $_SESSION['msg_error'] = "Gagal menghapus produk: " . $conn->error;
                }
            }
        }
    }
    header("Location: produk.php?tab=produk");
    exit;
}

// D. PULIHKAN PRODUK (Diarsipkan → Aktif)
if (isset($_GET['action']) && $_GET['action'] === 'pulihkan') {
    $id_pulih = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_pulih > 0) {
        $stmt_pulih = $conn->prepare("UPDATE produk SET status_produk = 'Aktif' WHERE id_produk = ?");
        $stmt_pulih->bind_param("i", $id_pulih);
        if ($stmt_pulih->execute()) {
            $_SESSION['msg_success'] = "Produk berhasil dipulihkan dan kini aktif kembali di katalog pelanggan.";
        } else {
            $_SESSION['msg_error'] = "Gagal memulihkan produk: " . $conn->error;
        }
        $stmt_pulih->close();
    }
    header("Location: produk.php?tab=produk");
    exit;
}

// ==========================================
// 3. AMBIL DATA DARI DATABASE
// ==========================================

// Ambil Kategori untuk diedit jika ada request
$edit_kategori = null;
if (isset($_GET['action_kategori']) && $_GET['action_kategori'] === 'edit_kategori') {
    $id_edit = isset($_GET['id_kategori']) ? intval($_GET['id_kategori']) : 0;
    if ($id_edit > 0) {
        $res = $conn->query("SELECT * FROM kategori WHERE id_kategori = $id_edit");
        if ($res && $res->num_rows > 0) {
            $edit_kategori = $res->fetch_assoc();
        }
    }
}

// Ambil Kategori untuk dropdown & list
$kategori_list = [];
$res_kat = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
if ($res_kat) {
    while($row = $res_kat->fetch_assoc()) {
        $kategori_list[] = $row;
    }
}

// Form state Produk: Check if in EDIT mode
$edit_produk = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_edit > 0) {
        $res = $conn->query("SELECT * FROM produk WHERE id_produk = $id_edit");
        if ($res && $res->num_rows > 0) {
            $edit_produk = $res->fetch_assoc();
        }
    }
}

// Ambil Semua Produk beserta jumlah transaksi per produk
$list_produk = $conn->query("
    SELECT p.*, 
        (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_produk = p.id_produk) AS total_transaksi
    FROM produk p
    ORDER BY p.status_produk ASC, p.dibuat_pada DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk & Kategori - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=<?= time(); ?>">
    <style>
        .admin-tab-nav {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--admin-border);
            padding-bottom: 12px;
        }
        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            color: var(--admin-text-muted);
            background-color: rgba(210, 179, 140, 0.03);
            border: 1px solid var(--admin-border);
            transition: all 0.3s ease;
        }
        .tab-btn:hover {
            color: var(--admin-text-main);
            background-color: rgba(210, 179, 140, 0.08);
        }
        .tab-btn.active {
            color: var(--admin-dark-bg);
            background-color: var(--admin-accent);
            border-color: var(--admin-accent);
        }
        /* Badge Status Produk */
        .badge-status-aktif {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            background-color: rgba(46, 196, 182, 0.15);
            color: var(--admin-success);
            border: 1px solid rgba(46, 196, 182, 0.35);
        }
        .badge-status-arsip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            background-color: rgba(180, 180, 180, 0.12);
            color: var(--admin-text-muted);
            border: 1px solid rgba(180, 180, 180, 0.3);
        }
        /* Tombol Arsipkan (kuning/amber) */
        .admin-btn-archive {
            background-color: rgba(234, 179, 8, 0.12);
            color: #ca8a04;
            border: 1px solid rgba(234, 179, 8, 0.35);
        }
        .admin-btn-archive:hover {
            background-color: rgba(234, 179, 8, 0.22);
            color: #92400e;
        }
        /* Tombol Pulihkan (hijau) */
        .admin-btn-restore {
            background-color: rgba(46, 196, 182, 0.12);
            color: var(--admin-success);
            border: 1px solid rgba(46, 196, 182, 0.35);
        }
        .admin-btn-restore:hover {
            background-color: rgba(46, 196, 182, 0.22);
        }
        /* Tombol Hapus - outline merah agar konsisten dengan tombol lain */
        .admin-btn-danger.admin-btn-sm {
            background-color: rgba(220, 38, 38, 0.09);
            color: #c53030;
            border: 1px solid rgba(220, 38, 38, 0.35);
            font-weight: 600;
        }
        .admin-btn-danger.admin-btn-sm:hover {
            background-color: rgba(220, 38, 38, 0.18);
            color: #991b1b;
            border-color: rgba(220, 38, 38, 0.55);
        }
        /* Baris produk diarsipkan redup */
        .row-archived {
            opacity: 0.55;
        }
        .row-archived:hover {
            opacity: 1;
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
                <h1>Kelola Produk</h1>
                <p>Manajemen menu kue terdaftar serta kategori pengelompokannya di Olin's Cake.</p>
            </div>
            
            <?php if ($tab === 'produk'): ?>
                <div class="admin-header-actions">
                    <?php if (isset($_GET['action']) || $edit_produk): ?>
                        <a href="produk.php?tab=produk" class="admin-btn admin-btn-secondary">
                            <i class="fa-solid fa-list"></i> Lihat Daftar Produk
                        </a>
                    <?php else: ?>
                        <a href="produk.php?tab=produk&action=add" class="admin-btn admin-btn-primary">
                            <i class="fa-solid fa-plus"></i> Tambah Produk Baru
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Navigation Bar -->
        <div class="admin-tab-nav">
            <a href="produk.php?tab=produk" class="tab-btn <?= $tab === 'produk' ? 'active' : '' ?>">
                <i class="fa-solid fa-cookie-bite"></i> Menu Produk
            </a>
            <a href="produk.php?tab=kategori" class="tab-btn <?= $tab === 'kategori' ? 'active' : '' ?>">
                <i class="fa-solid fa-tags"></i> Kategori Produk
            </a>
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

        <!-- ======================================================= -->
        <!-- TAB 1: MANAJEMEN PRODUK -->
        <!-- ======================================================= -->
        <?php if ($tab === 'produk'): ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
                <!-- FORM TAMBAH PRODUK -->
                <div class="admin-panel-card" style="max-width: 800px; margin: 0 auto;">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-plus"></i> Isi Informasi Produk Baru</h3>
                    </div>
                    
                    <form action="produk.php?tab=produk" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action_add_produk" value="1">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="nama_produk">Nama Produk *</label>
                                <input type="text" id="nama_produk" name="nama_produk" class="admin-form-control" placeholder="Contoh: Classic Red Velvet" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="harga">Harga (Rp) *</label>
                                <input type="text" id="harga" name="harga" class="admin-form-control" placeholder="Contoh: 150000" required inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="kategori">Kategori *</label>
                                <select id="kategori" name="kategori" class="admin-form-control" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="ukuran">Ukuran / Porsi *</label>
                                <input type="text" id="ukuran" name="ukuran" class="admin-form-control" placeholder="Contoh: Diameter 20 cm / Toples 500g" required autocomplete="off">
                            </div>

                            <div class="admin-form-group">
                                <label for="masa_simpan">Masa Simpan *</label>
                                <input type="text" id="masa_simpan" name="masa_simpan" class="admin-form-control" placeholder="Contoh: 3 Hari (Suhu Ruang)" required autocomplete="off">
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label for="deskripsi">Deskripsi Produk</label>
                            <textarea id="deskripsi" name="deskripsi" class="admin-form-control" rows="4" placeholder="Tuliskan deskripsi lengkap mengenai produk ini..."></textarea>
                        </div>

                        <div class="admin-form-group">
                            <label for="gambar">Gambar Produk *</label>
                            <input type="file" id="gambar" name="gambar" class="admin-form-control" accept="image/*" required>
                            <span style="font-size: 0.75rem; color: var(--admin-text-light);">Disarankan dimensi 4:3 (Kotak/Lanskap) dan format JPG/PNG/WEBP (Maksimal 2 MB).</span>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 10px;">
                            <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Produk
                            </button>
                            <a href="produk.php?tab=produk" class="admin-btn admin-btn-secondary" style="flex: 1; justify-content: center; text-align: center;">Batal</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($edit_produk): ?>
                <!-- FORM EDIT PRODUK -->
                <div class="admin-panel-card" style="max-width: 800px; margin: 0 auto;">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-pen-to-square"></i> Ubah Informasi Produk</h3>
                    </div>
                    
                    <form action="produk.php?tab=produk" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action_edit_produk" value="1">
                        <input type="hidden" name="id_produk" value="<?= $edit_produk['id_produk'] ?>">
                        <input type="hidden" name="old_gambar" value="<?= htmlspecialchars($edit_produk['gambar']) ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="nama_produk">Nama Produk *</label>
                                <input type="text" id="nama_produk" name="nama_produk" class="admin-form-control" value="<?= htmlspecialchars($edit_produk['nama_produk']) ?>" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="harga">Harga (Rp) *</label>
                                <input type="text" id="harga" name="harga" class="admin-form-control" value="<?= $edit_produk['harga'] ?>" required inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div class="admin-form-group">
                                <label for="kategori">Kategori *</label>
                                <select id="kategori" name="kategori" class="admin-form-control" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>" <?= $edit_produk['kategori'] === $kat['nama_kategori'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="ukuran">Ukuran / Porsi *</label>
                                <input type="text" id="ukuran" name="ukuran" class="admin-form-control" value="<?= htmlspecialchars($edit_produk['ukuran']) ?>" required autocomplete="off">
                            </div>

                            <div class="admin-form-group">
                                <label for="masa_simpan">Masa Simpan *</label>
                                <input type="text" id="masa_simpan" name="masa_simpan" class="admin-form-control" value="<?= htmlspecialchars($edit_produk['masa_simpan']) ?>" required autocomplete="off">
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label for="deskripsi">Deskripsi Produk</label>
                            <textarea id="deskripsi" name="deskripsi" class="admin-form-control" rows="4"><?= htmlspecialchars($edit_produk['deskripsi']) ?></textarea>
                        </div>

                        <div class="admin-form-group">
                            <label for="gambar">Gambar Produk (Biarkan kosong jika tidak ingin diubah)</label>
                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                                <img src="../assets/images/<?= htmlspecialchars($edit_produk['gambar']) ?>" alt="Preview" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                <span style="font-size: 0.85rem; color: var(--admin-text-light);">Gambar Aktif: <?= htmlspecialchars($edit_produk['gambar']) ?></span>
                            </div>
                            <input type="file" id="gambar" name="gambar" class="admin-form-control" accept="image/*">
                        </div>

                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                        <a href="produk.php?tab=produk" class="admin-btn admin-btn-secondary" style="flex: 1; justify-content: center; text-align: center;">Batal</a>
                    </div>
                </form>
            </div>

            <?php else: ?>
                <!-- DAFTAR TABEL PRODUK -->
                <div class="admin-panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-cookie-bite"></i> Daftar Menu Kue Terdaftar</h3>
                    </div>

                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Gambar</th>
                                    <th>Nama Kue</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Ukuran / Porsi</th>
                                    <th>Masa Simpan</th>
                                    <th style="width: 90px; text-align: center;">Status</th>
                                    <th style="width: 200px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($list_produk && $list_produk->num_rows > 0): ?>
                                    <?php while($row = $list_produk->fetch_assoc()): ?>
                                        <?php $is_archived = ($row['status_produk'] === 'Diarsipkan'); ?>
                                        <tr class="<?= $is_archived ? 'row-archived' : '' ?>">
                                            <td>
                                                <img src="../assets/images/<?= htmlspecialchars($row['gambar']) ?>" alt="Kue" style="width: 55px; height: 55px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                            </td>
                                            <td>
                                                <strong style="font-size: 1rem; color: var(--admin-text-main);"><?= htmlspecialchars($row['nama_produk']) ?></strong>
                                                <p style="font-size: 0.75rem; color: var(--admin-text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($row['deskripsi']) ?></p>
                                            </td>
                                            <td><span class="admin-badge admin-badge-info"><?= htmlspecialchars($row['kategori']) ?></span></td>
                                            <td><strong>Rp <?= number_format($row['harga'], 0, ',', '.') ?></strong></td>
                                            <td><?= htmlspecialchars($row['ukuran']) ?></td>
                                            <td><span style="font-size: 0.85rem; color: var(--admin-text-muted);"><i class="fa-regular fa-clock" style="margin-right: 4px;"></i> <?= htmlspecialchars($row['masa_simpan']) ?></span></td>
                                            <td style="text-align: center;">
                                                <?php if ($is_archived): ?>
                                                    <span class="badge-status-arsip"><i class="fa-solid fa-box-archive"></i> Diarsipkan</span>
                                                <?php else: ?>
                                                    <span class="badge-status-aktif"><i class="fa-solid fa-circle-check"></i> Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space: nowrap;">
                                                <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap;">
                                                <?php if ($is_archived): ?>
                                                    <!-- Produk diarsipkan: tampilkan Pulihkan saja -->
                                                    <a href="produk.php?tab=produk&action=pulihkan&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-restore admin-btn-sm" title="Pulihkan produk ini ke katalog" onclick="return confirm('Pulihkan produk \'<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>\' agar aktif kembali di katalog?')">
                                                        <i class="fa-solid fa-rotate-left"></i> Pulihkan
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Produk aktif: tampilkan Edit + (Hapus atau Arsipkan) -->
                                                    <a href="produk.php?tab=produk&action=edit&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Ubah">
                                                        <i class="fa-solid fa-pen"></i> Edit
                                                    </a>
                                                    <?php if ($row['total_transaksi'] > 0): ?>
                                                        <!-- Sudah ada transaksi → Arsipkan (soft delete) -->
                                                        <a href="produk.php?tab=produk&action=delete&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-archive admin-btn-sm" title="Arsipkan produk (punya riwayat transaksi)" onclick="return confirm('Produk \'<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>\' memiliki riwayat transaksi dan tidak dapat dihapus permanen.\nProduk akan diarsipkan dan disembunyikan dari katalog. Lanjutkan?')">
                                                            <i class="fa-solid fa-box-archive"></i> Arsipkan
                                                        </a>
                                                    <?php else: ?>
                                                        <!-- Belum ada transaksi → Hapus permanen -->
                                                        <a href="produk.php?tab=produk&action=delete&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus permanen" onclick="return confirm('Hapus permanen produk \'<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>\' beserta gambarnya? Tindakan ini tidak dapat dibatalkan.')">
                                                            <i class="fa-solid fa-trash"></i> Hapus
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--admin-text-light); padding: 40px 0;">Belum ada menu produk terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <!-- ======================================================= -->
        <!-- TAB 2: MANAJEMEN KATEGORI -->
        <!-- ======================================================= -->
        <?php elseif ($tab === 'kategori'): ?>
            
            <div class="admin-row" style="grid-template-columns: 0.7fr 1.3fr;">
                
                <!-- Kolom Form (Tambah/Edit Kategori) -->
                <div class="admin-panel-card" style="height: fit-content;">
                    <?php if ($edit_kategori): ?>
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-pen-to-square"></i> Edit Kategori</h3>
                        </div>
                        <form action="produk.php?tab=kategori" method="POST">
                            <input type="hidden" name="action_edit_kategori" value="1">
                            <input type="hidden" name="id_kategori" value="<?= $edit_kategori['id_kategori'] ?>">
                            
                            <div class="admin-form-group">
                                <label for="nama_kategori">Nama Kategori</label>
                                <input type="text" id="nama_kategori" name="nama_kategori" class="admin-form-control" value="<?= htmlspecialchars($edit_kategori['nama_kategori']) ?>" required autocomplete="off">
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; justify-content: center;">Simpan</button>
                                <a href="produk.php?tab=kategori" class="admin-btn admin-btn-secondary" style="flex: 1; justify-content: center; text-align: center;">Batal</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="panel-card-header">
                            <h3><i class="fa-solid fa-plus"></i> Tambah Kategori</h3>
                        </div>
                        <form action="produk.php?tab=kategori" method="POST">
                            <input type="hidden" name="action_add_kategori" value="1">
                            
                            <div class="admin-form-group">
                                <label for="nama_kategori">Nama Kategori *</label>
                                <input type="text" id="nama_kategori" name="nama_kategori" class="admin-form-control" placeholder="Contoh: Bolu, Kue Kering" required autocomplete="off">
                            </div>
                            
                            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Kategori
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Kolom Tabel Daftar Kategori -->
                <div class="admin-panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-list"></i> Daftar Kategori Produk</h3>
                    </div>
                    
                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">No</th>
                                    <th>Nama Kategori</th>
                                    <th style="width: 200px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($kategori_list) > 0): ?>
                                    <?php $no = 1; foreach ($kategori_list as $row): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><strong><?= htmlspecialchars($row['nama_kategori']) ?></strong></td>
                                            <td style="text-align: right;">
                                                <a href="produk.php?tab=kategori&action_kategori=edit_kategori&id_kategori=<?= $row['id_kategori'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-right: 4px;" title="Edit">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </a>
                                                <a href="produk.php?tab=kategori&action_kategori=delete_kategori&id_kategori=<?= $row['id_kategori'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                                    <i class="fa-solid fa-trash"></i> Hapus
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--admin-text-light); padding: 30px 0;">Belum ada data kategori terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

</body>
</html>
