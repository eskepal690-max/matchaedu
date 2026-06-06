<?php
// Jika user sudah login, tendang langsung ke dashboard masing-masing
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] === 'admin') ? 'admin_dashboard' : 'dashboard';
    header("Location: " . BASE_URL . "index.php?page=" . $redirect);
    exit();
}
?>
<div style="min-height: 80vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px;">
    <div style="margin-bottom: 24px;">
        <div style="width: 80px; height: 80px; background: var(--matcha-light); border-radius: 24px; display: inline-flex; align-items: center; justify-content: center; color: var(--matcha-dark); box-shadow: var(--shadow-soft);">
            <i class="ph-fill ph-leaf" style="font-size: 48px;"></i>
        </div>
    </div>
    
    <h1 style="font-size: 2.5rem; color: var(--matcha-dark); font-weight: 700; margin-bottom: 16px;">Matcha Edu</h1>
    
    <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 500px; margin-bottom: 40px;">
        Platform Ujian Berbasis Komputer (CBT) modern dengan pengalaman belajar yang mulus, ringan, dan menyenangkan.
    </p>
    
    <div class="glass-card" style="display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: 350px;">
        <a href="<?= BASE_URL ?>index.php?page=login" class="btn btn-primary" style="width: 100%;">
            <i class="ph ph-sign-in"></i> Masuk Sekarang
        </a>
        <a href="<?= BASE_URL ?>index.php?page=register" class="btn" style="width: 100%; background: rgba(255,255,255,0.5); border: 1px solid var(--matcha-primary); color: var(--matcha-dark);">
            <i class="ph ph-user-plus"></i> Daftar Akun Baru
        </a>
        
        <button id="btn-install-pwa" class="btn" style="display: none; width: 100%; background: var(--text-dark); color: var(--white);">
            <i class="ph ph-download-simple"></i> Install Aplikasi (PWA)
        </button>
    </div>
</div>
