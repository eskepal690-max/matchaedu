<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya admin
require_login('admin');

$success_msg = '';
$error_msg = '';

// ==========================================================================
// 1. PROSES AKSI ADMIN (Edit, Suspend, Hapus)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = intval($_POST['user_id']);

    if ($action === 'suspend') {
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $success_msg = "Status akun berhasil diperbarui.";
        } else {
            $error_msg = "Gagal memperbarui status akun.";
        }
        $stmt->close();
    } 
    elseif ($action === 'delete') {
        // Hapus user (Riwayat ujian akan otomatis terhapus karena ON DELETE CASCADE di SQL)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success_msg = "Akun siswa berhasil dihapus permanen.";
        } else {
            $error_msg = "Gagal menghapus akun.";
        }
        $stmt->close();
    }
    elseif ($action === 'edit') {
        $full_name = sanitize_input($conn, $_POST['full_name']);
        $gender = sanitize_input($conn, $_POST['gender']);
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, gender = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $gender, $user_id);
        if ($stmt->execute()) {
            $success_msg = "Data siswa berhasil diperbarui.";
        } else {
            $error_msg = "Gagal memperbarui data siswa.";
        }
        $stmt->close();
    }
}

// ==========================================================================
// 2. AMBIL DATA SISWA UNTUK GRID.JS
// ==========================================================================
$students_data = [];
$res = $conn->query("SELECT id, matcha_id, full_name, gender, status, DATE_FORMAT(created_at, '%d %b %Y') as joined_date FROM users WHERE role = 'student' ORDER BY created_at DESC");

while ($row = $res->fetch_assoc()) {
    // Array order menyesuaikan urutan kolom di Grid.js nanti
    $students_data[] = [
        $row['id'],
        $row['matcha_id'],
        $row['full_name'],
        $row['gender'] === 'L' ? 'Laki-laki' : 'Perempuan',
        $row['status'],
        $row['joined_date']
    ];
}
?>

<div style="margin-bottom: 30px;">
    <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Manajemen Siswa</h1>
    <p style="color: var(--text-muted);">Kelola data akun, status, dan akses siswa terdaftar.</p>
</div>

<!-- Container Tabel Grid.js -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div id="users-table"></div>
</div>

<!-- Form Tersembunyi untuk Proses Aksi (Tanpa AJAX ribet) -->
<form id="action-form" method="POST" style="display: none;">
    <input type="hidden" name="action" id="form-action">
    <input type="hidden" name="user_id" id="form-user-id">
    <input type="hidden" name="new_status" id="form-new-status">
    <input type="hidden" name="full_name" id="form-full-name">
    <input type="hidden" name="gender" id="form-gender">
</form>

