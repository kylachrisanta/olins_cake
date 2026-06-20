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
    $alamat = trim($_POST['alamat']);
    $kata_sandi_baru = $_POST['kata_sandi_baru'];
    $konfirmasi_sandi = $_POST['konfirmasi_sandi'];

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
            if ($update_password) {
                // Update dengan kata sandi baru
                $stmt = $conn->prepare("UPDATE pelanggan SET nama_lengkap = ?, nomor_wa = ?, alamat = ?, kata_sandi = ? WHERE id_pelanggan = ?");
                $stmt->bind_param("ssssi", $nama_lengkap, $nomor_wa, $alamat, $hashed_password, $id_pelanggan);
            } else {
                // Update tanpa ganti kata sandi
                $stmt = $conn->prepare("UPDATE pelanggan SET nama_lengkap = ?, nomor_wa = ?, alamat = ? WHERE id_pelanggan = ?");
                $stmt->bind_param("sssi", $nama_lengkap, $nomor_wa, $alamat, $id_pelanggan);
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

// Ambil Informasi Profil Terbaru Pelanggan
$stmt = $conn->prepare("SELECT nama_lengkap, nama_pengguna, nomor_wa, alamat FROM pelanggan WHERE id_pelanggan = ?");
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
    <link rel="stylesheet" href="assets/css/style.css">
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
                        <li><a href="index.php#testimoni" class="dropdown-menu-item">Testimoni</a></li>
                        <li><a href="index.php#hubungi" class="dropdown-menu-item">Hubungi Kami</a></li>
                    </ul>
                </li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link"><i class="fa-solid fa-basket-shopping"></i> Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                <li><a href="profil_saya.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Profil Saya</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="container">
            
            <div class="profile-title-area">
                <h1>Profil Saya</h1>
                <p>Kelola detail kontak pribadi dan alamat pengiriman default Anda.</p>
            </div>

            <div class="profile-grid">
                
                <!-- Kartu Kiri: Ringkasan Status Akun -->
                <div class="profile-left-col">
                    <div class="profile-card info-card">
                        <div class="avatar-large">
                            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <h3><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                        <p class="username">@<?= htmlspecialchars($user['nama_pengguna']) ?></p>
                        <hr class="card-divider">
                        <div class="info-row">
                            <span><i class="fa-solid fa-whatsapp text-success"></i> WhatsApp:</span>
                            <strong><?= htmlspecialchars($user['nomor_wa']) ?></strong>
                        </div>
                        <div class="info-row">
                            <span><i class="fa-solid fa-map-location-dot"></i> Alamat Default:</span>
                            <span class="addr-text"><?= !empty($user['alamat']) ? htmlspecialchars($user['alamat']) : '-' ?></span>
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

                            <form action="profil_saya.php" method="POST">
                                <div class="form-grid-2">
                                    <div class="contact-form-group">
                                        <label for="nama_lengkap">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="contact-form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                                    </div>
                                    <div class="contact-form-group">
                                        <label for="nama_pengguna_display">Nama Pengguna (Username) <span class="text-muted">(Tidak dapat diubah)</span></label>
                                        <input type="text" id="nama_pengguna_display" class="contact-form-control" value="<?= htmlspecialchars($user['nama_pengguna']) ?>" readonly style="background-color: var(--warm-bg); cursor: not-allowed;">
                                    </div>
                                </div>

                                <div class="contact-form-group" style="margin-top: 16px;">
                                    <label for="nomor_wa">Nomor WhatsApp <span class="text-danger">*</span></label>
                                    <input type="text" id="nomor_wa" name="nomor_wa" class="contact-form-control" value="<?= htmlspecialchars($user['nomor_wa']) ?>" placeholder="Contoh: 081234567890" required>
                                </div>

                                <div class="contact-form-group" style="margin-top: 16px;">
                                    <label for="alamat">Alamat Pengiriman Default <span class="text-muted">(Untuk mempermudah checkout)</span></label>
                                    <textarea id="alamat" name="alamat" class="contact-form-control" rows="3" placeholder="Masukkan alamat lengkap rumah Anda untuk pengantaran kue"><?= htmlspecialchars($user['alamat']) ?></textarea>
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
