<?php
/* ==========================================================================
   API: SUBMIT EXAM & AUTO-GRADING (SUPPORT ALL 5 QUESTION TYPES)
   Fungsi: Menerima jawaban, mengoreksi, dan menyimpan nilai akhir.
   Tipe soal: pg, pg_complex, true_false, match, short_answer
   ========================================================================== */

require_once '../include/config.php';

// Beri tahu browser bahwa balasan berupa JSON
header('Content-Type: application/json');

// Pastikan user yang submit adalah Siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

// Ambil input JSON dari frontend (fetch API)
$input = json_decode(file_get_contents('php://input'), true);

$attempt_id = isset($input['attempt_id']) ? intval($input['attempt_id']) : 0;
$answers = isset($input['answers']) ? $input['answers'] : []; // Format: [ question_id => user_answer ]

// 1. Validasi apakah ID Ujian (attempt_id) valid dan statusnya masih 'in_progress'
$stmt = $conn->prepare("SELECT package_id FROM exam_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
$stmt->bind_param("ii", $attempt_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi ujian tidak valid atau waktu sudah habis.']);
    exit();
}

$attempt_row = $result->fetch_assoc();
$package_id = $attempt_row['package_id'];
$stmt->close();

// 2. Tarik semua kunci jawaban & bobot dari bank soal berdasarkan package_id
$questions = [];
$total_weight = 0; // Total bobot maksimal
$stmt = $conn->prepare("SELECT id, question_type, correct_answer, options_data, weight FROM questions WHERE package_id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$q_result = $stmt->get_result();

while ($row = $q_result->fetch_assoc()) {
    $questions[$row['id']] = $row;
    $total_weight += $row['weight'];
}
$stmt->close();

// 3. Proses Pengoreksian & Simpan Detail Jawaban
$earned_score = 0; // Poin yang berhasil didapat siswa
$detail_results = []; // Untuk response (opsional, bisa dikirim ke frontend)

$insert_stmt = $conn->prepare("INSERT INTO exam_answers (attempt_id, question_id, user_answer, is_correct, score_earned) VALUES (?, ?, ?, ?, ?)");

foreach ($questions as $q_id => $q_data) {
    $user_ans_raw = isset($answers[$q_id]) ? $answers[$q_id] : '';
    $question_type = $q_data['question_type'];
    $correct_raw = $q_data['correct_answer'];
    $options_raw = $q_data['options_data'];
    $weight = floatval($q_data['weight']);
    
    $is_correct = 0;
    $score = 0;
    $user_ans_normalized = ''; // Untuk disimpan di DB (string)
    
    // ======================================================================
    // KOREKSI BERDASARKAN TIPE SOAL
    // ======================================================================
    
    // ---------- TIPE 1: PILIHAN GANDA (Single Choice) ----------
    if ($question_type === 'pg') {
        $user_ans_normalized = is_string($user_ans_raw) ? strtoupper(trim($user_ans_raw)) : '';
        $correct = strtoupper(trim($correct_raw));
        
        if ($user_ans_normalized === $correct) {
            $is_correct = 1;
            $score = $weight;
        }
    }
    
    // ---------- TIPE 2: PILIHAN GANDA KOMPLEKS (Multi Choice) ----------
    elseif ($question_type === 'pg_complex') {
        // Jawaban user bisa array atau string JSON
        if (is_array($user_ans_raw)) {
            $user_answers = array_map('strtoupper', array_map('trim', $user_ans_raw));
        } elseif (is_string($user_ans_raw)) {
            $decoded = json_decode($user_ans_raw, true);
            $user_answers = is_array($decoded) ? array_map('strtoupper', array_map('trim', $decoded)) : explode(',', str_replace(' ', '', strtoupper($user_ans_raw)));
        } else {
            $user_answers = [];
        }
        
        // Kunci jawaban disimpan sebagai JSON array, contoh: ["A","C","D"]
        $correct = json_decode($correct_raw, true);
        if (!is_array($correct)) {
            // Fallback kalau formatnya string biasa
            $correct = explode(',', str_replace(' ', '', strtoupper($correct_raw)));
        }
        $correct = array_map('strtoupper', array_map('trim', $correct));
        
        // Normalisasi untuk disimpan
        $user_ans_normalized = json_encode($user_answers);
        
        // Sort kedua array sebelum dibandingkan
        sort($user_answers);
        sort($correct);
        
        if ($user_answers === $correct) {
            $is_correct = 1;
            $score = $weight;
        }
        // Bisa juga tambahkan partial scoring kalau mau
        // else {
        //     $correct_count = count(array_intersect($user_answers, $correct));
        //     $wrong_count = count(array_diff($user_answers, $correct));
        //     if ($correct_count > 0 && $wrong_count == 0) {
        //         $score = $weight * 0.5; // 50% kalau kurang lengkap
        //     }
        // }
    }
    
    // ---------- TIPE 3: BENAR / SALAH (Tabel Pernyataan) ----------
    elseif ($question_type === 'true_false') {
        // Jawaban user: array atau JSON string, contoh: ["Benar","Salah","Benar"]
        if (is_array($user_ans_raw)) {
            $user_answers = array_map('trim', $user_ans_raw);
        } elseif (is_string($user_ans_raw)) {
            $decoded = json_decode($user_ans_raw, true);
            $user_answers = is_array($decoded) ? array_map('trim', $decoded) : explode(',', str_replace(' ', '', $user_ans_raw));
        } else {
            $user_answers = [];
        }
        
        // Kunci jawaban disimpan sebagai JSON array
        $correct = json_decode($correct_raw, true);
        if (!is_array($correct)) {
            $correct = explode(',', str_replace(' ', '', $correct_raw));
        }
        $correct = array_map('trim', $correct);
        
        // Normalisasi untuk disimpan
        $user_ans_normalized = json_encode($user_answers);
        
        // Bandingkan per elemen
        $all_match = true;
        if (count($user_answers) === count($correct)) {
            foreach ($correct as $i => $ans) {
                if (!isset($user_answers[$i]) || strtolower(trim($user_answers[$i])) !== strtolower(trim($ans))) {
                    $all_match = false;
                    break;
                }
            }
        } else {
            $all_match = false;
        }
        
        if ($all_match) {
            $is_correct = 1;
            $score = $weight;
        }
    }
    
    // ---------- TIPE 4: MENJODOHKAN (Matching) ----------
    elseif ($question_type === 'match') {
        // Jawaban user: object/array associative, contoh: {"Indonesia":"Jakarta","Jepang":"Tokyo"}
        if (is_array($user_ans_raw)) {
            $user_answers = $user_ans_raw;
        } elseif (is_string($user_ans_raw)) {
            $decoded = json_decode($user_ans_raw, true);
            $user_answers = is_array($decoded) ? $decoded : [];
        } else {
            $user_answers = [];
        }
        
        // Kunci jawaban: JSON object, contoh: {"Indonesia":"Jakarta","Jepang":"Tokyo"}
        $correct = json_decode($correct_raw, true);
        if (!is_array($correct)) {
            $correct = [];
        }
        
        // Normalisasi untuk disimpan
        $user_ans_normalized = json_encode($user_answers);
        
        // Bandingkan semua pasangan
        $all_match = true;
        $total_pairs = count($correct);
        $matched_pairs = 0;
        
        foreach ($correct as $key => $value) {
            $user_value = isset($user_answers[$key]) ? trim($user_answers[$key]) : '';
            if (strtolower($user_value) === strtolower(trim($value))) {
                $matched_pairs++;
            } else {
                $all_match = false;
            }
        }
        
        // Juga cek apakah user mengirimkan jawaban lebih dari yang seharusnya
        if (count($user_answers) !== $total_pairs) {
            $all_match = false;
        }
        
        if ($all_match) {
            $is_correct = 1;
            $score = $weight;
        }
        // Partial scoring: sesuai jumlah pasangan benar
        // else if ($total_pairs > 0) {
        //     $score = ($matched_pairs / $total_pairs) * $weight;
        //     if ($matched_pairs == $total_pairs) $is_correct = 1;
        // }
    }
    
    // ---------- TIPE 5: ISIAN SINGKAT ----------
    elseif ($question_type === 'short_answer') {
        $user_ans_normalized = is_string($user_ans_raw) ? trim($user_ans_raw) : '';
        $correct = trim($correct_raw);
        
        // Case-insensitive, trim spasi
        if (strtolower($user_ans_normalized) === strtolower($correct)) {
            $is_correct = 1;
            $score = $weight;
        }
        // Bisa tambahkan opsi mengandung kata kunci (contains)
        // elseif (stripos($user_ans_normalized, $correct) !== false) {
        //     $is_correct = 1;
        //     $score = $weight;
        // }
    }
    
    // Akumulasi skor
    $earned_score += $score;
    
    // Simpan detail hasil ke array (untuk response)
    $detail_results[] = [
        'question_id' => $q_id,
        'type' => $question_type,
        'user_answer' => $user_ans_normalized,
        'correct_answer' => $correct_raw,
        'is_correct' => $is_correct,
        'score_earned' => $score,
        'weight' => $weight
    ];
    
    // Masukkan ke tabel detail jawaban siswa
    $insert_stmt->bind_param("iisid", $attempt_id, $q_id, $user_ans_normalized, $is_correct, $score);
    $insert_stmt->execute();
}
$insert_stmt->close();

// 4. Hitung Nilai Akhir (Skala 0 - 100)
$final_score = ($total_weight > 0) ? ($earned_score / $total_weight) * 100 : 0;
$final_score = round($final_score, 2);

// 5. Update status ujian menjadi 'completed' beserta nilai akhirnya
$stmt = $conn->prepare("UPDATE exam_attempts SET final_score = ?, status = 'completed', finished_at = NOW() WHERE id = ?");
$stmt->bind_param("di", $final_score, $attempt_id);
$stmt->execute();
$stmt->close();

// Beri respons sukses ke frontend
echo json_encode([
    'status' => 'success',
    'message' => 'Ujian berhasil dikumpulkan.',
    'final_score' => $final_score,
    'total_weight' => $total_weight,
    'earned_score' => $earned_score,
    'total_questions' => count($questions),
    'details' => $detail_results // Array detail per soal (opsional, bisa dihapus kalau tidak perlu)
]);
exit();
?>