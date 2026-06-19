<?php
// Start Session
session_start();

// Include Database Connection
require_once 'config/database.php';

// Set timezone default
date_default_timezone_set('Asia/Jakarta');

$msg_success = "";
$msg_error = "";

// 1. EXTRACT & VALIDASI PARAMETER ID_PRODUK
$id_produk = isset($_GET['id_produk']) ? intval($_GET['id_produk']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_testi'])) {
    $id_produk = intval($_POST['id_produk']);
}

if ($id_produk <= 0) {
    header("Location: produk.php");
    exit;
}

// Ambil data produk
$prod_stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
$prod_stmt->bind_param("i", $id_produk);
$prod_stmt->execute();
$product = $prod_stmt->get_result()->fetch_assoc();
$prod_stmt->close();

if (!$product) {
    header("Location: produk.php");
    exit;
}

// Handle message dari redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $msg_success = "Testimoni Anda berhasil dikirim dan ditambahkan! Terima kasih atas ulasan Anda.";
}

// 2. PROSES KIRIM TESTIMONI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_testi'])) {
    $is_ajax = isset($_POST['is_ajax']);
    
    if (!isset($_SESSION['pelanggan_id'])) {
        $msg_error = "Anda harus masuk terlebih dahulu untuk mengirim testimoni.";
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg_error]);
            exit;
        }
    } else {
        $id_pelanggan = intval($_SESSION['pelanggan_id']);
        $id_pesanan = intval($_POST['id_pesanan']);
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $isi_testimoni = isset($_POST['isi_testimoni']) ? trim($_POST['isi_testimoni']) : '';
        
        // Validasi input wajib
        if ($id_produk <= 0 || $id_pesanan <= 0 || $rating < 1 || $rating > 5 || empty($isi_testimoni)) {
            $msg_error = "Harap isi rating dan isi ulasan Anda.";
        } else {
            // Verifikasi apakah pelanggan memiliki hak untuk mengulas produk ini di pesanan ini (status Selesai & belum pernah mengulas)
            $check_query = "SELECT dp.id_produk, o.id_pesanan 
                            FROM detail_pesanan dp
                            JOIN pesanan o ON dp.id_pesanan = o.id_pesanan
                            WHERE o.id_pelanggan = ? AND o.id_pesanan = ? AND dp.id_produk = ? AND o.status_pesanan = 'Selesai'
                              AND NOT EXISTS (
                                  SELECT 1 FROM testimoni t 
                                  WHERE t.id_pelanggan = o.id_pelanggan 
                                    AND t.id_produk = dp.id_produk 
                                    AND t.id_pesanan = dp.id_pesanan
                              )";
            $stmt_check = $conn->prepare($check_query);
            $stmt_check->bind_param("iii", $id_pelanggan, $id_pesanan, $id_produk);
            $stmt_check->execute();
            $check_res = $stmt_check->get_result();
            
            if ($check_res->num_rows === 0) {
                $msg_error = "Anda tidak memiliki akses untuk mengulas produk ini pada pesanan terpilih, atau Anda sudah pernah mengulasnya.";
            } else {
                // Ambil Nama Lengkap Pelanggan
                $pel_query = "SELECT nama_lengkap FROM pelanggan WHERE id_pelanggan = ?";
                $stmt_pel = $conn->prepare($pel_query);
                $stmt_pel->bind_param("i", $id_pelanggan);
                $stmt_pel->execute();
                $pel_res = $stmt_pel->get_result()->fetch_assoc();
                $nama_lengkap = $pel_res['nama_lengkap'];
                $stmt_pel->close();
                
                // Hitung Inisial Avatar
                $words = explode(" ", $nama_lengkap);
                $avatar_initial = "";
                if (count($words) >= 2) {
                    $avatar_initial = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                } else {
                    $avatar_initial = strtoupper(substr($nama_lengkap, 0, min(2, strlen($nama_lengkap))));
                }
                
                // Proses Unggah Foto (Opsional)
                $gambar_name = null;
                if (isset($_FILES['foto_testimoni']) && $_FILES['foto_testimoni']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['foto_testimoni'];
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
                        $msg_error = "Format file tidak didukung. Hanya file JPG, JPEG, dan PNG yang diperbolehkan.";
                    } elseif ($file_size > 2 * 1024 * 1024) {
                        $msg_error = "Ukuran file terlalu besar. Maksimal ukuran file adalah 2 MB.";
                    } else {
                        // Buat nama unik
                        $gambar_name = 'testi_' . $id_pelanggan . '_' . $id_produk . '_' . $id_pesanan . '_' . uniqid() . '.' . $file_ext;
                        $dest_path = __DIR__ . '/assets/uploads/testimoni/' . $gambar_name;
                        
                        if (!move_uploaded_file($file_tmp, $dest_path)) {
                            $msg_error = "Gagal mengunggah foto. Silakan coba lagi.";
                            $gambar_name = null;
                        }
                    }
                }
                
                // Jika tidak ada error file, simpan ke DB
                if (empty($msg_error)) {
                    $insert_query = "INSERT INTO testimoni (id_pelanggan, id_produk, id_pesanan, nama_lengkap, pekerjaan, isi_testimoni, avatar_initial, rating, gambar, status) 
                                     VALUES (?, ?, ?, ?, 'Pelanggan', ?, ?, ?, ?, 'Aktif')";
                    $stmt_ins = $conn->prepare($insert_query);
                    $stmt_ins->bind_param("iiisssis", $id_pelanggan, $id_produk, $id_pesanan, $nama_lengkap, $isi_testimoni, $avatar_initial, $rating, $gambar_name);
                    
                    if ($stmt_ins->execute()) {
                        $new_id = $stmt_ins->insert_id;
                        $stmt_ins->close();
                        $stmt_check->close();
                        
                        // Ambil data testimoni yang baru saja dimasukkan
                        $get_new = $conn->prepare("SELECT * FROM testimoni WHERE id_testimoni = ?");
                        $get_new->bind_param("i", $new_id);
                        $get_new->execute();
                        $new_review = $get_new->get_result()->fetch_assoc();
                        $get_new->close();

                        // Ambil stats terbaru
                        $stats_query2 = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM testimoni WHERE id_produk = ? AND status = 'Aktif'";
                        $stmt_stats2 = $conn->prepare($stats_query2);
                        $stmt_stats2->bind_param("i", $id_produk);
                        $stmt_stats2->execute();
                        $stats_res2 = $stmt_stats2->get_result()->fetch_assoc();
                        $stmt_stats2->close();

                        $new_total_reviews = intval($stats_res2['total_reviews']);
                        $new_avg_rating = $new_total_reviews > 0 ? round(floatval($stats_res2['avg_rating']), 1) : 0.0;

                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => 'Testimoni Anda berhasil dikirim!',
                                'review' => [
                                    'nama_lengkap' => $new_review['nama_lengkap'],
                                    'pekerjaan' => $new_review['pekerjaan'],
                                    'isi_testimoni' => $new_review['isi_testimoni'],
                                    'avatar_initial' => $new_review['avatar_initial'],
                                    'rating' => intval($new_review['rating']),
                                    'gambar' => $new_review['gambar'],
                                    'dibuat_pada_formatted' => formatIndonesianDate($new_review['dibuat_pada'])
                                ],
                                'stats' => [
                                    'total_reviews' => $new_total_reviews,
                                    'avg_rating' => number_format($new_avg_rating, 1, ',', '.'),
                                    'avg_rating_raw' => $new_avg_rating,
                                    'stars_html' => renderStars($new_avg_rating),
                                    'satisfaction_text' => getSatisfactionText($new_avg_rating, $new_total_reviews)
                                ]
                            ]);
                            exit;
                        }

                        // Redirect ke halaman testimoni produk ini dengan sukses
                        header("Location: testimoni.php?id_produk=" . $id_produk . "&msg=success");
                        exit;
                    } else {
                        $msg_error = "Gagal menyimpan ulasan ke database: " . $conn->error;
                    }
                }
            }
            $stmt_check->close();
        }
    }
    
    // Jika ada error dan AJAX request
    if (!empty($msg_error) && $is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $msg_error
        ]);
        exit;
    }
}

