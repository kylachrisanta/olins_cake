-- Skema Database untuk Olin's Cake (Bahasa Indonesia)
CREATE DATABASE IF NOT EXISTS `olins_cake`;
USE `olins_cake`;

-- Tabel Pelanggan
CREATE TABLE IF NOT EXISTS `pelanggan` (
  `id_pelanggan` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `nama_pengguna` VARCHAR(50) NOT NULL UNIQUE,
  `nomor_wa` VARCHAR(20) NOT NULL,
  `kata_sandi` VARCHAR(255) NOT NULL,
  `alamat` TEXT,
  `foto_profil` VARCHAR(255) DEFAULT NULL,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Memasukkan Data Pelanggan Bawaan (Seed Data)
INSERT INTO `pelanggan` (`id_pelanggan`, `nama_lengkap`, `nama_pengguna`, `nomor_wa`, `kata_sandi`, `alamat`) VALUES
(1, 'Budi Santoso', 'budi_santoso', '081234567890', '$2y$10$MqLtIfRJopIwhZNFU1odoelo7D2YgCvyK2oZnJjItDezUUttPkTPe', 'Jl. Mawar No. 10, Jakarta Selatan'),
(2, 'Ani Lestari', 'ani_lestari', '08111222333', '$2y$10$kNZ/aP/Nn6uFbxxaLV.hWOtgrA1YclgkWvA3VvXrs837ElKTQCIKa', 'Jl. Melati No. 5, Jakarta Pusat')
ON DUPLICATE KEY UPDATE 
  `nama_lengkap` = VALUES(`nama_lengkap`),
  `nomor_wa` = VALUES(`nomor_wa`),
  `kata_sandi` = VALUES(`kata_sandi`),
  `alamat` = VALUES(`alamat`);


-- Tabel Produk dengan Kolom Kategori, Ukuran, dan Masa Simpan
CREATE TABLE IF NOT EXISTS `produk` (
  `id_produk` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_produk` VARCHAR(100) NOT NULL,
  `deskripsi` TEXT,
  `harga` INT NOT NULL,
  `gambar` VARCHAR(255) NOT NULL,
  `kategori` ENUM('Bolu', 'Kue Kering', 'Kue Basah') NOT NULL,
  `ukuran` VARCHAR(50) NOT NULL,
  `masa_simpan` VARCHAR(100) NOT NULL,
  `status_produk` ENUM('Aktif', 'Diarsipkan') NOT NULL DEFAULT 'Aktif',
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Keranjang Belanja Pelanggan
CREATE TABLE IF NOT EXISTS `keranjang` (
  `id_keranjang` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pelanggan` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `jumlah` INT NOT NULL DEFAULT 1,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Memasukkan Data Produk Lengkap
INSERT INTO `produk` (`id_produk`, `nama_produk`, `deskripsi`, `harga`, `gambar`, `kategori`, `ukuran`, `masa_simpan`) VALUES
(1, 'Strawberry Shortcake', 'Kue spons vanila lembut dilapisi krim segar melimpah dan buah strawberry pilihan segar.', 180000, 'strawberry_shortcake.png', 'Bolu', 'Diameter 18 cm', '3 Hari (Kulkas)'),
(2, 'Signature Chocolate Fudge', 'Kue cokelat premium berlapis fudge cokelat pekat yang lumer di setiap gigitan.', 195000, 'chocolate_fudge.png', 'Bolu', 'Diameter 20 cm', '3 Hari (Suhu Ruang) / 7 Hari (Kulkas)'),
(3, 'Classic Pandan Cheese', 'Kue pandan wangi alami daun suji dikombinasikan dengan gurihnya keju parut premium.', 165000, 'pandan_cheese.png', 'Bolu', 'Diameter 20 cm', '3 Hari (Suhu Ruang)'),
(4, 'Nastar Keju Premium', 'Nastar nanas lembut dengan selai nanas homemade yang legit, dibalur dengan keju parut renyah di atasnya.', 95000, 'nastar.png', 'Kue Kering', 'Toples 500 gram', '1 Bulan (Wadah Kedap Udara)'),
(5, 'Kastengel Edam', 'Kue kering keju premium bercita rasa gurih dari keju edam asli berkualitas tinggi.', 110000, 'kastengel.png', 'Kue Kering', 'Toples 500 gram', '1 Bulan (Wadah Kedap Udara)'),
(6, 'Lapis Legit Premium', 'Kue lapis legit dengan aroma rempah harum khas tradisional, lembut dan moist di setiap lapisnya.', 250000, 'lapis_legit.png', 'Kue Basah', 'Loyang 20x20 cm', '4 Hari (Suhu Ruang) / 10 Hari (Kulkas)'),
(7, 'Risoles Mayo Melt', 'Risoles gurih dengan balutan tepung roti renyah, diisi dengan smoked beef, telur rebus, dan saus mayones lumer.', 45000, 'risoles.png', 'Kue Basah', '1 Pax (Isi 10 Pcs)', '2 Hari (Kulkas) / 1 Bulan (Freezer)')
ON DUPLICATE KEY UPDATE 
  `nama_produk` = VALUES(`nama_produk`),
  `deskripsi` = VALUES(`deskripsi`),
  `harga` = VALUES(`harga`),
  `gambar` = VALUES(`gambar`),
  `kategori` = VALUES(`kategori`),
  `ukuran` = VALUES(`ukuran`),
  `masa_simpan` = VALUES(`masa_simpan`);

-- Tabel Pesanan Utama
CREATE TABLE IF NOT EXISTS `pesanan` (
  `id_pesanan` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pelanggan` INT NOT NULL,
  `nama_penerima` VARCHAR(100) NOT NULL,
  `nomor_wa` VARCHAR(20) NOT NULL,
  `metode_pengiriman` ENUM('Kirim ke Alamat', 'Ambil Sendiri') NOT NULL,
  `alamat_pengiriman` TEXT NULL,
  `garis_lintang` VARCHAR(50) NULL,
  `garis_bujur` VARCHAR(50) NULL,
  `jarak_km` DECIMAL(5,2) DEFAULT 0.00,
  `tanggal_pengiriman` DATE NOT NULL,
  `waktu_pengiriman` ENUM('08.00 - 12.00', '12.00 - 16.00', '16.00 - 20.00') NOT NULL,
  `catatan` TEXT NULL,
  `ongkos_kirim` INT DEFAULT 0,
  `total_bayar` INT NOT NULL,
  `status_pesanan` VARCHAR(50) DEFAULT 'Menunggu Pembayaran',
  `status_pembayaran` VARCHAR(50) DEFAULT 'Belum Dibayar',
  `metode_pembayaran` VARCHAR(50) DEFAULT NULL,
  `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
  `batas_pembayaran` DATETIME DEFAULT NULL,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Detail Pesanan Kue
CREATE TABLE IF NOT EXISTS `detail_pesanan` (
  `id_detail` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pesanan` INT NOT NULL,
  `id_produk` INT NOT NULL,
  `jumlah` INT NOT NULL,
  `harga_satuan` INT NOT NULL,
  FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE,
  FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrasi: Tambahkan kolom status_produk pada tabel produk (jalankan sekali pada database yang sudah ada)
ALTER TABLE `produk` 
  ADD COLUMN IF NOT EXISTS `status_produk` ENUM('Aktif','Diarsipkan') NOT NULL DEFAULT 'Aktif'
  AFTER `masa_simpan`;
