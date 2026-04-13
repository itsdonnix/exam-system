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

$full_name = $_SESSION['full_name'] ?? 'Siswa';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Siswa — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <style>
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

        /* Skeleton Loader Styles */
        .skeleton-loader {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
        }

        .skeleton-line {
            height: 12px;
            background: linear-gradient(90deg,
                    #f0f0f0 25%,
                    #e0e0e0 50%,
                    #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 4px;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .exam-skeleton {
            background: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            box-shadow: 0 4px 16px rgba(26, 60, 110, 0.08);
            border-left: 5px solid #e2e8f0;
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
                <p id="student-class">Memuat data...</p>
                <div class="info-row">
                    <span class="info-chip">📅 <span id="current-date">Memuat...</span> •
                        <span id="current-time">--:--</span></span>
                    <span class="info-chip">📋 <span id="available-exams-count">0</span> Ujian Tersedia</span>
                </div>
            </div>
            <div class="welcome-icon">📚</div>
        </div>

        <!-- Join Exam Section -->
        <div
            class="card"
            style="border-left: 5px solid var(--accent); background: #fff9db">
            <div
                style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap">
                <div style="font-size: 3rem">🔑</div>
                <div style="flex: 1">
                    <h3 style="color: #92400e">Masuk Ujian Baru</h3>
                    <p style="color: #b45309; font-size: 0.9rem">
                        Masukkan kode unik dari pengawas untuk memulai ujian Anda.
                    </p>
                    <div id="join-alert" style="margin-top: 10px"></div>
                </div>
                <div style="display: flex; gap: 10px; min-width: 300px">
                    <input
                        type="text"
                        id="exam-code-input"
                        class="form-control"
                        placeholder="Kode Ujian (Contoh: A1B2C3)"
                        style="
              text-transform: uppercase;
              font-weight: 700;
              letter-spacing: 2px;
              height: 48px;
            " />
                    <button
                        class="btn btn-primary"
                        onclick="joinExam()"
                        style="height: 48px; white-space: nowrap">
                        🚀 Masuk Ujian
                    </button>
                </div>
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
                <!-- Skeleton will be shown here -->
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
                        <!-- Skeleton will be shown here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // No more sessionStorage - using PHP session only
        const studentName = <?php echo json_encode($full_name); ?>;

        // Skeleton Loader Functions
        function showExamListSkeletonLoader() {
            const container = document.getElementById("exam-list");
            container.innerHTML = `
        <div class="exam-skeleton"><div class="skeleton-loader"><div class="skeleton-line" style="width: 40%"></div><div class="skeleton-line" style="width: 60%"></div><div class="skeleton-line" style="width: 30%"></div></div></div>
        <div class="exam-skeleton"><div class="skeleton-loader"><div class="skeleton-line" style="width: 50%"></div><div class="skeleton-line" style="width: 70%"></div><div class="skeleton-line" style="width: 45%"></div></div></div>
        <div class="exam-skeleton"><div class="skeleton-loader"><div class="skeleton-line" style="width: 35%"></div><div class="skeleton-line" style="width: 55%"></div><div class="skeleton-line" style="width: 40%"></div></div></div>
      `;
        }

        function showHistoryTableSkeletonLoader() {
            const tbody = document.getElementById("history-tbody");
            tbody.innerHTML = `
        <tr><td colspan="4"><div class="skeleton-loader"><div class="skeleton-line" style="width: 100%"></div></div></td></tr>
        <tr><td colspan="4"><div class="skeleton-loader"><div class="skeleton-line" style="width: 100%"></div></div></td></tr>
        <tr><td colspan="4"><div class="skeleton-loader"><div class="skeleton-line" style="width: 100%"></div></div></td></tr>
      `;
        }

        // Live clock
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

        async function joinExam() {
            const codeInput = document.getElementById("exam-code-input");
            const alertEl = document.getElementById("join-alert");
            const code = codeInput.value.trim().toUpperCase();

            if (!code) {
                alertEl.innerHTML = '<div class="alert alert-danger" style="margin:0; padding:8px 12px; font-size:0.85rem">⚠️ Masukkan kode ujian!</div>';
                return;
            }

            alertEl.innerHTML = '<div class="alert alert-info" style="margin:0; padding:8px 12px; font-size:0.85rem">⌛ Memverifikasi kode...</div>';

            try {
                const response = await fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        action: "join_exam",
                        exam_code: code
                    }),
                    credentials: "include" // Important for session cookies
                });

                const result = await response.json();

                if (result.success) {
                    alertEl.innerHTML = `<div class="alert alert-success" style="margin:0; padding:8px 12px; font-size:0.85rem">✅ ${result.message}</div>`;
                    setTimeout(() => {
                        window.location.href = `exam.html?exam_id=${result.exam_id}`;
                    }, 1000);
                } else {
                    alertEl.innerHTML = `<div class="alert alert-danger" style="margin:0; padding:8px 12px; font-size:0.85rem">❌ ${result.message}</div>`;
                }
            } catch (error) {
                console.error("Join exam error:", error);
                alertEl.innerHTML = '<div class="alert alert-danger" style="margin:0; padding:8px 12px; font-size:0.85rem">❌ Terjadi kesalahan koneksi.</div>';
            }
        }

        // Fetch Exams
        document.addEventListener("DOMContentLoaded", () => {
            showExamListSkeletonLoader();
            showHistoryTableSkeletonLoader();
            fetchExams();
            fetchProfile();
        });

        async function fetchProfile() {
            try {
                const response = await fetch("../php/exam_api.php?action=get_profile", {
                    credentials: "include"
                });
                const data = await response.json();
                if (data.success) {
                    const user = data.user;
                    if (user.class) {
                        document.getElementById("student-class").textContent = `${user.class} — SMA Kristen Mercusuar Kupang`;
                    }
                }
            } catch (e) {
                console.error("Profile fetch error:", e);
            }
        }

        async function fetchExams() {
            try {
                const response = await fetch("../php/exam_api.php?action=get_exams", {
                    credentials: "include",
                });
                const data = await response.json();

                if (data.success) {
                    renderExams(data.exams);
                    updateStats(data.exams);
                    fetchHistory();
                }
            } catch (error) {
                console.error("Error fetching exams:", error);
            }
        }

        async function fetchHistory() {
            try {
                const response = await fetch("../php/exam_api.php?action=get_student_history", {
                    credentials: "include"
                });
                const data = await response.json();

                const tbody = document.getElementById("history-tbody");
                if (data.success) {
                    if (data.history.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px">Belum ada riwayat ujian.</td></tr>';
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
                }
            } catch (error) {
                console.error("History fetch error:", error);
                const tbody = document.getElementById("history-tbody");
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:#64748b">Terjadi kesalahan saat memuat riwayat.</td></tr>';
            }
        }

        function renderExams(exams) {
            const container = document.getElementById("exam-list");
            if (!exams || exams.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b">Tidak ada ujian yang tersedia saat ini.</div>';
                return;
            }

            container.innerHTML = exams.map((exam) => {
                const isStarted = new Date(exam.start_time) <= new Date();
                const isEnded = new Date(exam.end_time) < new Date();
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
                        actionHtml = `<a href="exam.html?exam_id=${exam.id}" class="btn btn-primary">Mulai Ujian →</a>`;
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
