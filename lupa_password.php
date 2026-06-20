<?php
// Mulai Session
session_start();

// Import Koneksi Database & Fonnte Helper
require_once 'config/database.php';
require_once 'config/fonnte_helper.php';

// Jika sudah masuk, alihkan ke beranda
if (isset($_SESSION['pelanggan_id'])) {
    header("Location: index.php");
    exit;
}

$pesan_error = "";
$pesan_sukses = "";

// Proses form saat dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_wa_raw = trim($_POST['nomor_wa']);
    
    if (empty($nomor_wa_raw)) {
        $pesan_error = "Nomor WhatsApp wajib diisi.";
    } else {
        // Normalisasi nomor WhatsApp
        $nomor_wa_input = formatWhatsAppNumber($nomor_wa_raw);
        
        // Buat format lokal 08xxxxxxxx untuk mencocokkan jika di database disimpan dengan awal '0'
        $nomor_wa_lokal = '0' . substr($nomor_wa_input, 2);
        
        // Cari pelanggan dengan nomor WA yang cocok (format internasional atau format lokal)
        $stmt = $conn->prepare("SELECT id_pelanggan, nama_lengkap FROM pelanggan WHERE nomor_wa = ? OR nomor_wa = ?");
        $stmt->bind_param("ss", $nomor_wa_input, $nomor_wa_lokal);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate OTP 6 digit
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Set waktu expired 10 menit dari sekarang
            $expired_at = date('Y-m-d H:i:s', time() + 600); // 600 detik = 10 menit
            
            // Simpan OTP & Expired ke Database
            $update_stmt = $conn->prepare("UPDATE pelanggan SET otp = ?, otp_expired = ? WHERE id_pelanggan = ?");
            $update_stmt->bind_param("ssi", $otp, $expired_at, $user['id_pelanggan']);
            
            if ($update_stmt->execute()) {
                // Kirim OTP via Fonnte WhatsApp API
                $kirim = kirimOTPWhatsApp($nomor_wa_input, $otp);
                
                if ($kirim['success']) {
                    // Set session sementara untuk verifikasi OTP
                    $_SESSION['reset_pelanggan_id'] = $user['id_pelanggan'];
                    $_SESSION['reset_nomor_wa'] = $nomor_wa_input;
                    $_SESSION['otp_kirim_sukses'] = "Kode OTP berhasil dikirim ke nomor WhatsApp Anda.";
                    
                    $update_stmt->close();
                    $stmt->close();
                    
                    // Alihkan ke halaman verifikasi OTP
                    header("Location: verifikasi_otp.php");
                    exit;
                } else {
                    $pesan_error = $kirim['message'];
                }
            } else {
                $pesan_error = "Gagal memproses pembuatan kode OTP. Silakan coba lagi.";
            }
            $update_stmt->close();
        } else {
            $pesan_error = "Nomor WhatsApp tidak terdaftar di sistem kami.";
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
    <title>Lupa Password - Olin's Cake</title>
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
        
        <!-- Panel Form Lupa Password -->
        <div class="auth-side-form">
            <div class="auth-form-wrapper">
                <a href="masuk.php" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Login
                </a>
                
                <div class="auth-form-header">
                    <h1>Lupa Password</h1>
                    <p>Masukkan nomor WhatsApp terdaftar Anda untuk menerima kode verifikasi OTP.</p>
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
                
                <form action="lupa_password.php" method="POST">
                    <div class="contact-form-group">
                        <label for="nomor_wa">Nomor WhatsApp Terdaftar</label>
                        <input type="text" id="nomor_wa" name="nomor_wa" class="contact-form-control" placeholder="Contoh: 081234567890" value="<?= isset($_POST['nomor_wa']) ? htmlspecialchars($_POST['nomor_wa']) : '' ?>" required autocomplete="tel">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                        Kirim Kode OTP <i class="fa-solid fa-paper-plane" style="margin-left: 8px;"></i>
                    </button>
                </form>
                
                <div class="modal-footer-text">
                    Sudah ingat kata sandi? <a href="masuk.php">Masuk di Sini</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
