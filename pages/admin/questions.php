<?php
// Pastikan tidak diakses langsung
if (!defined('BASE_URL')) exit('Akses ditolak.');

// Proteksi: Hanya admin
require_login('admin');

$success_msg = '';
$error_msg = '';

// Validasi ID Paket
$package_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($package_id === 0) {
    header("Location: " . BASE_URL . "index.php?page=admin_packages");
    exit();
}

$stmt = $conn->prepare("SELECT title FROM exam_packages WHERE id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$pkg_result = $stmt->get_result();
if ($pkg_result->num_rows === 0) {
    header("Location: " . BASE_URL . "index.php?page=admin_packages");
    exit();
}
$package_title = $pkg_result->fetch_assoc()['title'];
$stmt->close();

// ==========================================================================
// 1. PROSES AKSI (Tambah Manual, Import CSV, Hapus)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ========== TAMBAH MANUAL ==========
    if ($action === 'add_manual') {
        $q_type = sanitize_input($conn, $_POST['question_type']);
        $q_text = $_POST['question_text_html'];
        $weight = floatval($_POST['weight']);
        
        $options_data = null;
        $correct_answer = "";

        if ($q_type === 'pg') {
            $options = [
                'A' => $_POST['pg_a_html'], 'B' => $_POST['pg_b_html'],
                'C' => $_POST['pg_c_html'], 'D' => $_POST['pg_d_html']
            ];
            if (!empty(trim(strip_tags($_POST['pg_e_html'])))) {
                $options['E'] = $_POST['pg_e_html'];
            }
            $options_data = json_encode($options);
            $correct_answer = sanitize_input($conn, $_POST['correct_pg']);
        } 
        elseif ($q_type === 'pg_complex') {
            $options = [
                'A' => $_POST['pgc_a_html'], 'B' => $_POST['pgc_b_html'],
                'C' => $_POST['pgc_c_html'], 'D' => $_POST['pgc_d_html'],
                'E' => $_POST['pgc_e_html']
            ];
            $options = array_filter($options, function($val) { return !empty(trim(strip_tags($val))); });
            $options_data = json_encode($options);
            $correct_answer = json_encode($_POST['correct_pgc'] ?? []);
        }
        elseif ($q_type === 'true_false') {
            $statements = $_POST['tf_statement'] ?? [];
            $answers = $_POST['tf_answer'] ?? [];
            
            $filtered_statements = [];
            $filtered_answers = [];
            foreach ($statements as $i => $stmt) {
                if (!empty(trim($stmt))) {
                    $filtered_statements[] = trim($stmt);
                    $filtered_answers[] = $answers[$i] ?? 'Benar';
                }
            }
            
            $options_data = json_encode($filtered_statements);
            $correct_answer = json_encode($filtered_answers);
        }
        elseif ($q_type === 'match') {
            $lefts = $_POST['match_left'] ?? [];
            $rights = $_POST['match_right'] ?? [];
            
            $pairs = ['left' => [], 'right' => []];
            foreach ($lefts as $i => $left) {
                if (!empty(trim($left)) && !empty(trim($rights[$i] ?? ''))) {
                    $pairs['left'][] = trim($left);
                    $pairs['right'][] = trim($rights[$i]);
                }
            }
            
            $options_data = json_encode($pairs);
            $correct_answer = json_encode(array_combine($pairs['left'], $pairs['right'])); 
        }
        elseif ($q_type === 'short_answer') {
            $correct_answer = sanitize_input($conn, $_POST['correct_short']);
        }

        $stmt = $conn->prepare("INSERT INTO questions (package_id, question_type, question_text, options_data, correct_answer, weight) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssd", $package_id, $q_type, $q_text, $options_data, $correct_answer, $weight);
        
        if ($stmt->execute()) $success_msg = "Soal baru berhasil ditambahkan.";
        else $error_msg = "Gagal menyimpan soal: " . $conn->error;
        $stmt->close();
    }
    
    // ========== IMPORT CSV (FIXED - OPSI GA NULL LAGI) ==========
    elseif ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $parsed_data = parse_csv_questions($_FILES['csv_file']['tmp_name']);
            
            if (empty($parsed_data)) {
                $error_msg = "File CSV kosong atau format tidak valid.";
            } else {
                $count = 0;
                $errors = [];
                $stmt = $conn->prepare("INSERT INTO questions (package_id, question_type, question_text, options_data, correct_answer, weight) VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($parsed_data as $row_num => $row) {
                    if (!isset($row['Type']) || !isset($row['Question']) || empty(trim($row['Type'])) || empty(trim($row['Question']))) {
                        $errors[] = "Baris " . ($row_num + 2) . ": Type atau Question kosong, dilewati.";
                        continue;
                    }
                    
                    $q_type = strtolower(trim($row['Type']));
                    $q_text = "<p>" . trim($row['Question']) . "</p>";
                    $weight = isset($row['Weight']) && is_numeric($row['Weight']) ? floatval($row['Weight']) : 10;
                    $opt_data = null;
                    $correct = null;
                    
                    // ========== PG ==========
                    if ($q_type === 'pg') {
                        $opts = [];
                        if (!empty(trim($row['Opt_A'] ?? ''))) $opts['A'] = trim($row['Opt_A']);
                        if (!empty(trim($row['Opt_B'] ?? ''))) $opts['B'] = trim($row['Opt_B']);
                        if (!empty(trim($row['Opt_C'] ?? ''))) $opts['C'] = trim($row['Opt_C']);
                        if (!empty(trim($row['Opt_D'] ?? ''))) $opts['D'] = trim($row['Opt_D']);
                        if (!empty(trim($row['Opt_E'] ?? ''))) $opts['E'] = trim($row['Opt_E']);
                        
                        if (count($opts) < 2) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Minimal 2 opsi. Dilewati.";
                            continue;
                        }
                        
                        $opt_data = json_encode($opts);
                        $correct = strtoupper(trim($row['Correct'] ?? ''));
                        
                        if (!in_array($correct, array_keys($opts))) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Kunci '$correct' tidak ada di opsi. Dilewati.";
                            continue;
                        }
                    }
                    
                    // ========== PG KOMPLEKS ==========
                    elseif ($q_type === 'pg_complex') {
                        // SIMPAN SEMUA OPSI MESKIPUN ADA YANG KOSONG
                        $opts = [
                            'A' => trim($row['Opt_A'] ?? ''),
                            'B' => trim($row['Opt_B'] ?? ''),
                            'C' => trim($row['Opt_C'] ?? ''),
                            'D' => trim($row['Opt_D'] ?? '')
                        ];
                        if (!empty(trim($row['Opt_E'] ?? ''))) {
                            $opts['E'] = trim($row['Opt_E']);
                        }
                        
                        // HANYA filter yang benar-benar string kosong
                        $filtered_opts = array_filter($opts, function($val) { 
                            return $val !== ''; 
                        });
                        
                        if (count($filtered_opts) < 2) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Minimal 2 opsi untuk PG Kompleks. Dilewati.";
                            continue;
                        }
                        
                        // SIMPAN opsi yang sudah difilter
                        $opt_data = json_encode($filtered_opts);
                        
                        // Parse kunci jawaban
                        $correct_raw = $row['Correct'] ?? '';
                        $answers = array_map('trim', explode(',', str_replace(' ', '', strtoupper($correct_raw))));
                        $answers = array_filter($answers, function($a) { return $a !== ''; });
                        
                        if (empty($answers)) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Kunci jawaban kosong. Dilewati.";
                            continue;
                        }
                        
                        // Validasi
                        $valid = true;
                        foreach ($answers as $ans) {
                            if (!isset($filtered_opts[$ans])) {
                                $errors[] = "Baris " . ($row_num + 2) . ": Kunci '$ans' tidak ada di opsi. Dilewati.";
                                $valid = false;
                                break;
                            }
                        }
                        if (!$valid) continue;
                        
                        $correct = json_encode($answers);
                    }
                    
                    // ========== BENAR SALAH ==========
                    elseif ($q_type === 'true_false') {
                        // AMBIL SEMUA KOLOM OPT SEBAGAI PERNYATAAN
                        $statements = [];
                        $opt_keys = ['Opt_A', 'Opt_B', 'Opt_C', 'Opt_D', 'Opt_E'];
                        
                        foreach ($opt_keys as $key) {
                            if (isset($row[$key]) && !empty(trim($row[$key]))) {
                                $statements[] = trim($row[$key]);
                            }
                        }
                        
                        if (empty($statements)) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Tidak ada pernyataan. Dilewati.";
                            continue;
                        }
                        
                        // SIMPAN PERNYATAAN
                        $opt_data = json_encode($statements);
                        
                        // Parse jawaban
                        $correct_raw = $row['Correct'] ?? '';
                        $answers_raw = array_map('trim', explode(',', $correct_raw));
                        $answers = [];
                        
                        foreach ($answers_raw as $ans) {
                            $ans_lower = strtolower($ans);
                            if (in_array($ans_lower, ['benar', 'b', '1', 'true', 'yes'])) {
                                $answers[] = 'Benar';
                            } else {
                                $answers[] = 'Salah';
                            }
                        }
                        
                        // Sesuaikan jumlah
                        while (count($answers) < count($statements)) {
                            $answers[] = 'Salah';
                        }
                        $answers = array_slice($answers, 0, count($statements));
                        
                        $correct = json_encode($answers);
                    }
                    
                    // ========== MENJODOHKAN ==========
                    elseif ($q_type === 'match') {
                        $left = [];
                        $right = [];
                        
                        // Format: Opt_A = kiri, Opt_B = kanan
                        if (!empty(trim($row['Opt_A'] ?? '')) && !empty(trim($row['Opt_B'] ?? ''))) {
                            $left = array_map('trim', explode(',', $row['Opt_A']));
                            $right = array_map('trim', explode(',', $row['Opt_B']));
                        }
                        
                        if (empty($left) || empty($right) || count($left) !== count($right)) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Format menjodohkan tidak valid. Dilewati.";
                            continue;
                        }
                        
                        $opt_data = json_encode(['left' => $left, 'right' => $right]);
                        $correct = json_encode(array_combine($left, $right));
                    }
                    
                    // ========== ISIAN SINGKAT ==========
                    elseif ($q_type === 'short_answer') {
                        $correct = trim($row['Correct'] ?? '');
                        if (empty($correct)) {
                            $errors[] = "Baris " . ($row_num + 2) . ": Kunci jawaban kosong. Dilewati.";
                            continue;
                        }
                    }
                    
                    else {
                        $errors[] = "Baris " . ($row_num + 2) . ": Tipe '$q_type' tidak dikenal. Dilewati.";
                        continue;
                    }
                    
                    // SIMPAN KE DATABASE
                    $stmt->bind_param("issssd", $package_id, $q_type, $q_text, $opt_data, $correct, $weight);
                    if ($stmt->execute()) {
                        $count++;
                    } else {
                        $errors[] = "Baris " . ($row_num + 2) . ": Gagal menyimpan - " . $conn->error;
                    }
                }
                $stmt->close();
                
                if ($count > 0) {
                    $success_msg = "$count soal berhasil diimpor dari CSV.";
                }
                if (!empty($errors)) {
                    $error_msg = "Beberapa soal gagal diimpor:<br>" . implode("<br>", array_slice($errors, 0, 10));
                    if (count($errors) > 10) {
                        $error_msg .= "<br>...dan " . (count($errors) - 10) . " error lainnya.";
                    }
                }
            }
        } else {
            $error_msg = "Upload file gagal. Error code: " . ($_FILES['csv_file']['error'] ?? 'unknown');
        }
    }
    
    // ========== HAPUS SOAL ==========
    elseif ($action === 'delete') {
        $q_id = intval($_POST['question_id']);
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ? AND package_id = ?");
        $stmt->bind_param("ii", $q_id, $package_id);
        if ($stmt->execute()) {
            $success_msg = "Soal berhasil dihapus.";
        } else {
            $error_msg = "Gagal menghapus soal.";
        }
        $stmt->close();
    }
}

