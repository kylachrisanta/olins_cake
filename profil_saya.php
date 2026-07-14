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
$pesan_sukses = "";
$pesan_error = "";

// Tangani Pengiriman Formulir Update Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $nomor_wa = trim($_POST['nomor_wa']);
    $kata_sandi_baru = $_POST['kata_sandi_baru'];
    $konfirmasi_sandi = $_POST['konfirmasi_sandi'];
    $hapus_foto = isset($_POST['hapus_foto']) ? (int)$_POST['hapus_foto'] : 0;

    // Validasi
    if (empty($nama_lengkap) || empty($nomor_wa)) {
        $pesan_error = "Nama Lengkap dan Nomor WhatsApp wajib diisi.";
    } elseif (!preg_match("/^[0-9]+$/", $nomor_wa)) {
        $pesan_error = "Nomor WhatsApp hanya boleh berisi angka saja.";
    } else {
        // Cek apakah ingin mengganti kata sandi
        $update_password = false;
        if (!empty($kata_sandi_baru)) {
            if (strlen($kata_sandi_baru) < 8) {
                $pesan_error = "Kata sandi baru minimal harus terdiri dari 8 karakter.";
            } elseif ($kata_sandi_baru !== $konfirmasi_sandi) {
                $pesan_error = "Konfirmasi kata sandi baru tidak cocok.";
            } else {
                $update_password = true;
                $hashed_password = password_hash($kata_sandi_baru, PASSWORD_DEFAULT);
            }
        }

        if (empty($pesan_error)) {
            // Ambil foto profil lama
            $stmt_old = $conn->prepare("SELECT foto_profil FROM pelanggan WHERE id_pelanggan = ?");
            $stmt_old->bind_param("i", $id_pelanggan);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result()->fetch_assoc();
            $foto_lama = $res_old ? $res_old['foto_profil'] : null;
            $stmt_old->close();

            $foto_profil_new = $foto_lama;

            // Proses Hapus Foto jika dicentang
            if ($hapus_foto === 1) {
                if (!empty($foto_lama) && file_exists(__DIR__ . '/assets/uploads/profil/' . $foto_lama)) {
                    unlink(__DIR__ . '/assets/uploads/profil/' . $foto_lama);
                }
                $foto_profil_new = null;
            }

            // Proses Unggah Foto Baru
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['foto_profil'];
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_name = $file['name'];
                
                // Format file extension
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png'];
                
                // Cek format & mime
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $file_mime = finfo_file($finfo, $file_tmp);
                finfo_close($finfo);
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];
                
                if (!in_array($file_ext, $allowed_exts) || !in_array($file_mime, $allowed_mimes)) {
                    $pesan_error = "Format foto tidak didukung. Hanya file JPG, JPEG, dan PNG yang diperbolehkan.";
                } elseif ($file_size > 2 * 1024 * 1024) {
                    $pesan_error = "Ukuran foto terlalu besar. Maksimal ukuran file adalah 2 MB.";
                } else {
                    // Buat folder jika belum ada
                    $upload_dir = __DIR__ . '/assets/uploads/profil/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    // Buat nama unik
                    $new_gambar_name = 'profil_' . $id_pelanggan . '_' . uniqid() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_gambar_name;
                    
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // Hapus foto lama jika ada
                        if (!empty($foto_lama) && file_exists($upload_dir . $foto_lama)) {
                            unlink($upload_dir . $foto_lama);
                        }
                        $foto_profil_new = $new_gambar_name;
                    } else {
                        $pesan_error = "Gagal mengunggah foto profil. Silakan coba lagi.";
                    }
                }
            }

            // Jika tidak ada error dalam proses file
            if (empty($pesan_error)) {
                if ($update_password) {
                    // Update dengan kata sandi baru
                    $stmt = $conn->prepare("UPDATE pelanggan SET nama_lengkap = ?, nomor_wa = ?, kata_sandi = ?, foto_profil = ? WHERE id_pelanggan = ?");
                    $stmt->bind_param("ssssi", $nama_lengkap, $nomor_wa, $hashed_password, $foto_profil_new, $id_pelanggan);
                } else {
                    // Update tanpa ganti kata sandi
                    $stmt = $conn->prepare("UPDATE pelanggan SET nama_lengkap = ?, nomor_wa = ?, foto_profil = ? WHERE id_pelanggan = ?");
                    $stmt->bind_param("sssi", $nama_lengkap, $nomor_wa, $foto_profil_new, $id_pelanggan);
                }

                if ($stmt->execute()) {
                    $pesan_sukses = "Profil Anda berhasil diperbarui.";
                    // Perbarui nama di sesi
                    $_SESSION['pelanggan_nama'] = $nama_lengkap;
                } else {
                    $pesan_error = "Terjadi kesalahan sistem saat memperbarui profil.";
                }
                $stmt->close();
            }
        }
    }
}

