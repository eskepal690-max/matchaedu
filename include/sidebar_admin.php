<?php
// Pastikan file ini tidak diakses langsung dari URL
if (!defined('BASE_URL')) {
    exit('Akses ditolak.');
}
?>
<aside class="sidebar" id="admin-sidebar">
    <!-- Header Sidebar (Logo / Nama Aplikasi) -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; padding-bottom: 15px; border-bottom: 1px solid var(--glass-border);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 35px; height: 35px; background: var(--matcha-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--matcha-dark);">
                <i class="ph-fill ph-leaf" style="font-size: 20px;"></i>
            </div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--matcha-dark);">Matcha Edu</h3>
        </div>
        <!-- Tombol Tutup Sidebar (Hanya muncul di Mobile) -->
        <button onclick="toggleAdminSidebar()" class="d-md-none" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer;">
            <i class="ph ph-x"></i>
        </button>
    </div>

    <!-- Navigasi Menu Admin -->
    <nav style="display: flex; flex-direction: column; gap: 8px; flex: 1;">
        <a href="<?= BASE_URL ?>index.php?page=admin_dashboard" 
           class="btn <?= ($page === 'admin_dashboard') ? 'btn-primary' : '' ?>" 
           style="justify-content: flex-start; padding: 12px 16px; background: <?= ($page === 'admin_dashboard') ? 'var(--matcha-primary)' : 'transparent' ?>; color: <?= ($page === 'admin_dashboard') ? '#fff' : 'var(--text-muted)' ?>; box-shadow: none;">
            <i class="ph <?= ($page === 'admin_dashboard') ? 'ph-squares-four-fill' : 'ph-squares-four' ?>" style="font-size: 20px;"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>index.php?page=admin_users" 
           class="btn <?= ($page === 'admin_users') ? 'btn-primary' : '' ?>" 
           style="justify-content: flex-start; padding: 12px 16px; background: <?= ($page === 'admin_users') ? 'var(--matcha-primary)' : 'transparent' ?>; color: <?= ($page === 'admin_users') ? '#fff' : 'var(--text-muted)' ?>; box-shadow: none;">
            <i class="ph <?= ($page === 'admin_users') ? 'ph-users-fill' : 'ph-users' ?>" style="font-size: 20px;"></i>
            <span>Siswa Terdaftar</span>
        </a>

        <a href="<?= BASE_URL ?>index.php?page=admin_packages" 
           class="btn <?= ($page === 'admin_packages') ? 'btn-primary' : '' ?>" 
           style="justify-content: flex-start; padding: 12px 16px; background: <?= ($page === 'admin_packages') ? 'var(--matcha-primary)' : 'transparent' ?>; color: <?= ($page === 'admin_packages') ? '#fff' : 'var(--text-muted)' ?>; box-shadow: none;">
            <i class="ph <?= ($page === 'admin_packages') ? 'ph-folder-open-fill' : 'ph-folder-open' ?>" style="font-size: 20px;"></i>
            <span>Paket Ujian</span>
        </a>

        <a href="<?= BASE_URL ?>index.php?page=admin_videos" 
           class="btn <?= ($page === 'admin_videos') ? 'btn-primary' : '' ?>" 
           style="justify-content: flex-start; padding: 12px 16px; background: <?= ($page === 'admin_videos') ? 'var(--matcha-primary)' : 'transparent' ?>; color: <?= ($page === 'admin_videos') ? '#fff' : 'var(--text-muted)' ?>; box-shadow: none;">
            <i class="ph <?= ($page === 'admin_videos') ? 'ph-video-camera-fill' : 'ph-video-camera' ?>" style="font-size: 20px;"></i>
            <span>Video Belajar</span>
        </a>

        <a href="<?= BASE_URL ?>index.php?page=admin_grades" 
           class="btn <?= ($page === 'admin_grades') ? 'btn-primary' : '' ?>" 
           style="justify-content: flex-start; padding: 12px 16px; background: <?= ($page === 'admin_grades') ? 'var(--matcha-primary)' : 'transparent' ?>; color: <?= ($page === 'admin_grades') ? '#fff' : 'var(--text-muted)' ?>; box-shadow: none;">
            <i class="ph <?= ($page === 'admin_grades') ? 'ph-chart-bar-fill' : 'ph-chart-bar' ?>" style="font-size: 20px;"></i>
            <span>Nilai & Laporan</span>
        </a>
    </nav>

    <!-- Profil Admin & Tombol Logout di Bawah -->
    <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--glass-border);">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--matcha-light); display: flex; align-items: center; justify-content: center; color: var(--matcha-dark);">
                <i class="ph-fill ph-user-circle" style="font-size: 28px;"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></h4>
                <span style="font-size: 12px; color: var(--matcha-primary); font-weight: 500;">Mode Admin</span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>index.php?page=logout" class="btn" style="width: 100%; background: #FEE2E2; color: #E53E3E; box-shadow: none; padding: 10px;">
            <i class="ph ph-sign-out" style="font-size: 18px;"></i> Logout
        </a>
    </div>
</aside>

<!-- Tambahan sedikit CSS inline untuk d-md-none (biar rapi di mobile) -->
<style>
    @media (min-width: 769px) {
        .d-md-none { display: none !important; }
    }
    /* Efek hover untuk menu yang tidak aktif */
    nav .btn:not(.btn-primary):hover {
        background: var(--bg-color) !important;
        color: var(--matcha-dark) !important;
    }
</style>
