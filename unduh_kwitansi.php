<?php
// Start Session
session_start();

// Import database connection
require_once 'config/database.php';

// Check if user is logged in (either customer or admin)
if (!isset($_SESSION['pelanggan_id']) && !isset($_SESSION['admin_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_pesanan <= 0) {
    die("ID Pesanan tidak valid.");
}

// Fetch order details
$stmt = $conn->prepare("SELECT p.*, pl.nama_lengkap as nama_pelanggan FROM pesanan p JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan WHERE p.id_pesanan = ?");
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// Access control: Customer can only view their own order
if (isset($_SESSION['pelanggan_id']) && !isset($_SESSION['admin_id'])) {
    if ($order['id_pelanggan'] != $_SESSION['pelanggan_id']) {
        die("Akses ditolak. Anda tidak memiliki wewenang untuk melihat kwitansi ini.");
    }
}

// Rule check: payment must be verified ('Sudah Dibayar')
if ($order['status_pembayaran'] !== 'Sudah Dibayar') {
    die("Kwitansi belum tersedia. Pembayaran harus diverifikasi oleh admin terlebih dahulu.");
}

// Fetch order items
$stmt = $conn->prepare("SELECT dp.*, pr.nama_produk, pr.kategori, pr.ukuran FROM detail_pesanan dp JOIN produk pr ON dp.id_produk = pr.id_produk WHERE dp.id_pesanan = ?");
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Include FPDF library
require_once 'lib/fpdf/fpdf.php';

// Generate receipt number (KWT/YYYYMMDD/ID)
$no_kwitansi = "KWT/" . date('Ymd', strtotime($order['dibuat_pada'])) . "/" . str_pad($order['id_pesanan'], 5, '0', STR_PAD_LEFT);
$tanggal_terbit = date('d F Y', strtotime($order['dibuat_pada']));

// Initialize PDF class extending FPDF
class KwitansiPDF extends FPDF {
    // Header
    function Header() {
        // Logo & Brand Name
        $this->SetY(15);
        $this->SetX(15);
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(68, 45, 28); // Cocoa color
        $this->Cell(80, 10, "Olin's Cake", 0, 0, 'L');
        
        // Title: KWITANSI PEMBAYARAN
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(116, 48, 20); // Spiced Wine
        $this->Cell(0, 10, 'KWITANSI PEMBAYARAN', 0, 1, 'R');
        
        // Underline Header
        $this->SetDrawColor(157, 145, 103); // Olive Harvest
        $this->SetLineWidth(1);
        $this->Line(15, 28, 195, 28);
        $this->Ln(8);
    }

    // Page footer
    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8.5);
        $this->SetTextColor(110, 92, 81); // Text muted
        
        // Thank you note
        $this->Cell(0, 5, '"Terima kasih telah mempercayakan pesanan Anda kepada Olin\'s Cake."', 0, 1, 'C');
        
        // System generated warning note
        $this->SetFont('Arial', '', 7.5);
        $this->Cell(0, 5, 'Kwitansi ini dibuat secara otomatis oleh sistem Olin\'s Cake dan berlaku sebagai bukti pembayaran yang sah.', 0, 1, 'C');
    }
}

// Create new PDF instance (A4 Portrait)
$pdf = new KwitansiPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// Document Meta Info Row
$pdf->SetY(33);
$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(110, 92, 81); // Muted
$pdf->Cell(90, 5, 'No. Kwitansi: ' . $no_kwitansi, 0, 0, 'L');
$pdf->Cell(0, 5, 'Tanggal Diterbitkan: ' . date('d F Y'), 0, 1, 'R');
$pdf->Ln(5);

// 1. Box Informasi Pembayaran & Pelanggan
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetFillColor(250, 246, 240); // Warm BG
$pdf->Rect(15, 45, 180, 48, 'DF');

$pdf->SetY(48);
$pdf->SetX(20);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(68, 45, 28); // Cocoa
$pdf->Cell(90, 6, 'INFORMASI PEMBAYARAN', 0, 0, 'L');
$pdf->Cell(0, 6, 'STATUS: LUNAS', 0, 1, 'R');

$pdf->SetLineWidth(0.3);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(20, 55, 190, 55);
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(44, 27, 16); // Main text

// Column 1
$pdf->SetX(20);
$pdf->Cell(40, 5.5, 'Nomor Pesanan', 0, 0, 'L');
$pdf->Cell(50, 5.5, ': OLN-' . (10000 + $order['id_pesanan']), 0, 0, 'L');

