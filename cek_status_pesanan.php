<?php
// Mulai Session
session_start();

// Set Header JSON
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Import Koneksi Database
require_once 'config/database.php';

// Proteksi: Wajib Login Pelanggan
if (!isset($_SESSION['pelanggan_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id_pelanggan = $_SESSION['pelanggan_id'];
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pesanan <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Ambil detail pesanan dari database
$query = "SELECT status_pesanan, status_pembayaran, bukti_pembayaran FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id_pesanan, $id_pelanggan);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// Return data status pesanan
echo json_encode([
    'status_pesanan' => $order['status_pesanan'],
    'status_pembayaran' => $order['status_pembayaran'],
    'has_bukti' => !empty($order['bukti_pembayaran'])
]);
$conn->close();
?>