// 3. AMBIL RINGKASAN STATISTIK TESTIMONI PRODUK INI
$stats_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM testimoni WHERE id_produk = ? AND status = 'Aktif'";
$stmt_stats = $conn->prepare($stats_query);
$stmt_stats->bind_param("i", $id_produk);
$stmt_stats->execute();
$stats_res = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

$total_reviews = intval($stats_res['total_reviews']);
$avg_rating = $total_reviews > 0 ? round(floatval($stats_res['avg_rating']), 1) : 0.0;

// Helper function to render stars
function renderStars($rating) {
    $html = '';
    $floor = floor($rating);
    $diff = $rating - $floor;
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $floor) {
            $html .= '<i class="fa-solid fa-star"></i>';
        } elseif ($i == $floor + 1 && $diff >= 0.4) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
    }
    return $html;
}

// Helper satisfaction text
function getSatisfactionText($rating, $count) {
    if ($count == 0) {
        return "Belum ada ulasan untuk kue premium ini. Jadilah yang pertama memberikan ulasan!";
    }
    if ($rating >= 4.7) {
        return "Sangat Puas! Kebanyakan pelanggan sangat menyukai cita rasa kue premium ini.";
    } elseif ($rating >= 4.3) {
        return "Puas! Sebagian besar pembeli puas dengan tekstur dan rasa kue ini.";
    } elseif ($rating >= 4.0) {
        return "Memuaskan! Kue ini direkomendasikan oleh banyak pelanggan kami.";
    } else {
        return "Cukup Memuaskan! Kami berterima kasih atas setiap masukan untuk meningkatkan resep kue ini.";
    }
}