// Ambil Informasi Profil Terbaru Pelanggan
$stmt = $conn->prepare("SELECT nama_lengkap, nama_pengguna, nomor_wa, foto_profil FROM pelanggan WHERE id_pelanggan = ?");
$stmt->bind_param("i", $id_pelanggan);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Olin's Cake</title>
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
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                <li><a href="profil_saya.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Profil</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
            </ul>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="container">
            
            <div class="profile-title-area">
                <h1>Profil Saya</h1>
                <p>Kelola detail kontak pribadi Anda.</p>
            </div>

            <div class="profile-grid">
                
                <!-- Kartu Kiri: Ringkasan Status Akun -->
                <div class="profile-left-col">
                    <div class="profile-card info-card">
                        <div class="avatar-large" style="overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
                            <?php if (!empty($user['foto_profil'])): ?>
                                <img src="assets/uploads/profil/<?= htmlspecialchars($user['foto_profil']) ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <h3><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                        <p class="username">@<?= htmlspecialchars($user['nama_pengguna']) ?></p>
                        <hr class="card-divider">
                        <div class="info-row">
                            <span><i class="fa-solid fa-whatsapp text-success"></i> WhatsApp:</span>
                            <strong><?= htmlspecialchars($user['nomor_wa']) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Kartu Kanan: Form Edit Data -->
                <div class="profile-right-col">
                    <div class="profile-card edit-card">
                        <div class="edit-card-header">
                            <i class="fa-solid fa-user-pen"></i>
                            <h2>Ubah Detail Profil</h2>
                        </div>
                        <div class="edit-card-body">
                            
                            <!-- Notifikasi Sukses / Error -->
                            <?php if (!empty($pesan_sukses)): ?>
                                <div class="profile-alert profile-alert-success">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span><?= htmlspecialchars($pesan_sukses) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($pesan_error)): ?>
                                <div class="profile-alert profile-alert-danger">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span><?= htmlspecialchars($pesan_error) ?></span>
                                </div>
                            <?php endif; ?>

                            <form action="profil_saya.php" method="POST" enctype="multipart/form-data">
                                <div class="form-grid-2">
                                    <div class="contact-form-group">
                                        <label for="nama_lengkap">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="contact-form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                                    </div>
                                    <div class="contact-form-group">
                                        <label for="nama_pengguna_display">Nama Pengguna <span class="text-muted">(Tidak dapat diubah)</span></label>
                                        <input type="text" id="nama_pengguna_display" class="contact-form-control" value="<?= htmlspecialchars($user['nama_pengguna']) ?>" readonly style="background-color: var(--warm-bg); cursor: not-allowed;">
                                    </div>
                                </div>

                                <div class="contact-form-group" style="margin-top: 16px;">
                                    <label for="nomor_wa">Nomor WhatsApp <span class="text-danger">*</span></label>
                                    <input type="text" id="nomor_wa" name="nomor_wa" class="contact-form-control" value="<?= htmlspecialchars($user['nomor_wa']) ?>" placeholder="Contoh: 6281234567890" required>
                                </div>


                                <div class="contact-form-group" style="margin-top: 16px;">
                                    <label for="foto_profil">Foto Profil <span class="text-muted">(Format: JPG, JPEG, PNG. Maks: 2MB)</span></label>
                                    <input type="file" id="foto_profil" name="foto_profil" class="contact-form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 8px;">
                                    <?php if (!empty($user['foto_profil'])): ?>
                                        <div class="form-check" style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" id="hapus_foto" name="hapus_foto" value="1" style="width: auto;">
                                            <label for="hapus_foto" style="margin-bottom: 0; font-size: 0.9rem; cursor: pointer; color: var(--text-muted);">Hapus foto profil saat ini</label>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Bagian Ganti Kata Sandi -->
                                <div class="password-change-header">
                                    <i class="fa-solid fa-key"></i>
                                    <h4>Ganti Kata Sandi <span class="text-muted">(Isi hanya jika ingin mengubah)</span></h4>
                                </div>

                                <div class="form-grid-2">
                                    <div class="contact-form-group">
                                        <label for="kata_sandi_baru">Kata Sandi Baru</label>
                                        <input type="password" id="kata_sandi_baru" name="kata_sandi_baru" class="contact-form-control" placeholder="Kata sandi baru (min 8 karakter)" minlength="8" autocomplete="new-password">
                                    </div>
                                    <div class="contact-form-group">
                                        <label for="konfirmasi_sandi">Konfirmasi Kata Sandi Baru</label>
                                        <input type="password" id="konfirmasi_sandi" name="konfirmasi_sandi" class="contact-form-control" placeholder="Ulangi kata sandi baru" autocomplete="new-password">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" style="margin-top: 24px; width: 100%;">
                                    Simpan Perubahan Profil <i class="fa-solid fa-floppy-disk" style="margin-left: 8px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

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
                    <a href="https://www.instagram.com/olinscake?igsh=MTgzeWszeGFxc2Iwag==" class="social-btn" aria-label="Instagram" target="_blank"><i class="fa-brands fa-instagram"></i></a>
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

    <!-- JavaScript Actions -->
    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    </script>
</body>
</html>
