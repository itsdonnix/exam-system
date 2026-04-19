<?php
require_once 'includes/init.php';
$activePage = 'results';

// Fetch teacher's exams for selector dropdown
$exams = [];
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, subject, class, status 
        FROM exams 
        WHERE teacher_id = ? 
        ORDER BY class ASC, created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $exams = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("[results.php] Database error: " . $e->getMessage());
}

// Group exams by class
$groupedExams = [];
foreach ($exams as $exam) {
    $className = $exam['class'];
    if (!isset($groupedExams[$className])) {
        $groupedExams[$className] = [];
    }
    $groupedExams[$className][] = $exam;
}

// Generate CSRF token for grading modal
require_once '../includes/csrf.php';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hasil Ujian — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .score-bar {
            height: 0.5rem;
            background: #e2e8f0;
            border-radius: 50px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 50px;
            background: linear-gradient(90deg, #2563eb, #0ea5e9);
        }

        .score-cell {
            font-weight: 700;
        }

        .score-cell.high {
            color: var(--success);
        }

        .score-cell.mid {
            color: var(--warning);
        }

        .score-cell.low {
            color: var(--danger);
        }

        .violation-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.25rem 0.625rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 2.5rem;
        }

        .violation-badge.low {
            background: #fef3c7;
            color: #92400e;
        }

        .violation-badge.medium {
            background: #fed7aa;
            color: #9a3412;
        }

        .violation-badge.high {
            background: #fee2e2;
            color: #991b1b;
        }

        .violation-badge.zero {
            background: #f1f5f9;
            color: #64748b;
            cursor: default;
        }

        .chart-bar-wrap {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            height: 7.5rem;
            padding: 0 0.5rem;
        }

        .chart-bar {
            flex: 1;
            background: linear-gradient(180deg, #2563eb, #0ea5e9);
            border-radius: 6px 6px 0 0;
            min-width: 1.875rem;
            position: relative;
            transition: opacity 0.2s;
            cursor: pointer;
        }

        .chart-bar:hover {
            opacity: 0.8;
        }

        .chart-bar-label {
            text-align: center;
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .chart-bar-val {
            position: absolute;
            top: -1.25rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--primary);
            white-space: nowrap;
        }

        .filter-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .filter-bar select,
        .filter-bar input {
            padding: 0.5rem 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: "Poppins", sans-serif;
            font-size: 0.88rem;
            outline: none;
        }

        .filter-bar select:focus,
        .filter-bar input:focus {
            border-color: var(--primary-light);
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .exam-info-bar {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .exam-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .exam-info-item .label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .exam-info-item .value {
            font-weight: 700;
            color: var(--primary);
        }

        .exam-info-item .icon {
            font-size: 1.2rem;
        }

        .skeleton-loader {
            display: inline-block;
            width: 100%;
            height: 20px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .exam-selector-bar {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .exam-selector-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .exam-selector-item .label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .exam-selector-item select {
            padding: 0.375rem 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: "Poppins", sans-serif;
            font-size: 0.88rem;
            outline: none;
            background: white;
            cursor: pointer;
            min-width: 17.5rem;
        }

        .exam-selector-item select:focus {
            border-color: var(--primary-light);
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .chart-container {
            height: 15.625rem;
        }

        .filter-section {
            display: flex;
            gap: 0.9375rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #results-table tbody tr {
            transition: background 0.15s ease;
        }

        #results-table tbody tr:hover {
            background: #f8fafc;
        }

        .stat-card {
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .violation-badge:not(.zero) {
            position: relative;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-wrap: wrap;
                gap: 0.75rem;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-end;
                flex-wrap: wrap;
            }

            .exam-info-bar {
                gap: 0.75rem;
                padding: 0.75rem 1rem;
            }

            .exam-info-item {
                flex: 1 1 45%;
                min-width: 11.25rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr !important;
            }

            .analytics-grid {
                grid-template-columns: 1fr !important;
            }

            .chart-container {
                height: 12.5rem;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }

            .filter-section>div {
                min-width: 100% !important;
                width: 100% !important;
            }

            .filter-section .filter-checkbox {
                width: 100%;
                align-items: flex-start;
            }

            .filter-section .filter-checkbox label {
                font-size: 0.82rem;
                line-height: 1.4;
            }
        }

        @media (max-width: 480px) {
            .exam-selector-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 0.75rem 0.875rem;
            }

            .exam-selector-item {
                flex-wrap: wrap;
                gap: 6px;
            }

            .exam-selector-item select {
                min-width: 0;
                width: 100%;
            }

            .exam-info-item {
                flex: 1 1 100%;
                font-size: 0.85rem;
            }

            .exam-info-item .icon {
                font-size: 1rem;
            }

            .stats-grid {
                gap: 0.625rem !important;
            }

            .stat-card {
                padding: 0.75rem !important;
            }

            .stat-label {
                font-size: 0.75rem !important;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.375rem 0.625rem;
            }

            .modal {
                width: calc(100% - 1.5rem) !important;
                max-width: none !important;
                margin: 0.75rem !important;
                border-radius: 12px !important;
            }

            .modal-header {
                padding: 0.875rem 1rem !important;
            }
        }

        @media print {

            .exam-selector-bar,
            .header-actions,
            .filter-section {
                display: none !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            .stats-grid,
            .analytics-grid {
                break-inside: avoid;
            }

            #results-table th:last-child,
            #results-table td:last-child {
                display: none;
            }

            .stat-card {
                transform: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Exam Selector Bar (slim version) -->
        <div class="exam-selector-bar">
            <div class="exam-selector-item">
                <span class="icon">📋</span>
                <span class="label">Pilih Ujian:</span>
                <select id="exam-selector">
                    <option value="">-- Pilih Ujian --</option>
                    <?php foreach ($groupedExams as $className => $classExams): ?>
                        <optgroup label="<?php echo htmlspecialchars($className); ?>">
                            <?php foreach ($classExams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>"
                                    <?php echo ($examId === (int)$exam['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['name']); ?>
                                    <?php echo $exam['status'] === 'active' ? '🟢' : '🔴'; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <div class="page-title" id="exam-title">
                    <span class="skeleton-loader" style="width: 18.75rem"></span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="exportToExcel()">Excel (.csv) 📊</button>
                <button class="btn btn-outline btn-sm" onclick="window.print()">🖨️ Cetak</button>
            </div>
        </div>

        <!-- Exam Info Bar -->
        <div class="exam-info-bar" id="examInfoBar" style="display: none">
            <div class="exam-info-item">
                <span class="icon">📚</span>
                <span class="label">Mata Pelajaran:</span>
                <span class="value" id="exam-subject">-</span>
            </div>
            <div class="exam-info-item">
                <span class="icon">👥</span>
                <span class="label">Kelas:</span>
                <span class="value" id="exam-class">-</span>
            </div>
            <div class="exam-info-item">
                <span class="icon">⏱️</span>
                <span class="label">Durasi:</span>
                <span class="value" id="exam-duration">-</span>
            </div>
            <div class="exam-info-item">
                <span class="icon">🔑</span>
                <span class="label">Kode Ujian:</span>
                <span class="value" id="exam-code">-</span>
            </div>
            <div class="exam-info-item">
                <span class="icon">📅</span>
                <span class="label">Status:</span>
                <span class="value" id="exam-status">-</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div>
                    <div class="stat-value">-</div>
                    <div class="stat-label">Total Peserta</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success)">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1)">✅</div>
                <div>
                    <div class="stat-value" style="color: var(--success)">-</div>
                    <div class="stat-label">Lulus (≥75)</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--danger)">
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1)">❌</div>
                <div>
                    <div class="stat-value" style="color: var(--danger)">-</div>
                    <div class="stat-label">Tidak Lulus</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent)">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1)">⭐</div>
                <div>
                    <div class="stat-value" style="color: var(--accent)">-</div>
                    <div class="stat-label">Rata-rata Nilai</div>
                </div>
            </div>
        </div>

        <!-- Analytics Panel -->
        <div class="analytics-grid">
            <div class="card">
                <div class="page-title" style="font-size: 1rem; margin-bottom: 16px">📊 Distribusi Nilai Siswa</div>
                <div class="chart-container">
                    <canvas id="scoreChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="page-title" style="font-size: 1rem; margin-bottom: 16px">🔥 Soal Paling Sulit</div>
                <div id="difficult-questions" style="font-size: 0.85rem">
                    <p class="text-center" style="color: #64748b; padding-top: 40px">Menghitung analisis soal...</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card" style="margin-bottom: 20px">
            <div class="filter-section">
                <div style="flex: 1; min-width: 15.625rem">
                    <label style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.3125rem; display: block">Cari Siswa</label>
                    <input type="text" id="search-student" class="form-control" placeholder="Ketik nama atau NISN..." oninput="filterResults()" />
                </div>
                <div style="width: 11.25rem">
                    <label style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.3125rem; display: block">Filter Kelas</label>
                    <select class="form-control" id="class-filter" onchange="filterResults()">
                        <option value="">Semua Kelas</option>
                    </select>
                </div>
                <div class="filter-checkbox">
                    <input type="checkbox" id="filter-violations-only" onchange="filterResults()" />
                    <label style="margin: 0; cursor: pointer">⚠️ Tampilkan hanya siswa dengan pelanggaran</label>
                </div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="card">
            <div class="table-wrapper">
                <table id="results-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa / NISN</th>
                            <th style="text-align: center">Kelas</th>
                            <th style="text-align: center">Nilai Auto</th>
                            <th style="text-align: center">Nilai Esai</th>
                            <th style="text-align: center">Total</th>
                            <th style="text-align: center">Status</th>
                            <th style="text-align: center">⚠️ Pelanggaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="results-body">
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2.5rem">
                                <?php echo empty($exams) ? 'Belum ada ujian. Silakan buat ujian terlebih dahulu.' : 'Pilih ujian untuk melihat hasil.'; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Grading Modal -->
    <div class="modal-overlay" id="grading-modal">
        <div class="modal" style="max-width: 800px">
            <div class="modal-header">
                <div class="modal-title">Penilaian Manual Esai</div>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div id="grading-content" style="padding: 1.25rem; max-height: 70vh; overflow-y: auto">
                <!-- Dynamic content -->
            </div>
            <div style="padding: 1.25rem; border-top: 1px solid #eee; text-align: right">
                <button class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button class="btn btn-primary" onclick="submitManualGrade()">Simpan Penilaian</button>
            </div>
        </div>
    </div>

    <!-- Violation Detail Modal -->
    <div class="modal-overlay" id="violation-detail-modal">
        <div class="modal" style="max-width: 500px">
            <div class="modal-header">
                <div class="modal-title">📋 Detail Pelanggaran</div>
                <button class="modal-close" onclick="closeViolationModal()">✕</button>
            </div>
            <div id="violation-detail-content" style="padding: 1.25rem">Memuat...</div>
            <div style="padding: 1.25rem; border-top: 1px solid #eee; text-align: right">
                <button class="btn btn-outline" onclick="closeViolationModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="../js/teacher-layout.js"></script>
    <script src="../js/toast.js"></script>
    <script>
        const csrfToken = document.getElementById('csrf-token').value;
        let currentExamId = <?php echo $examId; ?>;
        let allResults = [];
        let examInfo = null;
        let currentViolationStudentId = null;
        let currentViolationExamId = null;
        let scoreChart = null;
        let currentSubmissionId = null;

        document.addEventListener("DOMContentLoaded", () => {
            const examSelector = document.getElementById("exam-selector");
            if (examSelector) {
                examSelector.addEventListener("change", (e) => {
                    const newExamId = e.target.value;
                    if (newExamId) {
                        window.location.href = `results.php?exam_id=${newExamId}`;
                    } else {
                        window.location.href = "results.php";
                    }
                });
            }

            if (currentExamId) {
                fetchAllData();
            }
        });

        async function fetchAllData() {
            try {
                const [examRes, resultsRes] = await Promise.all([
                    fetch(`../php/exam_api.php?action=get_exam_info&exam_id=${currentExamId}`),
                    fetch(`../php/exam_api.php?action=get_results&exam_id=${currentExamId}`),
                ]);

                const examData = await examRes.json();
                const resultsData = await resultsRes.json();

                if (examData.success && examData.exam) {
                    updateExamInfo(examData.exam);
                }

                if (resultsData.success) {
                    allResults = resultsData.results;
                    updateStats(resultsData.stats);
                    populateClassFilter(allResults);
                    renderTable(allResults);
                    renderAnalytics(allResults);
                } else {
                    showResultsError(resultsData.message || "Gagal memuat hasil ujian");
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                showResultsError("Terjadi kesalahan saat memuat data.");
            }
        }

        function updateExamInfo(exam) {
            examInfo = exam;
            document.getElementById("exam-title").innerHTML = `📊 ${escapeHtml(exam.name)}`;
            document.getElementById("exam-subject").textContent = exam.subject;
            document.getElementById("exam-class").textContent = exam.class;
            document.getElementById("exam-duration").textContent = `${exam.duration_minutes} menit`;
            document.getElementById("exam-code").textContent = exam.exam_code || "-";

            const statusEl = document.getElementById("exam-status");
            const status = exam.status || "unknown";
            let statusText = "";
            let statusColor = "";

            switch (status) {
                case "active":
                    statusText = "🟢 Aktif";
                    statusColor = "var(--success)";
                    break;
                case "ended":
                    statusText = "🔴 Berakhir";
                    statusColor = "var(--danger)";
                    break;
                default:
                    statusText = status;
                    statusColor = "var(--text-muted)";
            }

            statusEl.textContent = statusText;
            statusEl.style.color = statusColor;
            document.getElementById("examInfoBar").style.display = "flex";
            document.title = `${exam.name} - Hasil Ujian | ExamSafe`;
        }

        function showResultsError(message) {
            const tbody = document.getElementById("results-body");
            tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--danger)">❌ ${escapeHtml(message)}</td></tr>`;
        }

        function updateStats(stats) {
            const vals = document.querySelectorAll(".stat-value");
            if (vals[0]) vals[0].textContent = stats.total || 0;
            if (vals[1]) vals[1].textContent = stats.passed || 0;
            if (vals[2]) vals[2].textContent = (stats.total || 0) - (stats.passed || 0);
            if (vals[3]) vals[3].textContent = parseFloat(stats.average || 0).toFixed(1);
        }

        function populateClassFilter(results) {
            const select = document.getElementById("class-filter");
            const classes = [...new Set(results.map((r) => r.class))].sort();
            select.innerHTML = '<option value="">Semua Kelas</option>';
            classes.forEach((cls) => {
                if (!cls) return;
                const opt = document.createElement("option");
                opt.value = cls;
                opt.textContent = cls;
                select.appendChild(opt);
            });
        }

        function renderTable(results) {
            const tbody = document.getElementById("results-body");
            if (results.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2.5rem;color:#64748b">Tidak ada data yang cocok.</td></tr>';
                return;
            }

            tbody.innerHTML = results.map((s, i) => {
                const autoScore = parseFloat(s.auto_score || 0);
                const manualScore = parseFloat(s.manual_score || 0);
                const totalScore = parseFloat(s.total_score || 0);
                const violationCount = s.violation_count || 0;

                let violationBadge = "";
                if (violationCount === 0) {
                    violationBadge = '<span class="violation-badge zero">🟢 0</span>';
                } else if (violationCount <= 2) {
                    violationBadge = `<span class="violation-badge low" onclick="showViolationDetails(${s.student_id}, '${escapeHtml(s.full_name)}', ${currentExamId})">⚠️ ${violationCount}</span>`;
                } else {
                    violationBadge = `<span class="violation-badge high" onclick="showViolationDetails(${s.student_id}, '${escapeHtml(s.full_name)}', ${currentExamId})">🔴 ${violationCount}</span>`;
                }

                return `
                    <tr>
                        <td>${i + 1}</td>
                        <td><b>${escapeHtml(s.full_name)}</b><br><small style="color:#64748b">${escapeHtml(s.nisn)}</small></td>
                        <td style="text-align:center"><span class="badge badge-outline">${escapeHtml(s.class)}</span></td>
                        <td style="text-align:center"><b>${autoScore.toFixed(1)}</b></td>
                        <td style="text-align:center">${manualScore.toFixed(1)}</td>
                        <td style="text-align:center"><span class="score-cell ${totalScore >= 75 ? 'high' : 'low'}">${totalScore.toFixed(1)}</span></td>
                        <td style="text-align:center"><span class="badge badge-${s.status === "graded" ? "success" : "warning"}">${s.status === "graded" ? "Selesai" : "Perlu Nilai"}</span></td>
                        <td style="text-align:center">${violationBadge}</td>
                        <td><button class="btn btn-sm btn-primary" onclick="openGrading(${s.submission_id})">✏️ Nilai Esai</button></td>
                    </tr>
                `;
            }).join("");
        }

        function filterResults() {
            const searchQuery = document.getElementById("search-student")?.value.toLowerCase() || "";
            const classFilter = document.getElementById("class-filter")?.value || "";
            const violationsOnly = document.getElementById("filter-violations-only")?.checked || false;

            const filtered = allResults.filter((s) => {
                const matchName = s.full_name.toLowerCase().includes(searchQuery) || s.nisn.includes(searchQuery);
                const matchClass = !classFilter || s.class === classFilter;
                const matchViolations = !violationsOnly || s.violation_count > 0;
                return matchName && matchClass && matchViolations;
            });

            renderTable(filtered);
        }

        async function showViolationDetails(studentId, studentName, examId) {
            currentViolationStudentId = studentId;
            currentViolationExamId = examId;

            const modal = document.getElementById("violation-detail-modal");
            const content = document.getElementById("violation-detail-content");
            modal.classList.add("active");
            content.innerHTML = '<div style="text-align: center; color: #64748b">Memuat...</div>';

            try {
                const response = await fetch(`../php/exam_api.php?action=get_student_violations&student_id=${studentId}&exam_id=${examId}`);
                const data = await response.json();

                if (data.success && data.violations) {
                    if (data.violations.length === 0) {
                        content.innerHTML = '<div style="text-align: center; color: #64748b">Tidak ada catatan pelanggaran.</div>';
                        return;
                    }

                    let html = `<div style="margin-bottom: 1rem"><strong>Siswa:</strong> ${escapeHtml(studentName)}<br><strong>Total:</strong> <span class="badge badge-danger">${data.total_count}</span></div>`;
                    html += `<div style="max-height: 18.75rem; overflow-y: auto;">`;

                    data.violations.forEach((v) => {
                        html += `
                            <div style="padding: 0.75rem; margin-bottom: 0.5rem; background: #f8fafc; border-radius: 8px; border-left: 3px solid #f59e0b">
                                <div style="font-size: 0.8rem; color: #64748b">${new Date(v.created_at).toLocaleString("id-ID")}</div>
                                <div style="font-size: 0.9rem">${escapeHtml(v.reason)}</div>
                            </div>
                        `;
                    });
                    html += `</div>`;
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<div style="text-align: center; color: #ef4444">Gagal memuat data.</div>';
                }
            } catch (error) {
                content.innerHTML = '<div style="text-align: center; color: #ef4444">Terjadi kesalahan.</div>';
            }
        }

        function closeViolationModal() {
            document.getElementById("violation-detail-modal").classList.remove("active");
        }

        function renderAnalytics(results) {
            if (!results.length) return;
            const ranges = {
                "0-20": 0,
                "21-40": 0,
                "41-60": 0,
                "61-80": 0,
                "81-100": 0
            };
            results.forEach((r) => {
                const score = parseFloat(r.total_score);
                if (score <= 20) ranges["0-20"]++;
                else if (score <= 40) ranges["21-40"]++;
                else if (score <= 60) ranges["41-60"]++;
                else if (score <= 80) ranges["61-80"]++;
                else ranges["81-100"]++;
            });

            const ctx = document.getElementById("scoreChart").getContext("2d");
            if (scoreChart) scoreChart.destroy();
            scoreChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: Object.keys(ranges),
                    datasets: [{
                        label: "Jumlah Siswa",
                        data: Object.values(ranges),
                        backgroundColor: "#3b82f6",
                        borderRadius: 8
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                },
            });
            analyzeDifficultQuestions();
        }

        async function analyzeDifficultQuestions() {
            const diffContainer = document.getElementById("difficult-questions");
            diffContainer.innerHTML = `
                <div style="padding: 0.75rem; background: #fef3c7; border-radius: 8px; margin-bottom: 0.75rem;">
                    <small>💡 Analisis butir soal memerlukan data jawaban detail per siswa.</small>
                </div>
                <p style="color:#64748b; font-size:0.8rem">Fitur ini akan tersedia pada update berikutnya.</p>
            `;
        }

        function exportToExcel() {
            if (allResults.length === 0) {
                showToast("Tidak ada data untuk diekspor!", "error");
                return;
            }
            let html = `<table border="1"><thead><tr style="background-color: #1a3c6e; color: #ffffff;"><th>No</th><th>Nama Siswa</th><th>NISN</th><th>Kelas</th><th>Nilai Auto</th><th>Nilai Esai</th><th>Total</th><th>Pelanggaran</th></tr></thead><tbody>`;
            allResults.forEach((r, i) => {
                html += `<tr><td>${i + 1}</td><td>${escapeHtml(r.full_name)}</td><td>${escapeHtml(r.nisn)}</td><td>${escapeHtml(r.class)}</td><td>${r.auto_score}</td><td>${r.manual_score}</td><td>${r.total_score}</td><td>${r.violation_count}</td></tr>`;
            });
            html += `</tbody></table>`;
            const blob = new Blob(["\uFEFF", html], {
                type: "application/vnd.ms-excel"
            });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Hasil_Ujian_${currentExamId}.xls`;
            link.click();
        }

        async function openGrading(id) {
            currentSubmissionId = id;
            document.getElementById("grading-modal").classList.add("active");
            const content = document.getElementById("grading-content");
            content.innerHTML = "Memuat jawaban...";

            try {
                const res = await fetch(`../php/exam_api.php?action=get_submission_detail&id=${id}`);
                const data = await res.json();
                if (data.success) {
                    const sub = data.submission;
                    const questions = data.questions.filter((q) => q.question_type === "essay");
                    let html = `<div style="margin-bottom:0.9375rem"><strong>Siswa:</strong> ${escapeHtml(sub.full_name)}</div>`;
                    if (questions.length === 0) {
                        html += "<p>Tidak ada soal esai.</p>";
                    } else {
                        questions.forEach((q, idx) => {
                            const ans = sub.answers.find((a) => a.question_id == q.id)?.student_answer || "Tidak menjawab";
                            html += `
                                <div class="card" style="margin-bottom:0.625rem">
                                    <p><strong>Soal ${idx + 1}:</strong> ${escapeHtml(q.question_text)}</p>
                                    <div style="background:#f1f5f9; padding:0.625rem; border-radius:5px; margin:0.625rem 0">${escapeHtml(ans)}</div>
                                    <label>Nilai (Maks ${q.points}):</label>
                                    <input type="number" class="form-control essay-point" data-max="${q.points}" style="width:6.25rem">
                                </div>
                            `;
                        });
                    }
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="color: red">Gagal memuat data.</p>';
                }
            } catch (e) {
                content.innerHTML = '<p style="color: red">Terjadi kesalahan.</p>';
            }
        }

        async function submitManualGrade() {
            const inputs = document.querySelectorAll(".essay-point");
            let total = 0;
            for (let input of inputs) {
                const val = parseFloat(input.value || 0);
                if (val > parseFloat(input.dataset.max)) {
                    showToast("Nilai melebihi batas!", "error");
                    return;
                }
                total += val;
            }
            try {
                const res = await fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        action: "save_manual_grade",
                        submission_id: currentSubmissionId,
                        manual_score: total,
                        csrf_token: csrfToken
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    showToast("Nilai disimpan!", "success");
                    closeModal();
                    fetchAllData();
                } else {
                    showToast("Gagal menyimpan: " + (data.message || "Terjadi kesalahan"), "error");
                }
            } catch (e) {
                showToast("Terjadi kesalahan koneksi.", "error");
            }
        }

        function closeModal() {
            document.getElementById("grading-modal").classList.remove("active");
        }

        function escapeHtml(str) {
            if (!str) return "";
            return str.replace(/[&<>]/g, function(m) {
                if (m === "&") return "&amp;";
                if (m === "<") return "&lt;";
                if (m === ">") return "&gt;";
                return m;
            });
        }
    </script>
</body>

</html>
