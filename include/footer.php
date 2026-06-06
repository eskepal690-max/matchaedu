<?php
// =========================================================================
// LOGIKA PENUTUP WRAPPER & BOTTOM NAVIGATION
// =========================================================================

if ($role === 'student' && in_array($page, ['dashboard', 'video', 'history'])): 
?>
    </div> <!-- Tutup .content-wrapper Siswa -->

    <!-- BOTTOM NAVIGATION (Hanya Muncul di Halaman Utama Siswa) -->
    <nav class="bottom-nav">
        <!-- Gunakan ikon Phosphor yang terisi penuh (-fill) saat halaman sedang aktif -->
        <a href="<?= BASE_URL ?>index.php?page=dashboard" class="nav-item <?= ($page === 'dashboard') ? 'active' : '' ?>">
            <i class="ph <?= ($page === 'dashboard') ? 'ph-house-fill' : 'ph-house' ?>"></i>
            <span>Beranda</span>
        </a>
        <a href="<?= BASE_URL ?>index.php?page=video" class="nav-item <?= ($page === 'video') ? 'active' : '' ?>">
            <i class="ph <?= ($page === 'video') ? 'ph-play-circle-fill' : 'ph-play-circle' ?>"></i>
            <span>Belajar</span>
        </a>
        <a href="<?= BASE_URL ?>index.php?page=history" class="nav-item <?= ($page === 'history') ? 'active' : '' ?>">
            <i class="ph <?= ($page === 'history') ? 'ph-clock-counter-clockwise-fill' : 'ph-clock-counter-clockwise' ?>"></i>
            <span>Riwayat</span>
        </a>
    </nav>

<?php elseif ($role === 'student' && $page === 'exam'): ?>
    </div> <!-- Tutup .content-wrapper Ujian (Tanpa Bottom Nav biar siswa fokus) -->

<?php elseif ($role === 'admin'): ?>
        </div> <!-- Tutup .admin-content -->
    </div> <!-- Tutup .admin-wrapper -->

<?php else: ?>
    </div> <!-- Tutup wrapper Auth/Home -->
<?php endif; ?>

<!-- Load Javascript Global -->
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<!-- Inisialisasi Pusher Global (Opsional jika mau ada notif real-time) -->
<script>
    const pusher = new Pusher('<?= PUSHER_KEY ?>', {
        cluster: '<?= PUSHER_CLUSTER ?>'
    });
    // Contoh: Subscribe ke channel 'matcha-global'
    // const channel = pusher.subscribe('matcha-global');
    // channel.bind('new-announcement', function(data) {
    //     showToast('Pengumuman', data.message, 'info');
    // });
</script>

</body>
</html>
