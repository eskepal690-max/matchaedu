<?php
/* ==========================================================================
   API: PUSHER VERIFY (AUTH)
   Fungsi: Otentikasi channel private Pusher tanpa menggunakan Composer.
   ========================================================================== */

// Panggil file konfigurasi untuk mendapatkan konstanta PUSHER (SECRET & KEY)
require_once '../include/config.php';

// Beri tahu browser bahwa ini adalah balasan JSON
header('Content-Type: application/json');

// 1. Pastikan yang meminta otentikasi adalah user yang sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Anda harus login.']);
    exit();
}

// 2. Pusher akan mengirim data socket_id dan channel_name via POST
$socket_id = isset($_POST['socket_id']) ? $_POST['socket_id'] : '';
$channel_name = isset($_POST['channel_name']) ? $_POST['channel_name'] : '';

// Validasi input
if (empty($socket_id) || empty($channel_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: socket_id atau channel_name kosong.']);
    exit();
}

// 3. Buat Signature secara Native (Pengganti Composer Pusher PHP Server)
// Format string yang harus dienkripsi: socket_id:channel_name
$string_to_sign = $socket_id . ':' . $channel_name;

// Gunakan fungsi hash_hmac PHP dengan algoritma sha256 dan PUSHER_SECRET sebagai kunci
$signature = hash_hmac('sha256', $string_to_sign, PUSHER_SECRET);

// 4. Susun balasan JSON sesuai standar yang diminta oleh Pusher JS
$auth_string = PUSHER_KEY . ':' . $signature;

// Kirim balasan sukses
echo json_encode([
    'auth' => $auth_string
]);
exit();
?>