// Ambil Semua Soal
$questions = [];
$res = $conn->query("SELECT * FROM questions WHERE package_id = $package_id ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $questions[] = $row;
}
?>

<div style="margin-bottom: 30px;">
    <a href="<?= BASE_URL ?>index.php?page=admin_packages" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--bg-color); border: 1px solid var(--glass-border); margin-bottom: 15px;">
        <i class="ph ph-arrow-left"></i> Kembali ke Daftar Paket
    </a>
    <h1 style="font-size: 24px; color: var(--text-dark); margin-bottom: 5px;">Bank Soal</h1>
    <p style="color: var(--matcha-dark); font-weight: 600; margin: 0;">Paket: <?= htmlspecialchars($package_title) ?></p>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
    
    <!-- Bagian Form Input Soal -->
    <div class="glass-card" style="padding: 24px;">
        
        <!-- Tab Navigation -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--glass-border); padding-bottom: 10px;">
            <button onclick="switchTab('manual')" id="tab-manual" class="btn btn-primary" style="box-shadow:none;"><i class="ph ph-pencil-line"></i> Input Manual (Rich Text)</button>
            <button onclick="switchTab('csv')" id="tab-csv" class="btn" style="background:transparent; color:var(--text-muted); box-shadow:none;"><i class="ph ph-file-csv"></i> Import CSV Massal</button>
        </div>

        <!-- 1. FORM INPUT MANUAL -->
        <div id="form-manual">
            <form method="POST" action="" id="manualQuestionForm">
                <input type="hidden" name="action" value="add_manual">
                
                <input type="hidden" name="question_text_html" id="html-main">
                <input type="hidden" name="pg_a_html" id="html-pg-a"><input type="hidden" name="pg_b_html" id="html-pg-b">
                <input type="hidden" name="pg_c_html" id="html-pg-c"><input type="hidden" name="pg_d_html" id="html-pg-d">
                <input type="hidden" name="pg_e_html" id="html-pg-e">
                <input type="hidden" name="pgc_a_html" id="html-pgc-a"><input type="hidden" name="pgc_b_html" id="html-pgc-b">
                <input type="hidden" name="pgc_c_html" id="html-pgc-c"><input type="hidden" name="pgc_d_html" id="html-pgc-d">
                <input type="hidden" name="pgc_e_html" id="html-pgc-e">

                <div style="display: grid; grid-template-columns: 1fr 100px; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Jenis Soal</label>
                        <select name="question_type" id="q_type" class="form-control" onchange="toggleOptionBlocks()">
                            <option value="pg">1. Pilihan Ganda (Single Choice)</option>
                            <option value="pg_complex">2. Pilihan Ganda Kompleks (Multi Choice)</option>
                            <option value="true_false">3. Benar / Salah (Tabel Pernyataan)</option>
                            <option value="match">4. Menjodohkan (Pasangan)</option>
                            <option value="short_answer">5. Isian Singkat</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Bobot Poin</label>
                        <input type="number" name="weight" class="form-control" value="10" min="1" step="0.5" required>
                    </div>
                </div>

                <div id="guide-box" style="background: #E0F2FE; color: #0369A1; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start;">
                    <i class="ph-fill ph-info" style="font-size: 20px; margin-top: 2px;"></i>
                    <span id="guide-text"></span>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 8px;">Teks Pertanyaan (Bisa Insert Gambar & Rumus)</label>
                    <div id="editor-main" style="height: 150px; background: var(--white); border-radius: 0 0 8px 8px;"></div>
                </div>

                <!-- BLOK PG -->
                <div id="block-pg" class="opt-block">
                    <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">Opsi Jawaban & Kunci (Pilih salah satu)</h4>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach(['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="radio" name="correct_pg" value="<?= $opt ?>" style="width: 20px; height: 20px; margin-top: 10px;">
                            <span style="font-weight: 600; font-size: 18px; margin-top: 8px;"><?= $opt ?>.</span>
                            <div style="flex: 1;">
                                <div id="editor-pg-<?= strtolower($opt) ?>" style="height: 60px; background: var(--white);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- BLOK PG KOMPLEKS -->
                <div id="block-pgc" class="opt-block" style="display: none;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">Opsi Jawaban & Kunci (Centang lebih dari satu)</h4>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach(['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="checkbox" name="correct_pgc[]" value="<?= $opt ?>" style="width: 20px; height: 20px; margin-top: 10px;">
                            <span style="font-weight: 600; font-size: 18px; margin-top: 8px;"><?= $opt ?>.</span>
                            <div style="flex: 1;">
                                <div id="editor-pgc-<?= strtolower($opt) ?>" style="height: 60px; background: var(--white);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- BLOK BENAR SALAH -->
                <div id="block-tf" class="opt-block" style="display: none;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">Daftar Pernyataan & Kunci</h4>
                    <div id="tf-container" style="display: flex; flex-direction: column; gap: 10px;">
                        <?php for($i=0; $i<3; $i++): ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="tf_statement[]" class="form-control" placeholder="Tulis pernyataan..." style="flex: 1;">
                            <select name="tf_answer[]" class="form-control" style="width: 120px;">
                                <option value="Benar">Benar</option>
                                <option value="Salah">Salah</option>
                            </select>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" onclick="addTfRow()" class="btn" style="margin-top: 10px; background: var(--matcha-light); color: var(--matcha-dark);"><i class="ph ph-plus"></i> Tambah Baris</button>
                </div>

                <!-- BLOK MENJODOHKAN -->
                <div id="block-match" class="opt-block" style="display: none;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">Pasangan Kunci</h4>
                    <div id="match-container" style="display: flex; flex-direction: column; gap: 10px;">
                        <?php for($i=0; $i<3; $i++): ?>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" name="match_left[]" class="form-control" placeholder="Kolom Kiri..." style="flex: 1;">
                            <i class="ph ph-arrows-left-right" style="color: var(--text-muted);"></i>
                            <input type="text" name="match_right[]" class="form-control" placeholder="Kolom Kanan..." style="flex: 1;">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" onclick="addMatchRow()" class="btn" style="margin-top: 10px; background: var(--matcha-light); color: var(--matcha-dark);"><i class="ph ph-plus"></i> Tambah Pasangan</button>
                </div>

                <!-- BLOK ISIAN SINGKAT -->
                <div id="block-short" class="opt-block" style="display: none;">
                    <h4 style="font-size: 14px; margin-bottom: 15px; color: var(--text-dark);">Kunci Jawaban Teks</h4>
                    <input type="text" name="correct_short" id="inp_short" class="form-control" placeholder="Ketik kata/frasa kunci (Case-insensitive)">
                </div>

                <div style="margin-top: 25px;">
                    <button type="button" onclick="submitManual()" class="btn btn-primary" style="width: 100%;"><i class="ph ph-floppy-disk"></i> Simpan Soal</button>
                </div>
            </form>
        </div>

        <!-- 2. FORM IMPORT CSV -->
        <div id="form-csv" style="display: none;">
            <div style="background: #E0F2FE; color: #0369A1; padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.9rem;">
                <h4 style="margin-bottom: 8px;"><i class="ph-fill ph-info"></i> Panduan Format CSV</h4>
                <p><strong>Type | Question | Opt_A | Opt_B | Opt_C | Opt_D | Opt_E | Correct | Weight</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li><strong>Type:</strong> <code>pg</code>, <code>pg_complex</code>, <code>true_false</code>, <code>match</code>, <code>short_answer</code></li>
                    <li><strong>pg:</strong> Correct = A/B/C/D/E</li>
                    <li><strong>pg_complex:</strong> Correct = A,C,D (koma, tanpa spasi)</li>
                    <li><strong>true_false:</strong> Opt_A,B,C = pernyataan. Correct = Benar,Salah,Benar</li>
                    <li><strong>match:</strong> Opt_A = kiri (koma), Opt_B = kanan (koma)</li>
                    <li><strong>short_answer:</strong> Correct = kata kunci</li>
                </ul>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <input type="file" name="csv_file" class="form-control" accept=".csv" required style="padding: 10px; margin-bottom: 20px;">
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="ph ph-upload-simple"></i> Upload CSV</button>
            </form>
        </div>
    </div>

    <!-- Daftar Soal -->
    <div class="glass-card" style="padding: 24px;">
        <h3 style="color: var(--text-dark); margin-bottom: 15px; display: flex; justify-content: space-between;">
            Daftar Soal Tersimpan 
            <span style="background: var(--matcha-light); color: var(--matcha-dark); padding: 4px 14px; border-radius: 20px; font-size: 14px;"><?= count($questions) ?> Soal</span>
        </h3>
        
        <?php if (count($questions) === 0): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                <i class="ph ph-empty" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                <p>Belum ada soal.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php 
                $type_labels = [
                    'pg' => ['Pilihan Ganda', '#DBEAFE', '#1E40AF'],
                    'pg_complex' => ['PG Kompleks', '#FEF3C7', '#92400E'],
                    'true_false' => ['Benar/Salah', '#D1FAE5', '#065F46'],
                    'match' => ['Menjodohkan', '#EDE9FE', '#5B21B6'],
                    'short_answer' => ['Isian Singkat', '#FEE2E2', '#991B1B']
                ];
                foreach ($questions as $index => $q): 
                    $label = $type_labels[$q['question_type']] ?? ['Unknown', '#E2E8F0', '#475569'];
                ?>
                <div style="border: 1px solid var(--glass-border); border-radius: var(--radius-sm); padding: 16px; background: var(--white);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px;">
                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 40px;">
                            <span style="font-weight: 700; color: var(--matcha-dark);">#<?= $index + 1 ?></span>
                            <span style="font-size: 10px; color: var(--text-muted);"><?= $q['weight'] ?> pts</span>
                        </div>
                        
                        <div style="flex: 1; font-size: 0.95rem; min-width: 0;">
                            <span style="font-size: 11px; background: <?= $label[1] ?>; color: <?= $label[2] ?>; padding: 2px 10px; border-radius: 12px; font-weight: 600;">
                                <?= $label[0] ?>
                            </span>
                            <div class="quill-content-display" style="margin-top: 8px;">
                                <?= $q['question_text'] ?>
                            </div>
                            
                            <?php if ($q['question_type'] === 'pg' || $q['question_type'] === 'pg_complex'): 
                                $opts = json_decode($q['options_data'], true) ?: [];
                                $corrects = json_decode($q['correct_answer'], true);
                                if (!is_array($corrects)) $corrects = [$q['correct_answer']];
                            ?>
                                <div style="margin-top: 10px; display: grid; gap: 4px; font-size: 0.85rem;">
                                    <?php foreach ($opts as $key => $val): ?>
                                        <div style="padding: 4px 8px; border-radius: 4px; <?= in_array($key, $corrects) ? 'color: #059669; font-weight: bold; background: #D1FAE5;' : 'color: var(--text-muted);' ?>">
                                            <?= $key ?>. <?= strip_tags($val) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($q['question_type'] === 'true_false'): 
                                $stmts = json_decode($q['options_data'], true) ?: [];
                                $ans = json_decode($q['correct_answer'], true) ?: [];
                            ?>
                                <table style="width: 100%; margin-top: 10px; font-size: 0.85rem; border-collapse: collapse;">
                                    <?php foreach($stmts as $k => $s): ?>
                                    <tr>
                                        <td style="padding: 4px 8px; border: 1px solid #E2E8F0;"><?= htmlspecialchars($s) ?></td>
                                        <td style="padding: 4px 8px; border: 1px solid #E2E8F0; text-align: center; font-weight: 600; color: <?= ($ans[$k]??'')==='Benar'?'#059669':'#DC2626' ?>;">
                                            <?= htmlspecialchars($ans[$k] ?? '?') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php elseif ($q['question_type'] === 'match'): 
                                $data = json_decode($q['options_data'], true);
                            ?>
                                <table style="width: 100%; margin-top: 10px; font-size: 0.85rem; border-collapse: collapse;">
                                    <?php if ($data && isset($data['left'])): ?>
                                        <?php foreach($data['left'] as $k => $left): ?>
                                        <tr>
                                            <td style="padding: 4px 8px; border: 1px solid #E2E8F0;"><?= htmlspecialchars($left) ?></td>
                                            <td style="padding: 4px 8px; border: 1px solid #E2E8F0; text-align: center;">➡️</td>
                                            <td style="padding: 4px 8px; border: 1px solid #E2E8F0; color: #059669; font-weight: 600;">
                                                <?= htmlspecialchars($data['right'][$k] ?? '?') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </table>
                            <?php elseif ($q['question_type'] === 'short_answer'): ?>
                                <div style="margin-top: 10px; padding: 8px 12px; background: #FEF2F2; border-radius: 6px; font-size: 0.85rem;">
                                    🔑 <strong>Kunci:</strong> <?= htmlspecialchars($q['correct_answer']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="" onsubmit="return confirm('Yakin hapus soal ini?');" style="flex-shrink: 0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <button type="submit" class="btn" style="padding: 6px 8px; color: #DC2626; background: #FEE2E2; box-shadow: none;">
                                <i class="ph-fill ph-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($success_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Berhasil', '<?= addslashes($success_msg) ?>', 'success'));</script>
<?php endif; ?>
<?php if ($error_msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => showToast('Gagal', '<?= addslashes($error_msg) ?>', 'error'));</script>
<?php endif; ?>

<script>
    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file'); input.setAttribute('accept', 'image/*'); input.click();
        input.onchange = async () => {
            const file = input.files[0];
            const formData = new FormData(); formData.append('image', file);
            iziToast.info({ title: 'Mengunggah...', message: 'Gambar dikirim...', timeout: 2000 });
            try {
                const res = await fetch('<?= BASE_URL ?>api/upload_image.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.url) {
                    const range = qMain.getSelection(true);
                    qMain.insertEmbed(range.index, 'image', data.url);
                    showToast('Sukses', 'Gambar ditambahkan.', 'success');
                } else showToast('Gagal', data.error, 'error');
            } catch (err) { showToast('Gagal', 'Error jaringan.', 'error'); }
        };
    }

    const toolbarOptions = [
        [{ 'header': [1, 2, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'script': 'sub'}, { 'script': 'super' }],
        ['formula', 'image']
    ];
    const quillConfig = { theme: 'snow', modules: { toolbar: { container: toolbarOptions, handlers: { image: imageHandler } } } };

    const qMain = new Quill('#editor-main', { ...quillConfig, placeholder: 'Ketik stimulus soal...' });
    
    const pgEditors = {};
    const pgcEditors = {};
    ['a','b','c','d','e'].forEach(opt => {
        pgEditors[opt] = new Quill(`#editor-pg-${opt}`, { ...quillConfig, placeholder: `Opsi ${opt.toUpperCase()}...`});
        pgcEditors[opt] = new Quill(`#editor-pgc-${opt}`, { ...quillConfig, placeholder: `Opsi ${opt.toUpperCase()}...`});
    });

    const guides = {
        'pg': 'Isi soal utama dan opsi A-E. Pilih SATU radio button sebagai kunci.',
        'pg_complex': 'Centang SEMUA opsi yang benar (bisa lebih dari satu).',
        'true_false': 'Buat daftar pernyataan dan pilih Benar/Salah.',
        'match': 'Masukkan pasangan Kiri → Kanan. Sistem akan mengacak saat ujian.',
        'short_answer': 'Jawaban siswa harus sama persis (case-insensitive).'
    };

    function toggleOptionBlocks() {
        const type = document.getElementById('q_type').value;
        document.querySelectorAll('.opt-block').forEach(b => b.style.display = 'none');
        document.getElementById('guide-text').innerHTML = guides[type];
        const blockMap = { 'pg': 'block-pg', 'pg_complex': 'block-pgc', 'true_false': 'block-tf', 'match': 'block-match', 'short_answer': 'block-short' };
        const target = document.getElementById(blockMap[type]);
        if (target) target.style.display = 'block';
    }

    function addTfRow() {
        document.getElementById('tf-container').insertAdjacentHTML('beforeend', `
            <div style="display: flex; gap: 10px;">
                <input type="text" name="tf_statement[]" class="form-control" placeholder="Pernyataan..." style="flex: 1;">
                <select name="tf_answer[]" class="form-control" style="width: 120px;">
                    <option value="Benar">Benar</option><option value="Salah">Salah</option>
                </select>
            </div>
        `);
    }
    function addMatchRow() {
        document.getElementById('match-container').insertAdjacentHTML('beforeend', `
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="match_left[]" class="form-control" placeholder="Kiri..." style="flex: 1;">
                <i class="ph ph-arrows-left-right"></i>
                <input type="text" name="match_right[]" class="form-control" placeholder="Kanan..." style="flex: 1;">
            </div>
        `);
    }

    function submitManual() {
        document.getElementById('html-main').value = qMain.root.innerHTML;
        const type = document.getElementById('q_type').value;
        if (type === 'pg') {
            if(!document.querySelector('input[name="correct_pg"]:checked')) return showToast('Peringatan', 'Pilih kunci!', 'error');
            ['a','b','c','d','e'].forEach(opt => document.getElementById(`html-pg-${opt}`).value = pgEditors[opt].root.innerHTML);
        } else if (type === 'pg_complex') {
            if(document.querySelectorAll('input[name="correct_pgc[]"]:checked').length === 0) return showToast('Peringatan', 'Centang minimal 1!', 'error');
            ['a','b','c','d','e'].forEach(opt => document.getElementById(`html-pgc-${opt}`).value = pgcEditors[opt].root.innerHTML);
        }
        document.getElementById('manualQuestionForm').submit();
    }

    function switchTab(tab) {
        if(tab === 'manual') {
            document.getElementById('tab-manual').className = 'btn btn-primary';
            document.getElementById('tab-csv').className = 'btn';
            document.getElementById('tab-csv').style.background = 'transparent';
            document.getElementById('form-manual').style.display = 'block';
            document.getElementById('form-csv').style.display = 'none';
        } else {
            document.getElementById('tab-csv').className = 'btn btn-primary';
            document.getElementById('tab-csv').style.background = '#2563EB';
            document.getElementById('tab-manual').className = 'btn';
            document.getElementById('tab-manual').style.background = 'transparent';
            document.getElementById('form-csv').style.display = 'block';
            document.getElementById('form-manual').style.display = 'none';
        }
    }

    toggleOptionBlocks();
</script>