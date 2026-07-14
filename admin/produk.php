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
            
            $prod_check = $conn->prepare("SELECT COUNT(*) as count FROM produk WHERE id_kategori = ?");
            $prod_check->bind_param("i", $id_del);
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
    $id_kategori = isset($_POST['kategori']) ? intval($_POST['kategori']) : 0;
    $ukuran = isset($_POST['ukuran']) ? trim($_POST['ukuran']) : '';
    $masa_simpan = isset($_POST['masa_simpan']) ? trim($_POST['masa_simpan']) : '';
    
    $redirect_url = "produk.php?tab=produk";
    
    if (empty($nama_produk) || empty($id_kategori) || $harga <= 0 || empty($ukuran) || empty($masa_simpan)) {
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
                $stmt = $conn->prepare("INSERT INTO produk (nama_produk, deskripsi, harga, gambar, id_kategori, ukuran, masa_simpan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisiss", $nama_produk, $deskripsi, $harga, $new_filename, $id_kategori, $ukuran, $masa_simpan);
                
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
    $id_kategori = isset($_POST['kategori']) ? intval($_POST['kategori']) : 0;
    $ukuran = isset($_POST['ukuran']) ? trim($_POST['ukuran']) : '';
    $masa_simpan = isset($_POST['masa_simpan']) ? trim($_POST['masa_simpan']) : '';
    $old_gambar = isset($_POST['old_gambar']) ? trim($_POST['old_gambar']) : '';
    
    $redirect_url = "produk.php?tab=produk";
    
    if ($id_produk <= 0 || empty($nama_produk) || empty($id_kategori) || $harga <= 0 || empty($ukuran) || empty($masa_simpan)) {
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
            $stmt = $conn->prepare("UPDATE produk SET nama_produk = ?, deskripsi = ?, harga = ?, gambar = ?, id_kategori = ?, ukuran = ?, masa_simpan = ? WHERE id_produk = ?");
            $stmt->bind_param("ssisissi", $nama_produk, $deskripsi, $harga, $new_filename, $id_kategori, $ukuran, $masa_simpan, $id_produk);
            
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

// C. HAPUS PRODUK
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
            // Produk pernah digunakan dalam transaksi → Blokir penghapusan
            $_SESSION['msg_error'] = "Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi pelanggan.";
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

// ==========================================
// 3. LOGIKA KELOLA TESTIMONI
// ==========================================

// E. TOGGLE STATUS TESTIMONI (Aktif / Nonaktif)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_testi') {
    $id_toggle = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_toggle > 0) {
        $res = $conn->query("SELECT status FROM testimoni WHERE id_testimoni = $id_toggle");
        if ($res && $res->num_rows > 0) {
            $curr_status = $res->fetch_assoc()['status'];
            $new_status = ($curr_status === 'Aktif') ? 'Nonaktif' : 'Aktif';
            if ($conn->query("UPDATE testimoni SET status = '$new_status' WHERE id_testimoni = $id_toggle")) {
                $_SESSION['msg_success'] = "Status testimoni berhasil diubah menjadi '$new_status'.";
            } else {
                $_SESSION['msg_error'] = "Gagal mengubah status: " . $conn->error;
            }
        }
    }
    header("Location: produk.php?tab=testimoni");
    exit;
}

