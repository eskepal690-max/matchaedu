<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi ekstra: Pastikan hanya admin yang bisa buka halaman ini
require_login('admin');

// 1. Ambil Statistik Cepat dari Database
$total_students = $conn->query("SELECT COUNT(id) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$total_packages = $conn->query("SELECT COUNT(id) as total FROM exam_packages WHERE status = 'active'")->fetch_assoc()['total'];
$total_exams = $conn->query("SELECT COUNT(id) as total FROM exam_attempts WHERE status = 'completed'")->fetch_assoc()['total'];

// 2. Siapkan Data untuk Grafik Chart.js (Pendaftar 7 Hari Terakhir)
$chart_labels = [];
$chart_data_raw = [];

// Buat array tanggal 7 hari terakhir (default = 0)
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    $chart_data_raw[$date] = 0; 
}

// Ambil data sebenarnya dari database
$res_chart = $conn->query("SELECT DATE(created_at) as reg_date, COUNT(id) as count FROM users WHERE role = 'student' AND created_at >= DATE(NOW() - INTERVAL 7 DAY) GROUP BY DATE(created_at)");
while ($row = $res_chart->fetch_assoc()) {
    $date = $row['reg_date'];
    if (isset($chart_data_raw[$date])) {
        $chart_data_raw[$date] = $row['count'];
    }
}
$chart_values = array_values($chart_data_raw);
?>

<div style="margin-bottom: 30px;">
    <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Selamat Datang, <?= htmlspecialchars($_SESSION['full_name']) ?>! 👋</h1>
    <p style="color: var(--text-muted);">Pantau aktivitas Matcha Edu hari ini.</p>
</div>

<!-- Kartu Statistik -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="glass-card" style="display: flex; align-items: center; gap: 15px;">
        <div style="width: 50px; height: 50px; border-radius: 15px; background: var(--matcha-light); display: flex; align-items: center; justify-content: center; color: var(--matcha-dark);">
            <i class="ph-fill ph-users" style="font-size: 28px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500;">Total Siswa</p>
            <h3 style="color: var(--text-dark); font-size: 24px; margin: 0;"><?= number_format($total_students) ?></h3>
        </div>
    </div>
    
    <div class="glass-card" style="display: flex; align-items: center; gap: 15px;">
        <div style="width: 50px; height: 50px; border-radius: 15px; background: #FEF3C7; display: flex; align-items: center; justify-content: center; color: #D97706;">
            <i class="ph-fill ph-folder-open" style="font-size: 28px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500;">Paket Aktif</p>
            <h3 style="color: var(--text-dark); font-size: 24px; margin: 0;"><?= number_format($total_packages) ?></h3>
        </div>
    </div>

    <div class="glass-card" style="display: flex; align-items: center; gap: 15px;">
        <div style="width: 50px; height: 50px; border-radius: 15px; background: #DBEAFE; display: flex; align-items: center; justify-content: center; color: #2563EB;">
            <i class="ph-fill ph-check-circle" style="font-size: 28px;"></i>
        </div>
        <div>
            <p style="color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500;">Ujian Selesai</p>
            <h3 style="color: var(--text-dark); font-size: 24px; margin: 0;"><?= number_format($total_exams) ?></h3>
        </div>
    </div>
</div>

<!-- Layout Dua Kolom: Chart & Quick Actions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    
    <!-- Bagian Grafik -->
    <div class="glass-card" style="padding: 20px;">
        <h4 style="margin-bottom: 20px; color: var(--text-dark);">Pendaftar Baru (7 Hari Terakhir)</h4>
        <div style="position: relative; height: 250px; width: 100%;">
            <canvas id="registrationChart"></canvas>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="glass-card" style="padding: 20px;">
        <h4 style="margin-bottom: 20px; color: var(--text-dark);">Tindakan Cepat</h4>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="<?= BASE_URL ?>index.php?page=admin_packages" class="btn btn-primary" style="justify-content: flex-start; background: var(--matcha-primary); color: white; box-shadow: none;">
                <i class="ph ph-plus-circle" style="font-size: 20px;"></i> Buat Paket Baru
            </a>
            <a href="<?= BASE_URL ?>index.php?page=admin_videos" class="btn" style="justify-content: flex-start; background: var(--bg-color); border: 1px solid var(--glass-border);">
                <i class="ph ph-video-camera" style="font-size: 20px;"></i> Tambah Video Belajar
            </a>
            <a href="<?= BASE_URL ?>index.php?page=admin_grades" class="btn" style="justify-content: flex-start; background: var(--bg-color); border: 1px solid var(--glass-border);">
                <i class="ph ph-chart-bar" style="font-size: 20px;"></i> Lihat Laporan Nilai
            </a>
        </div>
    </div>

</div>

<!-- Inisialisasi Chart.js -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('registrationChart').getContext('2d');
    
    // Inject data dari PHP ke JavaScript
    const labels = <?= json_encode($chart_labels) ?>;
    const dataValues = <?= json_encode($chart_values) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Siswa Baru',
                data: dataValues,
                borderColor: '#81C784',
                backgroundColor: 'rgba(129, 199, 132, 0.2)',
                borderWidth: 3,
                pointBackgroundColor: '#509556',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: true,
                tension: 0.4 // Membuat garis melengkung (smooth curve)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false } // Sembunyikan legenda agar lebih rapi
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 } // Angka bulat (tidak ada 0.5 siswa)
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>
