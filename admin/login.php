<?php
// Start Session
session_start();

// Include Database
require_once '../config/database.php';

// Jika admin sudah login, langsung alihkan ke dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error_login = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error_login = "Username dan password wajib diisi.";
    } else {
        // Cari admin di database
        $stmt = $conn->prepare("SELECT * FROM `admin` WHERE `username` = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin_user = $result->fetch_assoc();
        $stmt->close();

        if ($admin_user && password_verify($password, $admin_user['password'])) {
            // Set session
            $_SESSION['admin_id'] = $admin_user['id_admin'];
            $_SESSION['admin_username'] = $admin_user['username'];
            $_SESSION['admin_name'] = $admin_user['nama_lengkap'];

            // Redirect
            header("Location: index.php");
            exit;
        } else {
            $error_login = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - Olin's Cake</title>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=1.2">
    <style>
        .login-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--admin-dark-bg);
            padding: 24px;
        }
        .login-card {
            background-color: var(--admin-sidebar-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--admin-border);
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            box-shadow: var(--shadow-md);
        }
        .login-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        .login-logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(210, 179, 140, 0.1);
            border: 2px solid var(--admin-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--admin-accent);
        }
        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--admin-text-main);
        }
        .login-logo h1 span {
            color: var(--admin-accent);
        }
        .login-logo p {
            color: var(--admin-text-light);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .alert-error {
            background-color: rgba(231, 29, 54, 0.1);
            color: var(--admin-danger);
            border: 1px solid rgba(231, 29, 54, 0.2);
            padding: 12px;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-logo">
                <div class="login-logo-circle">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h1>Olin's <span>Cake</span> Admin</h1>
                <p>Silakan masuk menggunakan kredensial Administrator Anda</p>
            </div>

            <?php if (!empty($error_login)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error_login) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="admin-form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="admin-form-control" placeholder="Masukkan username admin" required autocomplete="off">
                </div>
                
                <div class="admin-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="admin-form-control" placeholder="Masukkan password" required>
                </div>

                <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; margin-top: 10px; justify-content: center;">
                    Masuk Panel Admin <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px;">
                <a href="../index.php" style="color: var(--admin-text-light); font-size: 0.85rem; text-decoration: none; transition: color 0.3s ease;" onmouseover="this.style.color='var(--admin-accent)'" onmouseout="this.style.color='var(--admin-text-light)'">
                    <i class="fa-solid fa-globe" style="margin-right: 4px;"></i> Kembali ke Halaman Utama Toko
                </a>
            </div>

        </div>
    </div>

</body>
</html>
