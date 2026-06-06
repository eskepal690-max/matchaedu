<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya admin
require_login('admin');

$success_msg = '';
$error_msg = '';

// ==========================================================================
// FUNGSI HELPER: Konversi Link YouTube Biasa Jadi Link Embed
// ==========================================================================
function getEmbedUrl($url) {
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['host'])) {
        if ($parsedUrl['host'] === 'www.youtube.com' || $parsedUrl['host'] === 'youtube.com') {
            parse_str($parsedUrl['query'], $queryVars);
            if (isset($queryVars['v'])) return 'https://www.youtube.com/embed/' . $queryVars['v'];
        } elseif ($parsedUrl['host'] === 'youtu.be') {
            $path = trim($parsedUrl['path'], '/');
            return 'https://www.youtube.com/embed/' . $path;
        }
    }
    return $url; // Kembalikan aslinya jika format tidak dikenali (Vimeo/Drive dll)
}

// ==========================================================================
// 1. PROSES AKSI ADMIN (Tambah, Edit, Hapus)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $title = sanitize_input($conn, $_POST['title']);
        $description = sanitize_input($conn, $_POST['description']);
        $raw_url = sanitize_input($conn, $_POST['video_url']);
        $embed_url = getEmbedUrl($raw_url);
        $is_active = intval($_POST['is_active']);

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO videos (title, description, video_url, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $title, $description, $embed_url, $is_active);
            if ($stmt->execute()) $success_msg = "Video berhasil ditambahkan.";
            else $error_msg = "Gagal menyimpan video.";
            $stmt->close();
        } elseif ($action === 'edit') {
            $id = intval($_POST['video_id']);
            $stmt = $conn->prepare("UPDATE videos SET title=?, description=?, video_url=?, is_active=? WHERE id=?");
            $stmt->bind_param("sssii", $title, $description, $embed_url, $is_active, $id);
            if ($stmt->execute()) $success_msg = "Video berhasil diperbarui.";
            else $error_msg = "Gagal memperbarui video.";
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['video_id']);
        $stmt = $conn->prepare("DELETE FROM videos WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success_msg = "Video berhasil dihapus.";
        else $error_msg = "Gagal menghapus video.";
        $stmt->close();
    }
}

// ==========================================================================
// 2. AMBIL DATA VIDEO UNTUK GRID.JS
// ==========================================================================
$table_data = [];
$raw_data_dict = []; 

$res = $conn->query("SELECT * FROM videos ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $raw_data_dict[$row['id']] = $row;
    
    $table_data[] = [
        $row['id'],
        $row['title'],
        $row['video_url'],
        $row['is_active']
    ];
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Video Pembelajaran</h1>
        <p style="color: var(--text-muted); margin: 0;">Kelola daftar video materi untuk siswa.</p>
    </div>
    <button onclick="openModal('add')" class="btn btn-primary">
        <i class="ph ph-video-camera" style="font-size: 20px;"></i> Tambah Video
    </button>
</div>

<!-- Container Tabel Grid.js -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <div id="videos-table"></div>
</div>

<!-- Form Hapus (Hidden) -->
<form id="delete-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="video_id" id="delete-id">
</form>

<!-- MODAL FORM INPUT VIDEO -->
<div id="videoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(5px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div class="glass-card" style="width: 100%; max-width: 500px; background: var(--white); position: relative;">
        
        <button onclick="closeModal('videoModal')" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted);">
            <i class="ph ph-x"></i>
        </button>

        <h3 id="modalTitle" style="margin-bottom: 20px; color: var(--text-dark);">Tambah Video</h3>
        
        <form id="videoForm" method="POST" action="">
            <input type="hidden" name="action" id="modalAction" value="add">
            <input type="hidden" name="video_id" id="modalVideoId" value="">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Judul Video</label>
                <input type="text" name="title" id="inp-title" class="form-control" required placeholder="Contoh: Pembahasan Soal Aljabar">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">URL YouTube / Embed</label>
                <input type="url" name="video_url" id="inp-url" class="form-control" required placeholder="https://youtube.com/watch?v=...">
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 5px; display: block;">Paste link YouTube biasa, sistem otomatis mengubahnya ke format embed.</small>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Deskripsi Singkat (Opsional)</label>
                <textarea name="description" id="inp-desc" class="form-control" rows="3" placeholder="Penjelasan tentang video ini..."></textarea>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Status Penayangan</label>
                <select name="is_active" id="inp-status" class="form-control">
                    <option value="1">Aktif (Tampil di Siswa)</option>
                    <option value="0">Disembunyikan</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="ph ph-floppy-disk"></i> Simpan Video</button>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW PLAYER VIDEO -->
