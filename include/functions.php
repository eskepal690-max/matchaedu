<?php
/* ==========================================================================
   MATCHA EDU - CORE FUNCTIONS
   Fungsi Bantuan PHP Native (Auth, Keamanan, Parsing)
   ========================================================================== */

/**
 * 1. Fungsi Sanitasi Input (Anti XSS & SQL Injection Basic)
 * Wajib dipakai setiap kali menerima data dari $_POST atau $_GET
 */
function sanitize_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    // Escape string untuk MySQLi
    return $conn->real_escape_string($data);
}

/**
 * 2. Fungsi Generate MatchaID (Format: MCH-XXXXXX)
 * Otomatis ngecek ke database biar nggak ada ID yang ganda
 */
function generateMatchaID($conn) {
    $isUnique = false;
    $matchaID = "";
    
    while (!$isUnique) {
        // Bikin 6 digit angka/huruf acak (uppercase)
        $randomStr = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $matchaID = "MCH-" . $randomStr;
        
        // Cek ke database apakah ID sudah dipakai
        $stmt = $conn->prepare("SELECT id FROM users WHERE matcha_id = ?");
        $stmt->bind_param("s", $matchaID);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            $isUnique = true; // Lolos, ID unik!
        }
        $stmt->close();
    }
    
    return $matchaID;
}

/**
 * 3. Fungsi Parse CSV untuk Import Bank Soal
 * Mengubah file CSV menjadi array asosiatif
 */
function parse_csv_questions($file_tmp_path) {
    $questions = [];
    
    // Buka file CSV mode read
    if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Ambil baris pertama (Header)
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Pastikan jumlah kolom data sama dengan header untuk menghindari error array offset
            if(count($header) == count($data)){
                $questions[] = array_combine($header, $data);
            }
        }
        fclose($handle);
    }
    
    return $questions;
}

/**
 * 4. Fungsi Cek Login Siswa / Admin
 * Redirect ke halaman login jika belum ada sesi
 */
function require_login($required_role = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['matcha_id'])) {
        header("Location: " . BASE_URL . "index.php?page=login");
        exit();
    }
    
    // Jika butuh role khusus (misal halaman admin hanya untuk role 'admin')
    if ($required_role !== null && $_SESSION['role'] !== $required_role) {
        // Lempar kembali ke dashboard masing-masing jika role tidak sesuai
        $redirect_page = ($_SESSION['role'] === 'admin') ? 'admin_dashboard' : 'dashboard';
        header("Location: " . BASE_URL . "index.php?page=" . $redirect_page);
        exit();
    }
}
?>
