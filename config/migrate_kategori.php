<?php
// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Include database connection
require_once __DIR__ . '/database.php';

echo "=== Memulai Migrasi Kategori Produk ===\n<br>";

// 1. Pastikan tabel kategori ada
$query_kategori = "CREATE TABLE IF NOT EXISTS `kategori` (
  `id_kategori` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(50) NOT NULL UNIQUE,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($query_kategori)) {
    echo "✔ Tabel 'kategori' siap.\n<br>";
} else {
    die("❌ Gagal membuat/memastikan tabel 'kategori': " . $conn->error . "\n<br>");
}

// 2. Masukkan data kategori awal jika kosong
$check_kat = $conn->query("SELECT COUNT(*) as count FROM `kategori`")->fetch_assoc();
if ($check_kat['count'] == 0) {
    $conn->query("INSERT INTO `kategori` (`nama_kategori`) VALUES ('Bolu'), ('Kue Kering'), ('Kue Basah')");
    echo "✔ Seed data kategori awal ('Bolu', 'Kue Kering', 'Kue Basah') berhasil dimasukkan.\n<br>";
} else {
    echo "ℹ Tabel 'kategori' sudah memiliki data. Seeding dilewati.\n<br>";
}

// 3. Periksa apakah kolom 'kategori' bertipe string/ENUM masih ada di tabel 'produk'
$columns_res = $conn->query("SHOW COLUMNS FROM `produk` LIKE 'kategori'");
if ($columns_res && $columns_res->num_rows > 0) {
    echo "ℹ Ditemukan kolom 'kategori' lama (bertipe string/ENUM) pada tabel 'produk'. Memulai proses migrasi data...\n<br>";
    
    // Pastikan semua kategori unik yang ada di tabel produk terdaftar di tabel kategori terlebih dahulu
    $distinct_res = $conn->query("SELECT DISTINCT kategori FROM produk WHERE kategori IS NOT NULL AND kategori != ''");
    if ($distinct_res) {
        while ($row = $distinct_res->fetch_assoc()) {
            $kat_name = trim($row['kategori']);
            $check = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ?");
            $check->bind_param("s", $kat_name);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
                $ins->bind_param("s", $kat_name);
                if ($ins->execute()) {
                    echo "✔ Kategori baru '$kat_name' otomatis ditambahkan ke tabel kategori.\n<br>";
                }
                $ins->close();
            }
            $check->close();
        }
    }

    // Tambahkan kolom 'id_kategori' baru (sementara bertipe INT DEFAULT NULL agar aman saat ALTER)
    $add_col = $conn->query("ALTER TABLE `produk` ADD COLUMN `id_kategori` INT DEFAULT NULL AFTER `id_produk`");
    if ($add_col) {
        echo "✔ Kolom 'id_kategori' berhasil ditambahkan ke tabel 'produk'.\n<br>";
    } else {
        // Cek apakah kolom id_kategori sebenarnya sudah ada
        $check_id_kat = $conn->query("SHOW COLUMNS FROM `produk` LIKE 'id_kategori'");
        if ($check_id_kat && $check_id_kat->num_rows > 0) {
            echo "ℹ Kolom 'id_kategori' sudah ada sebelumnya.\n<br>";
        } else {
            die("❌ Gagal menambahkan kolom 'id_kategori': " . $conn->error . "\n<br>");
        }
    }

    // Petakan nilai dari kolom 'kategori' lama ke kolom 'id_kategori' baru
    $update_mapping = $conn->query("
        UPDATE produk p 
        JOIN kategori k ON TRIM(p.kategori) = k.nama_kategori 
        SET p.id_kategori = k.id_kategori
    ");
    if ($update_mapping) {
        echo "✔ Pemetaan data kategori lama ke ID kategori baru sukses.\n<br>";
    } else {
        die("❌ Gagal memetakan data kategori: " . $conn->error . "\n<br>");
    }

    // Jika ada produk yang id_kategori nya masih NULL, beri kategori default pertama
    $check_null = $conn->query("SELECT COUNT(*) as count FROM produk WHERE id_kategori IS NULL")->fetch_assoc();
    if ($check_null['count'] > 0) {
        echo "ℹ Ditemukan " . $check_null['count'] . " produk tanpa kategori. Memetakan ke kategori pertama...\n<br>";
        $first_kat = $conn->query("SELECT id_kategori FROM kategori LIMIT 1")->fetch_assoc();
        if ($first_kat) {
            $id_first = $first_kat['id_kategori'];
            $conn->query("UPDATE produk SET id_kategori = $id_first WHERE id_kategori IS NULL");
            echo "✔ Produk tanpa kategori berhasil dipetakan ke ID kategori $id_first.\n<br>";
        }
    }

    // Ubah kolom id_kategori menjadi NOT NULL
    if ($conn->query("ALTER TABLE `produk` MODIFY COLUMN `id_kategori` INT NOT NULL")) {
        echo "✔ Kolom 'id_kategori' diubah menjadi NOT NULL.\n<br>";
    } else {
        die("❌ Gagal mengubah kolom 'id_kategori' menjadi NOT NULL: " . $conn->error . "\n<br>");
    }

    // Hapus kolom kategori yang lama
    if ($conn->query("ALTER TABLE `produk` DROP COLUMN `kategori`")) {
        echo "✔ Kolom 'kategori' lama berhasil dihapus.\n<br>";
    } else {
        die("❌ Gagal menghapus kolom 'kategori' lama: " . $conn->error . "\n<br>");
    }
} else {
    echo "ℹ Kolom 'kategori' lama tidak ditemukan di tabel 'produk'. Kemungkinan migrasi kolom sudah pernah dijalankan.\n<br>";
}

// 4. Tambahkan constraint Foreign Key jika belum ada
$check_fk = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = '$db' 
      AND TABLE_NAME = 'produk' 
      AND COLUMN_NAME = 'id_kategori' 
      AND REFERENCED_TABLE_NAME = 'kategori'
");

if ($check_fk && $check_fk->num_rows == 0) {
    // Tambahkan foreign key constraint
    $add_fk = "ALTER TABLE `produk` 
               ADD CONSTRAINT `fk_produk_kategori` 
               FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) 
               ON DELETE RESTRICT ON UPDATE CASCADE";
    if ($conn->query($add_fk)) {
        echo "✔ Constraint Foreign Key 'fk_produk_kategori' (ON DELETE RESTRICT ON UPDATE CASCADE) berhasil dipasang.\n<br>";
    } else {
        die("❌ Gagal memasang constraint Foreign Key: " . $conn->error . "\n<br>");
    }
} else {
    echo "ℹ Constraint Foreign Key 'fk_produk_kategori' sudah ada.\n<br>";
}

echo "=== Migrasi Selesai dengan Sukses! ===\n<br>";
?>
