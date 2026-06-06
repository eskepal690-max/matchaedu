<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya siswa
require_login('student');

$user_id = $_SESSION['user_id'];
$view_attempt_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

// ==========================================================================
// TAMPILAN 2: DETAIL REVIEW JAWABAN
// ==========================================================================
if ($view_attempt_id > 0):
    // 1. Validasi Attempt dan Izin Review
    $stmt = $conn->prepare("
        SELECT a.final_score, p.title, p.show_review 
        FROM exam_attempts a 
        JOIN exam_packages p ON a.package_id = p.id 
        WHERE a.id = ? AND a.user_id = ? AND a.status = 'completed'
    ");
    $stmt->bind_param("ii", $view_attempt_id, $user_id);
    $stmt->execute();
    $att_res = $stmt->get_result();

    if ($att_res->num_rows === 0) {
        echo "<script>window.location.href='".BASE_URL."index.php?page=history';</script>";
        exit();
    }
    
    $attempt_data = $att_res->fetch_assoc();
    $stmt->close();

    // Jika admin menonaktifkan fitur review untuk paket ini
    if ($attempt_data['show_review'] == 0) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error', title: 'Akses Ditolak', text: 'Admin tidak mengizinkan review jawaban untuk ujian ini.',
                    confirmButtonColor: '#81C784'
                }).then(() => { window.location.href='".BASE_URL."index.php?page=history'; });
            });
        </script>";
        exit();
    }

    // 2. Ambil Data Detail Jawaban
    $answers = [];
    $stmt = $conn->prepare("
        SELECT ans.user_answer, ans.is_correct, ans.score_earned, 
               q.question_type, q.question_text, q.options_data, q.correct_answer, q.weight
        FROM exam_answers ans
        JOIN questions q ON ans.question_id = q.id
        WHERE ans.attempt_id = ?
        ORDER BY q.id ASC
    ");
    $stmt->bind_param("i", $view_attempt_id);
    $stmt->execute();
    $ans_res = $stmt->get_result();
    while ($row = $ans_res->fetch_assoc()) {
        $answers[] = $row;
    }
    $stmt->close();
?>

    <div style="margin-bottom: 25px;">
        <a href="<?= BASE_URL ?>index.php?page=history" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--bg-color); border: 1px solid var(--glass-border); margin-bottom: 15px;">
            <i class="ph ph-arrow-left"></i> Kembali ke Riwayat
        </a>
        <h2 style="color: var(--text-dark); font-size: 20px; margin-bottom: 5px;">Review Jawaban</h2>
        <p style="color: var(--text-muted); font-size: 14px;"><?= htmlspecialchars($attempt_data['title']) ?></p>
    </div>

    <!-- Skor Sticky -->
    <div class="glass-card" style="position: sticky; top: 80px; z-index: 100; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
        <span style="font-weight: 600; color: var(--text-dark);">Nilai Akhir:</span>
        <span style="font-size: 24px; font-weight: 700; color: <?= $attempt_data['final_score'] >= 75 ? '#065F46' : '#991B1B' ?>;">
            <?= $attempt_data['final_score'] ?>
        </span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 15px;">
        <?php foreach ($answers as $idx => $ans): 
            $is_correct = $ans['is_correct'] == 1;
            $bg_color = $is_correct ? '#D1FAE5' : '#FEE2E2';
            $border_color = $is_correct ? '#34D399' : '#F87171';
            $icon = $is_correct ? '<i class="ph-fill ph-check-circle" style="color:#059669; font-size:20px;"></i>' : '<i class="ph-fill ph-x-circle" style="color:#DC2626; font-size:20px;"></i>';
            
            // Format jawaban user untuk ditampilkan
            $user_ans_display = htmlspecialchars($ans['user_answer']);
            if (empty($user_ans_display) || $user_ans_display == '[]' || $user_ans_display == '{}') {
                $user_ans_display = "<em>Tidak dijawab</em>";
            } else {
                // Rapikan tampilan array JSON jika ada
                $decoded = json_decode($ans['user_answer'], true);
                if (is_array($decoded)) {
                    if (isset($decoded[0])) {
                        // Array biasa (PG Kompleks / TF)
                        $user_ans_display = implode(', ', $decoded);
                    } else {
                        // Object (Match)
                        $pairs = [];
                        foreach ($decoded as $k => $v) $pairs[] = "$k &rarr; $v";
                        $user_ans_display = implode('<br>', $pairs);
                    }
                }
            }
            
            // Format kunci jawaban
            $correct_display = htmlspecialchars($ans['correct_answer']);
            $decoded_correct = json_decode($ans['correct_answer'], true);
            if (is_array($decoded_correct)) {
                if (isset($decoded_correct[0])) {
                    $correct_display = implode(', ', $decoded_correct);
                } else {
                    $pairs = [];
                    foreach ($decoded_correct as $k => $v) $pairs[] = "$k &rarr; $v";
                    $correct_display = implode('<br>', $pairs);
                }
            }
        ?>
        <div style="border: 1px solid <?= $border_color ?>; background: var(--white); border-radius: var(--radius-sm); padding: 15px; position: relative; overflow: hidden;">
            <!-- Indikator warna di tepi kiri -->
            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: <?= $border_color ?>;"></div>
            
            <div style="padding-left: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <span style="font-weight: 700; color: var(--text-dark);">Soal #<?= $idx + 1 ?></span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; color: var(--text-muted);">Poin: <?= $ans['score_earned'] ?>/<?= $ans['weight'] ?></span>
                        <?= $icon ?>
                    </div>
                </div>
                
                <div class="quill-content-display" style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">
                    <?= $ans['question_text'] ?>
                </div>

                <div style="background: var(--bg-color); border-radius: var(--radius-sm); padding: 12px; font-size: 13px;">
                    <div style="margin-bottom: 8px;">
                        <span style="color: var(--text-muted); display: block; font-size: 11px; margin-bottom: 2px;">Jawaban Kamu:</span>
                        <div style="color: <?= $is_correct ? '#065F46' : '#991B1B' ?>; font-weight: 500;">
                            <?= $user_ans_display ?>
                        </div>
                    </div>
                    <?php if (!$is_correct): ?>
                    <div style="border-top: 1px dashed var(--glass-border); padding-top: 8px;">
                        <span style="color: var(--text-muted); display: block; font-size: 11px; margin-bottom: 2px;">Kunci Jawaban Benar:</span>
                        <div style="color: #059669; font-weight: 500;">
                            <?= $correct_display ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <style>
        .quill-content-display img { max-width: 100%; border-radius: 8px; margin: 10px 0; }
        .quill-content-display p { margin-bottom: 5px; }
    </style>

