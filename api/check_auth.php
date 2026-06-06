<?php
/* ==========================================================================
   API: CHECK AUTH
   Fungsi: Mengembalikan status login user saat ini dalam format JSON
   ========================================================================== */

// Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Beri tahu browser bahwa ini adalah balasan JSON
header('Content-Type: application/json');

// Cek apakah ada sesi user_id
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Jika sedang login, kembalikan data dasar user (tanpa password)
    echo json_encode([
        'status' => 'success',
        'is_logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'matcha_id' => $_SESSION['matcha_id'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'gender' => $_SESSION['gender']
        ]
    ]);
} else {
    // Jika tidak ada sesi (belum login / expired)
    echo json_encode([
        'status' => 'error',
        'is_logged_in' => false,
        'message' => 'Sesi telah habis atau belum login.'
    ]);
}
exit();
?>
