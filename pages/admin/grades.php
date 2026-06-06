<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya admin
require_login('admin');

// Cek apakah sedang melihat detail paket atau daftar paket
$pkg_id = isset($_GET['pkg_id']) ? intval($_GET['pkg_id']) : 0;

// ==========================================================================
// TAMPILAN 2: DETAIL NILAI PER PAKET (Cetak Laporan)
// ==========================================================================
if ($pkg_id > 0): 
    // Ambil info paket
    $stmt = $conn->prepare("SELECT title, status FROM exam_packages WHERE id = ?");
    $stmt->bind_param("i", $pkg_id);
    $stmt->execute();
    $pkg_res = $stmt->get_result();
    
    if ($pkg_res->num_rows === 0) {
        echo "<script>window.location.href='".BASE_URL."index.php?page=admin_grades';</script>";
        exit();
    }
    $package = $pkg_res->fetch_assoc();
    $stmt->close();

    // Ambil data nilai (Hanya ujian yang sudah selesai)
    $grades_data = [];
    $stmt = $conn->prepare("
        SELECT u.full_name, u.matcha_id, a.final_score, a.attempt_number, DATE_FORMAT(a.finished_at, '%d/%m/%Y %H:%i') as finished_date 
        FROM exam_attempts a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.package_id = ? AND a.status = 'completed' 
        ORDER BY a.final_score DESC
    ");
    $stmt->bind_param("i", $pkg_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $grades_data[] = [
            $row['full_name'],
            $row['matcha_id'],
            $row['final_score'],
            $row['finished_date'],
            "Ke-" . $row['attempt_number']
        ];
    }
    $stmt->close();
?>

    <!-- STYLE KHUSUS PRINT PDF -->
    <style>
        .print-only { display: none; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; color: black !important; font-family: 'Times New Roman', serif; }
            .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid black; padding-bottom: 10px; }
            .print-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .print-table th, .print-table td { border: 1px solid black; padding: 8px; text-align: left; }
            .print-table th { background-color: #f2f2f2; }
            .text-center { text-align: center !important; }
        }
    </style>

    <div class="no-print" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;">
        <div>
            <a href="<?= BASE_URL ?>index.php?page=admin_grades" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--bg-color); border: 1px solid var(--glass-border); margin-bottom: 15px;">
                <i class="ph ph-arrow-left"></i> Kembali ke Daftar Paket
            </a>
            <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Detail Laporan Nilai</h1>
            <p style="color: var(--matcha-dark); font-weight: 600; margin: 0;">Paket: <?= htmlspecialchars($package['title']) ?></p>
        </div>
        
        <?php if (count($grades_data) > 0): ?>
        <button onclick="window.print()" class="btn btn-primary" style="background: #2563EB;">
            <i class="ph ph-printer"></i> Cetak / Save PDF
        </button>
        <?php endif; ?>
    </div>

    <!-- Tampilan Interaktif di Layar (Grid.js) -->
    <div class="glass-card no-print" style="padding: 0; overflow: hidden;">
        <div id="grades-detail-table"></div>
    </div>

    <!-- Tampilan Khusus Saat Dicetak (Disembunyikan di layar) -->
    <div class="print-only">
        <div class="print-header">
            <h2 style="margin: 0;">LAPORAN HASIL UJIAN SISWA</h2>
            <h3 style="margin: 5px 0;">MATCHA EDU LEARNING SYSTEM</h3>
            <p style="margin: 0; font-size: 14px;"><strong>Paket Ujian:</strong> <?= htmlspecialchars($package['title']) ?></p>
            <p style="margin: 0; font-size: 14px;"><strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i') ?> WIB</p>
        </div>
        
        <?php if (count($grades_data) > 0): ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">No</th>
                        <th style="width: 30%;">Nama Lengkap</th>
                        <th style="width: 15%;" class="text-center">MatchaID</th>
                        <th style="width: 15%;" class="text-center">Nilai Akhir</th>
                        <th style="width: 20%;" class="text-center">Tanggal Selesai</th>
                        <th style="width: 15%;" class="text-center">Percobaan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades_data as $index => $row): ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row[0]) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row[1]) ?></td>
                        <td class="text-center"><strong><?= htmlspecialchars($row[2]) ?></strong></td>
                        <td class="text-center"><?= htmlspecialchars($row[3]) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row[4]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 50px;">Belum ada siswa yang menyelesaikan paket ujian ini.</p>
        <?php endif; ?>
        
        <div style="margin-top: 50px; text-align: right;">
            <p>Probolinggo, <?= date('d F Y') ?></p>
            <br><br><br>
            <p><strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong><br>Administrator</p>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tableData = <?= json_encode($grades_data) ?>;
            new gridjs.Grid({
                columns: [
                    { name: "Nama Siswa" },
                    { name: "MatchaID" },
                    { 
                        name: "Nilai Akhir",
                        formatter: (cell) => {
                            let color = cell >= 75 ? '#065F46' : '#991B1B';
                            let bg = cell >= 75 ? '#D1FAE5' : '#FEE2E2';
                            return gridjs.html(`<span style="background: ${bg}; color: ${color}; padding: 4px 10px; border-radius: 20px; font-weight: 700;">${cell}</span>`);
                        }
                    },
                    { name: "Selesai Pada" },
                    { name: "Percobaan" }
                ],
                data: tableData,
                search: true,
                sort: true,
                pagination: { limit: 15 },
                language: {
                    search: { placeholder: 'Cari nama atau ID...' },
                    pagination: { previous: 'Sebelumnya', next: 'Selanjutnya', showing: 'Menampilkan', results: () => 'Siswa' },
                    noRecordsFound: 'Belum ada data nilai.'
                },
                style: { table: { width: '100%' }, th: { backgroundColor: 'var(--bg-color)' } }
            }).render(document.getElementById("grades-detail-table"));
        });
    </script>

