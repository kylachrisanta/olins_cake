<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

// Proteksi Halaman: Wajib melewati proses lupa_password.php terlebih dahulu
if (!isset($_SESSION['reset_pelanggan_id'])) {
    header("Location: lupa_password.php");
    exit;
}

$id_pelanggan = $_SESSION['reset_pelanggan_id'];
$nomor_wa = isset($_SESSION['reset_nomor_wa']) ? $_SESSION['reset_nomor_wa'] : '';

$pesan_error = "";
$pesan_sukses = "";

// Ambil pesan notifikasi berhasil kirim dari session
if (isset($_SESSION['otp_kirim_sukses'])) {
    $pesan_sukses = $_SESSION['otp_kirim_sukses'];
    unset($_SESSION['otp_kirim_sukses']);
}

// Proses verifikasi OTP saat form dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp']);
    
    if (empty($otp_input)) {
        $pesan_error = "Kode OTP wajib diisi.";
    } elseif (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        $pesan_error = "Kode OTP harus berupa 6 digit angka.";
    } else {
        // Ambil data OTP dari database
        $stmt = $conn->prepare("SELECT otp, otp_expired FROM pelanggan WHERE id_pelanggan = ?");
        $stmt->bind_param("i", $id_pelanggan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $db_otp = $user['otp'];
            $db_expired = $user['otp_expired'];
            $now = date('Y-m-d H:i:s');
            
            // Validasi kebenaran OTP dan masa berlakunya
            if ($db_otp === $otp_input) {
                if (strtotime($db_expired) > time()) {
                    // OTP benar dan belum expired
                    $_SESSION['reset_authorized'] = true;
                    
                    $stmt->close();
                    $conn->close();
                    
                    // Alihkan ke halaman ganti password baru
                    header("Location: reset_password.php");
                    exit;
                } else {
                    $pesan_error = "Kode OTP sudah kedaluwarsa. Silakan minta kode OTP baru.";
                }
            } else {
                $pesan_error = "Kode OTP yang Anda masukkan salah.";
            }
        } else {
            $pesan_error = "Terjadi kesalahan sistem. Pengguna tidak ditemukan.";
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
    <title>Verifikasi OTP - Olin's Cake</title>
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 8px; margin-top: -3px;">
                    <circle cx="9" cy="7" r="2"/>
                    <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
                    <path d="M5.1 12.8 19 12"/>
                    <path d="M8.9 15.6 19 15"/>
                </svg> Olin's <span>Cake</span>
            </a>
            
            <div class="auth-quote">
                <p>"Keamanan akun Anda adalah prioritas kami. Ikuti langkah mudah untuk mengatur ulang kata sandi Anda."</p>
                <span>Secure Password Reset</span>
            </div>
            
            <div class="auth-footer-tag">
                &copy; <?= date('Y') ?> Olin's Cake
            </div>
        </div>
        
        <!-- Panel Form Verifikasi OTP -->
        <div class="auth-side-form">
            <div class="auth-form-wrapper">
                <a href="lupa_password.php" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
                
                <div class="auth-form-header">
                    <h1>Verifikasi OTP</h1>
                    <p>Masukkan 6 digit kode verifikasi OTP yang telah dikirim ke nomor WhatsApp Anda (<strong><?= htmlspecialchars($nomor_wa) ?></strong>).</p>
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
                
                <form action="verifikasi_otp.php" method="POST">
                    <div class="contact-form-group">
                        <label for="otp">Kode OTP (6 Digit)</label>
                        <input type="text" id="otp" name="otp" class="contact-form-control" placeholder="Masukkan 6 digit kode OTP" required pattern="[0-9]{6}" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                        Verifikasi Kode <i class="fa-solid fa-shield-halved" style="margin-left: 8px;"></i>
                    </button>
                </form>
                
                <div class="modal-footer-text">
                    Tidak menerima kode? <a href="lupa_password.php">Kirim Ulang OTP</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
