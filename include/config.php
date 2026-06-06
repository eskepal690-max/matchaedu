<?php
/* ==========================================================================
   MATCHA EDU - RAILWAY CONFIGURATION
   ========================================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

// --- 1. KONFIGURASI DATABASE (RAILWAY) ---
define('DB_HOST', 'mysql.railway.internal'); 
define('DB_USER', 'root');      
define('DB_PASS', 'PfYVahtKekLlTrtWjdNhbFToqtBMwsOM');          
define('DB_NAME', 'railway');
define('DB_PORT', '3306');

// Koneksi MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// --- 2. KONFIGURASI API ---
define('PUSHER_APP_ID', '2161018');
define('PUSHER_KEY', 'fcb3a112603e522beb67');
define('PUSHER_SECRET', '8bc8ce9e319528f94d9a');
define('PUSHER_CLUSTER', 'ap1');

define('CLOUDINARY_CLOUD_NAME', 'dcr9qrjm5');
define('CLOUDINARY_UPLOAD_PRESET', 'matcha_edu');
define('CLOUDINARY_API_KEY', '835136864776926');

// --- 3. KONFIGURASI APLIKASI ---
define('APP_NAME', 'Matcha Edu');
// Gunakan URL Railway langsung untuk menghindari error deteksi
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST']); 

?>