// Column 2
$pdf->Cell(40, 5.5, 'Metode Pembayaran', 0, 0, 'L');
$pdf->Cell(0, 5.5, ': ' . (!empty($order['metode_pembayaran']) ? $order['metode_pembayaran'] : 'Transfer Bank'), 0, 1, 'L');

$pdf->SetX(20);
$pdf->Cell(40, 5.5, 'Nama Penerima', 0, 0, 'L');
$pdf->Cell(50, 5.5, ': ' . $order['nama_penerima'], 0, 0, 'L');

$pdf->Cell(40, 5.5, 'Tanggal Transaksi', 0, 0, 'L');
$pdf->Cell(0, 5.5, ': ' . date('d-m-Y H:i', strtotime($order['dibuat_pada'])) . ' WIB', 0, 1, 'L');

$pdf->SetX(20);
$pdf->Cell(40, 5.5, 'Nomor WhatsApp', 0, 0, 'L');
$pdf->Cell(50, 5.5, ': ' . $order['nomor_wa'], 0, 0, 'L');

$pdf->Cell(40, 5.5, 'Total Pembayaran', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->Cell(0, 5.5, ': Rp ' . number_format($order['total_bayar'], 0, ',', '.'), 0, 1, 'L');

$pdf->Ln(12);

// 2. Keterangan Pembayaran
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10.5);
$pdf->SetTextColor(68, 45, 28);
$pdf->Cell(0, 5, 'Keterangan Pembayaran:', 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(110, 92, 81);
$pdf->MultiCell(0, 5.5, 'Pembayaran untuk pemesanan produk Olin\'s Cake telah diterima dan diverifikasi oleh admin.', 0, 'L');
$pdf->Ln(8);

// 3. Rincian Kue Dipesan Table
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10.5);
$pdf->SetTextColor(68, 45, 28);
$pdf->Cell(0, 5, 'Rincian Kue Dipesan:', 0, 1, 'L');
$pdf->Ln(3);

// Table Header
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFillColor(116, 48, 20); // Spiced Wine Header
$pdf->Cell(95, 8, ' Nama Produk / Spesifikasi', 1, 0, 'L', true);
$pdf->Cell(20, 8, 'Jumlah', 1, 0, 'C', true);
$pdf->Cell(32, 8, 'Harga Satuan', 1, 0, 'R', true);
$pdf->Cell(33, 8, 'Subtotal ', 1, 1, 'R', true);

// Table Content Rows
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(44, 27, 16);

foreach ($items as $item) {
    $subtotal = $item['harga_satuan'] * $item['jumlah'];
    
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    
    $spec = $item['kategori'] . ' | ' . $item['ukuran'];
    
    // Render product cells with borders
    $pdf->Rect($x, $y, 95, 12);
    $pdf->SetX($x + 2);
    $pdf->SetY($y + 1.5);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(93, 4, $item['nama_produk'], 0, 1, 'L');
    $pdf->SetX($x + 2);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(110, 92, 81);
    $pdf->Cell(93, 4, $spec, 0, 1, 'L');
    
    $pdf->SetTextColor(44, 27, 16);
    $pdf->SetY($y);
    $pdf->SetX($x + 95);
    
    $pdf->Cell(20, 12, $item['jumlah'] . ' pcs', 1, 0, 'C');
    $pdf->Cell(32, 12, 'Rp ' . number_format($item['harga_satuan'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(33, 12, 'Rp ' . number_format($subtotal, 0, ',', '.'), 1, 1, 'R');
}

// Totals breakdown row
$pdf->SetFont('Arial', '', 9.5);
$pdf->Cell(147, 8, 'Subtotal Kue', 1, 0, 'R');
$pdf->Cell(33, 8, 'Rp ' . number_format($order['total_bayar'] - $order['ongkos_kirim'], 0, ',', '.'), 1, 1, 'R');

$pdf->Cell(147, 8, 'Ongkos Kirim', 1, 0, 'R');
$pdf->Cell(33, 8, 'Rp ' . number_format($order['ongkos_kirim'], 0, ',', '.'), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(250, 246, 240);
$pdf->Cell(147, 8, 'Total Pembayaran (Lunas) ', 1, 0, 'R', true);
$pdf->Cell(33, 8, 'Rp ' . number_format($order['total_bayar'], 0, ',', '.'), 1, 1, 'R', true);

// Clean output buffer to avoid corruption in PDF stream
ob_clean();

// Output PDF directly for inline view / download
$pdf->Output('I', 'Kwitansi_OlinsCake_' . $order['id_pesanan'] . '.pdf');
exit;
?>
