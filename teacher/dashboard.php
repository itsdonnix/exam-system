<?php
require_once 'includes/init.php';
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Guru — ExamSafe</title>
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    /* Page-specific styles only */
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

    /* Draft count sub-label */
    .draft-count-label {
      font-size: 0.7rem;
      color: #f59e0b;
      font-weight: 600;
      display: block;
      margin-top: 2px;
    }
  </style>
</head>

<body>
  <?php include 'includes/header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <div class="page-title">Dashboard Guru</div>
        <div class="page-subtitle">Selamat datang, <?= htmlspecialchars($teacherData['full_name_with_gelar']) ?> — <?= htmlspecialchars($teacherData['subject']) ?></div>
      </div>
      <a href="create-exam.php" class="btn btn-primary">➕ Buat Ujian Baru</a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div>
          <div class="stat-value">-</div>
          <div class="stat-label">Total Ujian <span class="draft-count-label" id="draft-count-label"></span></div>
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
        onclick="window.location.href='create-exam.php'">
        <div class="icon">📝</div>
        <h4>Buat Ujian Baru</h4>
        <p>Tambah soal pilihan ganda & esai</p>
      </div>
      <div
        class="quick-action-card"
        onclick="window.location.href='question-bank.php'">
        <div class="icon">📚</div>
        <h4>Bank Soal</h4>
        <p>Kelola soal untuk digunakan kembali</p>
      </div>
      <div
        class="quick-action-card"
        onclick="window.location.href='results.php'">
        <div class="icon">📊</div>
        <h4>Lihat Hasil Ujian</h4>
        <p>Laporan nilai dan statistik siswa</p>
      </div>
      <div class="quick-action-card" onclick="window.TeacherDashboard.showMonitorForFirstExam()">
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
          <a href="create-exam.php" class="btn btn-primary btn-sm">+ Tambah</a>
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
  </main>

  <!-- Scripts - ORDER MATTERS -->
  <script src="../js/utils.js"></script>
  <script src="../js/api-client.js"></script>
  <script src="../js/toast.js"></script>
  <script src="../js/teacher-api.js"></script>
  <script src="../js/exam-manager.js"></script>
  <script src="../js/teacher-dashboard.js"></script>
  <script src="../js/teacher-layout.js"></script>

  <script>
    // Minimal inline initialization
    let examManager = null;

    document.addEventListener("DOMContentLoaded", async () => {
      // Initialize exam manager
      examManager = new ExamManager({
        containerId: "exam-list",
        searchInputId: "examSearch",
        role: "teacher",
        onExamAction: () => {
          // Refresh dashboard stats when exam actions occur
          if (examManager.allExams) {
            TeacherDashboard.updateStatsFromExams(examManager.allExams);
          }
          TeacherDashboard.loadDashboardData();
        },
      });

      // Make examManager globally available for modal buttons
      window.examManager = examManager;

      // Initialize dashboard controller
      TeacherDashboard.init(examManager);

      await examManager.fetchExams();

      // Update stats with fetched exams
      if (examManager.allExams) {
        TeacherDashboard.updateStatsFromExams(examManager.allExams);

        // Update draft count label
        const draftCount = examManager.allExams.filter(e => e.status === 'draft').length;
        const draftLabel = document.getElementById('draft-count-label');
        if (draftLabel && draftCount > 0) {
          draftLabel.textContent = `(${draftCount} draft)`;
        }
      }
    });
  </script>
</body>

</html>
