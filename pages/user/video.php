<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya siswa
require_login('student');

// Ambil data video yang aktif
$videos = [];
$res = $conn->query("SELECT title, description, video_url FROM videos WHERE is_active = 1 ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $videos[] = $row;
}

// Fungsi helper untuk mengambil thumbnail YouTube secara otomatis dari link embed
function getYoutubeThumbnail($url) {
    if (preg_match('/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $video_id = $matches[1];
        return "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
    }
    return null; // Jika bukan YouTube, kembalikan null
}
?>

<div style="margin-bottom: 25px;">
    <h2 style="color: var(--text-dark); font-size: 22px; margin-bottom: 5px;">Belajar Mandiri 📚</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Tonton materi video yang sudah disiapkan untuk membantumu belajar.</p>
</div>

<!-- Layout Grid untuk Galeri Video -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
    
    <?php if (count($videos) === 0): ?>
        <div class="glass-card" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px;">
            <i class="ph ph-video-camera-slash" style="font-size: 48px; color: var(--text-muted); margin-bottom: 10px;"></i>
            <h4 style="color: var(--text-dark); margin-bottom: 5px;">Belum Ada Video</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Saat ini belum ada video pembelajaran yang tersedia.</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($videos as $vid): 
            $thumb = getYoutubeThumbnail($vid['video_url']);
        ?>
        <div class="glass-card" style="padding: 15px; display: flex; flex-direction: column;">
            
            <!-- Area Thumbnail -->
            <div style="width: 100%; height: 160px; border-radius: var(--radius-sm); margin-bottom: 15px; background: var(--matcha-light); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="playVideo('<?= $vid['video_url'] ?>', '<?= addslashes($vid['title']) ?>')">
                <?php if ($thumb): ?>
                    <img src="<?= $thumb ?>" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                    <!-- Icon Play Melayang di Tengah -->
                    <div style="position: absolute; width: 50px; height: 50px; background: rgba(255,255,255,0.8); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <i class="ph-fill ph-play" style="font-size: 24px; color: var(--matcha-dark); margin-left: 3px;"></i>
                    </div>
                <?php else: ?>
                    <!-- Fallback Icon kalau linknya bukan dari YouTube -->
                    <i class="ph ph-video-camera" style="font-size: 40px; color: var(--matcha-dark);"></i>
                    <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px;">Video</div>
                <?php endif; ?>
            </div>

            <!-- Area Info Teks -->
            <h4 style="font-size: 16px; color: var(--text-dark); margin-bottom: 8px; line-height: 1.4;"><?= htmlspecialchars($vid['title']) ?></h4>
            <p style="color: var(--text-muted); font-size: 12px; line-height: 1.5; flex: 1; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                <?= htmlspecialchars($vid['description']) ?: 'Tidak ada deskripsi tambahan.' ?>
            </p>

            <button onclick="playVideo('<?= $vid['video_url'] ?>', '<?= addslashes($vid['title']) ?>')" class="btn" style="width: 100%; padding: 10px; background: var(--matcha-light); color: var(--matcha-dark); box-shadow: none;">
                <i class="ph-fill ph-play-circle" style="font-size: 18px;"></i> Tonton Sekarang
            </button>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<!-- ========================================================================== -->
<!-- MODAL PLAYER VIDEO KHUSUS SISWA -->
<!-- ========================================================================== -->
<div id="studentPlayerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="width: 100%; max-width: 800px; position: relative;">
        <!-- Header Modal Player -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; color: white;">
            <h3 id="studentPlayerTitle" style="margin: 0; font-size: 16px; font-weight: 500;"></h3>
            <button onclick="closeStudentPlayer()" style="background: rgba(255,255,255,0.2); border: none; font-size: 20px; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: white; display: flex; align-items: center; justify-content: center; transition: background 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                <i class="ph ph-x"></i>
            </button>
        </div>
        
        <!-- Iframe Container (Aspek Rasio 16:9 agar video tidak gepeng) -->
        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--radius-sm); box-shadow: 0 10px 30px rgba(0,0,0,0.5); background: #000;">
            <iframe id="studentVideoIframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
    const playerModal = document.getElementById('studentPlayerModal');
    const iframe = document.getElementById('studentVideoIframe');

    function playVideo(url, title) {
        document.getElementById('studentPlayerTitle').innerText = title;
        
        // Trik khusus YouTube: Tambahkan '?autoplay=1' supaya video langsung jalan begitu diklik
        const playUrl = url.includes('youtube.com') 
            ? url + (url.includes('?') ? '&' : '?') + 'autoplay=1' 
            : url;
            
        iframe.src = playUrl;
        playerModal.style.display = 'flex';
    }

    function closeStudentPlayer() {
        // Hapus link src agar video yang sedang jalan otomatis berhenti saat modal ditutup
        iframe.src = ''; 
        playerModal.style.display = 'none';
    }
</script>
