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
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.6rem; height: 1.6rem; display: inline-block; vertical-align: middle; margin-right: 8px; color: var(--admin-accent);">
            <circle cx="9" cy="7" r="2"/>
            <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
            <path d="M5.1 12.8 19 12"/>
            <path d="M8.9 15.6 19 15"/>
        </svg>
        <span>Olin's <span>Cake</span></span>
        <!-- Close Button Mobile -->
        <button class="sidebar-close-btn" onclick="document.getElementById('adminSidebar').classList.remove('active'); document.getElementById('sidebarOverlay').classList.remove('active')" aria-label="Tutup Menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
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

        <li class="admin-menu-item <?= $current_page === 'pengguna' ? 'active' : '' ?>">
            <a href="pengguna.php">
                <i class="fa-solid fa-users"></i> Daftar Pengguna
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
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="logo-svg" style="width: 1.5rem; height: 1.5rem; display: inline-block; vertical-align: middle; margin-right: 4px; margin-top: -2px; color: var(--admin-accent);">
            <circle cx="9" cy="7" r="2"/>
            <path d="M7.2 7.9 3 11v9c0 .6.4 1 1 1h16c.6 0 1-.4 1-1v-9l-4.2-3.1"/>
            <path d="M5.1 12.8 19 12"/>
            <path d="M8.9 15.6 19 15"/>
        </svg> Olin's Cake
    </div>
    <button class="admin-mobile-nav-toggle" id="adminSidebarToggle" onclick="document.getElementById('adminSidebar').classList.add('active'); document.getElementById('sidebarOverlay').classList.add('active')" style="display: block; font-size: 1.3rem;">
        <i class="fa-solid fa-bars"></i>
    </button>
</div>

<!-- Overlay Backdrop Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('adminSidebar').classList.remove('active'); this.classList.remove('active')"></div>

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
