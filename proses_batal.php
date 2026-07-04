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
    
    // Ambil ID Pesanan
    $id_pesanan = isset($_POST['id_pesanan']) ? intval($_POST['id_pesanan']) : 0;
    
    if ($id_pesanan <= 0) {
        die("Kesalahan: ID pesanan tidak valid.");
    }
    
    // Ambil detail pesanan dan pastikan milik pelanggan aktif
    $query = "SELECT status_pesanan FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id_pesanan, $id_pelanggan);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        die("Kesalahan: Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.");
    }
    
    // Verifikasi status pesanan harus 'Menunggu Pembayaran'
    if ($order['status_pesanan'] !== 'Menunggu Pembayaran') {
        die("Kesalahan: Hanya pesanan dengan status 'Menunggu Pembayaran' yang dapat dibatalkan.");
    }
    
    // Jalankan update status_pesanan menjadi 'Dibatalkan' dan status_pembayaran menjadi 'Dibatalkan'
    $update_query = "UPDATE pesanan SET status_pesanan = 'Dibatalkan', status_pembayaran = 'Dibatalkan' WHERE id_pesanan = ?";
    $stmt_update = $conn->prepare($update_query);
    $stmt_update->bind_param("i", $id_pesanan);
    
    if ($stmt_update->execute()) {
        $_SESSION['pesan_sukses'] = "Pesanan berhasil dibatalkan.";
    } else {
        $_SESSION['pesan_error'] = "Gagal membatalkan pesanan. Terjadi kesalahan pada sistem.";
    }
    $stmt_update->close();
    
    // Redirect ke halaman pesanan saya
    header("Location: pesanan_saya.php");
    exit;
    
} else {
    // Alihkan jika diakses tanpa POST
    header("Location: pesanan_saya.php");
    exit;
}

$conn->close();
?>
