<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Set page identification
$page = 'dashboard';

// 1. Ambil Total Produk
$res_produk = $conn->query("SELECT COUNT(*) as total FROM produk");
$total_produk = $res_produk ? $res_produk->fetch_assoc()['total'] : 0;

// 2. Ambil Total Pesanan
$res_pesanan = $conn->query("SELECT COUNT(*) as total FROM pesanan");
$total_pesanan = $res_pesanan ? $res_pesanan->fetch_assoc()['total'] : 0;

// 3. Ambil Pesanan Diproses
$res_proses = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Diproses'");
$pesanan_proses = $res_proses ? $res_proses->fetch_assoc()['total'] : 0;

// 4. Ambil Pesanan Selesai
$res_selesai = $conn->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'Selesai'");
$pesanan_selesai = $res_selesai ? $res_selesai->fetch_assoc()['total'] : 0;

// 5. Ambil Pendapatan (Pesanan yang sudah bayar)
$res_pendapatan = $conn->query("SELECT SUM(total_bayar) as total FROM pesanan WHERE status_pembayaran = 'Sudah Dibayar'");
$total_pendapatan = 0;
if ($res_pendapatan) {
    $row = $res_pendapatan->fetch_assoc();
    $total_pendapatan = $row['total'] ? $row['total'] : 0;
}



// 7. Data Grafik 1: Pendapatan Bulanan (6 Bulan Terakhir)
$month_labels = [];
$month_revenues = [];
$query_chart_rev = "SELECT DATE_FORMAT(dibuat_pada, '%Y-%m') as ym, DATE_FORMAT(dibuat_pada, '%b %Y') as label, SUM(total_bayar) as total 
                    FROM pesanan 
                    WHERE status_pembayaran = 'Sudah Dibayar' 
                    GROUP BY ym 
                    ORDER BY ym ASC 
                    LIMIT 6";
