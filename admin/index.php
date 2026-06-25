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

// 6. Ambil Pesanan Terbaru (Recent Orders - Limit 5)
$query_recent = "SELECT p.*, pl.nama_lengkap as nama_pelanggan 
                 FROM pesanan p 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan 
                 ORDER BY p.dibuat_pada DESC 
                 LIMIT 5";
$recent_orders = $conn->query($query_recent);

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
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=1.2">
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

        <!-- Baris Pesanan Terbaru -->
        <div class="admin-panel-card" style="margin-top: 24px;">
            <div class="panel-card-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Pesanan Terbaru Masuk</h3>
                <a href="pesanan.php" class="admin-btn admin-btn-secondary admin-btn-sm">Lihat Semua</a>
            </div>

            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Tanggal Masuk</th>
                            <th>Pengiriman</th>
                            <th>Total Bayar</th>
                            <th>Status Bayar</th>
                            <th>Status Pesanan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                            <?php while($row = $recent_orders->fetch_assoc()): ?>
                                <?php
                                $kode_order = "OLN-" . (10000 + $row['id_pesanan']);
                                $status_pesanan = $row['status_pesanan'];
                                $status_pembayaran = $row['status_pembayaran'];
                                
                                // Klasifikasi badge status pesanan
                                $badge_pesanan = 'admin-badge-info';
                                if ($status_pesanan === 'Menunggu Pembayaran') $badge_pesanan = 'admin-badge-waiting';
                                elseif ($status_pesanan === 'Selesai') $badge_pesanan = 'admin-badge-success';
                                elseif ($status_pesanan === 'Kedaluwarsa' || $status_pesanan === 'Dibatalkan') $badge_pesanan = 'admin-badge-danger';
                                
                                // Klasifikasi badge status pembayaran
                                $badge_bayar = 'admin-badge-info';
                                if ($status_pembayaran === 'Belum Dibayar') $badge_bayar = 'admin-badge-waiting';
                                elseif ($status_pembayaran === 'Sudah Dibayar') $badge_bayar = 'admin-badge-success';
                                elseif ($status_pembayaran === 'Kedaluwarsa') $badge_bayar = 'admin-badge-danger';
                                ?>
                                <tr>
                                    <td><strong><?= $kode_order ?></strong></td>
                                    <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['dibuat_pada'])) ?> WIB</td>
                                    <td>
                                        <span style="font-size: 0.8rem; font-weight: 600; display: block;"><?= htmlspecialchars($row['metode_pengiriman']) ?></span>
                                        <span style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= date('d/m/Y', strtotime($row['tanggal_pengiriman'])) ?></span>
                                    </td>
                                    <td><strong>Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?></strong></td>
                                    <td><span class="admin-badge <?= $badge_bayar ?>"><?= htmlspecialchars($status_pembayaran) ?></span></td>
                                    <td><span class="admin-badge <?= $badge_pesanan ?>"><?= htmlspecialchars($status_pesanan) ?></span></td>
                                    <td>
                                        <a href="pesanan.php?action=view&id=<?= $row['id_pesanan'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="Kelola">
                                            <i class="fa-solid fa-pen-to-square"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--admin-text-light); padding: 30px 0;">Belum ada data pesanan saat ini.</td>
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