<div id="playerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="width: 100%; max-width: 800px; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; color: white;">
            <h3 id="playerTitle" style="margin: 0; font-size: 18px;"></h3>
            <button onclick="closePlayer()" style="background: none; border: none; font-size: 32px; cursor: pointer; color: white;">
                <i class="ph ph-x-circle"></i>
            </button>
        </div>
        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--radius-sm); box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            <iframe id="videoIframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- Notifikasi SweetAlert & IziToast -->
<?php if ($success_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Berhasil', '<?= $success_msg ?>', 'success'));</script>
<?php endif; ?>
<?php if ($error_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Gagal', '<?= $error_msg ?>', 'error'));</script>
<?php endif; ?>

<!-- Script Logic (Grid.js & Modal) -->
<script>
    const rawData = <?= json_encode($raw_data_dict) ?>;
    const tableData = <?= json_encode($table_data) ?>;

    document.addEventListener("DOMContentLoaded", function() {
        new gridjs.Grid({
            columns: [
                { name: "ID", hidden: true },
                { name: "Judul Video", width: '40%' },
                { name: "URL", hidden: true },
                { 
                    name: "Status",
                    formatter: (cell) => {
                        if (cell == 1) return gridjs.html(`<span style="background: #D1FAE5; color: #065F46; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;"><i class="ph-fill ph-check-circle"></i> Aktif</span>`);
                        return gridjs.html(`<span style="background: #F3F4F6; color: #4B5563; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;"><i class="ph-fill ph-eye-slash"></i> Sembunyi</span>`);
                    }
                },
                { 
                    name: "Aksi",
                    sort: false,
                    formatter: (cell, row) => {
                        const id = row.cells[0].data;
                        const url = row.cells[2].data;
                        const title = row.cells[1].data.replace(/'/g, "\\'");
                        
                        return gridjs.html(`
                            <div style="display:flex; gap: 8px;">
                                <button onclick="playVideo('${url}', '${title}')" class="btn" style="padding: 6px 12px; font-size: 14px; background: #E0F2FE; color: #0284C7; box-shadow: none;" title="Tonton">
                                    <i class="ph-fill ph-play-circle"></i> Tonton
                                </button>
                                <button onclick="openModal('edit', ${id})" class="btn" style="padding: 6px; font-size: 16px; background: #F3F4F6; color: #4B5563; box-shadow: none;" title="Edit">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button onclick="deleteVideo(${id})" class="btn" style="padding: 6px; font-size: 16px; background: #FEE2E2; color: #DC2626; box-shadow: none;" title="Hapus">
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
                search: { placeholder: 'Cari judul video...' },
                pagination: { previous: 'Sebelumnya', next: 'Selanjutnya', showing: 'Menampilkan', results: () => 'Data' },
                noRecordsFound: 'Belum ada video.'
            },
            style: { table: { width: '100%' }, th: { backgroundColor: 'var(--bg-color)', color: 'var(--text-muted)', fontWeight: '600' } }
        }).render(document.getElementById("videos-table"));
    });

    // FUNGSI MODAL FORM
    const formModal = document.getElementById('videoModal');

    function openModal(type, id = null) {
        formModal.style.display = 'flex';
        
        if (type === 'add') {
            document.getElementById('modalTitle').innerText = 'Tambah Video Baru';
            document.getElementById('modalAction').value = 'add';
            document.getElementById('videoForm').reset();
        } else if (type === 'edit') {
            document.getElementById('modalTitle').innerText = 'Edit Video';
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('modalVideoId').value = id;
            
            const data = rawData[id];
            document.getElementById('inp-title').value = data.title;
            document.getElementById('inp-desc').value = data.description;
            document.getElementById('inp-url').value = data.video_url;
            document.getElementById('inp-status').value = data.is_active;
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // FUNGSI MODAL PLAYER
    const playerModal = document.getElementById('playerModal');
    const iframe = document.getElementById('videoIframe');

    function playVideo(url, title) {
        document.getElementById('playerTitle').innerText = title;
        iframe.src = url; // Load URL
        playerModal.style.display = 'flex';
    }

    function closePlayer() {
        iframe.src = ''; // Stop video yang sedang muter
        playerModal.style.display = 'none';
    }

    // FUNGSI HAPUS
    function deleteVideo(id) {
        confirmAction('Hapus Video?', 'Video akan dihapus dari daftar pembelajaran.', 'Ya, Hapus', () => {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-form').submit();
        });
    }
</script>
