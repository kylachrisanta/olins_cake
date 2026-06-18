<?php
session_start();
// Hapus semua session admin
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);

// Destruksi session jika sudah kosong
if (empty($_SESSION)) {
    session_destroy();
}

// Redirect ke halaman login admin
header("Location: login.php");
exit;
?>
