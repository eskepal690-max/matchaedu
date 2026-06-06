<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya siswa
require_login('student');

$user_id = $_SESSION['user_id'];
$current_time = time(); // Waktu saat ini (Timestamp)

// Ambil semua paket ujian yang statusnya 'active'
$packages = [];
$stmt = $conn->prepare("SELECT * FROM exam_packages WHERE status = 'active' ORDER BY created_at DESC");
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $pkg_id = $row['id'];
    
    // Cek riwayat ujian siswa untuk paket ini
    $attempt_stmt = $conn->prepare("SELECT status FROM exam_attempts WHERE user_id = ? AND package_id = ? ORDER BY started_at DESC");
    $attempt_stmt->bind_param("ii", $user_id, $pkg_id);
    $attempt_stmt->execute();
    $att_res = $attempt_stmt->get_result();
    
    $completed_count = 0;
    $has_in_progress = false;
    
    while ($att_row = $att_res->fetch_assoc()) {
        if ($att_row['status'] === 'completed') $completed_count++;
        if ($att_row['status'] === 'in_progress') $has_in_progress = true;
    }
    $attempt_stmt->close();
    
    $row['completed_count'] = $completed_count;
    $row['has_in_progress'] = $has_in_progress;
    
    $packages[] = $row;
}
$stmt->close();
?>

<div style="margin-bottom: 25px;">
    <h2 style="color: var(--text-dark); font-size: 22px; margin-bottom: 5px;">Hai, <?= htmlspecialchars(explode(' ', trim($_SESSION['full_name']))[0]) ?>! 👋</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Siap untuk belajar dan ujian hari ini?</p>
</div>

<h3 style="font-size: 16px; color: var(--text-dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
    <i class="ph-fill ph-notebook" style="color: var(--matcha-dark); font-size: 20px;"></i> Daftar Ujian
</h3>

<div style="display: flex; flex-direction: column; gap: 15px;">
    <?php if (count($packages) === 0): ?>
        <div class="glass-card" style="text-align: center; padding: 40px 20px;">
            <i class="ph ph-coffee" style="font-size: 48px; color: var(--text-muted); margin-bottom: 10px;"></i>
            <h4 style="color: var(--text-dark); margin-bottom: 5px;">Belum Ada Ujian</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Saat ini tidak ada paket ujian yang aktif. Waktunya istirahat!</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($packages as $pkg): 
            // Cek Jadwal
            $start_time = $pkg['start_date'] ? strtotime($pkg['start_date']) : null;
            $end_time = $pkg['end_date'] ? strtotime($pkg['end_date']) : null;
            
            $is_waiting = ($start_time && $current_time < $start_time);
            $is_expired = ($end_time && $current_time > $end_time);
            
            // Cek Limit
            $max_attempts = intval($pkg['max_attempts']);
            $is_maxed_out = ($max_attempts > 0 && $pkg['completed_count'] >= $max_attempts);
            
            // Penentuan Tombol & Status
            $btn_text = "Kerjakan";
            $btn_class = "btn-primary";
            $btn_icon = "ph-play-circle";
            $is_disabled = false;
            $status_badge = "";

            if ($pkg['has_in_progress']) {
                $btn_text = "Lanjutkan";
                $btn_class = "btn-primary";
                $btn_icon = "ph-arrow-circle-right";
                $status_badge = "<span style='background:#FEF3C7; color:#D97706; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight:600;'>Sedang Dikerjakan</span>";
            } elseif ($is_waiting) {
                $btn_text = "Belum Mulai";
                $btn_class = "btn";
                $is_disabled = true;
                $status_badge = "<span style='background:#E0E7FF; color:#4F46E5; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight:600;'>Segera Hadir</span>";
            } elseif ($is_expired) {
                $btn_text = "Ditutup";
                $btn_class = "btn";
                $is_disabled = true;
                $status_badge = "<span style='background:#FEE2E2; color:#DC2626; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight:600;'>Kadaluarsa</span>";
            } elseif ($is_maxed_out) {
                $btn_text = "Selesai";
                $btn_class = "btn";
                $btn_icon = "ph-check-circle";
                $is_disabled = true;
                $status_badge = "<span style='background:#D1FAE5; color:#065F46; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight:600;'>Sudah Dikerjakan</span>";
            } else {
                $status_badge = "<span style='background:#D1FAE5; color:#065F46; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight:600;'>Tersedia</span>";
            }
            
            // Format String Jadwal
            $jadwal_str = "Kapan saja";
            if ($start_time && $end_time) {
                $jadwal_str = date('d M Y, H:i', $start_time) . " - " . date('d M Y, H:i', $end_time);
            } elseif ($start_time) {
                $jadwal_str = "Mulai: " . date('d M Y, H:i', $start_time);
            } elseif ($end_time) {
                $jadwal_str = "Tutup: " . date('d M Y, H:i', $end_time);
            }
        ?>

        <div class="glass-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <h4 style="font-size: 16px; color: var(--text-dark); margin: 0; line-height: 1.4; padding-right: 10px;">
                    <?= htmlspecialchars($pkg['title']) ?>
                </h4>
                <?= $status_badge ?>
            </div>
            
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($pkg['description'] ?: 'Tidak ada deskripsi.') ?>
            </p>
            
            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; font-size: 12px; color: var(--text-dark);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-calendar" style="color: var(--matcha-dark); font-size: 16px;"></i>
                    <span><?= $jadwal_str ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-arrows-clockwise" style="color: var(--matcha-dark); font-size: 16px;"></i>
                    <span>Kesempatan: <?= $max_attempts > 0 ? $pkg['completed_count'] . " / " . $max_attempts : "Tak Terbatas" ?></span>
                </div>
            </div>

            <?php if ($is_disabled): ?>
                <button class="<?= $btn_class ?>" style="width: 100%; background: #E2E8F0; color: #94A3B8; cursor: not-allowed; box-shadow: none;">
                    <?= isset($btn_icon) ? "<i class='ph $btn_icon'></i>" : "" ?> <?= $btn_text ?>
                </button>
            <?php else: ?>
                <button onclick="startExam(<?= $pkg['id'] ?>, '<?= addslashes($pkg['title']) ?>', <?= $pkg['has_in_progress'] ? 'true' : 'false' ?>)" class="btn <?= $btn_class ?>" style="width: 100%;">
                    <i class="ph <?= $btn_icon ?>"></i> <?= $btn_text ?>
                </button>
            <?php endif; ?>
        </div>

        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function startExam(pkgId, title, isResume) {
        const dialogTitle = isResume ? 'Lanjutkan Ujian?' : 'Mulai Ujian?';
        const dialogText = isResume 
            ? `Kamu memiliki sesi ujian "${title}" yang belum selesai. Waktu akan dilanjutkan.` 
            : `Pastikan koneksi internet stabil. Setelah dimulai, ujian "${title}" tidak dapat dibatalkan.`;
        const confirmBtn = isResume ? 'Ya, Lanjutkan' : 'Ya, Mulai Sekarang';

        // Panggil fungsi confirmAction dari app.js
        confirmAction(dialogTitle, dialogText, confirmBtn, () => {
            // Redirect ke halaman mesin ujian (exam.php)
            window.location.href = '<?= BASE_URL ?>index.php?page=exam&id=' + pkgId;
        });
    }
</script>
