<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Proteksi Halaman: Wajib lolos verifikasi OTP
if (!isset($_SESSION['reset_pelanggan_id']) || !isset($_SESSION['reset_authorized']) || $_SESSION['reset_authorized'] !== true) {
    header("Location: lupa_password.php");
    exit;
}

$id_pelanggan = $_SESSION['reset_pelanggan_id'];
$pesan_error = "";

// Proses form ganti password saat dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kata_sandi = $_POST['kata_sandi'];
    $konfirmasi_kata_sandi = $_POST['konfirmasi_kata_sandi'];
    
    // Validasi input
    if (empty($kata_sandi) || empty($konfirmasi_kata_sandi)) {
        $pesan_error = "Semua kolom wajib diisi.";
    } elseif (strlen($kata_sandi) < 8) {
        $pesan_error = "Kata sandi minimal harus terdiri dari 8 karakter.";
    } elseif ($kata_sandi !== $konfirmasi_kata_sandi) {
        $pesan_error = "Konfirmasi kata sandi tidak cocok.";
    } else {
        // Enkripsi kata sandi baru
        $hashed_password = password_hash($kata_sandi, PASSWORD_DEFAULT);
        
        // Update database: set password baru dan kosongkan kolom OTP
        $stmt = $conn->prepare("UPDATE pelanggan SET kata_sandi = ?, otp = NULL, otp_expired = NULL WHERE id_pelanggan = ?");
        $stmt->bind_param("si", $hashed_password, $id_pelanggan);
        
        if ($stmt->execute()) {
            // Bersihkan session reset
            unset($_SESSION['reset_pelanggan_id']);
            unset($_SESSION['reset_nomor_wa']);
            unset($_SESSION['reset_authorized']);
            
            $stmt->close();
            $conn->close();
            
            // Alihkan ke masuk.php dengan pesan sukses
            header("Location: masuk.php?status=reset_sukses");
            exit;
        } else {
            $pesan_error = "Terjadi kesalahan sistem saat memperbarui kata sandi. Silakan coba lagi.";
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
    <title>Reset Password - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.0.1">
</head>
<body class="auth-body">

    <div class="auth-page-container">
        <!-- Panel Ilustrasi (Desktop) -->
        <div class="auth-side-img">
            <a href="index.php" class="auth-brand">
                <i class="fa-solid fa-cake-candles"></i> Olin's <span>Cake</span>
            </a>
            
            <div class="auth-quote">
                <p>"Keamanan akun Anda adalah prioritas kami. Ikuti langkah mudah untuk mengatur ulang kata sandi Anda."</p>
                <span>Secure Password Reset</span>
            </div>
            
            <div class="auth-footer-tag">
                &copy; <?= date('Y') ?> Olin's Cake
            </div>
        </div>
        
        <!-- Panel Form Reset Password -->
        <div class="auth-side-form">
            <div class="auth-form-wrapper">
                <a href="lupa_password.php" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Batal
                </a>
                
                <div class="auth-form-header">
                    <h1>Buat Password Baru</h1>
                    <p>Silakan buat kata sandi baru yang kuat untuk mengamankan akun Anda.</p>
                </div>
                
                <?php if (!empty($pesan_error)): ?>
                    <div class="auth-alert auth-alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($pesan_error) ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="reset_password.php" method="POST">
                    <div class="contact-form-group">
                        <label for="kata_sandi">Kata Sandi Baru (Min. 8 Karakter)</label>
                        <input type="password" id="kata_sandi" name="kata_sandi" class="contact-form-control" placeholder="Buat kata sandi baru" minlength="8" required autocomplete="new-password">
                    </div>

                    <div class="contact-form-group">
                        <label for="konfirmasi_kata_sandi">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" id="konfirmasi_kata_sandi" name="konfirmasi_kata_sandi" class="contact-form-control" placeholder="Ulangi kata sandi baru" required autocomplete="new-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                        Perbarui Sandi <i class="fa-solid fa-key" style="margin-left: 8px;"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
