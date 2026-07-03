<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

header('Content-Type: application/json');

// Cek apakah pelanggan sudah masuk
if (!isset($_SESSION['pelanggan_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan masuk (login) terlebih dahulu untuk mulai belanja.']);
    exit;
}

$id_pelanggan = $_SESSION['pelanggan_id'];
$id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;
$jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 1;

if ($id_produk <= 0 || $jumlah <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data produk tidak valid.']);
    exit;
}

// Validasi: pastikan produk masih aktif (belum diarsipkan)
$cek_status = $conn->prepare("SELECT status_produk FROM produk WHERE id_produk = ?");
$cek_status->bind_param("i", $id_produk);
$cek_status->execute();
$res_status = $cek_status->get_result();
if ($res_status->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Produk tidak ditemukan.']);
    $cek_status->close();
    exit;
}
$status_produk = $res_status->fetch_assoc()['status_produk'];
$cek_status->close();
if ($status_produk !== 'Aktif') {
    echo json_encode(['status' => 'error', 'message' => 'Produk ini sudah tidak tersedia dan tidak dapat ditambahkan ke keranjang.']);
    exit;
}

// Cek apakah produk sudah ada di keranjang belanja pelanggan tersebut
$stmt = $conn->prepare("SELECT id_keranjang, jumlah FROM keranjang WHERE id_pelanggan = ? AND id_produk = ?");
$stmt->bind_param("ii", $id_pelanggan, $id_produk);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Jika produk sudah ada, tambahkan jumlahnya
    $row = $result->fetch_assoc();
    $jumlah_baru = $row['jumlah'] + $jumlah;
    
    // Batas maksimal kuantitas per produk adalah 99
    if ($jumlah_baru > 99) $jumlah_baru = 99;

    $stmt_update = $conn->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ?");
    $stmt_update->bind_param("ii", $jumlah_baru, $row['id_keranjang']);
    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Jumlah pesanan kue berhasil ditambahkan di keranjang belanja.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui jumlah pesanan kue.']);
    }
    $stmt_update->close();
} else {
    // Jika produk belum ada, tambahkan baru
    $stmt_insert = $conn->prepare("INSERT INTO keranjang (id_pelanggan, id_produk, jumlah) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("iii", $id_pelanggan, $id_produk, $jumlah);
    if ($stmt_insert->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Kue pilihan Anda berhasil dimasukkan ke keranjang belanja.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memasukkan kue ke keranjang belanja.']);
    }
    $stmt_insert->close();
}

$stmt->close();
$conn->close();
?>
