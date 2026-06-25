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

// Ambil ID Pesanan dari URL
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_pesanan <= 0) {
    header("Location: pesanan_saya.php");
    exit;
}

// Ambil detail pesanan dan pastikan milik pelanggan aktif
$query = "SELECT * FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id_pesanan, $id_pelanggan);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    // Pesanan tidak ditemukan atau bukan milik user
    header("Location: pesanan_saya.php");
    exit;
}

// Inisialisasi variabel status
$status_pesanan = $order['status_pesanan'];
$status_pembayaran = $order['status_pembayaran'];
$batas_pembayaran = $order['batas_pembayaran'];

// Logika Auto-Expiration: Cek apakah batas waktu pembayaran sudah lewat
$is_expired = false;
if ($status_pembayaran === 'Belum Dibayar' && $status_pesanan === 'Menunggu Pembayaran') {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $batas = new DateTime($batas_pembayaran, new DateTimeZone('Asia/Jakarta'));
    
    // Fallback jika timezone PHP server berbeda dengan database
    $now_ts = time();
    $batas_ts = strtotime($batas_pembayaran);
    
    if ($now_ts > $batas_ts) {
        $is_expired = true;
        // Update status di database secara otomatis
        $up_stmt = $conn->prepare("UPDATE pesanan SET status_pesanan = 'Kedaluwarsa', status_pembayaran = 'Kedaluwarsa' WHERE id_pesanan = ?");
        $up_stmt->bind_param("i", $id_pesanan);
        $up_stmt->execute();
        $up_stmt->close();
        
        $status_pesanan = 'Kedaluwarsa';
        $status_pembayaran = 'Kedaluwarsa';
    }
}

if ($status_pesanan === 'Kedaluwarsa' || $status_pesanan === 'Dibatalkan' || $status_pembayaran === 'Kedaluwarsa') {
    $is_expired = true;
}

// Ambil rincian produk pesanan
$detail_query = "SELECT dp.jumlah, dp.harga_satuan, p.nama_produk, p.gambar, p.ukuran 
                 FROM detail_pesanan dp 
                 JOIN produk p ON dp.id_produk = p.id_produk 
                 WHERE dp.id_pesanan = ?";
$stmt_d = $conn->prepare($detail_query);
$stmt_d->bind_param("i", $id_pesanan);
$stmt_d->execute();
$d_result = $stmt_d->get_result();
$order_items = [];
while ($row = $d_result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt_d->close();

// Notifikasi Sukses dari session
$notifikasi_sukses = "";
if (isset($_SESSION['sukses_pembayaran'])) {
    $notifikasi_sukses = $_SESSION['sukses_pembayaran'];
    unset($_SESSION['sukses_pembayaran']);
}

// Proses Upload Bukti Pembayaran (POST)
$error_upload = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload'])) {
    if ($is_expired) {
        $error_upload = "Batas waktu pembayaran telah berakhir. Pesanan dibatalkan secara otomatis.";
    } elseif ($status_pembayaran !== 'Belum Dibayar') {
        $error_upload = "Pembayaran untuk pesanan ini sudah dilakukan atau sedang dalam proses verifikasi.";
    } else {
        $metode = isset($_POST['metode_pembayaran']) ? trim($_POST['metode_pembayaran']) : '';
        if (empty($metode)) {
            $error_upload = "Silakan pilih metode pembayaran terlebih dahulu.";
        } elseif (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_NO_FILE) {
            $error_upload = "Berkas bukti pembayaran wajib diunggah.";
        } else {
            $file = $_FILES['bukti_pembayaran'];
            $filename = $file['name'];
            $filetmp = $file['tmp_name'];
            $filesize = $file['size'];
            $fileerror = $file['error'];
            
            // Validasi format file
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png'];
            
            // Validasi mime-type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filetmp);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/png'];
            
            if ($fileerror !== UPLOAD_ERR_OK) {
                $error_upload = "Terjadi kesalahan saat mengunggah berkas.";
            } elseif (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
                $error_upload = "Format berkas tidak valid. Hanya menerima file JPG, JPEG, dan PNG.";
            } elseif ($filesize > 2 * 1024 * 1024) { // 2MB
                $error_upload = "Ukuran berkas melebihi batas maksimal 2 MB.";
            } else {
                // Buat folder jika belum ada (safety)
                $upload_dir = 'assets/uploads/bukti_pembayaran/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Beri nama file unik
                $new_filename = 'bukti_' . $id_pesanan . '_' . time() . '.' . $ext;
                $dest_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($filetmp, $dest_path)) {
                    // Update database
                    $stmt_update = $conn->prepare("UPDATE pesanan SET status_pesanan = 'Menunggu Verifikasi', status_pembayaran = 'Belum Dibayar', metode_pembayaran = ?, bukti_pembayaran = ? WHERE id_pesanan = ?");
                    $stmt_update->bind_param("ssi", $metode, $new_filename, $id_pesanan);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['sukses_pembayaran'] = "Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi admin.";
                        $stmt_update->close();
                        
                        // Redirect untuk menghindari double submission
                        header("Location: pembayaran.php?id=" . $id_pesanan);
                        exit;
                    } else {
                        $error_upload = "Gagal memperbarui status transaksi di database.";
                        $stmt_update->close();
                    }
                } else {
                    $error_upload = "Gagal menyimpan berkas di server.";
                }
            }
        }
    }
}

