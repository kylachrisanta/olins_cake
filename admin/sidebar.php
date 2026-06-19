<?php
// Proteksi halaman admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_initial = !empty($_SESSION['admin_name']) ? strtoupper(substr($_SESSION['admin_name'], 0, 2)) : 'AD';
$current_page = isset($page) ? $page : 'dashboard';
?>
<!-- Sidebar Admin Panel -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="admin-logo">
        <i class="fa-solid fa-cake-candles"></i>
        <span>Olin's <span>Cake</span></span>
    </div>
    
    <ul class="admin-menu">
        <li class="admin-menu-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <a href="index.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li class="admin-menu-item <?= $current_page === 'produk' ? 'active' : '' ?>">
            <a href="produk.php">
                <i class="fa-solid fa-cookie-bite"></i> Kelola Produk
            </a>
        </li>
        <li class="admin-menu-item <?= $current_page === 'pesanan' ? 'active' : '' ?>">
            <a href="pesanan.php">
                <i class="fa-solid fa-receipt"></i> Kelola Pesanan
            </a>
        </li>

        <li class="admin-menu-item <?= $current_page === 'testimoni' ? 'active' : '' ?>">
            <a href="testimoni.php">
                <i class="fa-solid fa-comment-dots"></i> Kelola Testimoni
            </a>
        </li>
        <li class="admin-menu-item <?= $current_page === 'pengguna' ? 'active' : '' ?>">
            <a href="pengguna.php">
                <i class="fa-solid fa-users"></i> Kelola Pengguna
            </a>
        </li>
    </ul>

    <div class="admin-user-profile">
        <div class="admin-avatar"><?= $admin_initial ?></div>
        <div class="admin-profile-info" style="flex-grow: 1; overflow: hidden;">
            <span class="admin-profile-name" style="display: block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($_SESSION['admin_name']) ?>">
                <?= htmlspecialchars($_SESSION['admin_name']) ?>
            </span>
            <span class="admin-profile-role">Administrator</span>
        </div>
        <a href="logout.php" style="color: var(--admin-danger); font-size: 1.15rem; display: flex; align-items: center; margin-left: 8px;" title="Keluar">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<!-- Header Mobile Hamburger -->
<div style="background-color: var(--admin-sidebar-bg); border-bottom: 1px solid var(--admin-border); padding: 12px 20px; display: none; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 99;" class="admin-mobile-header-bar">
    <div style="display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1.15rem;">
        <i class="fa-solid fa-cake-candles" style="color: var(--admin-accent);"></i> Olin's Cake
    </div>
    <button class="admin-mobile-nav-toggle" id="adminSidebarToggle" onclick="document.getElementById('adminSidebar').classList.toggle('active')" style="display: block; font-size: 1.3rem;">
        <i class="fa-solid fa-bars"></i>
    </button>
</div>

<style>
    @media (max-width: 768px) {
        .admin-mobile-header-bar {
            display: flex !important;
        }
        .admin-layout {
            padding: 24px 16px !important;
        }
    }
</style>