// Helper date format
function formatIndonesianDate($dateStr) {
    $timestamp = strtotime($dateStr);
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    return "$day " . $months[$month] . " $year";
}

// 4. AMBIL DAFTAR TESTIMONI AKTIF KHUSUS PRODUK INI
$testi_query = "SELECT * FROM testimoni WHERE id_produk = ? AND status = 'Aktif' ORDER BY dibuat_pada DESC";
$stmt_testi = $conn->prepare($testi_query);
$stmt_testi->bind_param("i", $id_produk);
$stmt_testi->execute();
$testi_result = $stmt_testi->get_result();
$testimonials = [];
while ($t_row = $testi_result->fetch_assoc()) {
    $testimonials[] = $t_row;
}
$stmt_testi->close();

// 5. AMBIL JADWAL PEMESANAN SELESAI YANG BELUM DIULAS KHUSUS PRODUK INI (JIKA LOGIN)
$pending_orders = [];
$target_pesan_id = 0;
$auto_open_modal = false;

if (isset($_SESSION['pelanggan_id'])) {
    $id_pelanggan = intval($_SESSION['pelanggan_id']);
    $pending_query = "SELECT dp.id_pesanan, o.tanggal_pengiriman
                      FROM detail_pesanan dp
                      JOIN pesanan o ON dp.id_pesanan = o.id_pesanan
                      WHERE o.id_pelanggan = ? AND dp.id_produk = ? AND o.status_pesanan = 'Selesai'
                        AND NOT EXISTS (
                          SELECT 1 FROM testimoni t
                          WHERE t.id_pelanggan = o.id_pelanggan
                            AND t.id_produk = dp.id_produk
                            AND t.id_pesanan = dp.id_pesanan
                        )
                      ORDER BY o.tanggal_pengiriman DESC";
    $stmt_pending = $conn->prepare($pending_query);
    $stmt_pending->bind_param("ii", $id_pelanggan, $id_produk);
    $stmt_pending->execute();
    $p_res = $stmt_pending->get_result();
    while ($p_row = $p_res->fetch_assoc()) {
        $pending_orders[] = $p_row;
    }
    $stmt_pending->close();
    
    // Penanganan auto modal jika diarahkan dari pesanan
    $auto_pesan_id = isset($_GET['id_pesanan']) ? intval($_GET['id_pesanan']) : 0;
    if ($auto_pesan_id > 0) {
        foreach ($pending_orders as $po) {
            if ($po['id_pesanan'] == $auto_pesan_id) {
                $target_pesan_id = $auto_pesan_id;
                $auto_open_modal = true;
                break;
            }
        }
    }
    
    // Default ke pesanan selesai pertama yang belum diulas
    if ($target_pesan_id == 0 && count($pending_orders) > 0) {
        $target_pesan_id = $pending_orders[0]['id_pesanan'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan <?= htmlspecialchars($product['nama_produk']) ?> - Olin's Cake</title>
    <meta name="description" content="Kumpulan ulasan tulus dari pelanggan kami untuk kue premium <?= htmlspecialchars($product['nama_produk']) ?>.">
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
                <?php if (isset($_SESSION['pelanggan_id'])): ?>
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
                    <li><a href="profil_saya.php" class="nav-link">Profil Saya</a></li>
                    <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="index.php#tentang" class="nav-link">Tentang Kami</a></li>
                    <li><a href="index.php#produk" class="nav-link">Produk Favorit</a></li>
                    <li><a href="index.php#cara-pesan" class="nav-link">Cara Pesan</a></li>
                    <li><a href="index.php#testimoni" class="nav-link">Testimoni</a></li>
                    <li><a href="index.php#hubungi" class="nav-link">Hubungi Kami</a></li>
                    <li class="nav-auth">
                        <a href="masuk.php" class="btn btn-outline btn-sm">Masuk</a>
                        <a href="daftar.php" class="btn btn-primary btn-sm">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Main Testimonials Page Content -->
    <section class="testi-page">
        <div class="container">

            <!-- Breadcrumbs -->
            <div class="detail-breadcrumb" style="margin-bottom: 24px;">
                <a href="index.php">Beranda</a> <span>/</span> <a href="produk.php">Katalog</a> <span>/</span> <a href="detail_produk.php?id=<?= $id_produk ?>"><?= htmlspecialchars($product['nama_produk']) ?></a> <span>/</span> <span>Ulasan Produk</span>
            </div>

            <!-- Notifikasi Alert Sukses / Error -->
            <?php if (!empty($msg_success)): ?>
                <div class="orders-alert-success" style="margin-bottom: 30px;">
                    <div class="alert-icon-circle"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="alert-text">
                        <strong>Berhasil!</strong>
                        <p><?= htmlspecialchars($msg_success) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($msg_error)): ?>
                <div class="orders-alert-success" style="background-color: #fef2f2; border-color: #fca5a5; margin-bottom: 30px;">
                    <div class="alert-icon-circle" style="background-color: #fee2e2; color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i></div>
                    <div class="alert-text">
                        <strong style="color: #991b1b;">Gagal!</strong>
                        <p style="color: #991b1b;"><?= htmlspecialchars($msg_error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TESTIMONIAL PRODUCT SUMMARY CARD -->
            <div class="testi-summary-wrapper" style="grid-template-columns: 1fr 1.2fr 1.2fr;">
                <div class="testi-summary-left" style="border-right: none; padding-right: 0;">
                    <img src="assets/images/<?= htmlspecialchars($product['gambar']) ?>" alt="<?= htmlspecialchars($product['nama_produk']) ?>" style="width: 100%; max-width: 140px; border-radius: var(--radius-sm); object-fit: cover; border: 1px solid rgba(68, 45, 28, 0.08); box-shadow: var(--shadow-sm);">
                </div>
                
                <div style="display: flex; flex-direction: column; justify-content: center; padding: 0 10px; border-right: 1px solid rgba(68, 45, 28, 0.08);">
                    <h1 class="testi-summary-title" style="font-size: 1.6rem; margin-bottom: 8px;"><?= htmlspecialchars($product['nama_produk']) ?></h1>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="testi-summary-stars" id="summary-stars" style="margin-bottom: 0; font-size: 1.25rem;">
                            <?= renderStars($avg_rating) ?>
                        </div>
                        <span id="summary-avg-rating-text" style="font-weight: 700; font-size: 1.1rem; color: var(--cowhide-cocoa);"><?= number_format($avg_rating, 1, ',', '.') ?> / 5</span>
                    </div>
                    <div class="testi-summary-total" id="summary-total-reviews-text" style="margin-top: 4px;">Berdasarkan <?= $total_reviews ?> ulasan pelanggan</div>
                </div>

                <div class="testi-summary-right" style="padding-left: 20px; justify-content: center;">
                    <div style="font-weight: 700; color: var(--cowhide-cocoa); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Tingkat Kepuasan</div>
                    <p class="testi-summary-desc" id="summary-satisfaction-text" style="font-size: 0.95rem; line-height: 1.5; color: var(--text-muted);">
                        <?= getSatisfactionText($avg_rating, $total_reviews) ?>
                    </p>
                </div>
            </div>

            <!-- CSS Breakpoint Overrides for 3-Column Summary Card -->
            <style>
                @media (max-width: 768px) {
                    .testi-summary-wrapper {
                        grid-template-columns: 1fr !important;
                        text-align: center;
                    }
                    .testi-summary-wrapper div {
                        border-right: none !important;
                        padding-left: 0 !important;
                        border-bottom: 1px solid rgba(68, 45, 28, 0.08);
                        padding-bottom: 16px;
                    }
                    .testi-summary-wrapper div:last-child {
                        border-bottom: none;
                        padding-bottom: 0;
                    }
                }
            </style>

            <!-- TOMBOL BERI TESTIMONI (JIKA LOGIN & BELUM MENGULAS PEMBELIAN PRODUK INI) -->
            <?php if (count($pending_orders) > 0): ?>
                <div class="testi-pending-card" style="display: flex; justify-content: space-between; align-items: center; border: 1px dashed var(--spiced-wine); background: var(--cream-light); padding: 20px 24px; border-radius: var(--radius-md); margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
                    <div style="flex: 1; min-width: 280px;">
                        <h4 style="font-weight: 700; color: var(--spiced-wine); font-size: 1.1rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-cookie-bite"></i> Ulas Kue Pembelian Anda</h4>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Anda terverifikasi memiliki pesanan kue ini yang telah selesai. Bagikan testimoni Anda!</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openReviewModal(<?= $id_produk ?>, <?= $target_pesan_id ?>, '<?= htmlspecialchars(addslashes($product['nama_produk'])) ?>')" style="padding: 10px 24px; font-size: 0.95rem;">
                        <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> Beri Testimoni
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTERS BAR -->
            <?php if ($total_reviews > 0): ?>
                <div class="testi-filters">
                    <button class="filter-btn active" onclick="filterReviews('all')">Semua Ulasan</button>
                    <button class="filter-btn" onclick="filterReviews('photo')"><i class="fa-solid fa-image" style="margin-right: 4px;"></i> Dengan Foto</button>
                    <button class="filter-btn" onclick="filterReviews('5')">5 Bintang</button>
                    <button class="filter-btn" onclick="filterReviews('4')">4 Bintang</button>
                    <button class="filter-btn" onclick="filterReviews('3')">3 Bintang</button>
                    <button class="filter-btn" onclick="filterReviews('2')">2 Bintang</button>
                    <button class="filter-btn" onclick="filterReviews('1')">1 Bintang</button>
                </div>
            <?php endif; ?>

            <!-- TESTIMONIALS LIST GRID -->
            <div class="testi-grid-custom" id="reviews-container">
                <?php if (count($testimonials) > 0): ?>
                    <?php foreach ($testimonials as $t): ?>
                        <?php 
                        $rating_val = intval($t['rating']);
                        ?>
                        <div class="testi-card-premium" data-rating="<?= $rating_val ?>" data-photo="<?= !empty($t['gambar']) ? 'true' : 'false' ?>">
                            <div>
                                <div class="testi-card-header">
                                    <div class="testi-card-stars">
                                        <?= renderStars($t['rating']) ?>
                                    </div>
                                    <div class="testi-card-date">
                                        <?= formatIndonesianDate($t['dibuat_pada']) ?>
                                    </div>
                                </div>

                                <p class="testi-card-text" style="margin-top: 6px;">
                                    "<?= htmlspecialchars($t['isi_testimoni']) ?>"
                                </p>
                                
                                <?php if (!empty($t['gambar'])): ?>
                                    <div class="testi-card-img-container" onclick="openLightbox('assets/uploads/testimoni/<?= htmlspecialchars($t['gambar']) ?>')">
                                        <img src="assets/uploads/testimoni/<?= htmlspecialchars($t['gambar']) ?>" alt="Foto ulasan pelanggan" class="testi-card-img" loading="lazy">
                                        <div class="testi-card-img-zoom-hint">
                                            <i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="testi-profile" style="margin-top: 10px; border-top: 1px solid rgba(68, 45, 28, 0.05); padding-top: 14px;">
                                <div class="testi-avatar" style="width: 40px; height: 40px; font-size: 0.95rem;"><?= htmlspecialchars($t['avatar_initial']) ?></div>
                                <div>
                                    <h4 class="testi-name" style="font-size: 0.95rem;"><?= htmlspecialchars($t['nama_lengkap']) ?></h4>
                                    <span class="testi-role" style="font-size: 0.75rem;"><?= htmlspecialchars($t['pekerjaan']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 0; color: var(--text-light); background-color: var(--white); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid rgba(68, 45, 28, 0.04);">
                        <i class="fa-regular fa-comment-dots" style="font-size: 3rem; color: var(--text-light); margin-bottom: 16px;"></i>
                        <h2>Belum Ada Ulasan</h2>
                        <p>Kue ini belum memiliki ulasan dari pelanggan saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- FORM TESTIMONI MODAL OVERLAY -->
    <div class="modal-overlay" id="review-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Beri Testimoni Kue</h3>
                <button type="button" class="modal-close" onclick="closeReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form action="testimoni.php?id_produk=<?= $id_produk ?>" method="POST" enctype="multipart/form-data" id="testi-form">
                    <input type="hidden" name="action_submit_testi" value="1">
                    <input type="hidden" id="form-id-produk" name="id_produk" value="<?= $id_produk ?>">
                    <input type="hidden" id="form-id-pesanan" name="id_pesanan" value="<?= $target_pesan_id ?>">

                    <div style="margin-bottom: 20px; text-align: center; background: var(--warm-bg); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(68, 45, 28, 0.05);">
                        <strong style="color: var(--cowhide-cocoa); font-size: 1.05rem;" id="form-product-name"><?= htmlspecialchars($product['nama_produk']) ?></strong>
                    </div>

                    <!-- Input Rating (1-5) -->
                    <div class="form-group-item">
                        <label class="field-label">Rating Produk *</label>
                        <div class="star-rating-selector">
                            <input type="radio" id="star5" name="rating" value="5">
                            <label for="star5" title="5 bintang"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4" title="4 bintang"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3" title="3 bintang"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2" title="2 bintang"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1" title="1 bintang"><i class="fa-solid fa-star"></i></label>
                        </div>
                        <span id="error-rating" style="color: #c93b2b; font-size: 0.85rem; display: none;">Harap pilih rating bintang.</span>
                    </div>

                    <!-- Input Isi Ulasan -->
                    <div class="form-group-item">
                        <label for="isi_testimoni" class="field-label">Isi Ulasan / Testimoni *</label>
                        <textarea id="isi_testimoni" name="isi_testimoni" class="form-control-item" rows="5" placeholder="Tulis ulasan Anda mengenai kelezatan kue ini..."></textarea>
                        <span id="error-ulasan" style="color: #c93b2b; font-size: 0.85rem; display: none;">Isi ulasan wajib diisi.</span>
                    </div>

                    <!-- Input Upload Foto -->
                    <div class="form-group-item">
                        <label for="foto_testimoni" class="field-label">Unggah Foto (Opsional)</label>
                        <input type="file" id="foto_testimoni" name="foto_testimoni" class="form-control-item" accept="image/png, image/jpeg, image/jpg" style="padding: 8px;">
                        <small style="color: var(--text-muted); display: block; margin-top: 4px;">Format diizinkan: JPG, JPEG, PNG. Maksimal ukuran file: 2 MB.</small>
                        <span id="error-file" style="color: #c93b2b; font-size: 0.85rem; display: none;">Format file atau ukuran tidak valid.</span>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; gap: 12px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Kirim Testimoni</button>
                        <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeReviewModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX OVERLAY FOR ZOOMING PHOTO -->
    <div class="lightbox-modal" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img src="" alt="Fullscreen image review" class="lightbox-content" id="lightbox-img">
    </div>

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
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 812-3456-7890<br>
                    <i class="fa-solid fa-map-marker-alt" style="margin-right: 8px; color: var(--olive-harvest);"></i> Kebayoran Baru, Jakarta Selatan
                </p>
            </div>
        </div>
        <div class="container footer-bottom">
            &copy; <?= date('Y') ?> Olin's Cake. All Rights Reserved. Made with <i class="fa-solid fa-heart" style="color: var(--spiced-wine);"></i> for Cake Lovers.
        </div>
    </footer>

    <!-- JavaScript Actions -->
    <script>
        // Header Background on Scroll
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');

        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Review Filter Logic
        function filterReviews(filterVal) {
            // Update active button state
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            const cards = document.querySelectorAll('.testi-card-premium');
            
            cards.forEach(card => {
                const cardRating = card.getAttribute('data-rating');
                const cardHasPhoto = card.getAttribute('data-photo') === 'true';

                if (filterVal === 'all') {
                    card.style.display = 'flex';
                } else if (filterVal === 'photo') {
                    if (cardHasPhoto) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                } else {
                    if (cardRating === filterVal) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }

        // Lightbox Functions
        function openLightbox(imgUrl) {
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            lightboxImg.src = imgUrl;
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Lock scrolling
        }

        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.style.display = 'none';
            document.body.style.overflow = ''; // Unlock scrolling
        }

        // Review Modal Functions
        function openReviewModal(idProduk, idPesanan, namaProduk) {
            document.getElementById('form-id-produk').value = idProduk;
            document.getElementById('form-id-pesanan').value = idPesanan;
            document.getElementById('form-product-name').textContent = namaProduk;
            
            const modal = document.getElementById('review-modal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
                modal.classList.add('active');
            }, 10);
            document.body.style.overflow = 'hidden';
        }

        function closeReviewModal() {
            const modal = document.getElementById('review-modal');
            modal.classList.remove('show');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
            
            // Reset form
            document.getElementById('testi-form').reset();
            hideErrors();
            document.body.style.overflow = '';
        }

        function hideErrors() {
            document.getElementById('error-rating').style.display = 'none';
            document.getElementById('error-ulasan').style.display = 'none';
            document.getElementById('error-file').style.display = 'none';
        }

        // Form Frontend Validation
        function validateForm() {
            hideErrors();
            let isValid = true;
            
            // Check rating
            const ratings = document.getElementsByName('rating');
            let ratingChecked = false;
            for (let i = 0; i < ratings.length; i++) {
                if (ratings[i].checked) {
                    ratingChecked = true;
                    break;
                }
            }
            
            if (!ratingChecked) {
                document.getElementById('error-rating').style.display = 'block';
                isValid = false;
            }
            
            // Check review text
            const ulasan = document.getElementById('isi_testimoni').value.trim();
            if (ulasan === '') {
                document.getElementById('error-ulasan').style.display = 'block';
                isValid = false;
            }
            
            // Check image file
            const fileInput = document.getElementById('foto_testimoni');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const fileType = file.type;
                const fileSize = file.size;
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                
                if (!allowedTypes.includes(fileType)) {
                    document.getElementById('error-file').textContent = "Format file tidak didukung. Hanya JPG, JPEG, PNG.";
                    document.getElementById('error-file').style.display = 'block';
                    isValid = false;
                } else if (fileSize > 2 * 1024 * 1024) {
                    document.getElementById('error-file').textContent = "Ukuran file maksimal 2 MB.";
                    document.getElementById('error-file').style.display = 'block';
                    isValid = false;
                }
            }
            
            return isValid;
        }

        // Escape HTML helper
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Show ajax alert banner helper
        function showBannerNotification(type, message) {
            // Remove existing alert banners if any
            const existingAlerts = document.querySelectorAll('.ajax-notification-alert');
            existingAlerts.forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = 'ajax-notification-alert';
            
            if (type === 'success') {
                alertDiv.innerHTML = `
                    <div class="orders-alert-success" style="margin-bottom: 30px;">
                        <div class="alert-icon-circle"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="alert-text">
                            <strong>Berhasil!</strong>
                            <p>${escapeHtml(message)}</p>
                        </div>
                    </div>
                `;
            } else {
                alertDiv.innerHTML = `
                    <div class="orders-alert-success" style="background-color: #fef2f2; border-color: #fca5a5; margin-bottom: 30px;">
                        <div class="alert-icon-circle" style="background-color: #fee2e2; color: #ef4444;"><i class="fa-solid fa-circle-xmark"></i></div>
                        <div class="alert-text">
                            <strong style="color: #991b1b;">Gagal!</strong>
                            <p style="color: #991b1b;">${escapeHtml(message)}</p>
                        </div>
                    </div>
                `;
            }
            
            const breadcrumb = document.querySelector('.detail-breadcrumb');
            if (breadcrumb) {
                breadcrumb.parentNode.insertBefore(alertDiv, breadcrumb.nextSibling);
            } else {
                const container = document.querySelector('.testi-page .container');
                if (container) {
                    container.insertBefore(alertDiv, container.firstChild);
                }
            }
            
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // AJAX submit handler
        document.getElementById('testi-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append('is_ajax', '1');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                
                if (data.success) {
                    closeReviewModal();
                    showBannerNotification('success', data.message);
                    
                    // Update stats
                    const summaryStars = document.getElementById('summary-stars');
                    if (summaryStars) {
                        summaryStars.innerHTML = data.stats.stars_html;
                    }
                    
                    const avgRatingSpan = document.getElementById('summary-avg-rating-text');
                    if (avgRatingSpan) {
                        avgRatingSpan.innerHTML = data.stats.avg_rating + ' / 5';
                    }
                    
                    const summaryTotal = document.getElementById('summary-total-reviews-text');
                    if (summaryTotal) {
                        summaryTotal.textContent = 'Berdasarkan ' + data.stats.total_reviews + ' ulasan pelanggan';
                    }
                    
                    const summaryDesc = document.getElementById('summary-satisfaction-text');
                    if (summaryDesc) {
                        summaryDesc.textContent = data.stats.satisfaction_text;
                    }
                    
                    // Sembunyikan card "Beri Testimoni" pending
                    const pendingCard = document.querySelector('.testi-pending-card');
                    if (pendingCard) {
                        pendingCard.style.display = 'none';
                    }
                    
                    // Tambah ulasan baru ke grid
                    const reviewsContainer = document.getElementById('reviews-container');
                    
                    // Hapus placeholder jika ulasan pertama
                    if (reviewsContainer && (reviewsContainer.innerHTML.includes('Belum Ada Ulasan') || reviewsContainer.innerHTML.includes('Belum ada ulasan'))) {
                        reviewsContainer.innerHTML = '';
                    }
                    
                    if (reviewsContainer) {
                        let imageHtml = '';
                        if (data.review.gambar) {
                            imageHtml = `
                                <div class="testi-card-img-container" onclick="openLightbox('assets/uploads/testimoni/${data.review.gambar}')">
                                    <img src="assets/uploads/testimoni/${data.review.gambar}" alt="Foto ulasan pelanggan" class="testi-card-img" loading="lazy">
                                    <div class="testi-card-img-zoom-hint">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i> Perbesar
                                    </div>
                                </div>
                            `;
                        }
                        
                        let starIconsHtml = '';
                        for (let i = 1; i <= 5; i++) {
                            if (i <= data.review.rating) {
                                starIconsHtml += '<i class="fa-solid fa-star"></i>';
                            } else {
                                starIconsHtml += '<i class="fa-regular fa-star"></i>';
                            }
                        }
                        
                        const newCard = document.createElement('div');
                        newCard.className = 'testi-card-premium';
                        newCard.setAttribute('data-rating', data.review.rating);
                        newCard.setAttribute('data-photo', data.review.gambar ? 'true' : 'false');
                        newCard.innerHTML = `
                            <div>
                                <div class="testi-card-header">
                                    <div class="testi-card-stars">
                                        ${starIconsHtml}
                                    </div>
                                    <div class="testi-card-date">
                                        ${data.review.dibuat_pada_formatted}
                                    </div>
                                </div>

                                <p class="testi-card-text" style="margin-top: 6px;">
                                    "${escapeHtml(data.review.isi_testimoni)}"
                                </p>
                                ${imageHtml}
                            </div>

                            <div class="testi-profile" style="margin-top: 10px; border-top: 1px solid rgba(68, 45, 28, 0.05); padding-top: 14px;">
                                <div class="testi-avatar" style="width: 40px; height: 40px; font-size: 0.95rem;">${escapeHtml(data.review.avatar_initial)}</div>
                                <div>
                                    <h4 class="testi-name" style="font-size: 0.95rem;">${escapeHtml(data.review.nama_lengkap)}</h4>
                                    <span class="testi-role" style="font-size: 0.75rem;">${escapeHtml(data.review.pekerjaan)}</span>
                                </div>
                            </div>
                        `;
                        
                        reviewsContainer.insertBefore(newCard, reviewsContainer.firstChild);
                    }
                } else {
                    showBannerNotification('error', data.message);
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showBannerNotification('error', 'Terjadi kesalahan sistem saat mengirim ulasan. Silakan coba lagi.');
                console.error('Error submitting form:', error);
            });
        });

        // On Page Load: Check auto-open params
        window.addEventListener('DOMContentLoaded', () => {
            <?php if ($auto_open_modal): ?>
                openReviewModal(
                    <?= $id_produk ?>, 
                    <?= $target_pesan_id ?>, 
                    '<?= htmlspecialchars(addslashes($product['nama_produk'])) ?>'
                );
            <?php endif; ?>
        });
    </script>
</body>
</html>
