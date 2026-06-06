<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya admin
require_login('admin');

$success_msg = '';
$error_msg = '';

// ==========================================================================
// 1. PROSES AKSI ADMIN (Tambah, Edit, Hapus)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $title = sanitize_input($conn, $_POST['title']);
        $description = sanitize_input($conn, $_POST['description']);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
        $duration = intval($_POST['duration_minutes']); 
        $max_attempts = intval($_POST['max_attempts']);
        $status = sanitize_input($conn, $_POST['status']);
        
        // Cek toggle (jika dicentang nilainya 1, jika tidak 0)
        $rand_q = isset($_POST['randomize_questions']) ? 1 : 0;
        $rand_o = isset($_POST['randomize_options']) ? 1 : 0;
        $show_review = isset($_POST['show_review']) ? 1 : 0;
        $show_score = isset($_POST['show_score']) ? 1 : 0;

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO exam_packages (title, description, start_date, end_date, duration_minutes, randomize_questions, randomize_options, show_review, show_score, max_attempts, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // FIX: 4 string, 6 integer, 1 string (Total 11 parameter)
            $stmt->bind_param("ssssiiiiiis", $title, $description, $start_date, $end_date, $duration, $rand_q, $rand_o, $show_review, $show_score, $max_attempts, $status);
            
            if ($stmt->execute()) $success_msg = "Paket ujian berhasil dibuat.";
            else $error_msg = "Gagal membuat paket ujian.";
            $stmt->close();
        } 
        elseif ($action === 'edit') {
            $id = intval($_POST['package_id']);
            $stmt = $conn->prepare("UPDATE exam_packages SET title=?, description=?, start_date=?, end_date=?, duration_minutes=?, randomize_questions=?, randomize_options=?, show_review=?, show_score=?, max_attempts=?, status=? WHERE id=?");
            // FIX: 4 string, 6 integer, 1 string, 1 integer id (Total 12 parameter)
            $stmt->bind_param("ssssiiiiiisi", $title, $description, $start_date, $end_date, $duration, $rand_q, $rand_o, $show_review, $show_score, $max_attempts, $status, $id);
            
            if ($stmt->execute()) $success_msg = "Paket ujian berhasil diperbarui.";
            else $error_msg = "Gagal memperbarui paket.";
            $stmt->close();
        }
    } 
    elseif ($action === 'delete') {
        $id = intval($_POST['package_id']);
        $stmt = $conn->prepare("DELETE FROM exam_packages WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success_msg = "Paket ujian dan seluruh soal di dalamnya berhasil dihapus.";
        else $error_msg = "Gagal menghapus paket.";
        $stmt->close();
    }
}

// ==========================================================================
// 2. AMBIL DATA PAKET UNTUK GRID.JS
// ==========================================================================
$table_data = [];
$raw_data_dict = []; 

$res = $conn->query("SELECT p.*, (SELECT COUNT(id) FROM questions WHERE package_id = p.id) as total_questions FROM exam_packages p ORDER BY p.created_at DESC");

while ($row = $res->fetch_assoc()) {
    $raw_data_dict[$row['id']] = $row;
    
    $jadwal = "Kapan Saja";
    if ($row['start_date'] || $row['end_date']) {
        $start = $row['start_date'] ? date('d/m/y H:i', strtotime($row['start_date'])) : '...';
        $end = $row['end_date'] ? date('d/m/y H:i', strtotime($row['end_date'])) : '...';
        $jadwal = "$start - $end";
    }

    $table_data[] = [
        $row['id'],
        $row['title'],
        $jadwal,
        $row['total_questions'] . " Soal",
        $row['duration_minutes'] . " Menit", 
        $row['status'],
        '' 
    ];
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Manajemen Paket Ujian</h1>
        <p style="color: var(--text-muted); margin: 0;">Buat paket ujian, atur jadwal, durasi, dan kelola pengaturan keamanan.</p>
    </div>
    <button onclick="openModal('add')" class="btn btn-primary">
        <i class="ph ph-plus-circle" style="font-size: 20px;"></i> Buat Paket Baru
    </button>
</div>

<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div id="packages-table"></div>
</div>

<form id="delete-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="package_id" id="delete-id">
</form>

<div id="packageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(5px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div class="glass-card" style="width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; background: var(--white); position: relative;">
        
        <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted);">
            <i class="ph ph-x"></i>
        </button>

        <h3 id="modalTitle" style="margin-bottom: 20px; color: var(--text-dark);">Buat Paket Baru</h3>
        
        <form id="packageForm" method="POST" action="">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="package_id" id="modalPackageId" value="">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Judul Paket</label>
                <input type="text" name="title" id="inp-title" class="form-control" required placeholder="Contoh: UTS Matematika Kelas 8">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Deskripsi Singkat</label>
                <textarea name="description" id="inp-desc" class="form-control" rows="3" placeholder="Informasi atau instruksi ujian..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Waktu Buka (Opsional)</label>
                    <input type="datetime-local" name="start_date" id="inp-start" class="form-control">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Waktu Tutup (Opsional)</label>
                    <input type="datetime-local" name="end_date" id="inp-end" class="form-control">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Durasi Ujian (Menit) <span style="color:red;">*</span></label>
                    <input type="number" name="duration_minutes" id="inp-duration" class="form-control" value="60" min="5" required>
                </div>
                <div>
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Batas Mengulang</label>
                    <input type="number" name="max_attempts" id="inp-max" class="form-control" value="1" min="0" placeholder="0 = Bebas">
                    <small style="color: var(--text-muted); font-size: 11px;">Isi 0 untuk tanpa batas.</small>
                </div>
            </div>

            <div style="background: var(--bg-color); padding: 15px; border-radius: var(--radius-sm); border: 1px dashed var(--glass-border); margin-bottom: 20px;">
                <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--matcha-dark);">Pengaturan Ujian</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="randomize_questions" id="inp-rq" value="1" style="width: 18px; height: 18px;"> Acak Urutan Soal
                        </label>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="randomize_options" id="inp-ro" value="1" style="width: 18px; height: 18px;"> Acak Opsi (PG)
                        </label>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="show_score" id="inp-ss" value="1" checked style="width: 18px; height: 18px;"> Tampilkan Nilai Akhir
                        </label>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="show_review" id="inp-sr" value="1" style="width: 18px; height: 18px;"> Izinkan Review Jawaban
                        </label>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Status Paket</label>
                    <select name="status" id="inp-status" class="form-control" style="height: 52px;">
                        <option value="draft">Draft (Disembunyikan)</option>
                        <option value="active">Aktif</option>
                        <option value="archived">Arsip (Kadaluarsa)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="ph ph-floppy-disk"></i> Simpan Paket</button>
        </form>
    </div>
