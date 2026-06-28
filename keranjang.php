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

// Penanganan Aksi AJAX (Kuantitas & Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_qty') {
        $id_keranjang = isset($_POST['id_keranjang']) ? (int)$_POST['id_keranjang'] : 0;
        $jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 1;

        if ($id_keranjang <= 0 || $jumlah <= 0 || $jumlah > 99) {
            echo json_encode(['status' => 'error', 'message' => 'Input tidak valid.']);
            exit;
        }

        // Pastikan keranjang ini benar-benar milik pelanggan yang login
        $stmt = $conn->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ? AND id_pelanggan = ?");
        $stmt->bind_param("iii", $jumlah, $id_keranjang, $id_pelanggan);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Jumlah berhasil diperbarui.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui jumlah.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete_item') {
        $id_keranjang = isset($_POST['id_keranjang']) ? (int)$_POST['id_keranjang'] : 0;

        if ($id_keranjang <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID keranjang tidak valid.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_pelanggan = ?");
        $stmt->bind_param("ii", $id_keranjang, $id_pelanggan);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Produk berhasil dihapus dari keranjang.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus produk.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'delete_selected') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
        if (empty($ids)) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada item terpilih.']);
            exit;
        }

        // Bersihkan ID untuk keamanan SQL
        $id_array = array_map('intval', explode(',', $ids));
        $placeholders = implode(',', array_fill(0, count($id_array), '?'));

        $query = "DELETE FROM keranjang WHERE id_pelanggan = ? AND id_keranjang IN ($placeholders)";
        $stmt = $conn->prepare($query);
        
        // Dynamic binding
        $types = 'i' . str_repeat('i', count($id_array));
        $bind_params = array_merge([$id_pelanggan], $id_array);
        $stmt->bind_param($types, ...$bind_params);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Item terpilih berhasil dihapus.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus item terpilih.']);
        }
        $stmt->close();
        exit;
    }
}

// Ambil data keranjang belanja saat ini
$query = "SELECT k.id_keranjang, k.jumlah, p.id_produk, p.nama_produk, p.harga, p.gambar, p.kategori, p.ukuran 
          FROM keranjang k 
          JOIN produk p ON k.id_produk = p.id_produk 
          WHERE k.id_pelanggan = ? 
          ORDER BY k.dibuat_pada DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_pelanggan);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Global CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