<?php 
// ==========================================================================
// TAMPILAN 1: DAFTAR SEMUA PAKET
// ==========================================================================
else: 
    $packages_data = [];
    $res = $conn->query("
        SELECT p.id, p.title, p.status, 
               (SELECT COUNT(id) FROM exam_attempts WHERE package_id = p.id AND status = 'completed') as total_participants 
        FROM exam_packages p 
        ORDER BY p.created_at DESC
    ");
    
    while ($row = $res->fetch_assoc()) {
        $packages_data[] = [
            $row['id'],
            $row['title'],
            $row['total_participants'] . " Orang",
            $row['status']
        ];
    }
?>

    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Laporan Nilai</h1>
        <p style="color: var(--text-muted);">Pilih paket ujian untuk melihat dan mencetak hasil nilai siswa.</p>
    </div>

    <!-- Container Tabel Grid.js -->
    <div class="glass-card" style="padding: 0; overflow: hidden;">
        <div id="grades-table"></div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tableData = <?= json_encode($packages_data) ?>;
            new gridjs.Grid({
                columns: [
                    { name: "ID", hidden: true },
                    { name: "Judul Paket", width: '40%' },
                    { name: "Peserta Selesai" },
                    { 
                        name: "Status",
                        formatter: (cell) => {
                            if (cell === 'active') return gridjs.html(`<span style="color: #065F46; font-weight: 600;"><i class="ph-fill ph-check-circle"></i> Aktif</span>`);
                            else if (cell === 'draft') return gridjs.html(`<span style="color: #92400E; font-weight: 600;"><i class="ph-fill ph-pencil-circle"></i> Draft</span>`);
                            else return gridjs.html(`<span style="color: #991B1B; font-weight: 600;"><i class="ph-fill ph-archive"></i> Arsip</span>`);
                        }
                    },
                    { 
                        name: "Aksi",
                        sort: false,
                        formatter: (cell, row) => {
                            const id = row.cells[0].data;
                            return gridjs.html(`
                                <a href="<?= BASE_URL ?>index.php?page=admin_grades&pkg_id=${id}" class="btn" style="padding: 6px 12px; font-size: 14px; background: var(--matcha-light); color: var(--matcha-dark); box-shadow: none;">
                                    <i class="ph-fill ph-chart-bar"></i> Lihat Nilai
                                </a>
                            `);
                        }
                    }
                ],
                data: tableData,
                search: true,
                sort: true,
                pagination: { limit: 10 },
                language: {
                    search: { placeholder: 'Cari paket ujian...' },
                    pagination: { previous: 'Sebelumnya', next: 'Selanjutnya', showing: 'Menampilkan', results: () => 'Paket' },
                    noRecordsFound: 'Belum ada paket ujian.'
                },
                style: { table: { width: '100%' }, th: { backgroundColor: 'var(--bg-color)', color: 'var(--text-muted)', fontWeight: '600' } }
            }).render(document.getElementById("grades-table"));
        });
    </script>
<?php endif; ?>
