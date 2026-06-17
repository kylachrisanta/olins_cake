<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Jika sudah masuk, alihkan ke beranda
if (isset($_SESSION['pelanggan_id'])) {
    header("Location: index.php");
    exit;
}

$pesan_error = "";
$pesan_sukses = "";

// Cek apakah ada cookie "Ingat Saya"
$cookie_username = "";
if (isset($_COOKIE['ingat_nama_pengguna'])) {
    $cookie_username = $_COOKIE['ingat_nama_pengguna'];
}

// Cek apakah ada kiriman pesan sukses dari pendaftaran
if (isset($_GET['status']) && $_GET['status'] === 'terdaftar') {
    $pesan_sukses = "Pendaftaran berhasil! Silakan masuk menggunakan nama pengguna Anda.";
}

// Proses form masuk saat dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengguna = trim($_POST['nama_pengguna']);
    $kata_sandi = $_POST['kata_sandi'];
    $ingat_saya = isset($_POST['ingat_saya']);

    if (empty($nama_pengguna) || empty($kata_sandi)) {
        $pesan_error = "Nama pengguna dan kata sandi wajib diisi.";
    } else {
        // Query verifikasi
        $stmt = $conn->prepare("SELECT id_pelanggan, nama_lengkap, kata_sandi FROM pelanggan WHERE nama_pengguna = ?");
        $stmt->bind_param("s", $nama_pengguna);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($kata_sandi, $user['kata_sandi'])) {
                // Set Session
                $_SESSION['pelanggan_id'] = $user['id_pelanggan'];
                $_SESSION['pelanggan_nama'] = $user['nama_lengkap'];

                // Kelola Cookie "Ingat Saya"
                if ($ingat_saya) {
                    setcookie('ingat_nama_pengguna', $nama_pengguna, time() + (86400 * 30), "/"); // 30 hari
                } else {
                    if (isset($_COOKIE['ingat_nama_pengguna'])) {
                        setcookie('ingat_nama_pengguna', '', time() - 3600, "/");
                    }
                }

                header("Location: index.php");
                exit;
            } else {
                $pesan_error = "Kata sandi yang Anda masukkan salah.";
            }
        } else {
            $pesan_error = "Nama pengguna tidak terdaftar.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Olin's Cake</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

    <div class="auth-page-container">
        <!-- Panel Ilustrasi (Desktop) -->
        <div class="auth-side-img">
            <a href="index.php" class="auth-brand">
                <i class="fa-solid fa-cake-candles"></i> Olin's <span>Cake</span>
            </a>
            
            <div class="auth-quote">
                <p>"Kue lembut premium buatan rumahan dengan bahan kualitas terbaik untuk melengkapi kebahagiaan setiap momen spesial Anda."</p>
                <span>Freshly Baked with Love</span>
            </div>
            
            <div class="auth-footer-tag">
                &copy; <?= date('Y') ?> Olin's Cake
            </div>
        </div>
        
        <!-- Panel Form Masuk -->
        <div class="auth-side-form">
            <div class="auth-form-wrapper">
                <a href="index.php" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
                </a>
                
                <div class="auth-form-header">
                    <h1>Selamat Datang</h1>
                    <p>Silakan masuk untuk melakukan pre-order kue favorit Anda.</p>
                </div>
                
                <?php if (!empty($pesan_error)): ?>
                    <div class="auth-alert auth-alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($pesan_error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pesan_sukses)): ?>
                    <div class="auth-alert auth-alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($pesan_sukses) ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="masuk.php" method="POST">
                    <div class="contact-form-group">
                        <label for="nama_pengguna">Nama Pengguna (Username)</label>
                        <input type="text" id="nama_pengguna" name="nama_pengguna" class="contact-form-control" placeholder="Masukkan nama pengguna Anda" value="<?= htmlspecialchars($cookie_username) ?>" required autocomplete="username">
                    </div>
                    
                    <div class="contact-form-group">
                        <label for="kata_sandi">Kata Sandi</label>
                        <input type="password" id="kata_sandi" name="kata_sandi" class="contact-form-control" placeholder="Masukkan kata sandi Anda" required autocomplete="current-password">
                    </div>
                    
                    <div class="auth-checkbox-group">
                        <input type="checkbox" id="ingat_saya" name="ingat_saya" <?= !empty($cookie_username) ? 'checked' : '' ?>>
                        <label for="ingat_saya">Ingat Saya (Hanya Nama Pengguna)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        Masuk <i class="fa-solid fa-right-to-bracket" style="margin-left: 8px;"></i>
                    </button>
                </form>
                
                <div class="modal-footer-text">
                    Belum memiliki akun? <a href="daftar.php">Daftar Akun Baru</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