// F. HAPUS TESTIMONI
if (isset($_GET['action']) && $_GET['action'] === 'delete_testi') {
    $id_del_testi = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_del_testi > 0) {
        if ($conn->query("DELETE FROM testimoni WHERE id_testimoni = $id_del_testi")) {
            $_SESSION['msg_success'] = "Testimoni berhasil dihapus.";
        } else {
            $_SESSION['msg_error'] = "Gagal menghapus testimoni: " . $conn->error;
        }
    }
    header("Location: produk.php?tab=testimoni");
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
$res_kat = $conn->query("
    SELECT k.*, 
        (SELECT COUNT(*) FROM produk p WHERE p.id_kategori = k.id_kategori) AS total_produk
    FROM kategori k
    ORDER BY k.nama_kategori ASC
");
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
    SELECT p.*, k.nama_kategori AS kategori, 
        (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_produk = p.id_produk) AS total_transaksi
    FROM produk p
    LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
    ORDER BY p.dibuat_pada DESC
");

// ==========================================
// 4. AMBIL DATA TESTIMONI (dikelompokkan per produk)
// ==========================================
$grouped_testi = [];
if ($tab === 'testimoni') {
    $query_testi = "SELECT t.*, p.nama_produk, p.gambar AS gambar_produk, k.nama_kategori AS kategori, pl.foto_profil 
                    FROM testimoni t 
                    LEFT JOIN produk p ON t.id_produk = p.id_produk 
                    LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
                    LEFT JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan 
                    ORDER BY (p.id_produk IS NULL) ASC, p.nama_produk ASC, t.dibuat_pada DESC";
    $list_testi = $conn->query($query_testi);
    if ($list_testi) {
        while ($row = $list_testi->fetch_assoc()) {
            $prod_key = $row['id_produk'] ?? 0;
            if (!isset($grouped_testi[$prod_key])) {
                $grouped_testi[$prod_key] = [
                    'id_produk'     => $prod_key,
                    'nama_produk'   => $row['nama_produk'] ?? 'Testimoni Umum',
                    'gambar_produk' => $row['gambar_produk'] ?? '',
                    'kategori'      => $row['kategori'] ?? 'Lainnya',
                    'testimoni'     => [],
                    'total_rating'  => 0,
                    'count_testimoni' => 0
                ];
            }
            $grouped_testi[$prod_key]['testimoni'][] = $row;
            $grouped_testi[$prod_key]['total_rating'] += $row['rating'];
            $grouped_testi[$prod_key]['count_testimoni']++;
        }
    }
}
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
                <i class="fa-solid fa-cookie-bite"></i> Produk
            </a>
            <a href="produk.php?tab=kategori" class="tab-btn <?= $tab === 'kategori' ? 'active' : '' ?>">
                <i class="fa-solid fa-tags"></i> Kategori Produk
            </a>
            <a href="produk.php?tab=testimoni" class="tab-btn <?= $tab === 'testimoni' ? 'active' : '' ?>">
                <i class="fa-solid fa-comment-dots"></i> Testimoni Produk
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
                        
                        <div class="admin-form-grid-2">
                            <div class="admin-form-group">
                                <label for="nama_produk">Nama Produk *</label>
                                <input type="text" id="nama_produk" name="nama_produk" class="admin-form-control" placeholder="Contoh: Classic Red Velvet" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="harga">Harga (Rp) *</label>
                                <input type="text" id="harga" name="harga" class="admin-form-control" placeholder="Contoh: 150000" required inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        <div class="admin-form-grid-3">
                            <div class="admin-form-group">
                                <label for="kategori">Kategori *</label>
                                <select id="kategori" name="kategori" class="admin-form-control" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?= $kat['id_kategori'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
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

                        <div class="admin-form-actions">
                            <button type="submit" class="admin-btn admin-btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Produk
                            </button>
                            <a href="produk.php?tab=produk" class="admin-btn admin-btn-secondary">Batal</a>
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
                        
                        <div class="admin-form-grid-2">
                            <div class="admin-form-group">
                                <label for="nama_produk">Nama Produk *</label>
                                <input type="text" id="nama_produk" name="nama_produk" class="admin-form-control" value="<?= htmlspecialchars($edit_produk['nama_produk']) ?>" required autocomplete="off">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="harga">Harga (Rp) *</label>
                                <input type="text" id="harga" name="harga" class="admin-form-control" value="<?= $edit_produk['harga'] ?>" required inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        <div class="admin-form-grid-3">
                            <div class="admin-form-group">
                                <label for="kategori">Kategori *</label>
                                <select id="kategori" name="kategori" class="admin-form-control" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?= $kat['id_kategori'] ?>" <?= $edit_produk['id_kategori'] == $kat['id_kategori'] ? 'selected' : '' ?>>
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

                        <div class="admin-form-actions">
                            <button type="submit" class="admin-btn admin-btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                            </button>
                            <a href="produk.php?tab=produk" class="admin-btn admin-btn-secondary">Batal</a>
                        </div>
                </form>
            </div>

            <?php else: ?>
                <!-- DAFTAR TABEL PRODUK -->
                <div class="admin-panel-card">
                    <div class="panel-card-header">
                        <h3><i class="fa-solid fa-cookie-bite"></i> Daftar Produk</h3>
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
                                    <th style="width: 200px; text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($list_produk && $list_produk->num_rows > 0): ?>
                                    <?php while($row = $list_produk->fetch_assoc()): ?>
                                        <tr>
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
                                            <td style="white-space: nowrap;">
                                                <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap;">
                                                    <!-- Tombol Edit -->
                                                    <a href="produk.php?tab=produk&action=edit&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Ubah">
                                                        <i class="fa-solid fa-pen"></i> Edit
                                                    </a>
                                                    <!-- Tombol Hapus -->
                                                    <?php if ($row['total_transaksi'] > 0): ?>
                                                        <a href="produk.php?tab=produk&action=delete&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus terblokir (ada transaksi)" onclick="alert('Produk \'<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>\' tidak dapat dihapus karena sudah memiliki riwayat transaksi pelanggan.'); return false;">
                                                            <i class="fa-solid fa-trash"></i> Hapus
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="produk.php?tab=produk&action=delete&id=<?= $row['id_produk'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus permanen" onclick="return confirm('Apakah Anda yakin ingin menghapus produk \'<?= htmlspecialchars(addslashes($row['nama_produk'])) ?>\' secara permanen? Tindakan ini tidak dapat dibatalkan.')">
                                                            <i class="fa-solid fa-trash"></i> Hapus
                                                        </a>
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
            
            <div class="admin-row admin-kategori-row">
                
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
                                                <?php if ($row['total_produk'] > 0): ?>
                                                    <a href="produk.php?tab=kategori&action_kategori=delete_kategori&id_kategori=<?= $row['id_kategori'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus terblokir (digunakan produk)" onclick="alert('Kategori \'<?= htmlspecialchars(addslashes($row['nama_kategori'])) ?>\' tidak dapat dihapus karena sedang digunakan oleh <?= $row['total_produk'] ?> produk. Hubungkan produk ke kategori lain terlebih dahulu.'); return false;">
                                                        <i class="fa-solid fa-trash"></i> Hapus
                                                    </a>
                                                <?php else: ?>
                                                    <a href="produk.php?tab=kategori&action_kategori=delete_kategori&id_kategori=<?= $row['id_kategori'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus kategori \'<?= htmlspecialchars(addslashes($row['nama_kategori'])) ?>\'?')">
                                                        <i class="fa-solid fa-trash"></i> Hapus
                                                    </a>
                                                <?php endif; ?>
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


        <!-- ======================================================= -->
        <!-- TAB 3: KELOLA TESTIMONI -->
        <!-- ======================================================= -->
        <?php elseif ($tab === 'testimoni'): ?>

            <?php if (!empty($grouped_testi)): ?>
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <?php foreach ($grouped_testi as $group): ?>
                        <div class="admin-panel-card">
                            <!-- Header per Produk -->
                            <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; padding: 15px 20px;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <?php if (!empty($group['gambar_produk']) && file_exists('../assets/images/' . $group['gambar_produk'])): ?>
                                        <img src="../assets/images/<?= htmlspecialchars($group['gambar_produk']) ?>" alt="<?= htmlspecialchars($group['nama_produk']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background-color: rgba(210,179,140,0.1); color: var(--admin-accent); display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); border: 1px solid var(--admin-border);">
                                            <i class="fa-solid fa-cookie-bite" style="font-size: 1.4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--admin-accent);"><?= htmlspecialchars($group['nama_produk']) ?></h3>
                                        <span class="admin-badge admin-badge-info" style="margin-top: 5px; font-size: 0.7rem;"><?= htmlspecialchars($group['kategori']) ?></span>
                                    </div>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <span style="font-size: 0.85rem; color: var(--admin-text-muted);">Ulasan: <strong><?= $group['count_testimoni'] ?></strong></span>
                                    <?php if ($group['count_testimoni'] > 0): ?>
                                        <?php $avg = $group['total_rating'] / $group['count_testimoni']; ?>
                                        <div style="color: #ffc107; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                            <?php for ($i = 1; $i <= 5; $i++) echo $i <= round($avg) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
                                            <strong style="color: var(--admin-text-main); margin-left: 2px;"><?= number_format($avg, 1) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tabel Ulasan -->
                            <div class="admin-table-container">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 55px;">Foto</th>
                                            <th style="width: 180px;">Pelanggan</th>
                                            <th style="width: 110px;">Rating</th>
                                            <th>Ulasan</th>
                                            <th style="width: 140px;">Tanggal</th>
                                            <th style="width: 95px;">Status</th>
                                            <th style="width: 210px; text-align: right;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['testimoni'] as $testi): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($testi['foto_profil']) && file_exists('../assets/uploads/profil/' . $testi['foto_profil'])): ?>
                                                        <img src="../assets/uploads/profil/<?= htmlspecialchars($testi['foto_profil']) ?>" alt="" style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%; border: 1px solid var(--admin-border);">
                                                    <?php else: ?>
                                                        <div style="width: 38px; height: 38px; background-color: var(--admin-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--admin-text-main);">
                                                            <?= strtoupper(substr($testi['nama_lengkap'] ?? 'U', 0, 1)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($testi['nama_lengkap']) ?></strong>
                                                    <?php if (!empty($testi['pekerjaan'])): ?>
                                                        <p style="font-size: 0.75rem; color: var(--admin-text-muted); margin: 2px 0 0;"><?= htmlspecialchars($testi['pekerjaan']) ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="color: #ffc107; font-size: 0.8rem;">
                                                        <?php for ($i = 1; $i <= 5; $i++) echo '<i class="' . ($i <= $testi['rating'] ? 'fa-solid' : 'fa-regular') . ' fa-star"></i>'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p style="font-size: 0.85rem; color: var(--admin-text-muted); line-height: 1.45; margin: 0;">
                                                        "<?= htmlspecialchars($testi['isi_testimoni']) ?>"
                                                    </p>
                                                </td>
                                                <td>
                                                    <span style="font-size: 0.8rem; color: var(--admin-text-muted);">
                                                        <?= date('d/m/Y H:i', strtotime($testi['dibuat_pada'])) ?> WIB
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="admin-badge <?= $testi['status'] === 'Aktif' ? 'admin-badge-success' : 'admin-badge-danger' ?>">
                                                        <?= htmlspecialchars($testi['status']) ?>
                                                    </span>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                        <?php if ($testi['status'] === 'Aktif'): ?>
                                                            <a href="produk.php?tab=testimoni&action=toggle_testi&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Nonaktifkan">
                                                                <i class="fa-solid fa-ban"></i> Nonaktifkan
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="produk.php?tab=testimoni&action=toggle_testi&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-restore admin-btn-sm" title="Aktifkan">
                                                                <i class="fa-solid fa-check"></i> Aktifkan
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="produk.php?tab=testimoni&action=delete_testi&id=<?= $testi['id_testimoni'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" title="Hapus" onclick="return confirm('Hapus ulasan ini secara permanen?')">
                                                            <i class="fa-solid fa-trash"></i> Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-panel-card" style="padding: 50px; text-align: center; color: var(--admin-text-light);">
                    <i class="fa-solid fa-comments-slash" style="font-size: 3rem; margin-bottom: 16px; color: var(--admin-border); display: block;"></i>
                    <p>Belum ada ulasan testimoni dari pelanggan.</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</body>
</html>
