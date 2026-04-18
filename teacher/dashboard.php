<?php
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include authentication helpers
require_once '../includes/auth.php';

// Require login with guru role, redirects to ../index.php if fails
requireLogin('guru');

// Refresh session timer to prevent timeout while viewing dashboard
$_SESSION['login_time'] = time();

// Fetch teacher data from database (eliminates get_profile API call)
require_once '../php/db.php';
$db = getDB();

try {
  $stmt = $db->prepare("SELECT full_name, gelar, subject FROM teachers WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $teacher = $stmt->fetch();

  if (!$teacher) {
    // Fallback to session data if database returns nothing
    error_log("[Dashboard] Teacher not found in DB for user_id: " . $_SESSION['user_id'] . ", using session fallback");
    $teacher = [
      'full_name' => $_SESSION['full_name'] ?? 'Guru',
      'gelar' => '',
      'subject' => 'Guru'
    ];
  }
} catch (Exception $e) {
  // Log error but never break the page
  error_log("[Dashboard] Failed to fetch teacher data: " . $e->getMessage() . " (user_id: " . $_SESSION['user_id'] . ")");
  $teacher = [
    'full_name' => $_SESSION['full_name'] ?? 'Guru',
    'gelar' => '',
    'subject' => 'Guru'
  ];
}

// Build display name with gelar (e.g., "Dr. Ahmad, M.Pd")
$fullNameWithGelar = trim($teacher['full_name'] . ($teacher['gelar'] ? ', ' . $teacher['gelar'] : ''));
// Get first character for avatar (UTF-8 safe)
$firstChar = !empty($teacher['full_name']) ? mb_strtoupper(mb_substr($teacher['full_name'], 0, 1, 'UTF-8'), 'UTF-8') : 'G';
// Subject fallback to 'Guru' if not set
$teacherSubject = !empty($teacher['subject']) ? $teacher['subject'] : 'Guru';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Guru — ExamSafe</title>
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    .exam-card {
      background: #fff;
      border-radius: 14px;
      padding: 20px 24px;
      box-shadow: 0 4px 16px rgba(26, 60, 110, 0.08);
      border-left: 4px solid var(--primary-light);
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
      transition: transform 0.2s;
      flex-wrap: wrap;
      gap: 16px;
    }

    .exam-card:hover {
      transform: translateY(-2px);
    }

    .exam-card-info {
      flex: 1;
      min-width: 200px;
    }

    .exam-card-info h4 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--primary);
    }

    .exam-card-meta {
      display: flex;
      gap: 14px;
      margin-top: 6px;
      flex-wrap: wrap;
    }

    .exam-card-meta span {
      font-size: 0.8rem;
      color: #64748b;
    }

    .exam-card-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }

    .quick-action-card {
      background: #fff;
      border-radius: 14px;
      padding: 24px;
      text-align: center;
      box-shadow: 0 4px 16px rgba(26, 60, 110, 0.08);
      cursor: pointer;
      transition: all 0.2s;
      border: 2px solid transparent;
    }

    .quick-action-card:hover {
      border-color: var(--primary-light);
      transform: translateY(-3px);
    }

    .quick-action-card .icon {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .quick-action-card h4 {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--primary);
    }

    .quick-action-card p {
      font-size: 0.8rem;
      color: #64748b;
      margin-top: 4px;
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

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: #fff;
      border-radius: 16px;
      max-width: 900px;
      width: 90%;
      max-height: 85vh;
      overflow-y: auto;
      padding: 24px;
      position: relative;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 1px solid #e2e8f0;
    }

    .modal-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #64748b;
      transition: color 0.2s;
    }

    .modal-close:hover {
      color: var(--danger);
    }
  </style>
</head>

