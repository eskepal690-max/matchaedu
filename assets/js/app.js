/* ==========================================================================
   MATCHA EDU - GLOBAL JAVASCRIPT
   Fungsi: PWA, UI Interactivity, Notifikasi, dan Logika CBT
   ========================================================================== */

// --- 1. PWA & SERVICE WORKER SETUP ---
let deferredPrompt;

document.addEventListener('DOMContentLoaded', () => {
    // Daftarkan Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./sw.js')
                .then(registration => {
                    console.log('ServiceWorker registered with scope:', registration.scope);
                })
                .catch(error => {
                    console.error('ServiceWorker registration failed:', error);
                });
        });
    }

    // Tangkap event install PWA
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const installBtn = document.getElementById('btn-install-pwa');
        if (installBtn) {
            installBtn.style.display = 'inline-flex'; // Munculkan tombol install
            installBtn.addEventListener('click', async () => {
                installBtn.style.display = 'none';
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                deferredPrompt = null;
            });
        }
    });
});

// --- 2. NOTIFICATIONS (SweetAlert2 & IziToast) ---

// Fungsi Notifikasi Toast (Pojok Layar)
const showToast = (title, message, type = 'success') => {
    let color = type === 'success' ? '#81C784' : (type === 'error' ? '#E53E3E' : '#B8E0D2');
    iziToast.show({
        title: title,
        message: message,
        color: color,
        position: 'topRight',
        theme: 'light',
        timeout: 3000,
        progressBarColor: '#2D3748'
    });
};

// Fungsi Konfirmasi Dialog
const confirmAction = (title, text, confirmBtnText, callback) => {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#81C784',
        cancelButtonColor: '#E53E3E',
        confirmButtonText: confirmBtnText,
        cancelButtonText: 'Batal',
        backdrop: `rgba(255, 255, 255, 0.4)` // Efek glass di backdrop
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
};

// --- 3. UI INTERACTIONS ---

// Toggle Sidebar Admin (Mobile)
const toggleAdminSidebar = () => {
    const sidebar = document.getElementById('admin-sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
};

// Copy MatchaID ke Clipboard (Untuk Register)
const copyMatchaID = (elementId) => {
    const textToCopy = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('Berhasil', 'MatchaID berhasil disalin!', 'success');
    }).catch(err => {
        showToast('Gagal', 'Tidak dapat menyalin teks.', 'error');
    });
};


// --- 4. ENGINE UJIAN (CBT) ---

// Menyimpan jawaban ke localStorage
const saveAnswerLocal = (examId, questionId, answer) => {
    let examData = JSON.parse(localStorage.getItem(`exam_${examId}`)) || {};
    examData[questionId] = answer;
    localStorage.setItem(`exam_${examId}`, JSON.stringify(examData));
    
    // Ubah warna grid nomor soal jadi hijau (answered)
    const navBtn = document.getElementById(`nav-q-${questionId}`);
    if (navBtn) {
        navBtn.classList.add('answered');
    }
};

// Mengambil jawaban dari localStorage (saat halaman dimuat ulang)
const loadAnswersLocal = (examId) => {
    let examData = JSON.parse(localStorage.getItem(`exam_${examId}`)) || {};
    for (const [questionId, answer] of Object.entries(examData)) {
        // Logika untuk mengisi kembali input form (radio/checkbox/text)
        let input = document.querySelector(`input[name="q_${questionId}"][value="${answer}"]`);
        if (input && (input.type === 'radio' || input.type === 'checkbox')) {
            input.checked = true;
        } else {
            let textInput = document.querySelector(`input[name="q_${questionId}"][type="text"]`);
            if (textInput) textInput.value = answer;
        }
        
        // Tandai grid soal
        const navBtn = document.getElementById(`nav-q-${questionId}`);
        if (navBtn) navBtn.classList.add('answered');
    }
};

// Menghapus data ujian setelah submit berhasil
const clearExamLocal = (examId) => {
    localStorage.removeItem(`exam_${examId}`);
};

// Timer Ujian Realtime
const startExamTimer = (durationInSeconds, displayElementId, timeoutCallback) => {
    let timer = durationInSeconds;
    const displayElement = document.getElementById(displayElementId);
    
    if (!displayElement) return;

    const interval = setInterval(() => {
        let hours = parseInt(timer / 3600, 10);
        let minutes = parseInt((timer % 3600) / 60, 10);
        let seconds = parseInt(timer % 60, 10);

        hours = hours < 10 ? "0" + hours : hours;
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        displayElement.textContent = hours > 0 ? `${hours}:${minutes}:${seconds}` : `${minutes}:${seconds}`;

        // Tambah animasi berdetak (pulse) jika sisa waktu < 1 menit
        if (timer < 60) {
            displayElement.classList.add('timer-pulse');
        }

        if (--timer < 0) {
            clearInterval(interval);
            // Waktu habis, panggil fungsi submit otomatis
            timeoutCallback(); 
        }
    }, 1000);
};