</head>
<body>

    <!-- Floating Header -->
    <header id="header" class="scrolled">
        <div class="container navbar">
            <a href="index.php" class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 8px; margin-top: -3px;">
                    <circle cx="9" cy="7" r="2"/>
                    <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
                    <path d="M5.1 12.8 19 12"/>
                    <path d="M8.9 15.6 19 15"/>
                </svg> Olin's <span>Cake</span>
            </a>
            
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <ul class="nav-menu" id="nav-menu">
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <li><a href="tentang.php" class="nav-link">Tentang Kami</a></li>
                <li><a href="cara_pesan.php" class="nav-link">Cara Pesan</a></li>
                <li><a href="produk.php" class="nav-link">Produk</a></li>
                <li><a href="keranjang.php" class="nav-link active" style="color: var(--spiced-wine); font-weight: 700;">Keranjang</a></li>
                <li><a href="pesanan_saya.php" class="nav-link">Pesanan</a></li>
                <li><a href="profil_saya.php" class="nav-link">Profil</a></li>
                <li><a href="index.php?action=logout" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-from-bracket" style="margin-right: 6px;"></i> Keluar</a></li>
            </ul>
        </div>
    </header>

    <!-- Shopping Cart Section -->
    <section class="cart-section">
        <div class="container">
            
            <div class="cart-title-area">
                <h1>Keranjang Belanja</h1>
                <p>Kelola pre-order kue pilihan Anda sebelum melanjutkan ke pembayaran.</p>
            </div>

            <?php if (count($cart_items) > 0): ?>
                <div class="cart-grid">
                    
                    <!-- Sisi Kiri: Daftar Keranjang Belanja -->
                    <div class="cart-left-col">
                        <div class="cart-card">
                            
                            <!-- Header Aksi Massal -->
                            <div class="cart-header-actions">
                                <label class="select-all-label">
                                    <input type="checkbox" id="select-all" class="cart-checkbox" onchange="toggleSelectAll(this)">
                                    Pilih Semua
                                </label>
                                <button type="button" class="btn-delete-selected" onclick="deleteSelectedItems()">
                                    <i class="fa-solid fa-trash-can"></i> Hapus Terpilih
                                </button>
                            </div>

                            <!-- Baris Item Kategori Belanja -->
                            <div class="cart-items-list">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="cart-item-row" id="item-row-<?= $item['id_keranjang'] ?>" data-id="<?= $item['id_keranjang'] ?>" data-harga="<?= $item['harga'] ?>">
                                        <!-- Checkbox -->
                                        <div class="cart-item-checkbox-col">
                                            <input type="checkbox" name="item_select" class="cart-checkbox item-checkbox" onchange="updateSummary()" value="<?= $item['id_keranjang'] ?>">
                                        </div>
                                        
                                        <!-- Foto Kue -->
                                        <div class="cart-item-img-col">
                                            <div class="cart-item-img-wrapper">
                                                <img src="assets/images/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" class="cart-item-img">
                                            </div>
                                        </div>

                                        <!-- Detail Nama & Spesifikasi -->
                                        <div class="cart-item-details-col">
                                            <div class="cart-item-details">
                                                <span class="cart-item-category"><?= htmlspecialchars($item['kategori']) ?></span>
                                                <a href="detail_produk.php?id=<?= $item['id_produk'] ?>" class="cart-item-name"><?= htmlspecialchars($item['nama_produk']) ?></a>
                                                <small style="color: var(--text-light); font-weight: 500;">Ukuran: <?= htmlspecialchars($item['ukuran']) ?></small>
                                            </div>
                                        </div>

                                        <!-- Harga Satuan -->
                                        <div class="cart-item-price-col">
                                            <div class="cart-item-price">
                                                Rp <?= number_format($item['harga'], 0, ',', '.') ?>
                                            </div>
                                        </div>

                                        <!-- Pengatur Jumlah Kuantitas (Readonly +/-) -->
                                        <div class="cart-qty-col">
                                            <div class="qty-selector">
                                                <button type="button" class="qty-btn" onclick="updateItemQty(<?= $item['id_keranjang'] ?>, -1)">
                                                    <i class="fa-solid fa-minus"></i>
                                                </button>
                                                <input type="text" id="qty-<?= $item['id_keranjang'] ?>" class="qty-input" value="<?= $item['jumlah'] ?>" readonly>
                                                <button type="button" class="qty-btn" onclick="updateItemQty(<?= $item['id_keranjang'] ?>, 1)">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal Item -->
                                        <div class="cart-item-subtotal-col">
                                            <div class="cart-item-subtotal" id="subtotal-<?= $item['id_keranjang'] ?>">
                                                Rp <?= number_format($item['harga'] * $item['jumlah'], 0, ',', '.') ?>
                                            </div>
                                        </div>

                                        <!-- Tombol Hapus Kue -->
                                        <div class="cart-remove-col">
                                            <button type="button" class="cart-item-remove" onclick="deleteCartItem(<?= $item['id_keranjang'] ?>)" aria-label="Hapus dari keranjang">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Sisi Kanan: Ringkasan Pesanan -->
                    <div class="cart-right-col">
                        <div class="cart-card cart-summary-card">
                            <h2>Ringkasan Pesanan</h2>
                            
                            <div class="summary-rows">
                                <div class="summary-row">
                                    <span>Total Item Terpilih</span>
                                    <span id="summary-items">0 Item</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total Harga</span>
                                    <span id="summary-total">Rp 0</span>
                                </div>
                            </div>

                            <button type="button" id="btn-checkout" class="btn btn-primary btn-checkout" onclick="proceedToCheckout()" disabled>
                                Checkout <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                            </button>
                        </div>
                    </div>

                </div>
            <?php else: ?>
                <!-- Keadaan Keranjang Belanja Kosong -->
                <div class="cart-card empty-cart-card">
                    <div class="empty-cart-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                    <h2>Keranjang Anda Kosong</h2>
                    <p>Sepertinya Anda belum menambahkan kue pre-order lezat buatan kami ke dalam daftar belanja.</p>
                    <a href="produk.php" class="btn btn-primary">Mulai Belanja Kue</a>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-grid">
            <div class="footer-col">
                <div class="footer-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 8px; margin-top: -3px;">
                        <circle cx="9" cy="7" r="2"/>
                        <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
                        <path d="M5.1 12.8 19 12"/>
                        <path d="M8.9 15.6 19 15"/>
                    </svg> Olin's <span>Cake</span>
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
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="tentang.php">Tentang Kami</a></li>
                    <li><a href="cara_pesan.php">Cara Pesan</a></li>
                    <li><a href="produk.php">Produk</a></li>
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
                    <i class="fa-solid fa-phone" style="margin-right: 8px; color: var(--olive-harvest);"></i> +62 895-2923-6657<br>
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
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Pilih Semua Checkbox Toggler
        function toggleSelectAll(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = masterCheckbox.checked;
            });
            updateSummary();
        }

        // Kalkulasi Summary & Aktifkan Tombol Checkout
        function updateSummary() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const masterCheckbox = document.getElementById('select-all');
            const checkoutBtn = document.getElementById('btn-checkout');
            const summaryItems = document.getElementById('summary-items');
            const summaryTotal = document.getElementById('summary-total');

            let selectedCount = 0;
            let totalHarga = 0;
            let totalCheckboxes = checkboxes.length;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedCount++;
                    const row = document.getElementById('item-row-' + cb.value);
                    const harga = parseInt(row.getAttribute('data-harga'));
                    const qty = parseInt(document.getElementById('qty-' + cb.value).value);
                    totalHarga += (harga * qty);
                }
            });

            // Sinkronkan checkbox "Pilih Semua"
            if (masterCheckbox) {
                masterCheckbox.checked = (selectedCount === totalCheckboxes && totalCheckboxes > 0);
            }

            // Tampilkan Ringkasan Pesanan
            summaryItems.innerText = selectedCount + " Item";
            summaryTotal.innerText = "Rp " + totalHarga.toLocaleString('id-ID');

            // Aktifkan / Matikan tombol Checkout
            if (selectedCount > 0) {
                checkoutBtn.disabled = false;
            } else {
                checkoutBtn.disabled = true;
            }
        }

        // Update Kuantitas Item via AJAX
        function updateItemQty(idKeranjang, amount) {
            const qtyInput = document.getElementById('qty-' + idKeranjang);
            const subtotalText = document.getElementById('subtotal-' + idKeranjang);
            const row = document.getElementById('item-row-' + idKeranjang);
            const harga = parseInt(row.getAttribute('data-harga'));

            let currentQty = parseInt(qtyInput.value);
            let newQty = currentQty + amount;

            if (newQty < 1 || newQty > 99) return; // Batasan 1 - 99

            const formData = new FormData();
            formData.append('action', 'update_qty');
            formData.append('id_keranjang', idKeranjang);
            formData.append('jumlah', newQty);

            fetch('keranjang.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    qtyInput.value = newQty;
                    // Recalculate Subtotal Row
                    const subtotal = harga * newQty;
                    subtotalText.innerText = "Rp " + subtotal.toLocaleString('id-ID');
                    
                    // Recalculate summary jika item dicentang
                    updateSummary();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan koneksi saat memperbarui jumlah.');
            });
        }

        // Hapus Satu Item via AJAX
        function deleteCartItem(idKeranjang) {
            if (!confirm('Apakah Anda yakin ingin menghapus kue ini dari keranjang belanja?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('id_keranjang', idKeranjang);

            fetch('keranjang.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById('item-row-' + idKeranjang);
                    
                    // Animasi menghilang (fade-out)
                    row.classList.add('fading-out');
                    setTimeout(() => {
                        row.remove();
                        // Jika keranjang benar-benar habis, muat ulang halaman untuk memunculkan Empty State
                        const rows = document.querySelectorAll('.cart-item-row');
                        if (rows.length === 0) {
                            window.location.reload();
                        } else {
                            updateSummary();
                        }
                    }, 300);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan koneksi saat menghapus kue.');
            });
        }

        // Hapus Banyak Item Terpilih via AJAX
        function deleteSelectedItems() {
            const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Silakan pilih produk yang ingin dihapus terlebih dahulu.');
                return;
            }

            if (!confirm('Apakah Anda yakin ingin menghapus ' + selectedCheckboxes.length + ' item terpilih dari keranjang?')) return;

            const ids = Array.from(selectedCheckboxes).map(cb => cb.value).join(',');
            const formData = new FormData();
            formData.append('action', 'delete_selected');
            formData.append('ids', ids);

            fetch('keranjang.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    selectedCheckboxes.forEach(cb => {
                        const row = document.getElementById('item-row-' + cb.value);
                        row.classList.add('fading-out');
                        setTimeout(() => {
                            row.remove();
                            const rows = document.querySelectorAll('.cart-item-row');
                            if (rows.length === 0) {
                                window.location.reload();
                            } else {
                                updateSummary();
                            }
                        }, 300);
                    });
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan koneksi saat menghapus item terpilih.');
            });
        }

        // Proses Menuju Checkout
        function proceedToCheckout() {
            const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
            if (selectedCheckboxes.length === 0) return;

            const ids = Array.from(selectedCheckboxes).map(cb => cb.value).join(',');
            // Alihkan ke checkout.php sambil mengirimkan ID keranjang terpilih
            window.location.href = "checkout.php?items=" + encodeURIComponent(ids);
        }

        // Inisialisasi awal summary jika ada barang
        window.addEventListener('DOMContentLoaded', () => {
            updateSummary();
        });
    </script>
</body>
</html>
