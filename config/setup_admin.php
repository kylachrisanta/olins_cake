<?php
// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Include database connection
require_once __DIR__ . '/database.php';

echo "=== Memulai Migrasi Database Admin Olin's Cake ===\n<br>";

// 1. Buat Tabel Admin
$query_admin = "CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($query_admin)) {
    echo "✔ Tabel 'admin' siap.\n<br>";
} else {
    echo "❌ Gagal membuat tabel 'admin': " . $conn->error . "\n<br>";
}

// 2. Buat Tabel Kategori
$query_kategori = "CREATE TABLE IF NOT EXISTS `kategori` (
  `id_kategori` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(50) NOT NULL UNIQUE,
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($query_kategori)) {
    echo "✔ Tabel 'kategori' siap.\n<br>";
} else {
    echo "❌ Gagal membuat tabel 'kategori': " . $conn->error . "\n<br>";
}

// 3. Buat Tabel Testimoni
$query_testimoni = "CREATE TABLE IF NOT EXISTS `testimoni` (
  `id_testimoni` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `pekerjaan` VARCHAR(100) NOT NULL,
  `isi_testimoni` TEXT NOT NULL,
  `avatar_initial` VARCHAR(5) NOT NULL,
  `status` ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif',
  `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($query_testimoni)) {
    echo "✔ Tabel 'testimoni' siap.\n<br>";
} else {
    echo "❌ Gagal membuat tabel 'testimoni': " . $conn->error . "\n<br>";
}

// 4. Modifikasi tabel produk (kolom kategori dari ENUM ke VARCHAR)
$query_alter_produk = "ALTER TABLE `produk` MODIFY COLUMN `kategori` VARCHAR(50) NOT NULL;";
if ($conn->query($query_alter_produk)) {
    echo "✔ Kolom 'kategori' pada tabel 'produk' berhasil dimodifikasi menjadi VARCHAR.\n<br>";
} else {
    echo "❌ Gagal memodifikasi kolom 'kategori' pada tabel 'produk': " . $conn->error . "\n<br>";
}

// 5. Seed Data Kategori awal
$check_kat = $conn->query("SELECT COUNT(*) as count FROM `kategori`")->fetch_assoc();
if ($check_kat['count'] == 0) {
    $conn->query("INSERT INTO `kategori` (`nama_kategori`) VALUES ('Bolu'), ('Kue Kering'), ('Kue Basah')");
    echo "✔ Seed kategori awal berhasil dimasukkan.\n<br>";
} else {
    echo "ℹ Kategori sudah memiliki data. Seeding dilewati.\n<br>";
}

// 6. Seed Data Admin default (username: admin, password: admin123)
$check_admin = $conn->query("SELECT COUNT(*) as count FROM `admin`")->fetch_assoc();
if ($check_admin['count'] == 0) {
    // Generate bcrypt hash untuk 'admin123'
    $hashed_password = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO `admin` (`username`, `password`, `nama_lengkap`) VALUES (?, ?, ?)");
    $username = 'admin';
    $nama_lengkap = 'Administrator Olin';
    $stmt->bind_param("sss", $username, $hashed_password, $nama_lengkap);
    if ($stmt->execute()) {
        echo "✔ Seed Akun Admin default berhasil dibuat (User: **admin** / Pass: **admin123**).\n<br>";
    } else {
        echo "❌ Gagal membuat akun admin default: " . $stmt->error . "\n<br>";
    }
    $stmt->close();
} else {
    echo "ℹ Akun Admin sudah ada. Seeding dilewati.\n<br>";
}

// 7. Seed Data Testimoni default dari homepage
$check_testi = $conn->query("SELECT COUNT(*) as count FROM `testimoni`")->fetch_assoc();
if ($check_testi['count'] == 0) {
    $testimonis = [
        [
            'nama' => 'Ratih Ningsih',
            'pekerjaan' => 'Ibu Rumah Tangga (42 thn)',
            'isi' => 'Pesan Strawberry Shortcake untuk ulang tahun mama kemarin, semua sepupu dan tante memuji kuenya! Lembut sekali, manisnya pas dan buah stroberinya banyak yang manis segar. Sangat direkomendasikan!',
            'avatar' => 'RN'
        ],
        [
            'nama' => 'Dewi Amalia',
            'pekerjaan' => 'Karyawati & Ibu 2 Anak (35 thn)',
            'isi' => 'Chocolate Fudge-nya juara! Cokelatnya terasa sangat premium, tidak getir dan tidak bikin enek. Anak-anak saya makan sampai habis tidak tersisa. Pre-ordernya juga gampang dan pengiriman on time.',
            'avatar' => 'DA'
        ],
        [
            'nama' => 'Hartati S.',
            'pekerjaan' => 'Pecinta Kuliner (55 thn)',
            'isi' => 'Pandan Cheese-nya wangi sekali pandan asli, taburan kejunya tebal melimpah. Teksturnya lumer banget di mulut. Sangat cocok dinikmati sore hari bersama teh hangat bersama keluarga.',
            'avatar' => 'HS'
        ]
    ];

    $stmt_t = $conn->prepare("INSERT INTO `testimoni` (`nama_lengkap`, `pekerjaan`, `isi_testimoni`, `avatar_initial`, `status`) VALUES (?, ?, ?, ?, 'Aktif')");
    foreach ($testimonis as $t) {
        $stmt_t->bind_param("ssss", $t['nama'], $t['pekerjaan'], $t['isi'], $t['avatar']);
        $stmt_t->execute();
    }
    $stmt_t->close();
    echo "✔ Seed testimoni default berhasil dimasukkan.\n<br>";
} else {
    echo "ℹ Testimoni sudah memiliki data. Seeding dilewati.\n<br>";
}

echo "=== Migrasi Selesai ===\n<br>";
$conn->close();
?>