<?php 
// ==========================================================================
// TAMPILAN 1: DAFTAR RIWAYAT UJIAN
// ==========================================================================
else: 
    $history = [];
    $stmt = $conn->prepare("
        SELECT a.id, a.final_score, a.attempt_number, a.finished_at, 
               p.title, p.show_score, p.show_review 
        FROM exam_attempts a 
        JOIN exam_packages p ON a.package_id = p.id 
        WHERE a.user_id = ? AND a.status = 'completed' 
        ORDER BY a.finished_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
?>

    <div style="margin-bottom: 25px;">
        <h2 style="color: var(--text-dark); font-size: 22px; margin-bottom: 5px;">Riwayat Ujian 🕒</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Daftar seluruh ujian yang telah kamu selesaikan.</p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 15px;">
        <?php if (count($history) === 0): ?>
            <div class="glass-card" style="text-align: center; padding: 40px 20px;">
                <i class="ph ph-clock-counter-clockwise" style="font-size: 48px; color: var(--text-muted); margin-bottom: 10px;"></i>
                <h4 style="color: var(--text-dark); margin-bottom: 5px;">Belum Ada Riwayat</h4>
                <p style="color: var(--text-muted); font-size: 13px;">Kamu belum menyelesaikan ujian apapun.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($history as $h): 
                $date_str = date('d M Y, H:i', strtotime($h['finished_at']));
            ?>
            <div class="glass-card" style="padding: 20px; display: flex; flex-direction: column;">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h4 style="font-size: 16px; color: var(--text-dark); margin: 0 0 5px 0; line-height: 1.4;">
                            <?= htmlspecialchars($h['title']) ?>
                        </h4>
                        <div style="display: flex; gap: 10px; font-size: 12px; color: var(--text-muted);">
                            <span><i class="ph ph-calendar-blank"></i> <?= $date_str ?></span>
                            <span>•</span>
                            <span>Percobaan ke-<?= $h['attempt_number'] ?></span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-color); padding: 12px 15px; border-radius: var(--radius-sm); border: 1px dashed var(--glass-border); margin-bottom: 15px;">
                    <span style="font-size: 13px; font-weight: 500; color: var(--text-dark);">Nilai Akhir:</span>
                    <?php if ($h['show_score'] == 1): ?>
                        <span style="font-size: 20px; font-weight: 700; color: <?= $h['final_score'] >= 75 ? '#065F46' : '#991B1B' ?>;">
                            <?= $h['final_score'] ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size: 13px; font-weight: 600; color: #D97706; background: #FEF3C7; padding: 4px 10px; border-radius: 20px;">
                            <i class="ph-fill ph-eye-slash"></i> Disembunyikan
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($h['show_review'] == 1): ?>
                    <a href="<?= BASE_URL ?>index.php?page=history&view=<?= $h['id'] ?>" class="btn" style="width: 100%; background: var(--matcha-light); color: var(--matcha-dark); border: none; box-shadow: none;">
                        <i class="ph-fill ph-magnifying-glass"></i> Lihat Review Jawaban
                    </a>
                <?php else: ?>
                    <button class="btn" disabled style="width: 100%; background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; box-shadow: none; border: none;">
                        <i class="ph-fill ph-lock-key"></i> Review Dinonaktifkan
                    </button>
                <?php endif; ?>
                
            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

<?php endif; ?>
