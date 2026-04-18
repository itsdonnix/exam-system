<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once '../includes/auth.php';
require_once '../includes/csrf.php';

// Check if logged in as student
requireLogin('siswa');

// Check session timeout (1 hour)
if (isSessionExpired(3600)) {
    clearSession();
    $_SESSION['error'] = 'Sesi Anda telah berakhir. Silakan login kembali.';
    header('Location: ../index.php');
    exit;
}

// Refresh login time
$_SESSION['login_time'] = time();

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();

$full_name = $_SESSION['full_name'] ?? 'Siswa';
$student_class = $_SESSION['class'] ?? 'Kelas tidak tersedia';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Siswa — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        /* ========== KODE UJIAN CARD STYLES (IMPROVED) ========== */
        .join-exam-card {
            border-left: 5px solid var(--accent);
            background: #fff9db;
        }

        .join-exam-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .join-exam-header {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .join-exam-icon {
            font-size: 2.5rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .join-exam-text h3 {
            color: #92400e;
            margin: 0 0 6px 0;
            font-size: 1.1rem;
        }

        .join-exam-text p {
            color: #b45309;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.4;
        }

        .join-exam-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 0;
        }

        .join-exam-input {
            width: 100%;
            padding: 12px 14px;
            font-size: 1rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 2px;
            border: 2px solid #fcd34d;
            border-radius: 8px;
            background: white;
            transition: all 0.2s;
        }

        .join-exam-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .join-exam-input::placeholder {
            color: #a0a0a0;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .join-exam-button {
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            white-space: nowrap;
            border: none;
            transition: all 0.2s;
        }

        .join-exam-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .join-exam-button:active {
            transform: translateY(0);
        }

        .join-exam-alert {
            min-height: 32px;
            margin-top: 4px;
        }

        .join-exam-alert:empty {
            display: none;
        }

        /* ========== RESPONSIVE BEHAVIOR ========== */
        @media (min-width: 768px) {
            .join-exam-container {
                flex-direction: row;
                gap: 32px;
                align-items: center;
                justify-content: space-between;
            }

            .join-exam-header {
                flex: 1;
                min-width: 280px;
            }

            .join-exam-form {
                flex: 1.2;
                min-width: 320px;
                flex-direction: row;
                gap: 10px;
                align-items: center;
            }

            .join-exam-input {
                flex: 1;
            }

            .join-exam-button {
                flex-shrink: 0;
                height: 44px;
                padding: 0 28px;
                display: flex;
                align-items: center;
            }

            .join-exam-alert {
                top: 100%;
                left: 0;
                right: 0;
                margin-top: 0;
            }
        }

        /* ========== EXISTING EXAM LIST STYLES ========== */
        .exam-list {
            display: grid;
            gap: 18px;
        }

        .exam-item {
            background: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            box-shadow: 0 4px 16px rgba(26, 60, 110, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid var(--primary-light);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .exam-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 60, 110, 0.14);
        }

        .exam-item.locked {
            border-left-color: #94a3b8;
            opacity: 0.7;
        }

        .exam-item.done {
            border-left-color: var(--success);
        }

        .exam-meta {
            display: flex;
            gap: 16px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .exam-meta span {
            font-size: 0.82rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .exam-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        .countdown-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .score-display {
            text-align: center;
        }

        .score-display .score {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
        }

        .score-display .score-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #1a3c6e, #2563eb);
            color: #fff;
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .welcome-banner h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .welcome-banner p {
            opacity: 0.85;
            margin-top: 4px;
        }

        .welcome-icon {
            font-size: 4rem;
        }

        .info-row {
            display: flex;
            gap: 12px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .info-chip {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.82rem;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-brand">🎓 ExamSafe</div>
        <div class="navbar-nav">
            <a href="../php/logout.php" class="logout-btn">Keluar</a>
        </div>
    </nav>

    <div style="max-width: 900px; margin: 0 auto; padding: 32px 20px">
        <div class="welcome-banner" id="welcome-banner">
            <div>
                <h2>Selamat Datang, <?php echo htmlspecialchars($full_name); ?>! 👋</h2>
                <p><?php echo htmlspecialchars($student_class); ?> — SMA Kristen Mercusuar Kupang</p>
                <div class="info-row">
                    <span class="info-chip">📅 <span id="current-date">Memuat...</span> •
                        <span id="current-time">--:--</span></span>
                    <span class="info-chip">📋 <span id="available-exams-count">0</span> Ujian Tersedia</span>
                </div>
            </div>
            <div class="welcome-icon">📚</div>
        </div>

        <!-- Join Exam Section -->
        <div class="card join-exam-card">
            <div id="join-alert" class="join-exam-alert"></div>
            <div class="join-exam-container">
                <div class="join-exam-header w-100">
                    <div class="join-exam-icon">🔑</div>
                    <div class="join-exam-text">
                        <h3>Masuk Ujian Baru</h3>
                        <p>Masukkan kode unik dari pengawas untuk memulai ujian Anda.</p>
                    </div>
                </div>
                <form id="join-exam-form" class="join-exam-form">
                    <input
                        type="text"
                        id="exam-code-input"
                        class="form-control join-exam-input"
                        placeholder="Kode Ujian (Contoh: A1B2C3)"
                        autocomplete="off"
                        required />
                    <button
                        type="submit"
                        class="btn btn-primary join-exam-button">
                        🚀 Masuk Ujian
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="page-header">
                <div>
                    <div class="page-title" style="font-size: 1.2rem">
                        📋 Daftar Ujian
                    </div>
                    <div class="page-subtitle">
                        Ujian yang tersedia untuk Anda hari ini
                    </div>
                </div>
            </div>

            <div class="exam-list" id="exam-list">
                <div style="text-align:center;padding:20px;color:#64748b">
                    Memuat daftar ujian...
                </div>
            </div>
        </div>

        <div class="card">
            <div class="page-title" style="font-size: 1.1rem; margin-bottom: 16px">
                📊 Riwayat Nilai
            </div>
            <div class="table-wrapper">
                <table id="history-table">
                    <thead>
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Tanggal</th>
                            <th>Durasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <tr>
                            <td colspan="4" style="text-align:center;padding:20px;color:#64748b">
                                Memuat riwayat...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../js/api-client.js"></script>
    <script src="../js/student-api.js"></script>

    <script>
        const studentName = <?php echo json_encode($full_name); ?>;
        const csrfToken = <?php echo json_encode($csrf_token); ?>;

        function updateTime() {
            const now = new Date();
            const timeEl = document.getElementById("current-time");
            const dateEl = document.getElementById("current-date");

            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                });
            }

            if (dateEl) {
                const options = {
                    weekday: "long",
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                };
                dateEl.textContent = now.toLocaleDateString("id-ID", options);
            }
        }
        updateTime();
        setInterval(updateTime, 1000);

        function focusJoinCode() {
            const input = document.getElementById("exam-code-input");
            input.focus();
            input.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
            input.style.boxShadow = "0 0 0 4px rgba(245, 158, 11, 0.2)";
            setTimeout(() => {
                input.style.boxShadow = "";
            }, 2000);
        }

        const codeInput = document.getElementById("exam-code-input");
        if (codeInput) {
            codeInput.addEventListener("input", function(e) {
                this.value = this.value.toUpperCase();
            });
        }

        const joinForm = document.getElementById("join-exam-form");
        if (joinForm) {
            joinForm.addEventListener("submit", async function(e) {
                e.preventDefault();

                const codeInput = document.getElementById("exam-code-input");
                const alertEl = document.getElementById("join-alert");
                const rawCode = codeInput.value.trim();

                if (!rawCode) {
                    alertEl.innerHTML = '<div class="alert alert-danger">⚠️ Masukkan kode ujian!</div>';
                    return;
                }

                const code = rawCode.toUpperCase();

                if (code.length !== 8 || !/^[A-Z0-9]{8}$/.test(code)) {
                    alertEl.innerHTML = '<div class="alert alert-danger">⚠️ Format kode tidak valid!</div>';
                    return;
                }

                alertEl.innerHTML = '<div class="alert alert-info">⌛ Memverifikasi kode...</div>';

                try {
                    const result = await StudentAPI.joinExam(code);

                    if (result.success) {
                        alertEl.innerHTML = `<div class="alert alert-success">✅ ${result.message}</div>`;

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'exam.php';
                        form.innerHTML = `
                            <input type="hidden" name="exam_id" value="${result.exam_id}">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        alertEl.innerHTML = `<div class="alert alert-danger">❌ ${result.message}</div>`;
                    }
                } catch (error) {
                    console.error("Join exam error:", error);
                    alertEl.innerHTML = '<div class="alert alert-danger">❌ Terjadi kesalahan koneksi.</div>';
                }
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
            fetchExams();
            fetchHistory();
        });

        async function fetchExams() {
            const container = document.getElementById("exam-list");

            try {
                const data = await StudentAPI.getExams();

                if (data.success) {
                    renderExams(data.exams);
                    updateStats(data.exams);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error("Error fetching exams:", error);
                container.innerHTML = `
                    <div style="text-align:center;padding:40px;color:#64748b">
                        Terjadi kesalahan saat memuat daftar ujian.
                    </div>`;
            }
        }

        async function fetchHistory() {
            const tbody = document.getElementById("history-tbody");

            try {
                const data = await StudentAPI.getHistory();

                if (data.success) {
                    if (data.history.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="4" style="text-align:center;padding:20px">
                                    Belum ada riwayat ujian.
                                </td>
                            </tr>`;
                        return;
                    }

                    tbody.innerHTML = data.history.map((h) => {
                        const status = h.status === "graded" ? "Selesai" : "Sedang Diproses";
                        const badge = h.status === "graded" ? "success" : "info";
                        return `
                            <tr>
                                <td><b>${h.subject}</b> — ${h.name}</td>
                                <td>${new Date(h.submitted_at).toLocaleDateString("id-ID")}</td>
                                <td>${Math.round(h.time_taken_seconds / 60)} menit</td>
                                <td><span class="badge badge-${badge}">${status}</span></td>
                            </tr>
                        `;
                    }).join("");
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error("History fetch error:", error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align:center;padding:40px;color:#64748b">
                            Terjadi kesalahan saat memuat riwayat.
                        </td>
                    </tr>`;
            }
        }

        function renderExams(exams) {
            const container = document.getElementById("exam-list");
            if (!exams || exams.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b">Tidak ada ujian yang tersedia saat ini.</div>';
                return;
            }

            container.innerHTML = exams.map((exam) => {
                const isSubmitted = exam.is_submitted || false;
                const isForced = exam.is_forced || false;

                let statusHtml = "";
                let actionHtml = "";
                let itemClass = "exam-item";

                if (isSubmitted && isForced) {
                    itemClass += " done";
                    statusHtml = `<span style="color:var(--danger); font-weight:700">🚫 Akses Terputus (Pelanggaran)</span>`;
                    actionHtml = `<button class="btn btn-outline" disabled style="opacity:0.9; cursor:not-allowed; background:#fee2e2; color:#991b1b; border-color:#fecaca; font-size:0.85rem">Terkena Pelanggaran</button>`;
                } else if (isSubmitted && !isForced) {
                    itemClass += " done";
                    statusHtml = `<span>✅ Anda sudah mengerjakan ujian ini</span>`;
                    actionHtml = `<button class="btn btn-outline" disabled style="opacity:0.7; cursor:not-allowed; background:#f0fdf4; color:#166534; border-color:#bbf7d0">✅ Selesai</button>`;
                } else {
                    statusHtml = `<span class="countdown-badge">⏳ Tersedia</span>`;
                    if (exam.is_authorized) {
                        actionHtml = `
                            <form method="POST" action="exam.php" style="display:inline" onsubmit="return confirm('Mulai ujian ${exam.name.replace(/'/g, "\\'")}?')">
                                <input type="hidden" name="exam_id" value="${exam.id}">
                                <input type="hidden" name="csrf_token" value="${csrfToken}">
                                <button type="submit" class="btn btn-primary">Mulai Ujian →</button>
                            </form>
                        `;
                    } else {
                        actionHtml = `<button class="btn btn-outline" onclick="focusJoinCode()" style="border-color:var(--accent); color:var(--accent)">🔑 Butuh Kode</button>`;
                    }
                }

                return `
                    <div class="${itemClass}">
                        <div>
                            <div class="exam-name">${exam.name}</div>
                            <div class="exam-meta">
                                <span>📚 ${exam.subject}</span>
                                <span>⏱️ ${exam.duration_minutes} menit</span>
                                <span>📝 ${exam.question_count} soal</span>
                            </div>
                            <div class="exam-meta">${statusHtml}</div>
                        </div>
                        <div style="text-align:right">${actionHtml}</div>
                    </div>
                `;
            }).join("");
        }

        function updateStats(exams) {
            const active = exams.length;
            document.getElementById("available-exams-count").textContent = active;
        }
    </script>
</body>

</html>
