<?php
/* ==========================================================================
   MATCHA EDU - CONFIGURATION FILE
   Koneksi Database & API Keys
   ========================================================================== */

// Mulai sesi untuk login/auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set zona waktu ke WIB (Probolinggo/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// --- 1. KONFIGURASI DATABASE ---
// Sesuaikan dengan kredensial InfinityFree kamu nanti
define('DB_HOST', 'sql309.byethost3.com'); // Di InfinityFree biasanya format: sqlXXX.infinityfree.com
define('DB_USER', 'b3_42096092');      // Username DB
define('DB_PASS', 'T38gjvqMuzj8IZsZ');          // Password DB
define('DB_NAME', 'b3_42096092_matcha');

// Buka koneksi MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi (Aman untuk hosting gratisan: tidak nampilin path directory)
if ($conn->connect_error) {
    die("Koneksi Database Gagal. Silakan hubungi Administrator.");
}

// Set charset ke utf8mb4 agar support emoji & karakter khusus di soal
$conn->set_charset("utf8mb4");


// --- 2. KONFIGURASI API (PUSHER & CLOUDINARY) ---

// Pusher Credentials (Realtime Notif)
define('PUSHER_APP_ID', '2161018');
define('PUSHER_KEY', 'fcb3a112603e522beb67');
define('PUSHER_SECRET', '8bc8ce9e319528f94d9a');
define('PUSHER_CLUSTER', 'ap1');

// Cloudinary Credentials (Hanya Cloud Name & Preset untuk Client-Side Upload)
define('CLOUDINARY_CLOUD_NAME', 'dcr9qrjm5');
define('CLOUDINARY_UPLOAD_PRESET', 'matcha_edu');
define('CLOUDINARY_API_KEY', '835136864776926'); // Disimpan jaga-jaga kalau butuh akses Server-Side

// --- 3. KONFIGURASI APLIKASI ---
define('APP_NAME', 'Matcha Edu');
// BASE_URL otomatis mendeteksi domain/localhost
$base_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];
$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
define('BASE_URL', $base_url);

?>
