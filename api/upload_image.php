<?php
/* ==========================================================================
   API: UPLOAD GAMBAR KE CLOUDINARY
   Fungsi: Menerima file gambar dari editor Quill.js (Admin)
   ========================================================================== */

require_once '../include/config.php';

// Pastikan yang nge-hit endpoint ini adalah Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Hanya Admin yang dapat mengunggah gambar.']);
    exit();
}

// Cek apakah ada file yang dikirim dengan nama 'image' atau 'file'
$fileKey = isset($_FILES['image']) ? 'image' : (isset($_FILES['file']) ? 'file' : null);

if (!$fileKey || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Tidak ada file yang diunggah atau terjadi kesalahan saat upload.']);
    exit();
}

// Persiapkan data file untuk dikirim via cURL
$tmp_name = $_FILES[$fileKey]['tmp_name'];
$cfile = new CURLFile($tmp_name, $_FILES[$fileKey]['type'], $_FILES[$fileKey]['name']);

$post_data = [
    'file' => $cfile,
    'upload_preset' => CLOUDINARY_UPLOAD_PRESET
];

// Inisialisasi cURL untuk nembak langsung ke API Cloudinary
$ch = curl_init();
$cloudinary_url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload";

curl_setopt($ch, CURLOPT_URL, $cloudinary_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Eksekusi dan ambil respon dari Cloudinary
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// Jika sukses, kembalikan URL gambar ke Quill.js
if ($http_code === 200 && isset($result['secure_url'])) {
    echo json_encode(['url' => $result['secure_url']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengunggah ke Cloudinary.', 'details' => $result]);
}
exit();
?>