</div>

<?php if ($success_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Berhasil', '<?= $success_msg ?>', 'success'));</script>
<?php endif; ?>
<?php if ($error_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Gagal', '<?= $error_msg ?>', 'error'));</script>
<?php endif; ?>

<script>
    const rawData = <?= json_encode($raw_data_dict) ?>;
    const tableData = <?= json_encode($table_data) ?>;

    document.addEventListener("DOMContentLoaded", function() {
        new gridjs.Grid({
            columns: [
                { name: "ID", hidden: true },
                { name: "Judul Paket" },
                { name: "Jadwal" },
                { name: "Jumlah Soal" },
                { name: "Durasi" }, 
                { 
                    name: "Status",
                    formatter: (cell) => {
                        let color, bg, icon, text;
                        if (cell === 'active') { color = '#065F46'; bg = '#D1FAE5'; icon = 'ph-check-circle'; text = 'Aktif'; }
                        else if (cell === 'draft') { color = '#92400E'; bg = '#FEF3C7'; icon = 'ph-pencil-circle'; text = 'Draft'; }
                        else { color = '#991B1B'; bg = '#FEE2E2'; icon = 'ph-archive'; text = 'Arsip'; }
                        
                        return gridjs.html(`<span style="background: ${bg}; color: ${color}; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;"><i class="ph-fill ${icon}"></i> ${text}</span>`);
                    }
                },
                { 
                    name: "Aksi",
                    sort: false,
                    formatter: (cell, row) => {
                        const id = row.cells[0].data;
                        return gridjs.html(`
                            <div style="display:flex; gap: 8px;">
                                <a href="<?= BASE_URL ?>index.php?page=admin_questions&id=${id}" class="btn" style="padding: 6px 10px; font-size: 13px; background: #E0E7FF; color: #4F46E5; box-shadow: none;" title="Isi Soal">
                                    <i class="ph ph-file-text"></i> Soal
                                </a>
                                <button onclick="openModal('edit', ${id})" class="btn" style="padding: 6px; font-size: 16px; background: #F3F4F6; color: #4B5563; box-shadow: none;" title="Edit Pengaturan">
                                    <i class="ph ph-gear"></i>
                                </button>
                                <button onclick="deletePackage(${id})" class="btn" style="padding: 6px; font-size: 16px; background: #FEE2E2; color: #DC2626; box-shadow: none;" title="Hapus Paket">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        `);
                    }
                }
            ],
            data: tableData,
            search: true,
            sort: true,
            pagination: { limit: 10 },
            language: {
                search: { placeholder: 'Cari judul paket...' },
                pagination: { previous: 'Sebelumnya', next: 'Selanjutnya', showing: 'Menampilkan', results: () => 'Data' },
                noRecordsFound: 'Belum ada paket ujian.'
            },
            style: { table: { width: '100%' }, th: { backgroundColor: 'var(--bg-color)', color: 'var(--text-muted)', fontWeight: '600' } }
        }).render(document.getElementById("packages-table"));
    });

    const modal = document.getElementById('packageModal');

    function openModal(type, id = null) {
        modal.style.display = 'flex';
        
        if (type === 'add') {
            document.getElementById('modalTitle').innerText = 'Buat Paket Baru';
            document.getElementById('modalAction').value = 'add';
            document.getElementById('packageForm').reset();
            document.getElementById('inp-status').value = 'draft';
            document.getElementById('inp-duration').value = '60';
        } else if (type === 'edit') {
            document.getElementById('modalTitle').innerText = 'Edit Paket Ujian';
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('modalPackageId').value = id;
            
            const data = rawData[id];
            document.getElementById('inp-title').value = data.title;
            document.getElementById('inp-desc').value = data.description;
            
            document.getElementById('inp-start').value = data.start_date ? data.start_date.replace(' ', 'T') : '';
            document.getElementById('inp-end').value = data.end_date ? data.end_date.replace(' ', 'T') : '';
            
            document.getElementById('inp-duration').value = data.duration_minutes; 
            document.getElementById('inp-rq').checked = data.randomize_questions == 1;
            document.getElementById('inp-ro').checked = data.randomize_options == 1;
            document.getElementById('inp-ss').checked = data.show_score == 1;
            document.getElementById('inp-sr').checked = data.show_review == 1;
            
            document.getElementById('inp-max').value = data.max_attempts;
            document.getElementById('inp-status').value = data.status;
        }
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function deletePackage(id) {
        Swal.fire({
            title: 'Hapus Paket Ujian?',
            text: "Seluruh bank soal dan nilai siswa di dalam paket ini akan ikut terhapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-id').value = id;
                document.getElementById('delete-form').submit();
            }
        });
    }
</script>
