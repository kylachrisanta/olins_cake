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

    // Penanganan Metode Pengiriman & Ongkos Kirim di Backend
    $alamat_pengiriman = null;
    $jarak_km = 0.00;
    $ongkos_kirim = 0;

    if ($metode_pengiriman === 'Kirim ke Alamat') {
        $alamat_pengiriman = isset($_POST['alamat_pengiriman']) ? trim($_POST['alamat_pengiriman']) : '';
        $kecamatan = isset($_POST['kecamatan_pengiriman']) ? trim($_POST['kecamatan_pengiriman']) : '';

        if (empty($alamat_pengiriman) || empty($kecamatan)) {
            die("Kesalahan: Alamat lengkap dan kecamatan wajib diisi untuk metode pengantaran.");
        }

        // Dictionary jarak per kecamatan di backend
        $kecamatan_jarak = [
            'tambun_utara' => 3,
            'tambun_selatan' => 9,
            'bekasi_utara' => 6,
            'bekasi_timur' => 8,
            'bekasi_barat' => 13,
            'bekasi_selatan' => 11,
            'babelan' => 7,
            'cibitung' => 12,
            'cikarang_utara' => 18,
            'cikarang_pusat' => 26,
            'cibarusah' => 35
        ];

        if (!array_key_exists($kecamatan, $kecamatan_jarak)) {
            die("Kesalahan: Kecamatan pengiriman tidak valid.");
        }

        $jarak_km = $kecamatan_jarak[$kecamatan];

        // Validasi jarak maksimal 20 km di backend
        if ($jarak_km > 20) {
            die("Kesalahan: Pengiriman di luar area layanan (jarak melebihi batas maksimal 20 km).");
        }

        // Hitung Ongkos Kirim: Jarak * Rp 3.000
        $ongkos_kirim = $jarak_km * 3000;
        
        // Lengkapkan alamat dengan nama kecamatan
        $alamat_pengiriman .= " (Kecamatan: " . ucfirst(str_replace('_', ' ', $kecamatan)) . ")";

    } else {
        // Metode: Ambil Sendiri
        $metode_pengiriman = 'Ambil Sendiri';
        $alamat_pengiriman = 'Diambil langsung di Toko Olin\'s Cake Tambun Utara';
        $jarak_km = 0.00;
        $ongkos_kirim = 0;
    }

    // Hitung Total Bayar Final
    $total_bayar = $subtotal_belanja + $ongkos_kirim;

    // --- PROSES TRANSAKSI DATABASE (ATOMIK) ---
    $conn->begin_transaction();

    try {
        // 1. Masukkan ke tabel pesanan dengan status 'Menunggu Pembayaran' dan batas_pembayaran 24 jam ke depan
        $stmt_order = $conn->prepare("INSERT INTO pesanan (id_pelanggan, nama_penerima, nomor_wa, metode_pengiriman, alamat_pengiriman, jarak_km, tanggal_pengiriman, waktu_pengiriman, catatan, ongkos_kirim, total_bayar, status_pesanan, status_pembayaran, batas_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', 'Belum Bayar', DATE_ADD(NOW(), INTERVAL 1 DAY))");
        $stmt_order->bind_param("issssssssii", $id_pelanggan, $nama_penerima, $nomor_wa, $metode_pengiriman, $alamat_pengiriman, $jarak_km, $tanggal_pengiriman, $waktu_pengiriman, $catatan, $ongkos_kirim, $total_bayar);
        
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
