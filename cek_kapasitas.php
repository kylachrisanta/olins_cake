<?php
// Mulai Session
session_start();

// Import Koneksi Database
require_once 'config/database.php';

header('Content-Type: application/json');

// Cek apakah pelanggan sudah masuk
if (!isset($_SESSION['pelanggan_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan masuk terlebih dahulu.']);
    exit;
}

$tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$items_str = isset($_GET['items']) ? trim($_GET['items']) : '';

if (empty($tanggal)) {
    echo json_encode(['status' => 'error', 'message' => 'Tanggal pengiriman wajib ditentukan.']);
    exit;
}

// Format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    echo json_encode(['status' => 'error', 'message' => 'Format tanggal tidak valid.']);
    exit;
}

// Cek kapasitas terpakai pada tanggal tersebut
$stmt_cap = $conn->prepare("
    SELECT COALESCE(SUM(dp.jumlah), 0) AS total_pcs
    FROM detail_pesanan dp
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
    WHERE p.tanggal_pengiriman = ?
      AND p.status_pesanan NOT IN ('Dibatalkan', 'Kedaluwarsa')
");
$stmt_cap->bind_param("s", $tanggal);
$stmt_cap->execute();
$res_cap = $stmt_cap->get_result()->fetch_assoc();
$total_pcs_terpakai = (int)$res_cap['total_pcs'];
$stmt_cap->close();

$batas_kapasitas = 5;
$sisa_kapasitas = $batas_kapasitas - $total_pcs_terpakai;
if ($sisa_kapasitas < 0) $sisa_kapasitas = 0;

// Hitung total pcs yang akan dicheckout dari keranjang (opsional, jika dikirim via parameter items)
$total_checkout = 0;
if (!empty($items_str)) {
    $id_array = array_map('intval', explode(',', $items_str));
    if (count($id_array) > 0) {
        $id_pelanggan = $_SESSION['pelanggan_id'];
        $placeholders = implode(',', array_fill(0, count($id_array), '?'));
        
        $query = "SELECT SUM(jumlah) AS total_qty FROM keranjang WHERE id_pelanggan = ? AND id_keranjang IN ($placeholders)";
        $stmt_qty = $conn->prepare($query);
        $types = 'i' . str_repeat('i', count($id_array));
        $bind_params = array_merge([$id_pelanggan], $id_array);
        $stmt_qty->bind_param($types, ...$bind_params);
        $stmt_qty->execute();
        $res_qty = $stmt_qty->get_result()->fetch_assoc();
        $total_checkout = (int)($res_qty['total_qty'] ?? 0);
        $stmt_qty->close();
    }
}

echo json_encode([
    'status' => 'success',
    'tanggal' => $tanggal,
    'terpakai' => $total_pcs_terpakai,
    'batas' => $batas_kapasitas,
    'sisa' => $sisa_kapasitas,
    'total_checkout' => $total_checkout,
    'penuh' => ($total_pcs_terpakai >= $batas_kapasitas),
    'melebihi' => (($total_pcs_terpakai + $total_checkout) > $batas_kapasitas)
]);
$conn->close();
?>
