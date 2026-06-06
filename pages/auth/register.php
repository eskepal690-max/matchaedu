<?php
// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] === 'admin') ? 'admin_dashboard' : 'dashboard';
    header("Location: " . BASE_URL . "index.php?page=" . $redirect);
    exit();
}

$error_msg = '';
$success_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($conn, $_POST['full_name']);
    $gender = sanitize_input($conn, $_POST['gender']);
    $password = $_POST['password'];

    if (strlen($password) < 6) {
        $error_msg = "Password minimal harus 6 karakter!";
    } else {
        // Enkripsi password menggunakan bycrypt murni PHP
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Panggil fungsi auto-generate ID
        $matcha_id = generateMatchaID($conn);

        // Secara default, pendaftar baru akan diberi role 'student'
        $stmt = $conn->prepare("INSERT INTO users (matcha_id, full_name, password, gender, role, status) VALUES (?, ?, ?, ?, 'student', 'active')");
        $stmt->bind_param("ssss", $matcha_id, $full_name, $hashed_password, $gender);
        
        if ($stmt->execute()) {
            // Berhasil mendaftar, simpan ID untuk ditampilkan di SweetAlert
            $success_id = $matcha_id;
        } else {
            $error_msg = "Terjadi kesalahan saat mendaftar pada database. Coba lagi.";
        }
        $stmt->close();
    }
}
?>
<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <div style="text-align: center; margin-bottom: 24px;">
            <i class="ph-fill ph-user-plus" style="font-size: 40px; color: var(--matcha-primary);"></i>
            <h2 style="color: var(--text-dark); margin-top: 10px;">Buat Akun Baru</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Bergabunglah dengan Matcha Edu</p>
        </div>

        <form method="POST" action="" id="registerForm">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Nama Lengkap</label>
                <input type="text" name="full_name" class="form-control" placeholder="Masukkan nama lengkap" required>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Jenis Kelamin</label>
                <select name="gender" class="form-control" required style="appearance: none; cursor: pointer;">
                    <option value="" disabled selected>Pilih Jenis Kelamin</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            
            <div style="margin-bottom: 24px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Password Baru</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="ph ph-check-circle"></i> Daftar Sekarang</button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.9rem;">
            <span style="color: var(--text-muted);">Sudah punya akun?</span> 
            <a href="<?= BASE_URL ?>index.php?page=login" style="color: var(--matcha-dark); font-weight: 600;">Masuk di sini</a>
        </div>
    </div>
</div>

<?php if ($error_msg): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'error',
            title: 'Pendaftaran Gagal',
            text: '<?= $error_msg ?>',
            confirmButtonColor: '#81C784',
            backdrop: `rgba(255, 255, 255, 0.4)`
        });
    });
</script>
<?php endif; ?>

<?php if ($success_id): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'success',
            title: 'Pendaftaran Berhasil!',
            // Custom HTML buat nampilin MatchaID biar gampang dicopy
            html: `
                <p style="margin-bottom: 10px; color: var(--text-muted);">Simpan MatchaID kamu baik-baik karena akan digunakan untuk login:</p>
                <div style="background: var(--bg-color); padding: 15px; border-radius: var(--radius-sm); border: 2px dashed var(--matcha-primary); margin-bottom: 15px;">
                    <h2 id="newMatchaId" style="color: var(--matcha-dark); margin: 0; letter-spacing: 2px;"><?= $success_id ?></h2>
                </div>
                <button class="btn btn-primary" onclick="copyNewMatchaID()" style="font-size: 14px;"><i class="ph ph-copy"></i> Salin MatchaID</button>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Lanjut ke Login',
            confirmButtonColor: '#81C784',
            backdrop: `rgba(255, 255, 255, 0.4)`,
            allowOutsideClick: false // Paksa user biar gak nggak sengaja nutup pop-up
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?= BASE_URL ?>index.php?page=login';
            }
        });
    });

    // Fungsi salin ke clipboard bawaan browser
    function copyNewMatchaID() {
        const text = document.getElementById('newMatchaId').innerText;
        navigator.clipboard.writeText(text).then(() => {
            // Memanggil showToast dari app.js
            showToast('Tersalin!', 'MatchaID berhasil disalin ke clipboard.', 'success');
        });
    }
</script>
<?php endif; ?>