<!-- Notifikasi Sukses/Error setelah Post -->
<?php if ($success_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Berhasil', '<?= $success_msg ?>', 'success'));</script>
<?php endif; ?>
<?php if ($error_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Gagal', '<?= $error_msg ?>', 'error'));</script>
<?php endif; ?>

<!-- Inisialisasi Grid.js & SweetAlert Logic -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Data dilempar dari PHP ke JavaScript
    const tableData = <?= json_encode($students_data) ?>;

    // Render Tabel
    new gridjs.Grid({
        columns: [
            { name: "ID", hidden: true }, // Index 0 (Sembunyi)
            { name: "MatchaID" },         // Index 1
            { name: "Nama Lengkap" },     // Index 2
            { name: "Gender" },           // Index 3
            { 
                name: "Status",           // Index 4
                formatter: (cell) => {
                    if (cell === 'active') {
                        return gridjs.html(`<span style="background: #D1FAE5; color: #065F46; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;"><i class="ph-fill ph-check-circle"></i> Aktif</span>`);
                    } else {
                        return gridjs.html(`<span style="background: #FEE2E2; color: #991B1B; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;"><i class="ph-fill ph-warning-circle"></i> Suspended</span>`);
                    }
                }
            },
            { name: "Terdaftar" },        // Index 5
            { 
                name: "Aksi",             // Index 6
                sort: false,
                formatter: (cell, row) => {
                    const userId = row.cells[0].data;
                    const name = row.cells[2].data.replace(/'/g, "\\'"); // escape quote
                    const gender = row.cells[3].data;
                    const status = row.cells[4].data;
                    
                    const suspendBtnColor = status === 'active' ? '#D97706' : '#059669';
                    const suspendBtnIcon = status === 'active' ? 'ph-prohibit' : 'ph-check-circle';
                    const suspendTitle = status === 'active' ? 'Suspend' : 'Unsuspend';
                    const newStatus = status === 'active' ? 'suspended' : 'active';

                    return gridjs.html(`
                        <div style="display:flex; gap: 8px;">
                            <button onclick="editUser(${userId}, '${name}', '${gender}')" class="btn" style="padding: 6px; font-size: 16px; background: #E0E7FF; color: #4F46E5;" title="Edit">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button onclick="suspendUser(${userId}, '${newStatus}')" class="btn" style="padding: 6px; font-size: 16px; background: #FEF3C7; color: ${suspendBtnColor};" title="${suspendTitle}">
                                <i class="ph ${suspendBtnIcon}"></i>
                            </button>
                            <button onclick="deleteUser(${userId})" class="btn" style="padding: 6px; font-size: 16px; background: #FEE2E2; color: #DC2626;" title="Hapus">
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
        pagination: {
            limit: 10
        },
        language: {
            search: { placeholder: 'Cari nama atau MatchaID...' },
            pagination: { 
                previous: 'Sebelumnya', 
                next: 'Selanjutnya', 
                showing: 'Menampilkan', 
                results: () => 'Data' 
            },
            noRecordsFound: 'Tidak ada data siswa.'
        },
        style: {
            table: { width: '100%' },
            th: { backgroundColor: 'var(--bg-color)', color: 'var(--text-muted)', fontWeight: '600' }
        }
    }).render(document.getElementById("users-table"));

    // --- FUNGSI AKSI ADMIN ---

    window.editUser = function(id, name, gender) {
        const genderL = gender === 'Laki-laki' ? 'selected' : '';
        const genderP = gender === 'Perempuan' ? 'selected' : '';

        Swal.fire({
            title: 'Edit Data Siswa',
            html: `
                <input id="swal-input-name" class="swal2-input form-control" value="${name}" placeholder="Nama Lengkap">
                <select id="swal-input-gender" class="swal2-input form-control" style="height: auto; padding: 14px 20px;">
                    <option value="L" ${genderL}>Laki-laki</option>
                    <option value="P" ${genderP}>Perempuan</option>
                </select>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#81C784',
            preConfirm: () => {
                return {
                    name: document.getElementById('swal-input-name').value,
                    gender: document.getElementById('swal-input-gender').value
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form-action').value = 'edit';
                document.getElementById('form-user-id').value = id;
                document.getElementById('form-full-name').value = result.value.name;
                document.getElementById('form-gender').value = result.value.gender;
                document.getElementById('action-form').submit();
            }
        });
    };

    window.suspendUser = function(id, newStatus) {
        const actionText = newStatus === 'suspended' ? 'menangguhkan' : 'mengaktifkan kembali';
        confirmAction('Konfirmasi', `Yakin ingin ${actionText} akun ini?`, 'Ya, Lanjutkan', () => {
            document.getElementById('form-action').value = 'suspend';
            document.getElementById('form-user-id').value = id;
            document.getElementById('form-new-status').value = newStatus;
            document.getElementById('action-form').submit();
        });
    };

    window.deleteUser = function(id) {
        Swal.fire({
            title: 'Hapus Permanen?',
            text: "Akun dan semua riwayat ujian siswa ini akan terhapus. Tindakan ini tidak bisa dibatalkan!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form-action').value = 'delete';
                document.getElementById('form-user-id').value = id;
                document.getElementById('action-form').submit();
            }
        });
    };
});
</script>