$res_chart_rev = $conn->query($query_chart_rev);
if ($res_chart_rev && $res_chart_rev->num_rows > 0) {
    while ($r = $res_chart_rev->fetch_assoc()) {
        $month_labels[] = $r['label'];
        $month_revenues[] = intval($r['total']);
    }
} else {
    // Dummy / Fallback data jika belum ada transaksi selesai
    $month_labels = ['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'Mei 2026', 'Jun 2026'];
    $month_revenues = [1200000, 1850000, 2400000, 1500000, 3100000, 4200000]; // data pemanis
}

// 8. Data Grafik 2: Status Pesanan Breakdown
$status_labels = [];
$status_counts = [];
$query_chart_status = "SELECT status_pesanan, COUNT(*) as count FROM pesanan GROUP BY status_pesanan";
$res_chart_status = $conn->query($query_chart_status);
if ($res_chart_status && $res_chart_status->num_rows > 0) {
    while ($r = $res_chart_status->fetch_assoc()) {
        $status_labels[] = $r['status_pesanan'];
        $status_counts[] = intval($r['count']);
    }
} else {
    // Dummy / Fallback data jika belum ada pesanan sama sekali
    $status_labels = ['Menunggu Pembayaran', 'Diproses', 'Selesai', 'Kedaluwarsa'];
    $status_counts = [3, 5, 12, 2];
}

// 9. Pesanan per Tanggal Pengiriman (untuk Perencanaan Produksi)
$filter_kurun = isset($_GET['filter']) ? trim($_GET['filter']) : 'minggu';
if (!in_array($filter_kurun, ['hari', 'minggu', 'bulan'])) {
    $filter_kurun = 'minggu';
}

$cond_filter = "";
if ($filter_kurun === 'hari') {
    $cond_filter = "p.tanggal_pengiriman = CURDATE()";
} elseif ($filter_kurun === 'minggu') {
    $cond_filter = "p.tanggal_pengiriman BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)";
} else {
    $cond_filter = "p.tanggal_pengiriman BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 29 DAY)";
}

$query_produksi = "SELECT p.tanggal_pengiriman, COUNT(DISTINCT p.id_pesanan) AS jumlah_pesanan, COALESCE(SUM(dp.jumlah), 0) AS total_produk
                   FROM pesanan p
                   LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
                   WHERE p.status_pesanan NOT IN ('Dibatalkan', 'Kedaluwarsa')
                     AND $cond_filter
                   GROUP BY p.tanggal_pengiriman
                   ORDER BY p.tanggal_pengiriman ASC";

$res_produksi = $conn->query($query_produksi);
$data_produksi = [];
$max_pcs = 0;

if ($res_produksi && $res_produksi->num_rows > 0) {
    while ($row = $res_produksi->fetch_assoc()) {
        $row['jumlah_pesanan'] = intval($row['jumlah_pesanan']);
        $row['total_produk'] = intval($row['total_produk']);
        $data_produksi[] = $row;
        if ($row['total_produk'] > $max_pcs) {
            $max_pcs = $row['total_produk'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=<?= time(); ?>">
</head>
<body>

    <!-- Include Sidebar -->
    <?php require_once 'sidebar.php'; ?>

    <!-- Layout Utama -->
    <div class="admin-layout">
        
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-title">
                <h1>Dashboard Admin</h1>
                <p>Selamat Datang Kembali, <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong>. Berikut adalah ringkasan performa toko Anda.</p>
            </div>

        </div>

        <!-- Grid Statistik Card Modern -->
        <div class="admin-stats-grid">
            <!-- Card Total Produk -->
            <div class="admin-stat-card card-total-produk">
                <i class="fa-solid fa-cookie-bite admin-stat-icon"></i>
                <span class="admin-stat-label">Total Produk</span>
                <span class="admin-stat-value"><?= number_format($total_produk) ?></span>
            </div>

            <!-- Card Total Pesanan -->
            <div class="admin-stat-card card-total-pesanan">
                <i class="fa-solid fa-receipt admin-stat-icon"></i>
                <span class="admin-stat-label">Total Pesanan</span>
                <span class="admin-stat-value"><?= number_format($total_pesanan) ?></span>
            </div>

            <!-- Card Pesanan Diproses -->
            <div class="admin-stat-card card-proses-pesanan">
                <i class="fa-solid fa-spinner fa-spin-custom admin-stat-icon" style="animation-duration: 4s;"></i>
                <span class="admin-stat-label">Pesanan Diproses</span>
                <span class="admin-stat-value"><?= number_format($pesanan_proses) ?></span>
            </div>

            <!-- Card Pesanan Selesai -->
            <div class="admin-stat-card card-selesai-pesanan">
                <i class="fa-solid fa-circle-check admin-stat-icon"></i>
                <span class="admin-stat-label">Pesanan Selesai</span>
                <span class="admin-stat-value"><?= number_format($pesanan_selesai) ?></span>
            </div>

            <!-- Card Pendapatan -->
            <div class="admin-stat-card card-pendapatan">
                <i class="fa-solid fa-wallet admin-stat-icon"></i>
                <span class="admin-stat-label">Total Pendapatan</span>
                <span class="admin-stat-value" style="font-size: 1.5rem;">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Baris Grafik Statistik -->
        <div class="admin-row">
            <!-- Grafik Pendapatan Bulanan -->
            <div class="admin-panel-card">
                <div class="panel-card-header">
                    <h3><i class="fa-solid fa-chart-column"></i> Tren Pendapatan Bulanan</h3>
                    <span style="font-size: 0.8rem; color: var(--admin-text-light);">Berdasarkan pesanan lunas</span>
                </div>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Grafik Proporsi Status Pesanan -->
            <div class="admin-panel-card">
                <div class="panel-card-header">
                    <h3><i class="fa-solid fa-chart-pie"></i> Status Pesanan</h3>
                </div>
                <div style="position: relative; height: 300px; width: 100%; display: flex; align-items: center; justify-content: center;">
                    <canvas id="statusChart" style="max-height: 280px; max-width: 280px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Baris Perencanaan Produksi (Pesanan per Tanggal Pengiriman) -->
        <div class="admin-panel-card" style="margin-top: 24px;">
            <div class="panel-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h3><i class="fa-solid fa-calendar-check"></i> Pesanan per Tanggal Pengiriman</h3>
                </div>
                <!-- Filter Hari | Minggu | Bulan -->
                <div class="admin-btn-group" style="display: flex; gap: 5px;">
                    <a href="index.php?filter=hari" class="admin-btn <?= $filter_kurun === 'hari' ? 'admin-btn-primary' : 'admin-btn-secondary' ?> admin-btn-sm" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">Hari Ini</a>
                    <a href="index.php?filter=minggu" class="admin-btn <?= $filter_kurun === 'minggu' ? 'admin-btn-primary' : 'admin-btn-secondary' ?> admin-btn-sm" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">Minggu Ini</a>
                    <a href="index.php?filter=bulan" class="admin-btn <?= $filter_kurun === 'bulan' ? 'admin-btn-primary' : 'admin-btn-secondary' ?> admin-btn-sm" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">Bulan Ini</a>
                </div>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tanggal Pengiriman</th>
                            <th style="text-align: center; width: 150px;">Jumlah Pesanan</th>
                            <th style="text-align: center; width: 180px;">Total Produk (pcs)</th>
                            <th style="text-align: center; width: 200px;">Status Produksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_produksi) > 0): ?>
                            <?php foreach ($data_produksi as $prod_row): ?>
                                <?php 
                                    $is_max = ($max_pcs > 0 && $prod_row['total_produk'] === $max_pcs);
                                    $tgl_formatted = date('d F Y', strtotime($prod_row['tanggal_pengiriman']));
                                ?>
                                <tr <?= $is_max ? 'style="background-color: rgba(234, 179, 8, 0.05);"' : '' ?>>
                                    <td>
                                        <strong><?= $tgl_formatted ?></strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="admin-badge admin-badge-info"><?= $prod_row['jumlah_pesanan'] ?> Pesanan</span>
                                    </td>
                                    <td style="text-align: center;">
                                        <strong><?= $prod_row['total_produk'] ?> pcs</strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($is_max): ?>
                                            <span class="admin-badge" style="background-color: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700;">
                                                <i class="fa-solid fa-fire"></i> Beban Tertinggi
                                            </span>
                                        <?php else: ?>
                                            <span class="admin-badge" style="background-color: rgba(46, 196, 182, 0.15); color: var(--admin-success); border: 1px solid rgba(46, 196, 182, 0.35); font-weight: 700;">
                                                Normal
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--admin-text-light); padding: 40px 0;">
                                    <i class="fa-solid fa-calendar-minus" style="font-size: 2.5rem; margin-bottom: 12px; color: var(--admin-border); display: block; margin: 0 auto 12px;"></i>
                                    <p>Tidak ada jadwal pengiriman pesanan dalam periode ini.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>



    </div>

    <!-- Chart rendering script -->
    <script>
        // Setup Chart.js global defaults for Light Mode
        Chart.defaults.color = '#6E5C51';
        Chart.defaults.borderColor = 'rgba(68, 45, 28, 0.08)';

        // 1. Chart Grafik Garis Pendapatan
        const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctxRevenue, {
            type: 'line',
            data: {
                labels: <?= json_encode($month_labels) ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($month_revenues) ?>,
                    backgroundColor: 'rgba(116, 48, 20, 0.06)',
                    borderColor: '#743014',
                    borderWidth: 3,
                    pointBackgroundColor: '#743014',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // 2. Chart Grafik Donut Status Pesanan
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels) ?>,
                datasets: [{
                    data: <?= json_encode($status_counts) ?>,
                    backgroundColor: [
                        '#ff9f1c', // Menunggu Pembayaran
                        '#3b82f6', // Menunggu Verifikasi / Info
                        '#a21caf', // Diproses
                        '#2ec4b6', // Dikirim / Siap Ambil / Selesai
                        '#e71d36', // Kedaluwarsa / Dibatalkan
                        '#a8a09a'  // Lainnya
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>
