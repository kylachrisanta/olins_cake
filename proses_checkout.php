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

// Proses form saat dikirim (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil input utama
    $items_str = isset($_POST['items']) ? trim($_POST['items']) : '';
    $nama_penerima = isset($_POST['nama_penerima']) ? trim($_POST['nama_penerima']) : '';
    $nomor_wa = isset($_POST['nomor_wa']) ? trim($_POST['nomor_wa']) : '';
    $metode_pengiriman = isset($_POST['metode_pengiriman']) ? trim($_POST['metode_pengiriman']) : '';
    $tanggal_pengiriman = isset($_POST['tanggal_pengiriman']) ? trim($_POST['tanggal_pengiriman']) : '';
    $waktu_pengiriman = isset($_POST['waktu_pengiriman']) ? trim($_POST['waktu_pengiriman']) : '';
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    // Validasi input wajib dasar
    if (empty($items_str) || empty($nama_penerima) || empty($nomor_wa) || empty($metode_pengiriman) || empty($tanggal_pengiriman) || empty($waktu_pengiriman)) {
        die("Kesalahan: Data yang Anda kirimkan tidak lengkap.");
    }

    // Bersihkan data ID keranjang
    $id_array = array_map('intval', explode(',', $items_str));
    $placeholders = implode(',', array_fill(0, count($id_array), '?'));

    // Ambil data produk di keranjang belanja untuk validasi harga di backend
    $query = "SELECT k.id_keranjang, k.jumlah, p.id_produk, p.harga 
              FROM keranjang k 
              JOIN produk p ON k.id_produk = p.id_produk 
              WHERE k.id_pelanggan = ? AND k.id_keranjang IN ($placeholders)";
              
    $stmt = $conn->prepare($query);
    $types = 'i' . str_repeat('i', count($id_array));
    $bind_params = array_merge([$id_pelanggan], $id_array);
    $stmt->bind_param($types, ...$bind_params);
    $stmt->execute();
    $result = $stmt->get_result();

    $checkout_items = [];
    $subtotal_belanja = 0;

    while ($row = $result->fetch_assoc()) {
        $checkout_items[] = $row;
        $subtotal_belanja += ($row['harga'] * $row['jumlah']);
    }
    $stmt->close();

    // Pastikan ada item yang diproses
    if (count($checkout_items) === 0) {
        die("Kesalahan: Tidak ada item belanja yang terpilih untuk checkout.");
    }

    // Validasi Tanggal H-3 di Backend (Sangat Wajib!)
    $today = new DateTime('today');
    $min_delivery_date = clone $today;
    $min_delivery_date->modify('+3 days');
    
    $selected_date = new DateTime($tanggal_pengiriman);
    if ($selected_date < $min_delivery_date) {
        die("Kesalahan: Minimal pemesanan adalah H-3 sebelum tanggal pengiriman.");
    }

    // Validasi Kapasitas Harian (Maks 5 Pcs) di Backend
    $total_pcs_checkout = 0;
    foreach ($checkout_items as $item) {
        $total_pcs_checkout += $item['jumlah'];
    }

    $stmt_cap = $conn->prepare("
        SELECT COALESCE(SUM(dp.jumlah), 0) AS total_pcs
        FROM detail_pesanan dp
        JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
        WHERE p.tanggal_pengiriman = ?
          AND p.status_pesanan NOT IN ('Dibatalkan', 'Kedaluwarsa')
    ");
    $stmt_cap->bind_param("s", $tanggal_pengiriman);
    $stmt_cap->execute();
    $res_cap = $stmt_cap->get_result()->fetch_assoc();
    $total_pcs_terpakai = (int)$res_cap['total_pcs'];
    $stmt_cap->close();

    $batas_kapasitas = 5;
    if (($total_pcs_terpakai + $total_pcs_checkout) > $batas_kapasitas) {
        die("Maaf, kapasitas pesanan untuk tanggal pengiriman yang dipilih telah penuh. Silakan pilih tanggal pengiriman lain atau kurangi jumlah produk yang dipesan.");
    }

    // Penanganan Metode Pengiriman & Ongkos Kirim di Backend
    $alamat_pengiriman = null;
    $jarak_km = 0.00;
    $ongkos_kirim = 0;
    $garis_lintang = null;
    $garis_bujur = null;

    if ($metode_pengiriman === 'Kirim ke Alamat') {
        $alamat_pengiriman = isset($_POST['alamat_pengiriman']) ? trim($_POST['alamat_pengiriman']) : '';
        $garis_lintang = isset($_POST['garis_lintang']) ? trim($_POST['garis_lintang']) : '';
        $garis_bujur = isset($_POST['garis_bujur']) ? trim($_POST['garis_bujur']) : '';

        if (empty($alamat_pengiriman)) {
            die("Kesalahan: Alamat lengkap pengiriman wajib ditentukan.");
        }
        if (empty($garis_lintang) || empty($garis_bujur)) {
            die("Kesalahan: Titik lokasi pengiriman pada peta harus ditentukan.");
        }

        // Hitung jarak dari toko (-6.2215453, 107.0463893) ke lokasi pelanggan
        $apiKey = 'AIzaSyBnSaMaGbbQGbP_JB78HYxlxi9P1pPXwbc';
        $storeLat = -6.2215453;
        $storeLng = 107.0463893;

        $verified_jarak = null;

        // Panggil Google Maps Distance Matrix API
        $api_url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode("{$storeLat},{$storeLng}") . "&destinations=" . urlencode("{$garis_lintang},{$garis_bujur}") . "&key=" . urlencode($apiKey);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3
            ]
        ]);
        
        $response_json = @file_get_contents($api_url, false, $ctx);
        if ($response_json !== false) {
            $response = json_decode($response_json, true);
            if (isset($response['status']) && $response['status'] === 'OK' && isset($response['rows'][0]['elements'][0]['status']) && $response['rows'][0]['elements'][0]['status'] === 'OK') {
                $distance_meters = $response['rows'][0]['elements'][0]['distance']['value'];
                $verified_jarak = $distance_meters / 1000;
            }
        }

        // Fallback ke Haversine (jarak garis lurus) jika API gagal/timeout
        if ($verified_jarak === null) {
            $earthRadius = 6371; // km
            $dLat = deg2rad(floatval($garis_lintang) - $storeLat);
            $dLon = deg2rad(floatval($garis_bujur) - $storeLng);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($storeLat)) * cos(deg2rad(floatval($garis_lintang))) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $verified_jarak = $earthRadius * $c;
        }

        $jarak_km = $verified_jarak;

        // Validasi jarak maksimal 20 km di backend
        if ($jarak_km > 20) {
            die("Kesalahan: Pengiriman di luar area layanan.");
        }

        // Hitung Ongkos Kirim: Jarak * Rp 3.000 (tanpa pembulatan jarak)
        $ongkos_kirim = round($jarak_km * 3000);

    } else {
        // Metode: Ambil Sendiri
        $metode_pengiriman = 'Ambil Sendiri';
        $alamat_pengiriman = 'Diambil langsung di Toko Olin\'s Cake – Kp. Karang Jaya Blok D No.1, Karang Satria, Tambun Utara, Bekasi';
        $jarak_km = 0.00;
        $ongkos_kirim = 0;
        $garis_lintang = null;
        $garis_bujur = null;
    }

    // Hitung Total Bayar Final
    $total_bayar = $subtotal_belanja + $ongkos_kirim;

    // --- PROSES TRANSAKSI DATABASE (ATOMIK) ---
    $conn->begin_transaction();

    try {
        // 1. Masukkan ke tabel pesanan dengan status 'Menunggu Pembayaran' dan batas_pembayaran 24 jam ke depan
        $stmt_order = $conn->prepare("INSERT INTO pesanan (id_pelanggan, nama_penerima, nomor_wa, metode_pengiriman, alamat_pengiriman, garis_lintang, garis_bujur, jarak_km, tanggal_pengiriman, waktu_pengiriman, catatan, ongkos_kirim, total_bayar, status_pesanan, status_pembayaran, batas_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', 'Belum Dibayar', DATE_ADD(NOW(), INTERVAL 1 DAY))");
        $stmt_order->bind_param("issssssdsssii", $id_pelanggan, $nama_penerima, $nomor_wa, $metode_pengiriman, $alamat_pengiriman, $garis_lintang, $garis_bujur, $jarak_km, $tanggal_pengiriman, $waktu_pengiriman, $catatan, $ongkos_kirim, $total_bayar);
        
        if (!$stmt_order->execute()) {
            throw new Exception("Gagal menyimpan data transaksi utama.");
        }
        
        // Dapatkan ID Pesanan yang baru saja dibuat
        $id_pesanan = $conn->insert_id;
        $stmt_order->close();

        // 2. Masukkan rincian produk ke tabel detail_pesanan
        $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan) VALUES (?, ?, ?, ?)");
        
        foreach ($checkout_items as $item) {
            $stmt_detail->bind_param("iiii", $id_pesanan, $item['id_produk'], $item['jumlah'], $item['harga']);
            if (!$stmt_detail->execute()) {
                throw new Exception("Gagal menyimpan rincian pesanan produk.");
            }
        }
        $stmt_detail->close();

        // 3. Bersihkan keranjang belanja dari item yang dipesan
        $query_del = "DELETE FROM keranjang WHERE id_pelanggan = ? AND id_keranjang IN ($placeholders)";
        $stmt_del = $conn->prepare($query_del);
        
        $types_del = 'i' . str_repeat('i', count($id_array));
        $bind_params_del = array_merge([$id_pelanggan], $id_array);
        $stmt_del->bind_param($types_del, ...$bind_params_del);
        
        if (!$stmt_del->execute()) {
            throw new Exception("Gagal membersihkan keranjang belanja.");
        }
        $stmt_del->close();

        // Commit transaksi jika seluruh tahapan sukses
        $conn->commit();

        // Alihkan ke Halaman Pembayaran dengan ID pesanan yang baru dibuat
        header("Location: pembayaran.php?id=" . $id_pesanan);
        exit;

    } catch (Exception $e) {
        // Rollback seluruh query jika terjadi kegagalan
        $conn->rollback();
        die("Error Sistem: Transaksi checkout gagal diproses. Alasan: " . $e->getMessage());
    }

} else {
    // Alihkan jika diakses tanpa POST
    header("Location: keranjang.php");
    exit;
}

$conn->close();
?>