// Generate order code
$kode_order = "OLN-" . (10000 + $order['id_pesanan']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Pesanan <?= $kode_order ?> - Olin's Cake</title>
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
                <li class="dropdown-container">
                    <a href="index.php" class="dropdown-trigger" style="text-decoration: none;">
                        Beranda <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </a>
                    <ul class="dropdown-menu-list">
                        <li><a href="index.php#tentang" class="dropdown-menu-item">Tentang Kami</a></li>
                        <li><a href="index.php#cara-pesan" class="dropdown-menu-item">Cara Pesan</a></li>
                    </ul>
                </li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan Saya</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Halaman Pembayaran Section -->
    <section class="payment-section">
        <div class="container">
            
            <div class="payment-title-area">
                <a href="pesanan_saya.php" class="back-link">
                    <i class="fa-solid fa-chevron-left"></i> Riwayat Pesanan Saya
                </a>
                <h1>Selesaikan Pembayaran Anda</h1>
                <p>Silakan selesaikan transaksi untuk pesanan <strong><?= $kode_order ?></strong> sebelum batas waktu berakhir.</p>
            </div>

            <!-- Pesan Alert Status -->
            <?php if (!empty($notifikasi_sukses)): ?>
                <div class="payment-alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div class="alert-text">
                        <strong>Kirim Bukti Sukses!</strong>
                        <p><?= htmlspecialchars($notifikasi_sukses) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_upload)): ?>
                <div class="payment-alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div class="alert-text">
                        <strong>Terjadi Kesalahan</strong>
                        <p><?= htmlspecialchars($error_upload) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="payment-grid">
                
                <!-- Kolom Kiri: Alur Pembayaran & Upload -->
                <div class="payment-main-col">

                    <!-- 1. Countdown Pembayaran (Hanya jika belum bayar & belum expired) -->
                    <?php if ($status_pembayaran === 'Belum Dibayar' && !$is_expired): ?>
                        <div class="payment-card timer-card">
                            <div class="timer-label">Sisa Waktu Pembayaran</div>
                            <div class="timer-countdown" id="timer-box">
                                <div class="timer-segment">
                                    <span id="hours" class="timer-number">23</span>
                                    <span class="timer-unit">Jam</span>
                                </div>
                                <div class="timer-divider">:</div>
                                <div class="timer-segment">
                                    <span id="minutes" class="timer-number">59</span>
                                    <span class="timer-unit">Menit</span>
                                </div>
                                <div class="timer-divider">:</div>
                                <div class="timer-segment">
                                    <span id="seconds" class="timer-number">59</span>
                                    <span class="timer-unit">Detik</span>
                                </div>
                            </div>
                            <p class="timer-subtext">Segera bayar sebelum tanggal <strong><?= date('d M Y, H:i', strtotime($batas_pembayaran)) ?> WIB</strong></p>
                        </div>
                    <?php elseif ($is_expired): ?>
                        <!-- Keadaan Kedaluwarsa -->
                        <div class="payment-card timer-card expired-card">
                            <i class="fa-solid fa-circle-xmark expired-icon"></i>
                            <h3>Waktu Pembayaran Habis</h3>
                            <p class="expired-msg">Batas waktu pembayaran telah berakhir. Pesanan dibatalkan secara otomatis.</p>
                        </div>
                    <?php else: ?>
                        <!-- Sudah Dibayar / Menunggu Verifikasi -->
                        <div class="payment-card success-status-card">
                            <i class="fa-solid fa-circle-check success-icon"></i>
                            <h3><?= $status_pembayaran === 'Sudah Dibayar' ? 'Pembayaran Sukses' : 'Menunggu Verifikasi Admin' ?></h3>
                            <p class="status-msg">
                                <?php if ($status_pembayaran === 'Sudah Dibayar'): ?>
                                    Pembayaran Anda telah diverifikasi oleh admin. Pesanan Anda saat ini sedang diproses.
                                <?php else: ?>
                                    Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi admin. Kami akan segera memperbarui status pesanan Anda.
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($order['bukti_pembayaran'])): ?>
                                <div class="uploaded-proof-preview">
                                    <span>Bukti Pembayaran Diunggah:</span>
                                    <a href="assets/uploads/bukti_pembayaran/<?= htmlspecialchars($order['bukti_pembayaran']) ?>" target="_blank" class="btn-view-proof">
                                        <i class="fa-solid fa-image"></i> Lihat Bukti Pembayaran
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 2. Total Pembayaran Card -->
                    <div class="payment-card total-card">
                        <span class="total-label">Total Pembayaran</span>
                        <div class="total-amount">
                            Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?>
                        </div>
                        <span class="total-note">Sudah termasuk ongkos kirim (jika diantar)</span>
                    </div>

                    <!-- 3. Form & Metode Pembayaran (Hanya jika belum bayar & belum expired) -->
                    <?php if ($status_pembayaran === 'Belum Dibayar' && !$is_expired): ?>
                        <form action="pembayaran.php?id=<?= $id_pesanan ?>" method="POST" enctype="multipart/form-data" id="payment-form">
                            <input type="hidden" name="action_upload" value="1">
                            
                            <!-- Card Pilihan Metode Pembayaran -->
                            <div class="payment-card">
                                <div class="card-header">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <h2>Pilih Metode Pembayaran</h2>
                                </div>
                                <div class="card-body">
                                    <div class="methods-selection-grid">
                                        <!-- Metode Transfer Bank -->
                                        <label class="pay-method-card active" id="method-bank-label">
                                            <input type="radio" name="metode_pembayaran" value="Transfer Bank" checked class="pay-radio" onchange="togglePaymentMethod(this.value)">
                                            <div class="method-card-content">
                                                <i class="fa-solid fa-building-columns"></i>
                                                <span class="title">Transfer Bank</span>
                                                <span class="desc">Transfer manual melalui ATM/M-Banking</span>
                                            </div>
                                        </label>

                                        <!-- Metode QRIS -->
                                        <label class="pay-method-card" id="method-qris-label">
                                            <input type="radio" name="metode_pembayaran" value="QRIS" class="pay-radio" onchange="togglePaymentMethod(this.value)">
                                            <div class="method-card-content">
                                                <i class="fa-solid fa-qrcode"></i>
                                                <span class="title">QRIS E-Wallet</span>
                                                <span class="desc">Scan kode QR menggunakan e-wallet Anda</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Area Detail Rekening Bank / QRIS -->
                            <div class="payment-card" id="payment-details-card">
                                
                                <!-- Detail Transfer Bank -->
                                <div id="bank-transfer-details" class="details-pane">
                                    <h3>Informasi Rekening Transfer</h3>
                                    <p class="pane-intro">Silakan transfer nominal pembayaran ke rekening resmi Olin's Cake berikut:</p>
                                    
                                    <div class="bank-account-card">
                                        <div class="bank-brand">
                                            <i class="fa-solid fa-building-columns"></i> Bank BCA
                                        </div>
                                        <div class="account-number-row">
                                            <span id="rekening-number" class="number">1234567890</span>
                                            <button type="button" class="btn-copy-account" onclick="copyAccountNo()">
                                                <i class="fa-regular fa-copy"></i> <span id="copy-btn-text">Salin</span>
                                            </button>
                                        </div>
                                        <div class="account-holder">
                                            a.n. <strong>Olin's Cake</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detail QRIS -->
                                <div id="qris-details" class="details-pane" style="display: none;">
                                    <h3>Scan QRIS Olin's Cake</h3>
                                    <p class="pane-intro">Pindai kode QRIS di bawah menggunakan GoPay, OVO, Dana, LinkAja, atau Mobile Banking Anda.</p>
                                    
                                    <div class="qris-code-container">
                                        <!-- Sharp vector placeholder QR code -->
                                        <svg class="qris-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="100" height="100" fill="#ffffff" rx="4"/>
                                            <!-- QR Border blocks -->
                                            <rect x="5" y="5" width="25" height="25" fill="#2C1B10" />
                                            <rect x="9" y="9" width="17" height="17" fill="#ffffff" />
                                            <rect x="13" y="13" width="9" height="9" fill="#2C1B10" />
                                            
                                            <rect x="70" y="5" width="25" height="25" fill="#2C1B10" />
                                            <rect x="74" y="9" width="17" height="17" fill="#ffffff" />
                                            <rect x="78" y="13" width="9" height="9" fill="#2C1B10" />

                                            <rect x="5" y="70" width="25" height="25" fill="#2C1B10" />
                                            <rect x="9" y="74" width="17" height="17" fill="#ffffff" />
                                            <rect x="13" y="78" width="9" height="9" fill="#2C1B10" />

                                            <!-- Random QR Blocks to simulate a QR code -->
                                            <rect x="35" y="5" width="5" height="15" fill="#2C1B10"/>
                                            <rect x="45" y="10" width="10" height="5" fill="#2C1B10"/>
                                            <rect x="60" y="5" width="5" height="5" fill="#2C1B10"/>
                                            <rect x="35" y="25" width="15" height="5" fill="#2C1B10"/>
                                            <rect x="55" y="20" width="10" height="10" fill="#2C1B10"/>
                                            <rect x="40" y="35" width="5" height="15" fill="#2C1B10"/>
                                            <rect x="10" y="35" width="15" height="5" fill="#2C1B10"/>
                                            <rect x="20" y="45" width="10" height="10" fill="#2C1B10"/>
                                            <rect x="5" y="60" width="20" height="5" fill="#2C1B10"/>
                                            
                                            <rect x="35" y="70" width="5" height="25" fill="#2C1B10"/>
                                            <rect x="45" y="75" width="15" height="5" fill="#2C1B10"/>
                                            <rect x="50" y="85" width="10" height="10" fill="#2C1B10"/>
                                            <rect x="70" y="35" width="15" height="5" fill="#2C1B10"/>
                                            <rect x="75" y="45" width="20" height="5" fill="#2C1B10"/>
                                            <rect x="85" y="55" width="10" height="10" fill="#2C1B10"/>
                                            <rect x="65" y="70" width="5" height="15" fill="#2C1B10"/>
                                            <rect x="80" y="75" width="15" height="15" fill="#2C1B10"/>
                                            <rect x="75" y="90" width="5" height="5" fill="#2C1B10"/>

                                            <!-- Center logo block -->
                                            <rect x="42" y="42" width="16" height="16" fill="#743014" rx="2"/>
                                            <text x="50" y="52" font-size="8" font-weight="bold" fill="#ffffff" text-anchor="middle">QRIS</text>
                                        </svg>
                                        <div class="qris-amount-badge">
                                            Nominal: <strong>Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="qris-instructions">
                                        <h4>Cara Pembayaran QRIS:</h4>
                                        <ol>
                                            <li>Buka aplikasi dompet digital (GoPay, OVO, Dana, LinkAja) atau Mobile Banking Anda.</li>
                                            <li>Pilih menu <strong>Pindai / Scan QRIS</strong>.</li>
                                            <li>Arahkan kamera ke kode QRIS di atas atau upload tangkapan layar kode ini.</li>
                                            <li>Pastikan nama merchant yang muncul adalah <strong>Olin's Cake</strong>.</li>
                                            <li>Masukkan nominal bayar persis <strong>Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></strong>.</li>
                                            <li>Selesaikan transaksi dan ambil bukti pembayarannya.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Upload Bukti Pembayaran -->
                            <div class="payment-card">
                                <div class="card-header">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <h2>Unggah Bukti Pembayaran</h2>
                                </div>
                                <div class="card-body">
                                    <div class="upload-area-wrapper">
                                        <label for="bukti_pembayaran" class="upload-dropzone" id="dropzone">
                                            <i class="fa-regular fa-image upload-icon"></i>
                                            <span class="upload-text">Pilih Gambar Bukti Bayar</span>
                                            <span class="upload-desc">Format: JPG, JPEG, PNG (Maksimal 2 MB)</span>
                                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/png, image/jpeg, image/jpg" style="display: none;" onchange="previewImage(this)">
                                        </label>
                                        
                                        <!-- Preview Box -->
                                        <div class="image-preview-container" id="preview-box" style="display: none;">
                                            <h4 class="preview-title">Pratinjau Gambar:</h4>
                                            <div class="img-frame">
                                                <img id="img-preview" src="#" alt="Preview Bukti">
                                                <button type="button" class="btn-remove-preview" onclick="removePreview()">
                                                    <i class="fa-solid fa-trash-can"></i> Hapus Gambar
                                                </button>
                                            </div>
                                            <div class="file-info" id="file-info-text">nama_file.png (1.2 MB)</div>
                                        </div>
                                    </div>

                                    <button type="submit" id="btn-submit-payment" class="btn btn-primary btn-submit-payment-proof" style="width: 100%; margin-top: 24px;" disabled>
                                        Kirim Bukti Pembayaran <i class="fa-solid fa-paper-plane" style="margin-left: 8px;"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- 6. Card Informasi Penting -->
                    <div class="payment-card info-important-card">
                        <h3><i class="fa-solid fa-circle-info text-primary"></i> Informasi Penting</h3>
                        <ul class="info-list">
                            <li>📌 Pembayaran harus dilakukan dalam waktu 1×24 jam setelah pesanan dibuat.</li>
                            <li>📌 Bukti pembayaran wajib diunggah untuk memproses pesanan.</li>
                            <li>📌 Pesanan akan mulai diproses setelah pembayaran diverifikasi oleh admin.</li>
                            <li>📌 Pesanan yang tidak dibayar dalam batas waktu akan otomatis dibatalkan oleh sistem.</li>
                        </ul>
                    </div>

                </div>

                <!-- Kolom Kanan: Ringkasan Pesanan -->
                <div class="payment-side-col">
                    <div class="payment-card summary-order-card">
                        <h3>Ringkasan Pesanan</h3>
                        <div class="summary-meta-rows">
                            <div class="meta-row">
                                <span>No. Pesanan:</span>
                                <strong><?= $kode_order ?></strong>
                            </div>
                            <div class="meta-row">
                                <span>Tanggal Pesan:</span>
                                <span><?= date('d/m/Y H:i', strtotime($order['dibuat_pada'])) ?></span>
                            </div>
                            <div class="meta-row">
                                <span>Penerima:</span>
                                <strong><?= htmlspecialchars($order['nama_penerima']) ?></strong>
                            </div>
                            <div class="meta-row">
                                <span>Metode:</span>
                                <span><?= htmlspecialchars($order['metode_pengiriman']) ?></span>
                            </div>
                            <div class="meta-row">
                                <span>Tgl. Pengiriman:</span>
                                <strong><?= date('d/m/Y', strtotime($order['tanggal_pengiriman'])) ?></strong>
                            </div>
                            <div class="meta-row">
                                <span>Jam Pengiriman:</span>
                                <span>Jam <?= htmlspecialchars($order['waktu_pengiriman']) ?> WIB</span>
                            </div>
                        </div>

                        <div class="side-product-list">
                            <h4>Produk yang Dipesan:</h4>
                            <?php foreach ($order_items as $item): ?>
                                <div class="side-product-row">
                                    <div class="product-info">
                                        <span class="name"><?= htmlspecialchars($item['nama_produk']) ?></span>
                                        <span class="spec"><?= htmlspecialchars($item['ukuran']) ?> &times; <?= $item['jumlah'] ?></span>
                                    </div>
                                    <div class="product-price">
                                        Rp <?= number_format($item['harga_satuan'] * $item['jumlah'], 0, ',', '.') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="side-costs-breakdown">
                            <div class="cost-row">
                                <span>Total Produk</span>
                                <span>Rp <?= number_format($order['total_bayar'] - $order['ongkos_kirim'], 0, ',', '.') ?></span>
                            </div>
                            <div class="cost-row">
                                <span>Ongkos Kirim</span>
                                <span>Rp <?= number_format($order['ongkos_kirim'], 0, ',', '.') ?></span>
                            </div>
                            <div class="cost-row grand-total-row">
                                <span>Total Pembayaran</span>
                                <span class="price-val">Rp <?= number_format($order['total_bayar'], 0, ',', '.') ?></span>
                            </div>
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
                    <a href="#" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
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

        // Toggle Payment Method Tampilan
        function togglePaymentMethod(val) {
            const bankDetails = document.getElementById('bank-transfer-details');
            const qrisDetails = document.getElementById('qris-details');
            
            const bankLabel = document.getElementById('method-bank-label');
            const qrisLabel = document.getElementById('method-qris-label');

            if (val === 'Transfer Bank') {
                bankDetails.style.display = 'block';
                qrisDetails.style.display = 'none';
                bankLabel.classList.add('active');
                qrisLabel.classList.remove('active');
            } else {
                bankDetails.style.display = 'none';
                qrisDetails.style.display = 'block';
                bankLabel.classList.remove('active');
                qrisLabel.classList.add('active');
            }
        }

        // Salin Rekening BCA ke Clipboard
        function copyAccountNo() {
            const rekeningNum = document.getElementById('rekening-number').innerText;
            navigator.clipboard.writeText(rekeningNum).then(() => {
                const copyBtnText = document.getElementById('copy-btn-text');
                copyBtnText.innerText = "Tersalin!";
                
                setTimeout(() => {
                    copyBtnText.innerText = "Salin";
                }, 2000);
            }).catch(err => {
                console.error("Gagal menyalin text: ", err);
            });
        }

        // Preview File Gambar Bukti Pembayaran
        function previewImage(input) {
            const file = input.files[0];
            const dropzone = document.getElementById('dropzone');
            const previewBox = document.getElementById('preview-box');
            const imgPreview = document.getElementById('img-preview');
            const fileInfoText = document.getElementById('file-info-text');
            const submitBtn = document.getElementById('btn-submit-payment');

            if (file) {
                // Validasi ukuran berkas (2MB = 2097152 Bytes)
                if (file.size > 2 * 1024 * 1024) {
                    alert("Ukuran berkas melebihi batas maksimal 2 MB. Silakan kompres atau pilih file lain.");
                    removePreview();
                    return;
                }

                // Validasi format ekstensi
                const fileType = file.type;
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(fileType)) {
                    alert("Format berkas tidak valid. Hanya menerima file gambar JPG, JPEG, dan PNG.");
                    removePreview();
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                    previewBox.style.display = 'block';
                    dropzone.style.display = 'none';
                    
                    // Tampilkan info file
                    const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
                    fileInfoText.innerText = `${file.name} (${sizeInMb} MB)`;
                    
                    // Aktifkan tombol kirim
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
                reader.readAsDataURL(file);
            }
        }

        // Remove Preview Image
        function removePreview() {
            const input = document.getElementById('bukti_pembayaran');
            const dropzone = document.getElementById('dropzone');
            const previewBox = document.getElementById('preview-box');
            const imgPreview = document.getElementById('img-preview');
            const submitBtn = document.getElementById('btn-submit-payment');

            input.value = ""; // Reset input file
            imgPreview.src = "#";
            previewBox.style.display = 'none';
            dropzone.style.display = 'flex';
            
            // Matikan tombol kirim
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }

        // Countdown Timer Real-time (1x24 Jam)
        <?php if ($status_pembayaran === 'Belum Dibayar' && !$is_expired): ?>
        const targetDate = new Date("<?= date('c', strtotime($batas_pembayaran)) ?>").getTime();
        
        const countdownTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                clearInterval(countdownTimer);
                // Waktu habis, reload halaman untuk update status
                window.location.reload();
                return;
            }

            // Hitung jam, menit, detik
            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Update DOM
            document.getElementById("hours").innerText = String(hours).padStart(2, '0');
            document.getElementById("minutes").innerText = String(minutes).padStart(2, '0');
            document.getElementById("seconds").innerText = String(seconds).padStart(2, '0');
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
