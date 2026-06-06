<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil parameter halaman saat ini (default: home)
$page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'home';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('APP_NAME') ? APP_NAME : 'Matcha Edu' ?> | CBT Modern</title>

    <!-- PWA Manifest & Icons (TAMBAHAN PENTING DI SINI) -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#81C784">

    <!-- 1. Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- 2. Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- 3. SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- 4. IziToast -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">
    <script src="https://cdn.jsdelivr.net/npm/izitoast/dist/js/iziToast.min.js"></script>

    <!-- 5. Grid.js & Chart.js (Untuk Admin) -->
    <?php if ($role === 'admin'): ?>
    <link href="https://cdn.jsdelivr.net/npm/gridjs/dist/theme/mermaid.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

    <!-- 6. Quill.js, KaTeX, Cloudinary (Untuk CBT) -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script src="https://upload-widget.cloudinary.com/global/all.js" type="text/javascript"></script>

    <!-- 7. Pusher (Realtime Notif) -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <!-- Custom CSS (Liquid Glass) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <!-- Eruda Console (Tools Debugging) -->
    <script src="https://cdn.jsdelivr.net/npm/eruda"></script>
    <script>eruda.init();</script>
    
</head>
<body>

<?php 
// =========================================================================
// LOGIKA TAMPILAN HEADER DINAMIS BERDASARKAN HALAMAN & ROLE
// =========================================================================

if ($role === 'student' && in_array($page, ['dashboard', 'video', 'history'])): 
?>
    <!-- A. HEADER SISWA (Sticky & Profil) -->
    <header class="sticky-header">
        <div class="header-profile">
            <?php 
            $gender = $_SESSION['gender'] ?? 'L';
            $avatar = ($gender === 'P') ? 'default_female.jpg' : 'default_male.jpg';
            ?>
            <img src="<?= BASE_URL ?>assets/images/<?= $avatar ?>" alt="Profil">
            <div>
                <h4 style="font-size: 14px; margin:0; font-weight:600;"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Siswa') ?></h4>
                <span style="font-size: 11px; color: var(--text-muted);"><?= $_SESSION['matcha_id'] ?? '' ?></span>
            </div>
        </div>
        <!-- Tombol Logout -->
        <a href="<?= BASE_URL ?>index.php?page=logout" onclick="return confirm('Yakin ingin keluar?')" style="color: #E53E3E;">
            <i class="ph ph-sign-out" style="font-size: 24px;"></i>
        </a>
    </header>
    <div class="content-wrapper"> <!-- Wrapper Konten Utama Siswa -->

<?php elseif ($role === 'student' && $page === 'exam'): ?>
    <!-- B. HEADER UJIAN (CBT Mode) -->
    <header class="sticky-header" style="justify-content: space-between; z-index: 1050;">
        <div style="display:flex; align-items:center; gap: 10px;">
            <button onclick="toggleExamSidebar()" style="background:none; border:none; font-size:24px; cursor:pointer; color: var(--text-dark);">
                <i class="ph ph-list"></i>
            </button>
            <h4 style="font-size: 15px; margin:0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;">
                Paket Dikerjakan
            </h4>
        </div>
        <!-- Timer -->
        <div style="display:flex; align-items:center; gap: 6px; background: rgba(255,255,255,0.8); padding: 4px 10px; border-radius: 20px;">
            <i class="ph ph-timer" style="font-size: 18px; color: var(--matcha-dark);"></i>
            <span id="exam-timer" style="font-weight: 600; font-size: 14px;">--:--:--</span>
        </div>
        <!-- Tombol Kumpul Manual -->
        <button id="btn-header-submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Kumpul</button>
    </header>
    <div class="content-wrapper"> <!-- Wrapper Konten Ujian -->

<?php elseif ($role === 'admin'): ?>
    <!-- C. LAYOUT ADMIN (Buka wrapper Admin) -->
    <div class="admin-wrapper">
        <?php include 'include/sidebar_admin.php'; ?>
        <div class="admin-content">
            <!-- Header Mobile Admin (Hamburger) -->
            <div class="admin-mobile-header" style="display: flex; align-items: center; gap: 15px; margin-bottom: 24px;">
                <button onclick="toggleAdminSidebar()" style="background:none; border:none; font-size:24px; cursor:pointer;" class="d-md-none">
                    <i class="ph ph-list"></i>
                </button>
                <h2 style="font-size: 20px; font-weight: 600;">Dashboard Admin</h2>
            </div>

<?php else: ?>
    <!-- D. HALAMAN AUTH / HOME (Tanpa Header) -->
    <div> 
<?php endif; ?>
        