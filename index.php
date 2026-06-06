<?php
/* ==========================================================================
   MATCHA EDU - FRONT CONTROLLER (Router Utama)
   Fungsi: Mengatur semua navigasi halaman dengan aman tanpa .htaccess
   ========================================================================== */

// 1. Panggil file konfigurasi dan fungsi inti
require_once 'include/config.php';
require_once 'include/functions.php';

// 2. Ambil parameter halaman dari URL (contoh: index.php?page=dashboard)
// Default ke 'home' jika tidak ada parameter page
$page = isset($_GET['page']) ? sanitize_input($conn, $_GET['page']) : 'home';

// 3. Logika khusus untuk Logout
if ($page === 'logout') {
    // Hapus semua data sesi
    session_unset();
    session_destroy();
    
    // Arahkan kembali ke halaman awal
    header("Location: " . BASE_URL . "index.php?page=home");
    exit();
}

// 4. Daftar rute (Whitelist) yang diizinkan untuk diakses
// Ini mencegah celah keamanan Local File Inclusion (LFI)
$routes = [
    // Auth & Publik
    'home'      => 'pages/auth/home.php',
    'login'     => 'pages/auth/login.php',
    'register'  => 'pages/auth/register.php',
    
    // Sisi Siswa (Student)
    'dashboard' => 'pages/user/dashboard.php',
    'video'     => 'pages/user/video.php',
    'history'   => 'pages/user/history.php',
    'exam'      => 'pages/user/exam.php',
    
    // Sisi Admin
    'admin_dashboard' => 'pages/admin/dashboard.php',
    'admin_users'     => 'pages/admin/users.php',
    'admin_packages'  => 'pages/admin/packages.php',
    'admin_questions' => 'pages/admin/questions.php',
    'admin_videos'    => 'pages/admin/videos.php',
    'admin_grades'    => 'pages/admin/grades.php'
];

// Cek apakah rute yang diminta ada di daftar whitelist
if (array_key_exists($page, $routes)) {
    $file_to_include = $routes[$page];
} else {
    // Jika tidak ada, arahkan ke variabel null untuk memicu error 404 di bawah
    $file_to_include = null;
}

// ==========================================================================
// RENDER HALAMAN UTAMA (Memuat Header -> Konten Utama -> Footer)
// ==========================================================================

// Buka HTML dan Header Dinamis
require_once 'include/header.php';

// Muat konten halaman yang sesuai
if ($file_to_include && file_exists($file_to_include)) {
    require_once $file_to_include;
} else {
    // Tampilan Error 404 jika file tidak ditemukan
    echo "
    <div style='text-align:center; padding: 100px 20px; flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;'>
        <div class='glass-card' style='max-width: 400px; width: 100%;'>
            <h1 style='font-size: 64px; color: var(--matcha-dark); margin-bottom: 10px;'>404</h1>
            <h3 style='color: var(--text-dark); margin-bottom: 15px;'>Halaman Tidak Ditemukan</h3>
            <p style='color: var(--text-muted); margin-bottom: 25px;'>Sepertinya kamu tersesat, halaman yang kamu tuju tidak ada atau sedang dalam perbaikan.</p>
            <a href='" . BASE_URL . "index.php' class='btn btn-primary'>Kembali ke Beranda</a>
        </div>
    </div>";
}

// Tutup HTML dan Footer Dinamis
require_once 'include/footer.php';
?>