<body>
  <nav class="navbar">
    <div style="display: flex; align-items: center; gap: 12px">
      <button
        class="hamburger-btn"
        id="hamburgerBtn"
        aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
      <div class="navbar-brand">
        🎓 Exam<span>Safe</span>
        <span
          style="
              font-size: 0.75rem;
              background: rgba(255, 255, 255, 0.2);
              padding: 2px 8px;
              border-radius: 50px;
              margin-left: 8px;
            ">GURU</span>
      </div>
    </div>
    <div class="navbar-nav">
      <div class="nav-user">
        <div class="nav-avatar"><?= htmlspecialchars($firstChar) ?></div>
        <span><?= htmlspecialchars($fullNameWithGelar) ?></span>
      </div>
      <a
        href="../php/logout.php"
        class="btn btn-sm btn-outline"
        style="color: #fff; border-color: rgba(255, 255, 255, 0.4)">Keluar</a>
    </div>
  </nav>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-avatar-section">
        <div
          class="sidebar-avatar"
          id="sidebarAvatar"
          style="background: var(--accent)">
          <?= htmlspecialchars($firstChar) ?>
        </div>
        <div class="sidebar-avatar-name" id="sidebarAvatarName">
          <?= htmlspecialchars($fullNameWithGelar) ?>
        </div>
        <div class="sidebar-avatar-role" id="sidebarAvatarRole">Guru</div>
      </div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
        </li>
        <li>
          <a href="create-exam.html"><span class="icon">➕</span> Buat Ujian Baru</a>
        </li>
        <li>
          <a href="question-bank.html"><span class="icon">📚</span> Bank Soal</a>
        </li>
        <li>
          <a href="results.html"><span class="icon">📊</span> Hasil Ujian</a>
        </li>
        <li>
          <a href="students.html"><span class="icon">👥</span> Data Siswa</a>
        </li>
        <li>
          <a href="settings.html"><span class="icon">⚙️</span> Pengaturan</a>
        </li>
      </ul>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div>
          <div class="page-title">Dashboard Guru</div>
          <div class="page-subtitle">Selamat datang, <?= htmlspecialchars($fullNameWithGelar) ?> — <?= htmlspecialchars($teacherSubject) ?></div>
        </div>
        <a href="create-exam.html" class="btn btn-primary">➕ Buat Ujian Baru</a>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📝</div>
          <div>
            <div class="stat-value">-</div>
            <div class="stat-label">Total Ujian</div>
          </div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success)">
          <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1)">
            ✅
          </div>
          <div>
            <div class="stat-value" style="color: var(--success)">-</div>
            <div class="stat-label">Ujian Aktif</div>
          </div>
        </div>
        <div class="stat-card" style="border-left-color: var(--accent)">
          <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1)">
            👨‍🎓
          </div>
          <div>
            <div class="stat-value" style="color: var(--accent)">-</div>
            <div class="stat-label">Total Siswa</div>
          </div>
        </div>
        <div class="stat-card" style="border-left-color: var(--secondary)">
          <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1)">
            ⭐
          </div>
          <div>
            <div class="stat-value" style="color: var(--secondary)">-</div>
            <div class="stat-label">Rata-rata Nilai</div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <div
          class="quick-action-card"
          onclick="window.location.href='create-exam.html'">
          <div class="icon">📝</div>
          <h4>Buat Ujian Baru</h4>
          <p>Tambah soal pilihan ganda & esai</p>
        </div>
        <div
          class="quick-action-card"
          onclick="window.location.href='question-bank.html'">
          <div class="icon">📚</div>
          <h4>Bank Soal</h4>
          <p>Kelola soal untuk digunakan kembali</p>
        </div>
        <div
          class="quick-action-card"
          onclick="window.location.href='results.html'">
          <div class="icon">📊</div>
          <h4>Lihat Hasil Ujian</h4>
          <p>Laporan nilai dan statistik siswa</p>
        </div>
        <div class="quick-action-card" onclick="showMonitorForFirstExam()">
          <div class="icon">👁️</div>
          <h4>Monitor Ujian</h4>
          <p>Pantau aktivitas siswa real-time</p>
        </div>
        <div class="quick-action-card">
          <div class="icon">📤</div>
          <h4>Export Nilai</h4>
          <p>Download laporan dalam format Excel</p>
        </div>
      </div>

      <!-- Exam List -->
      <div class="card">
        <div
          class="page-header"
          style="margin-bottom: 20px; flex-wrap: wrap; gap: 12px">
          <div style="flex: 1">
            <div class="page-title" style="font-size: 1.1rem">
              📋 Daftar Ujian Saya
            </div>
          </div>
          <div
            style="
                display: flex;
                gap: 10px;
                flex: 2;
                justify-content: flex-end;
                flex-wrap: wrap;
              ">
            <input
              type="text"
              id="examSearch"
              class="form-control"
              placeholder="Cari nama ujian atau kelas..."
              style="max-width: 300px; padding: 8px 14px" />
            <a href="create-exam.html" class="btn btn-primary btn-sm">+ Tambah</a>
          </div>
        </div>

        <div id="exam-list">
          <!-- Skeleton will be shown here -->
        </div>
      </div>

      <!-- Recent Violations -->
      <div class="card mt-3">
        <div
          class="page-title"
          style="font-size: 1.1rem; margin-bottom: 16px">
          ⚠️ Pelanggaran Terbaru
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Siswa</th>
                <th>Ujian</th>
                <th>Pelanggaran</th>
                <th>Waktu</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="violations-tbody">
              <!-- Skeleton will be shown here -->
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Monitor Modal -->
  <div class="modal-overlay" id="monitor-modal">
    <div class="modal" style="max-width: 100%">
      <div class="modal-header">
        <div class="modal-title">👁️ Monitor Ujian</div>
        <button
          class="modal-close"
          onclick="if(window.examManager) window.examManager.closeMonitor()">
          ✕
        </button>
      </div>
      <div
        class="stats-grid"
        style="grid-template-columns: repeat(4, 1fr); margin-bottom: 16px">
        <div class="stat-card" style="padding: 14px">
          <div>
            <div
              class="stat-value"
              style="font-size: 1.4rem"
              id="monitor-total">
              0
            </div>
            <div class="stat-label">Total</div>
          </div>
        </div>
        <div
          class="stat-card"
          style="padding: 14px; border-left-color: var(--success)">
          <div>
            <div
              class="stat-value"
              style="font-size: 1.4rem; color: var(--success)"
              id="monitor-active">
              0
            </div>
            <div class="stat-label">Aktif</div>
          </div>
        </div>
        <div
          class="stat-card"
          style="padding: 14px; border-left-color: var(--warning)">
          <div>
            <div
              class="stat-value"
              style="font-size: 1.4rem; color: var(--warning)"
              id="monitor-finished">
              0
            </div>
            <div class="stat-label">Selesai</div>
          </div>
        </div>
        <div
          class="stat-card"
          style="padding: 14px; border-left-color: var(--danger)">
          <div>
            <div
              class="stat-value"
              style="font-size: 1.4rem; color: var(--danger)"
              id="monitor-violations">
              0
            </div>
            <div class="stat-label">Pelanggaran</div>
          </div>
        </div>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Siswa</th>
              <th style="text-align: center">Skor</th>
              <th style="text-align: center">Waktu Submit</th>
              <th style="text-align: center">Status</th>
              <th style="text-align: center">Pelanggaran</th>
              <th style="text-align: center">Aksi</th>
            </tr>
          </thead>
          <tbody id="monitor-tbody">
            <tr>
              <td colspan="4" style="text-align: center">Memuat...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="../js/exam-manager.js"></script>
  <script>
    // Sidebar toggle functionality
    const hamburgerBtn = document.getElementById("hamburgerBtn");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    if (hamburgerBtn) {
      hamburgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("sidebar-open");
        sidebarOverlay.classList.toggle("sidebar-overlay-visible");
      });
    }

    if (sidebarOverlay) {
      sidebarOverlay.addEventListener("click", () => {
        sidebar.classList.remove("sidebar-open");
        sidebarOverlay.classList.remove("sidebar-overlay-visible");
      });
    }

    // Initialize ExamManager for teacher
    let examManager = null;

    document.addEventListener("DOMContentLoaded", () => {
      // Initialize shared exam manager
      examManager = new ExamManager({
        containerId: "exam-list",
        searchInputId: "examSearch",
        role: "teacher",
        onExamAction: () => {
          // Refresh teacher stats when exam actions occur
          if (examManager.allExams) {
            updateStats(examManager.allExams);
          }
        },
      });

      // Make examManager globally available for modal buttons
      window.examManager = examManager;

      // Load all dashboard data (excluding profile - now server-side)
      fetchDashboardData();
    });

    async function fetchDashboardData() {
      try {
        // Fetch exams using shared manager
        await examManager.fetchExams();

        // Update stats with fetched exams
        if (examManager.allExams) {
          updateStats(examManager.allExams);
        }

        // Fetch Teacher Stats (Total Siswa & Rata-rata Nilai)
        const statsRes = await fetch(
          "../php/exam_api.php?action=get_teacher_stats", {
            credentials: "include",
          }
        );
        const statsData = await statsRes.json();
        if (statsData.success) {
          const statValues = document.querySelectorAll(".stat-value");
          if (statValues[2])
            statValues[2].textContent = statsData.total_students || 0;
          if (statValues[3])
            statValues[3].textContent = statsData.average_score || 0;
        }

        // Fetch violations
        await fetchViolations();
      } catch (error) {
        console.error("Error fetching dashboard data:", error);
      }
    }

    async function fetchViolations() {
      try {
        const response = await fetch(
          "../php/exam_api.php?action=get_recent_violations", {
            credentials: "include",
          }
        );
        const data = await response.json();

        const tbody = document.getElementById("violations-tbody");
        if (!tbody) return;

        if (data.success) {
          if (data.violations.length === 0) {
            tbody.innerHTML =
              '<tr><td colspan="5" style="text-align:center;padding:20px">Tidak ada pelanggaran terdeteksi.</td></tr>';
            return;
          }

          tbody.innerHTML = data.violations
            .map(
              (v) => `
              <tr>
                <td><b>${escapeHtml(v.student_name)}</b></td>
                <td>${escapeHtml(v.exam_name)}</td>
                <td>${escapeHtml(v.reason)}</td>
                <td>${new Date(v.created_at).toLocaleTimeString("id-ID")}</td>
                <td><span class="badge badge-${
                  v.violation_count >= 3 ? "danger" : "warning"
                }">${
                  v.violation_count >= 3 ? "Dihentikan" : "Peringatan"
                }</span></td>
              </tr>
            `
            )
            .join("");
        } else {
          tbody.innerHTML =
            '<tr><td colspan="5" style="text-align:center;padding:20px;color:#64748b">Gagal memuat data pelanggaran.</td></tr>';
        }
      } catch (error) {
        console.error("Error fetching violations:", error);
        const tbody = document.getElementById("violations-tbody");
        if (tbody) {
          tbody.innerHTML =
            '<tr><td colspan="5" style="text-align:center;padding:40px;color:#64748b">Terjadi kesalahan saat memuat data pelanggaran.</td></tr>';
        }
      }
    }

    // Helper function to prevent XSS
    function escapeHtml(str) {
      if (!str) return '';
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function updateStats(exams) {
      const total = exams.length;
      const active = exams.filter((e) => e.status === "active").length;

      const statValues = document.querySelectorAll(".stat-value");
      if (statValues[0]) statValues[0].textContent = total;
      if (statValues[1]) statValues[1].textContent = active;
    }

    function showMonitorForFirstExam() {
      if (examManager.allExams && examManager.allExams.length > 0) {
        const activeExam = examManager.allExams.find(
          (e) => e.status === "active"
        );
        if (activeExam) {
          examManager.showMonitor(activeExam.id, activeExam.name);
        } else if (examManager.allExams[0]) {
          examManager.showMonitor(
            examManager.allExams[0].id,
            examManager.allExams[0].name
          );
        } else {
          alert("Belum ada ujian yang tersedia.");
        }
      } else {
        alert("Belum ada ujian yang tersedia.");
      }
    }
  </script>
</body>

</html>
