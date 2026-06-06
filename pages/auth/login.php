<?php
// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] === 'admin') ? 'admin_dashboard' : 'dashboard';
    header("Location: " . BASE_URL . "index.php?page=" . $redirect);
    exit();
}

$error_msg = '';

// Proses form saat disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matcha_id = strtoupper(sanitize_input($conn, $_POST['matcha_id']));
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password, gender, role, status FROM users WHERE matcha_id = ?");
    $stmt->bind_param("s", $matcha_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($user['status'] === 'suspended') {
            $error_msg = "Akun kamu ditangguhkan. Silakan hubungi Administrator.";
        } else if (password_verify($password, $user['password'])) {
            // Set Sesi Login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['matcha_id'] = $matcha_id;
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['gender'] = $user['gender'];
            $_SESSION['role'] = $user['role'];

            $redirect = ($user['role'] === 'admin') ? 'admin_dashboard' : 'dashboard';
            header("Location: " . BASE_URL . "index.php?page=" . $redirect);
            exit();
        } else {
            $error_msg = "Password yang kamu masukkan salah!";
        }
    } else {
        $error_msg = "MatchaID tidak ditemukan!";
    }
    $stmt->close();
}
?>
<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <div style="text-align: center; margin-bottom: 24px;">
            <i class="ph-fill ph-leaf" style="font-size: 40px; color: var(--matcha-primary);"></i>
            <h2 style="color: var(--text-dark); margin-top: 10px;">Selamat Datang!</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Silakan masuk menggunakan MatchaID</p>
        </div>

        <form method="POST" action="">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">MatchaID</label>
                <input type="text" name="matcha_id" class="form-control" placeholder="MCH-XXXXXX" required autocomplete="off" style="text-transform: uppercase;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="ph ph-sign-in"></i> Masuk</button>
        </form>

        <div style="text-align: center; margin-top: 24px; font-size: 0.9rem;">
            <span style="color: var(--text-muted);">Belum punya akun?</span> 
            <a href="<?= BASE_URL ?>index.php?page=register" style="color: var(--matcha-dark); font-weight: 600;">Daftar di sini</a>
        </div>
    </div>
</div>

<?php if ($error_msg): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'error',
            title: 'Gagal Masuk',
            text: '<?= $error_msg ?>',
            confirmButtonColor: '#81C784',
            backdrop: `rgba(255, 255, 255, 0.4)`
        });
    });
</script>
<?php endif; ?>
