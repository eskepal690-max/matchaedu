<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya siswa
require_login('student');

$pkg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

if ($pkg_id === 0) {
    echo "<script>window.location.href='".BASE_URL."index.php?page=dashboard';</script>";
    exit();
}

// 1. Ambil Info Paket
$stmt = $conn->prepare("SELECT * FROM exam_packages WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $pkg_id);
$stmt->execute();
$pkg_res = $stmt->get_result();

if ($pkg_res->num_rows === 0) {
    echo "<script>alert('Paket ujian tidak valid atau sudah ditutup.'); window.location.href='".BASE_URL."index.php?page=dashboard';</script>";
    exit();
}
$package = $pkg_res->fetch_assoc();
$stmt->close();

// 2. Cek atau Buat Sesi Ujian (Attempt)
$attempt_id = 0;
$started_at = "";

$stmt = $conn->prepare("SELECT id, started_at FROM exam_attempts WHERE user_id = ? AND package_id = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
$stmt->bind_param("ii", $user_id, $pkg_id);
$stmt->execute();
$att_res = $stmt->get_result();

if ($att_res->num_rows > 0) {
    // Lanjutkan sesi yang sudah ada
    $att_row = $att_res->fetch_assoc();
    $attempt_id = $att_row['id'];
    $started_at = $att_row['started_at'];
} else {
    // Hitung jumlah percobaan sebelumnya
    $count_stmt = $conn->prepare("SELECT COUNT(id) FROM exam_attempts WHERE user_id = ? AND package_id = ?");
    $count_stmt->bind_param("ii", $user_id, $pkg_id);
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    $attempt_number = $count_res->fetch_row()[0] + 1;
    $count_stmt->close();

    // Validasi Limit Percobaan
    if ($package['max_attempts'] > 0 && $attempt_number > $package['max_attempts']) {
        echo "<script>alert('Kamu sudah mencapai batas maksimal percobaan untuk ujian ini.'); window.location.href='".BASE_URL."index.php?page=dashboard';</script>";
        exit();
    }

    // Buat sesi baru
    $insert_stmt = $conn->prepare("INSERT INTO exam_attempts (user_id, package_id, attempt_number) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iii", $user_id, $pkg_id, $attempt_number);
    $insert_stmt->execute();
    $attempt_id = $insert_stmt->insert_id;
    $started_at = date('Y-m-d H:i:s');
    $insert_stmt->close();
}
$stmt->close();

// 3. Hitung Sisa Waktu Ujian
// REVISI: Ambil durasi dari database (duration_minutes * 60 detik)
$duration_seconds = intval($package['duration_minutes']) * 60; 
$time_elapsed = time() - strtotime($started_at);
$time_remaining = $duration_seconds - $time_elapsed;

// Jika waktu habis, paksa nilainya 0 atau langsung diarahkan submit
if ($time_remaining <= 0) {
    $time_remaining = 0;
}

// 4. Ambil Semua Soal untuk Paket Ini
$questions = [];
$stmt = $conn->prepare("SELECT id, question_type, question_text, options_data FROM questions WHERE package_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $pkg_id);
$stmt->execute();
$q_res = $stmt->get_result();

while ($row = $q_res->fetch_assoc()) {
    // Parse opsi JSON (Kunci jawaban sengaja tidak diikutkan ke frontend agar tidak dicontek via Inspect Element)
    $row['options_data'] = json_decode($row['options_data'], true);
    $questions[] = $row;
}
$stmt->close();

// Acak Soal jika pengaturan aktif
if ($package['randomize_questions']) {
    shuffle($questions);
}
?>

<div id="exam-sidebar" style="position: fixed; top: 70px; left: -100%; width: 280px; height: calc(100vh - 70px); background: var(--glass-bg); backdrop-filter: blur(20px); border-right: 1px solid var(--glass-border); z-index: 1040; transition: left 0.3s ease; padding: 20px; overflow-y: auto; box-shadow: 4px 0 20px rgba(0,0,0,0.05);">
    <h4 style="color: var(--text-dark); margin-bottom: 15px; font-size: 16px;">Navigasi Soal</h4>
    <div style="display: flex; gap: 10px; margin-bottom: 20px; font-size: 12px; color: var(--text-muted);">
        <div style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; background:var(--matcha-primary); border-radius:50%;"></span> Dijawab</div>
        <div style="display:flex; align-items:center; gap:5px;"><span style="width:12px; height:12px; background:#fff; border:1px solid #ccc; border-radius:50%;"></span> Belum</div>
    </div>
    
    <div class="question-nav-grid" id="nav-grid">
        </div>
</div>

<div style="max-width: 800px; margin: 0 auto; position: relative;">
    
    <div class="glass-card" style="min-height: 400px; position: relative; padding-bottom: 80px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed var(--glass-border); padding-bottom: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: var(--text-dark); font-size: 18px;" id="q-number">Soal Nomor -</h3>
            <span id="q-type-badge" style="background: var(--matcha-light); color: var(--matcha-dark); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">Tipe Soal</span>
        </div>

        <div id="q-text" class="quill-content-display" style="font-size: 16px; color: var(--text-dark); margin-bottom: 25px; line-height: 1.6;">
            </div>

        <div id="q-options" style="display: flex; flex-direction: column; gap: 12px;">
            </div>

        <div style="position: absolute; bottom: 20px; left: 24px; right: 24px; display: flex; justify-content: space-between; align-items: center;">
            <button id="btn-prev" onclick="changeQuestion(-1)" class="btn" style="background: var(--bg-color); border: 1px solid var(--glass-border); padding: 8px 16px; font-size: 14px;">
                <i class="ph ph-caret-left"></i> Sebelumnya
            </button>
            <button id="btn-next" onclick="changeQuestion(1)" class="btn btn-primary" style="padding: 8px 16px; font-size: 14px;">
                Selanjutnya <i class="ph ph-caret-right"></i>
            </button>
        </div>

    </div>
</div>

<script>
    // Injeksi Data dari PHP
    const questions = <?= json_encode($questions) ?>;
    const attemptId = <?= $attempt_id ?>;
    const packageId = <?= $pkg_id ?>;
    const timeRemaining = <?= $time_remaining ?>;
    const randomizeOptions = <?= $package['randomize_options'] ?> === 1;

    let currentIndex = 0;
    let answers = JSON.parse(localStorage.getItem(`exam_${attemptId}`)) || {};
    let isSidebarOpen = false;

    // 1. INISIALISASI
    document.addEventListener("DOMContentLoaded", () => {
        if (timeRemaining <= 0) {
            submitExam(true); // Waktu habis
            return;
        }
        
        renderNavGrid();
        renderQuestion(currentIndex);
        
        // Aktifkan Timer (Menggunakan fungsi dari app.js)
        startExamTimer(timeRemaining, 'exam-timer', () => submitExam(true));

        // Hubungkan Tombol Submit Header
        const btnHeaderSubmit = document.getElementById('btn-header-submit');
        if (btnHeaderSubmit) {
            btnHeaderSubmit.addEventListener('click', () => confirmSubmit());
        }
    });

    // 2. TOGGLE SIDEBAR NAVIGASI
    window.toggleExamSidebar = function() {
        const sidebar = document.getElementById('exam-sidebar');
        isSidebarOpen = !isSidebarOpen;
        sidebar.style.left = isSidebarOpen ? '0' : '-100%';
    };

    // 3. RENDER GRID NAVIGASI (Sidebar)
    function renderNavGrid() {
        const grid = document.getElementById('nav-grid');
        grid.innerHTML = '';
        
        questions.forEach((q, idx) => {
            const isAnswered = answers[q.id] !== undefined && answers[q.id] !== "" && answers[q.id] !== "[]" && answers[q.id] !== "{}";
            
            const btn = document.createElement('button');
            btn.className = `nav-btn ${isAnswered ? 'answered' : ''} ${idx === currentIndex ? 'active' : ''}`;
            btn.id = `nav-btn-${idx}`;
            btn.innerText = idx + 1;
            btn.onclick = () => {
                renderQuestion(idx);
                if (window.innerWidth < 768) toggleExamSidebar(); // Tutup sidebar di HP setelah klik
            };
            grid.appendChild(btn);
        });
    }

    // 4. RENDER SOAL DAN OPSI
    function renderQuestion(index) {
        currentIndex = index;
        const q = questions[index];
        const prevAnswer = answers[q.id];

        // Update Navigasi Aktif
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(`nav-btn-${index}`).classList.add('active');

        // Update Header
        document.getElementById('q-number').innerText = `Soal Nomor ${index + 1}`;
        document.getElementById('q-text').innerHTML = q.question_text;
        
        let typeBadge = '';
        let optionsHtml = '';

        // Tipe: Pilihan Ganda Normal
        if (q.question_type === 'pg') {
            typeBadge = 'Pilihan Ganda';
            const opts = q.options_data;
            
            // Konversi ke array agar bisa diacak jika perlu
            let optKeys = Object.keys(opts);
            if (randomizeOptions) {
                // Fisher-Yates Shuffle
                for (let i = optKeys.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [optKeys[i], optKeys[j]] = [optKeys[j], optKeys[i]];
                }
            }

            optKeys.forEach(k => {
                const isChecked = prevAnswer === k ? 'checked' : '';
                optionsHtml += `
                    <label style="display: flex; gap: 15px; padding: 12px 15px; border: 1px solid var(--glass-border); border-radius: var(--radius-sm); cursor: pointer; background: var(--white); transition: all 0.2s; align-items: flex-start;" class="opt-label">
                        <input type="radio" name="q_${q.id}" value="${k}" ${isChecked} onchange="saveAnswer(${q.id}, '${k}')" style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--matcha-dark);">
                        <div style="flex: 1; font-size: 15px; line-height: 1.5;">${opts[k]}</div>
                    </label>
                `;
            });
        } 
        // Tipe: PG Kompleks
        else if (q.question_type === 'pg_complex') {
            typeBadge = 'Pilihan Ganda Kompleks';
            const opts = q.options_data;
            const checkedArr = prevAnswer ? JSON.parse(prevAnswer) : [];

            Object.keys(opts).forEach(k => {
                const isChecked = checkedArr.includes(k) ? 'checked' : '';
                optionsHtml += `
                    <label style="display: flex; gap: 15px; padding: 12px 15px; border: 1px solid var(--glass-border); border-radius: var(--radius-sm); cursor: pointer; background: var(--white); align-items: flex-start;">
                        <input type="checkbox" name="q_${q.id}" value="${k}" ${isChecked} onchange="saveComplexPG(${q.id})" style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--matcha-dark);">
                        <div style="flex: 1; font-size: 15px;">${opts[k]}</div>
                    </label>
                `;
            });
        }
        // Tipe: Benar / Salah
        else if (q.question_type === 'true_false') {
            typeBadge = 'Benar / Salah';
            const stmts = q.options_data; // Array string
            const savedAns = prevAnswer ? JSON.parse(prevAnswer) : [];

            optionsHtml += `<div style="background: var(--white); border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--glass-border);"><table style="width: 100%; border-collapse: collapse; text-align: left;">`;
            stmts.forEach((stmt, i) => {
                const ans = savedAns[i] || '';
                optionsHtml += `
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <td style="padding: 12px 15px; font-size: 14px;">${stmt}</td>
                        <td style="padding: 12px 15px; width: 150px; text-align: right;">
                            <label style="margin-right: 15px; cursor: pointer;"><input type="radio" name="tf_${q.id}_${i}" value="Benar" ${ans === 'Benar' ? 'checked' : ''} onchange="saveTrueFalse(${q.id}, ${stmts.length})" style="accent-color: var(--matcha-dark);"> Benar</label>
                            <label style="cursor: pointer;"><input type="radio" name="tf_${q.id}_${i}" value="Salah" ${ans === 'Salah' ? 'checked' : ''} onchange="saveTrueFalse(${q.id}, ${stmts.length})" style="accent-color: var(--matcha-dark);"> Salah</label>
                        </td>
                    </tr>
                `;
            });
            optionsHtml += `</table></div>`;
        }
        // Tipe: Menjodohkan
        else if (q.question_type === 'match') {
            typeBadge = 'Menjodohkan';
            const data = q.options_data; // {left: [...], right: [...]}
            const savedAns = prevAnswer ? JSON.parse(prevAnswer) : {};
            
            // Acak opsi kanan untuk dropdown
            let rightOpts = [...data.right];
            rightOpts.sort(() => Math.random() - 0.5);

            data.left.forEach((leftItem, i) => {
                const selectedVal = savedAns[leftItem] || '';
                let dropHtml = `<select id="match_${q.id}_${i}" onchange="saveMatch(${q.id})" class="form-control" style="width: 100%; cursor: pointer;">
                                <option value="" disabled ${selectedVal === '' ? 'selected' : ''}>-- Pilih Pasangan --</option>`;
                
                rightOpts.forEach(r => {
                    dropHtml += `<option value="${r}" ${selectedVal === r ? 'selected' : ''}>${r}</option>`;
                });
                dropHtml += `</select>`;

                optionsHtml += `
                    <div style="display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; background: var(--white); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--glass-border);">
                        <div style="font-weight: 500; font-size: 14px; margin-bottom: 5px;">${leftItem}</div>
                        ${dropHtml}
                    </div>
                `;
            });
        }
        // Tipe: Isian Singkat
        else if (q.question_type === 'short_answer') {
            typeBadge = 'Isian Singkat';
            optionsHtml = `
                <input type="text" id="short_${q.id}" class="form-control" placeholder="Ketik jawaban kamu di sini..." value="${prevAnswer || ''}" oninput="saveAnswer(${q.id}, this.value)" style="font-size: 16px; padding: 16px; background: var(--white);">
            `;
        }

        document.getElementById('q-type-badge').innerText = typeBadge;
        document.getElementById('q-options').innerHTML = optionsHtml;

        // Kontrol Tombol Next/Prev
        document.getElementById('btn-prev').style.visibility = index === 0 ? 'hidden' : 'visible';
        
        const btnNext = document.getElementById('btn-next');
        if (index === questions.length - 1) {
            btnNext.innerHTML = '<i class="ph ph-check-circle"></i> Selesai & Kumpul';
            btnNext.onclick = () => confirmSubmit();
        } else {
            btnNext.innerHTML = 'Selanjutnya <i class="ph ph-caret-right"></i>';
            btnNext.onclick = () => changeQuestion(1);
        }
    }

    // 5. LOGIKA PENYIMPANAN JAWABAN (Ke LocalStorage)
    function saveAnswer(qId, value) {
        answers[qId] = value;
        localStorage.setItem(`exam_${attemptId}`, JSON.stringify(answers));
        document.getElementById(`nav-btn-${currentIndex}`).classList.add('answered');
    }

    function saveComplexPG(qId) {
        const checkboxes = document.querySelectorAll(`input[name="q_${qId}"]:checked`);
        let vals = [];
        checkboxes.forEach(cb => vals.push(cb.value));
        answers[qId] = JSON.stringify(vals);
        localStorage.setItem(`exam_${attemptId}`, JSON.stringify(answers));
        
        if (vals.length > 0) document.getElementById(`nav-btn-${currentIndex}`).classList.add('answered');
        else document.getElementById(`nav-btn-${currentIndex}`).classList.remove('answered');
    }

    function saveTrueFalse(qId, length) {
        let vals = [];
        let answeredCount = 0;
        for(let i=0; i<length; i++){
            const checked = document.querySelector(`input[name="tf_${qId}_${i}"]:checked`);
            if (checked) { vals.push(checked.value); answeredCount++; }
            else { vals.push(''); }
        }
        answers[qId] = JSON.stringify(vals);
        localStorage.setItem(`exam_${attemptId}`, JSON.stringify(answers));
        
        if (answeredCount === length) document.getElementById(`nav-btn-${currentIndex}`).classList.add('answered');
    }

    function saveMatch(qId) {
        const q = questions.find(x => x.id == qId);
        const leftItems = q.options_data.left;
        let result = {};
        let answeredCount = 0;

        leftItems.forEach((left, i) => {
            const val = document.getElementById(`match_${qId}_${i}`).value;
            result[left] = val;
            if (val !== "") answeredCount++;
        });

        answers[qId] = JSON.stringify(result);
        localStorage.setItem(`exam_${attemptId}`, JSON.stringify(answers));
        
        if (answeredCount === leftItems.length) document.getElementById(`nav-btn-${currentIndex}`).classList.add('answered');
        else document.getElementById(`nav-btn-${currentIndex}`).classList.remove('answered');
    }

    // 6. LOGIKA NAVIGASI & PENGUMPULAN
    function changeQuestion(step) {
        const newIndex = currentIndex + step;
        if (newIndex >= 0 && newIndex < questions.length) {
            renderQuestion(newIndex);
        }
    }

    function confirmSubmit() {
        // Cek jika ada yang belum dijawab
        const totalAnswered = Object.keys(answers).filter(k => answers[k] !== '' && answers[k] !== '[]' && answers[k] !== '{}').length;
        const totalQuestions = questions.length;

        let textMsg = totalAnswered < totalQuestions 
            ? `Masih ada ${totalQuestions - totalAnswered} soal yang belum dijawab! Yakin ingin mengumpulkan?` 
            : `Apakah kamu yakin ingin mengumpulkan jawaban sekarang?`;

        confirmAction('Kumpulkan Jawaban?', textMsg, 'Ya, Kumpulkan', () => {
            submitExam(false);
        });
    }

    function submitExam(isAuto = false) {
        if (isAuto) {
            Swal.fire({ title: 'Waktu Habis!', text: 'Sistem sedang mengumpulkan jawaban secara otomatis...', icon: 'info', allowOutsideClick: false, showConfirmButton: false });
        } else {
            Swal.fire({ title: 'Sedang Mengoreksi...', html: 'Robot Matcha Edu sedang menghitung nilaimu.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        }

        // Tembak API via fetch
        fetch('<?= BASE_URL ?>api/submit_exam.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attempt_id: attemptId, answers: answers })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Bersihkan LocalStorage
                localStorage.removeItem(`exam_${attemptId}`);
                
                // Tampilkan Nilai Akhir
                Swal.fire({
                    icon: 'success',
                    title: 'Ujian Selesai!',
                    html: `Nilai Akhir Kamu:<br><span style="font-size:48px; font-weight:700; color:var(--matcha-dark);">${data.final_score}</span>`,
                    confirmButtonText: 'Kembali ke Beranda',
                    confirmButtonColor: '#81C784',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = '<?= BASE_URL ?>index.php?page=dashboard';
                });
            } else {
                Swal.fire('Gagal!', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Terjadi Kesalahan', 'Pastikan koneksi internet stabil lalu coba klik Kumpul lagi.', 'error');
        });
    }
</script>

<style>
    /* Styling khusus konten Quill saat Ujian berjalan */
    .quill-content-display img { max-width: 100%; border-radius: 8px; margin: 10px 0; }
    .quill-content-display p { margin-bottom: 5px; }
    /* Hover state untuk opsi PG */
    .opt-label:hover { border-color: var(--matcha-primary) !important; background: var(--bg-color) !important; }
</style>