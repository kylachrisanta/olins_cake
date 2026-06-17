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

// Proses form daftar saat dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $nama_pengguna = trim($_POST['nama_pengguna']);
    $nomor_wa = trim($_POST['nomor_wa']);
    $kata_sandi = $_POST['kata_sandi'];
    $konfirmasi_kata_sandi = $_POST['konfirmasi_kata_sandi'];

    // Validasi input wajib
    if (empty($nama_lengkap) || empty($nama_pengguna) || empty($nomor_wa) || empty($kata_sandi) || empty($konfirmasi_kata_sandi)) {
        $pesan_error = "Semua kolom wajib diisi.";
    } 
    // Validasi panjang kata sandi minimal 8 karakter
    elseif (strlen($kata_sandi) < 8) {
        $pesan_error = "Kata sandi minimal harus terdiri dari 8 karakter.";
    } 
    // Validasi kesamaan kata sandi
    elseif ($kata_sandi !== $konfirmasi_kata_sandi) {
        $pesan_error = "Konfirmasi kata sandi tidak cocok.";
    } 
    // Validasi format nomor WhatsApp (hanya angka)
    elseif (!preg_match("/^[0-9]+$/", $nomor_wa)) {
        $pesan_error = "Nomor WhatsApp hanya boleh berisi angka saja.";
    } else {
        // Cek keunikan nama_pengguna
        $stmt = $conn->prepare("SELECT id_pelanggan FROM pelanggan WHERE nama_pengguna = ?");
        $stmt->bind_param("s", $nama_pengguna);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $pesan_error = "Nama pengguna sudah terdaftar. Silakan pilih nama pengguna lain.";
            $stmt->close();
        } else {
            $stmt->close();

            // Hash kata sandi
            $hashed_password = password_hash($kata_sandi, PASSWORD_DEFAULT);

            // Masukkan data pelanggan baru ke database
            $stmt = $conn->prepare("INSERT INTO pelanggan (nama_lengkap, nama_pengguna, nomor_wa, kata_sandi) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama_lengkap, $nama_pengguna, $nomor_wa, $hashed_password);

            if ($stmt->execute()) {
                $stmt->close();
                // Alihkan ke masuk.php dengan status terdaftar
                header("Location: masuk.php?status=terdaftar");
                exit;
            } else {
                $pesan_error = "Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi.";
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - Olin's Cake</title>
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
                <p>"Nikmati pengalaman memesan kue pre-order buatan rumah yang dipanggang segar dengan cinta khusus untuk Anda."</p>
                <span>Freshly Baked with Love</span>
            </div>
            
            <div class="auth-footer-tag">
                &copy; <?= date('Y') ?> Olin's Cake
            </div>
        </div>
        
        <!-- Panel Form Daftar -->
        <div class="auth-side-form">
            <div class="auth-form-wrapper">
                <a href="index.php" class="auth-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
                </a>
                
                <div class="auth-form-header">
                    <h1>Daftar Akun Baru</h1>
                    <p>Dapatkan kemudahan pre-order kue premium buatan rumah sekarang.</p>
                </div>
                
                <?php if (!empty($pesan_error)): ?>
                    <div class="auth-alert auth-alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($pesan_error) ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="daftar.php" method="POST">
                    <div class="contact-form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="contact-form-control" placeholder="Contoh: Ratih Ningsih" value="<?= isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : '' ?>" required>
                    </div>

                    <div class="contact-form-group">
                        <label for="nama_pengguna">Nama Pengguna (Username)</label>
                        <input type="text" id="nama_pengguna" name="nama_pengguna" class="contact-form-control" placeholder="Contoh: ratih_ningsih" value="<?= isset($_POST['nama_pengguna']) ? htmlspecialchars($_POST['nama_pengguna']) : '' ?>" required autocomplete="username">
                    </div>

                    <div class="contact-form-group">
                        <label for="nomor_wa">Nomor WhatsApp</label>
                        <input type="text" id="nomor_wa" name="nomor_wa" class="contact-form-control" placeholder="Contoh: 081234567890" value="<?= isset($_POST['nomor_wa']) ? htmlspecialchars($_POST['nomor_wa']) : '' ?>" required>
                    </div>
                    
                    <div class="contact-form-group">
                        <label for="kata_sandi">Kata Sandi (Min. 8 Karakter)</label>
                        <input type="password" id="kata_sandi" name="kata_sandi" class="contact-form-control" placeholder="Buat kata sandi baru" minlength="8" required autocomplete="new-password">
                    </div>

                    <div class="contact-form-group">
                        <label for="konfirmasi_kata_sandi">Konfirmasi Kata Sandi</label>
                        <input type="password" id="konfirmasi_kata_sandi" name="konfirmasi_kata_sandi" class="contact-form-control" placeholder="Ulangi kata sandi baru" required autocomplete="new-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        Daftar Akun <i class="fa-solid fa-user-plus" style="margin-left: 8px;"></i>
                    </button>
                </form>
                
                <div class="modal-footer-text">
                    Sudah memiliki akun? <a href="masuk.php">Masuk di Sini</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
