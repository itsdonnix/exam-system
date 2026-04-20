<?php
require_once 'includes/init.php';
$activePage = 'question-bank';

// Generate CSRF token for all POST operations
require_once '../includes/csrf.php';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bank Soal — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        /* === FILTER BAR === */
        .bank-header {
            display: flex;
            gap: 1em;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-filter {
            display: flex;
            gap: 0.75em;
            flex: 1;
            min-width: 0;
            flex-wrap: wrap;
        }

        .question-count {
            color: var(--text-muted);
            font-size: 0.85rem;
            white-space: nowrap;
        }

        /* === QUESTION CARDS === */
        .question-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.25em 1.5em;
            margin-bottom: 1em;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-light);
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .question-card:hover {
            box-shadow: 0 4px 16px rgba(26, 60, 110, 0.12);
            transform: translateY(-2px);
        }

        .question-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75em;
            gap: 0.75em;
            flex-wrap: wrap;
        }

        .question-badges {
            display: flex;
            gap: 0.5em;
            flex-wrap: wrap;
        }

        /* === BADGES === */
        .question-type-badge,
        .difficulty-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375em;
            padding: 0.25em 0.75em;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .type-multiple {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }

        .type-essay {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
        }

        .type-truefalse {
            background: rgba(168, 85, 247, 0.1);
            color: #6b21a8;
        }

        .type-checkbox {
            background: rgba(245, 158, 11, 0.1);
            color: #b45309;
        }

        .diff-easy {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
        }

        .diff-medium {
            background: rgba(245, 158, 11, 0.1);
            color: #b45309;
        }

        .diff-hard {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .category-label {
            display: inline-block;
            background: #f1f5f9;
            color: #64748b;
            padding: 0.125em 0.5em;
            border-radius: 4px;
            font-size: 0.72rem;
        }

        /* === QUESTION BODY === */
        .question-text {
            font-size: 0.95rem;
            color: #1e293b;
            margin-bottom: 0.75em;
            line-height: 1.6;
        }

        .question-meta {
            display: flex;
            gap: 1em;
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 0.75em;
            flex-wrap: wrap;
        }

        /* === ACTION BUTTONS === */
        .question-actions {
            display: flex;
            gap: 0.5em;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.375em 0.75em;
            font-size: 0.78rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-view {
            background: #eff6ff;
            color: #1e40af;
        }

        .btn-view:hover {
            background: #dbeafe;
        }

        .btn-edit {
            background: #fef3c7;
            color: #b45309;
        }

        .btn-edit:hover {
            background: #fde68a;
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-delete:hover {
            background: #fecaca;
        }

        .btn-delete.confirming {
            background: #dc2626;
            color: #fff;
            animation: pulse 0.8s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .btn-add-to-exam {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
        }

        .btn-add-to-exam:hover {
            background: rgba(34, 197, 94, 0.2);
        }

        .btn-more {
            background: #f1f5f9;
            color: #64748b;
            display: none;
        }

        .btn-more:hover {
            background: #e2e8f0;
        }

        /* === EMPTY STATES === */
        .empty-state {
            text-align: center;
            padding: 3.75em 1.25em;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1em;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5em;
            color: #475569;
        }

        .empty-state .btn {
            margin-top: 1em;
        }

        /* === SKELETON LOADER === */
        .skeleton-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.25em 1.5em;
            margin-bottom: 1em;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--border);
        }

        .skeleton-line {
            height: 0.875rem;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 4px;
            margin-bottom: 0.5em;
        }

        .skeleton-line.short {
            width: 40%;
        }

        .skeleton-line.medium {
            width: 70%;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* === FORM HELPERS === */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1em;
        }

        .option-row {
            display: flex;
            gap: 0.5em;
            margin-bottom: 0.5em;
            align-items: center;
        }

        .option-row .form-control {
            flex: 1;
        }

        .option-remove {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.375em 0.5em;
            font-size: 0.85rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .option-remove:hover {
            background: #fecaca;
        }

        .tf-toggle {
            display: flex;
            gap: 0.75em;
            margin-bottom: 0.75em;
        }

        .tf-option {
            flex: 1;
            padding: 0.75em;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .tf-option:hover {
            border-color: var(--primary-light);
        }

        .tf-option.selected {
            border-color: var(--primary-light);
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary-light);
        }

        .modal-footer {
            display: flex;
            gap: 0.75em;
            justify-content: flex-end;
            margin-top: 1.5em;
            padding-top: 1.25em;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }

        /* === VIEW MODAL CONTENT === */
        .view-badges {
            display: flex;
            gap: 0.5em;
            margin-bottom: 0.75em;
            flex-wrap: wrap;
        }

        .view-question-text {
            color: #1e293b;
            font-size: 1.05rem;
            margin-bottom: 0.75em;
            line-height: 1.6;
        }

        .view-meta {
            display: flex;
            gap: 1em;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 1em;
        }

        .view-options-title {
            color: #475569;
            margin-bottom: 0.625em;
            font-size: 0.9rem;
        }

        .view-option {
            padding: 0.5em 0.75em;
            margin-bottom: 0.375em;
            border-radius: 6px;
            border-left: 3px solid var(--border);
            background: #f8fafc;
            font-size: 0.9rem;
        }

        .view-option.correct {
            background: #dcfce7;
            border-left-color: #22c55e;
        }

        /* === EXAM PICKER ITEMS === */
        .exam-pick-item {
            padding: 0.75em;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 0.625em;
            border-left: 3px solid var(--primary-light);
        }

        .exam-pick-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.375em;
        }

        .exam-pick-name {
            font-weight: 700;
            color: #1e293b;
        }

        .exam-pick-class {
            font-size: 0.8rem;
            color: #64748b;
        }

        .exam-pick-subject {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 0.5em;
        }

        .exam-pick-btn {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
            width: 100%;
            padding: 0.5em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .exam-pick-btn:hover {
            background: rgba(34, 197, 94, 0.2);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .question-actions .action-btn:not(.btn-view):not(.btn-more) {
                display: none;
            }

            .question-actions .btn-more {
                display: inline-flex;
            }

            .question-actions.expanded .action-btn {
                display: inline-flex;
            }
        }

        @media (max-width: 480px) {
            .question-card {
                padding: 1em;
            }
        }

        /* === PRINT === */
        @media print {

            .toast-container,
            .bank-header,
            .question-actions,
            .empty-state .btn,
            #questionModal,
            #viewQuestionModal,
            #copyToExamModal {
                display: none !important;
            }

            .question-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .main-content {
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">📚 Bank Soal</div>
                <div class="page-subtitle">Kelola dan kelompokkan soal-soal Anda untuk digunakan kembali</div>
            </div>
            <button class="btn btn-primary" onclick="openNewQuestionModal()">+ Tambah Soal Baru</button>
        </div>

        <!-- Search & Filter -->
        <div class="card" style="margin-bottom: 1.25em;">
            <div class="bank-header">
                <div class="search-filter">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari soal..." oninput="debouncedFilter()" />
                    <select id="categoryFilter" class="form-control" onchange="filterQuestions()">
                        <option value="">Semua Kategori</option>
                        <option value="Umum">Umum</option>
                        <option value="Matematika">Matematika</option>
                        <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                        <option value="Bahasa Inggris">Bahasa Inggris</option>
                        <option value="Fisika">Fisika</option>
                        <option value="IPA">IPA</option>
                        <option value="IPS">IPS</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                    <select id="sortFilter" class="form-control" onchange="sortQuestions()" style="max-width: 12.5rem;">
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="points-high">Poin Tertinggi</option>
                        <option value="points-low">Poin Terendah</option>
                        <option value="difficulty">Kesulitan</option>
                    </select>
                </div>
                <div class="question-count" id="questionCount">Total: 0 soal</div>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div id="loadingSkeleton" style="display: none;">
            <div class="skeleton-card">
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
            </div>
        </div>

        <!-- Questions List -->
        <div id="questionsList"></div>

        <!-- Empty State: No questions at all -->
        <div id="emptyState" class="card" style="display: none">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>Belum ada soal</h3>
                <p>Mulai dengan membuat soal baru untuk bank Anda</p>
                <button class="btn btn-primary" onclick="openNewQuestionModal()">+ Buat Soal Pertama</button>
            </div>
        </div>

        <!-- Empty State: No search results -->
        <div id="noResultsState" class="card" style="display: none">
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3>Tidak ada hasil</h3>
                <p>Coba ubah kata kunci atau filter pencarian Anda</p>
                <button class="btn btn-outline" onclick="clearFilters()">Reset Filter</button>
            </div>
        </div>
    </main>
    </div>

    <!-- Modal: Add/Edit Question -->
    <div id="questionModal" class="modal-overlay">
        <div class="modal" style="max-width: 37.5em;">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Tambah Soal Baru</div>
                <button class="modal-close" onclick="closeQuestionModal()">×</button>
            </div>
            <form id="questionForm" onsubmit="saveQuestion(event)">
                <input type="hidden" id="questionId" />
                <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label>Pertanyaan / Soal *</label>
                    <textarea id="questionText" class="form-control" required placeholder="Masukkan teks pertanyaan..." rows="4"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipe Soal *</label>
                        <select id="questionType" class="form-control" required onchange="updateQuestionType()">
                            <option value="multiple">Pilihan Ganda</option>
                            <option value="essay">Essay / Uraian</option>
                            <option value="truefalse">Benar / Salah</option>
                            <option value="checkbox">Checkbox (Multi-jawab)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori *</label>
                        <select id="category" class="form-control" required>
                            <option value="Umum">Umum</option>
                            <option value="Matematika">Matematika</option>
                            <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                            <option value="Bahasa Inggris">Bahasa Inggris</option>
                            <option value="Fisika">Fisika</option>
                            <option value="IPA">IPA</option>
                            <option value="IPS">IPS</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tingkat Kesulitan *</label>
                        <select id="difficulty" class="form-control" required>
                            <option value="easy">Mudah</option>
                            <option value="medium">Sedang</option>
                            <option value="hard">Sulit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Poin / Score *</label>
                        <input type="number" id="points" class="form-control" value="1" min="1" max="100" required />
                    </div>
                </div>

                <!-- Options for Multiple Choice & Checkbox -->
                <div id="optionsContainer">
                    <div class="form-group">
                        <label>Opsi Jawaban</label>
                        <div id="optionsList"></div>
                        <button type="button" class="btn btn-sm btn-outline" style="margin-top: 0.5em;" onclick="addOption()">+ Tambah Opsi</button>
                    </div>
                    <div class="form-group">
                        <label>Jawaban Benar (Pilih dari opsi di atas)</label>
                        <select id="correctAnswer" class="form-control" required></select>
                    </div>
                </div>

                <!-- True/False Toggle -->
                <div id="tfContainer" style="display: none;">
                    <div class="form-group">
                        <label>Jawaban Benar</label>
                        <div class="tf-toggle">
                            <div class="tf-option" data-value="0" onclick="selectTF(this)">✅ Benar</div>
                            <div class="tf-option" data-value="1" onclick="selectTF(this)">❌ Salah</div>
                        </div>
                        <input type="hidden" id="tfAnswer" value="" />
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Media (Gambar/Video) - Opsional</label>
                    <input type="url" id="mediaUrl" class="form-control" placeholder="https://..." />
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeQuestionModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Question -->
    <div id="viewQuestionModal" class="modal-overlay">
        <div class="modal" style="max-width: 37.5em;">
            <div class="modal-header">
                <div class="modal-title">Detail Soal</div>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>
            <div id="viewQuestionContent" style="padding: 0 2em;"></div>
            <div class="modal-footer" style="padding: 1.25em 2em;">
                <button type="button" class="action-btn btn-view" onclick="editQuestion()">✏️ Edit</button>
                <button type="button" class="action-btn btn-add-to-exam" onclick="copyToExam()">📋 Pakai di Ujian</button>
                <button type="button" class="btn btn-outline" onclick="closeViewModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal: Copy to Exam -->
    <div id="copyToExamModal" class="modal-overlay">
        <div class="modal" style="max-width: 31.25em;">
            <div class="modal-header">
                <div class="modal-title">Pilih Ujian</div>
                <button class="modal-close" onclick="closeCopyModal()">×</button>
            </div>
            <div style="padding: 0 2em;">
                <p style="margin-bottom: 1em; color: #64748b;">Pilih ujian yang ingin ditambahi dengan soal ini:</p>
                <div id="examsList"></div>
            </div>
            <div class="modal-footer" style="padding: 1.25em 2em;">
                <button type="button" class="btn btn-outline" onclick="closeCopyModal()">Batal</button>
            </div>
        </div>
    </div>

    <script src="../js/teacher-layout.js"></script>
    <script src="../js/toast.js"></script>
    <script>
        const csrfToken = document.getElementById('csrf-token')?.value || '<?php echo $csrf_token; ?>';
        let allQuestions = [];
        let displayedQuestions = [];
        let currentQuestion = null;
        let currentViewQuestion = null;
        let optionCounter = 0;
        let deleteTimers = {};
        let isSearchActive = false;
        let debounceTimer = null;

        function debouncedFilter() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(() => {
                filterQuestions();
            }, 300);
        }

        document.addEventListener("DOMContentLoaded", function() {
            loadQuestions();
        });

        /* === LOADING === */
        function showLoading() {
            document.getElementById('loadingSkeleton').style.display = 'block';
            document.getElementById('questionsList').innerHTML = '';
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('noResultsState').style.display = 'none';
        }

        function hideLoading() {
            document.getElementById('loadingSkeleton').style.display = 'none';
        }

        /* === LOAD & RENDER === */
        function loadQuestions() {
            showLoading();
            const searchQuery = document.getElementById("searchInput").value;
            const categoryFilter = document.getElementById("categoryFilter").value;
            isSearchActive = !!(searchQuery || categoryFilter);

            let url = "../php/exam_api.php?action=get_bank_questions";
            if (searchQuery) url += "&search=" + encodeURIComponent(searchQuery);
            if (categoryFilter) url += "&category=" + encodeURIComponent(categoryFilter);

            fetch(url)
                .then((r) => r.json())
                .then((d) => {
                    hideLoading();
                    if (d.success) {
                        allQuestions = d.questions || [];
                        sortQuestions();
                    } else {
                        showToast(d.message || "Gagal memuat soal", "error");
                    }
                })
                .catch((e) => {
                    hideLoading();
                    showToast("Terjadi kesalahan koneksi", "error");
                });
        }

        function sortQuestions() {
            const sort = document.getElementById("sortFilter").value;
            displayedQuestions = [...allQuestions];
            switch (sort) {
                case "newest":
                    displayedQuestions.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    break;
                case "oldest":
                    displayedQuestions.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                    break;
                case "points-high":
                    displayedQuestions.sort((a, b) => b.points - a.points);
                    break;
                case "points-low":
                    displayedQuestions.sort((a, b) => a.points - b.points);
                    break;
                case "difficulty":
                    const order = {
                        hard: 0,
                        medium: 1,
                        easy: 2
                    };
                    displayedQuestions.sort((a, b) => (order[a.difficulty] ?? 1) - (order[b.difficulty] ?? 1));
                    break;
            }
            renderQuestions();
        }

        function renderQuestions() {
            const container = document.getElementById("questionsList");
            const emptyState = document.getElementById("emptyState");
            const noResults = document.getElementById("noResultsState");

            if (allQuestions.length === 0 && !isSearchActive) {
                container.innerHTML = "";
                emptyState.style.display = "block";
                noResults.style.display = "none";
                document.getElementById("questionCount").textContent = "Total: 0 soal";
                return;
            }
            if (displayedQuestions.length === 0 && isSearchActive) {
                container.innerHTML = "";
                emptyState.style.display = "none";
                noResults.style.display = "block";
                document.getElementById("questionCount").textContent = "Total: 0 soal";
                return;
            }

            emptyState.style.display = "none";
            noResults.style.display = "none";

            container.innerHTML = displayedQuestions.map((q) => {
                const typeClass = `type-${q.question_type}`;
                const diffClass = `diff-${q.difficulty}`;
                const typeLabel = {
                    multiple: "Pilihan Ganda",
                    essay: "Essay",
                    truefalse: "Benar/Salah",
                    checkbox: "Checkbox",
                } [q.question_type] || q.question_type;

                const diffLabel = {
                    easy: "Mudah",
                    medium: "Sedang",
                    hard: "Sulit"
                } [q.difficulty] || q.difficulty;

                return `
                    <div class="question-card">
                        <div class="question-card-header">
                            <div style="flex:1;">
                                <div class="question-badges">
                                    <span class="question-type-badge ${typeClass}">⭐ ${escapeHtml(typeLabel)}</span>
                                    <span class="difficulty-badge ${diffClass}">${escapeHtml(diffLabel)}</span>
                                    <span class="category-label">${escapeHtml(q.category)}</span>
                                </div>
                            </div>
                            <div class="question-meta" style="margin:0;">
                                <span>Poin: <strong>${q.points}</strong></span>
                                <span>${new Date(q.created_at).toLocaleDateString("id-ID")}</span>
                            </div>
                        </div>
                        <div class="question-text">${escapeHtml(q.question_text)}</div>
                        <div class="question-actions" id="actions-${q.id}">
                            <button class="action-btn btn-view" onclick="viewQuestion(${q.id})">👁️ Lihat</button>
                            <button class="action-btn btn-edit" onclick="editQuestionById(${q.id})">✏️ Edit</button>
                            <button class="action-btn btn-add-to-exam" onclick="showCopyToExamModal(${q.id})">📋 Pakai</button>
                            <button class="action-btn btn-delete" id="del-${q.id}" onclick="deleteQuestion(${q.id})">🗑️ Hapus</button>
                            <button class="action-btn btn-more" onclick="toggleActions(${q.id})">⋯</button>
                        </div>
                    </div>
                `;
            }).join("");

            document.getElementById("questionCount").textContent = `Total: ${displayedQuestions.length} soal`;
        }

        function toggleActions(id) {
            document.getElementById(`actions-${id}`).classList.toggle('expanded');
        }

        function filterQuestions() {
            loadQuestions();
        }

        function clearFilters() {
            document.getElementById("searchInput").value = "";
            document.getElementById("categoryFilter").value = "";
            loadQuestions();
        }

        function openNewQuestionModal() {
            currentQuestion = null;
            optionCounter = 0;
            document.getElementById("questionId").value = "";
            document.getElementById("questionForm").reset();
            document.getElementById("modalTitle").textContent = "Tambah Soal Baru";
            document.getElementById("optionsList").innerHTML = "";
            addOption();
            addOption();
            addOption();
            addOption();
            updateQuestionType();
            document.getElementById("questionModal").classList.add("active");
        }

        function closeQuestionModal() {
            document.getElementById("questionModal").classList.remove("active");
            currentQuestion = null;
        }

        function closeViewModal() {
            document.getElementById("viewQuestionModal").classList.remove("active");
        }

        function closeCopyModal() {
            document.getElementById("copyToExamModal").classList.remove("active");
        }

        function updateQuestionType() {
            const type = document.getElementById("questionType").value;
            const optionsEl = document.getElementById("optionsContainer");
            const tfEl = document.getElementById("tfContainer");
            if (type === "essay") {
                optionsEl.style.display = "none";
                tfEl.style.display = "none";
            } else if (type === "truefalse") {
                optionsEl.style.display = "none";
                tfEl.style.display = "block";
                document.getElementById("tfAnswer").value = "";
                document.querySelectorAll(".tf-option").forEach(el => el.classList.remove("selected"));
            } else {
                optionsEl.style.display = "block";
                tfEl.style.display = "none";
            }
        }

        function selectTF(el) {
            document.querySelectorAll(".tf-option").forEach(e => e.classList.remove("selected"));
            el.classList.add("selected");
            document.getElementById("tfAnswer").value = el.dataset.value;
        }

        function addOption() {
            optionCounter++;
            const container = document.getElementById("optionsList");
            const div = document.createElement("div");
            div.className = "option-row";
            div.innerHTML = `
                <input type="text" class="form-control option-input" placeholder="Opsi ${optionCounter}" 
                       oninput="updateCorrectAnswerOptions()" style="flex: 1;">
                <button type="button" class="option-remove" onclick="this.parentElement.remove(); updateCorrectAnswerOptions()">✕</button>
            `;
            container.appendChild(div);
            updateCorrectAnswerOptions();
        }

        function updateCorrectAnswerOptions() {
            const inputs = document.querySelectorAll(".option-input");
            const select = document.getElementById("correctAnswer");
            const currentValue = select.value;

            select.innerHTML = "";
            inputs.forEach((input, idx) => {
                if (input.value) {
                    const opt = document.createElement("option");
                    opt.value = idx;
                    opt.textContent = input.value;
                    select.appendChild(opt);
                }
            });

            if (currentValue < select.options.length) {
                select.value = currentValue;
            }
        }

        function getOptions() {
            const inputs = document.querySelectorAll(".option-input");
            const options = [];
            inputs.forEach((input) => {
                if (input.value) {
                    options.push(input.value);
                }
            });
            return options;
        }

        function saveQuestion(e) {
            e.preventDefault();
            const type = document.getElementById("questionType").value;
            let options, correctAnswer;

            if (type === "truefalse") {
                options = ["Benar", "Salah"];
                correctAnswer = document.getElementById("tfAnswer").value;
                if (correctAnswer === "") {
                    showToast("Pilih jawaban Benar atau Salah", "error");
                    return;
                }
            } else if (type === "essay") {
                options = [];
                correctAnswer = "";
            } else {
                options = getOptions();
                correctAnswer = document.getElementById("correctAnswer").value;
                if (options.length < 2) {
                    showToast("Minimal 2 opsi jawaban", "error");
                    return;
                }
            }

            const questionData = {
                action: document.getElementById("questionId").value ? "update_bank_question" : "save_question_to_bank",
                csrf_token: csrfToken,
                question_text: document.getElementById("questionText").value,
                question_type: type,
                options: options,
                correct_answer: correctAnswer,
                points: parseInt(document.getElementById("points").value),
                difficulty: document.getElementById("difficulty").value,
                category: document.getElementById("category").value,
                media_url: document.getElementById("mediaUrl").value || "",
            };

            const questionId = document.getElementById("questionId").value;
            if (questionId) {
                questionData.id = parseInt(questionId);
            }

            fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(questionData),
                })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        showToast(d.message || "Soal berhasil disimpan");
                        closeQuestionModal();
                        loadQuestions();
                    } else {
                        showToast("Error: " + d.message, "error");
                    }
                })
                .catch(() => showToast("Terjadi kesalahan koneksi.", "error"));
        }

        function viewQuestion(id) {
            fetch(`../php/exam_api.php?action=get_bank_question&id=${id}`)
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        currentViewQuestion = d.question;
                        renderViewQuestion();
                        document.getElementById("viewQuestionModal").classList.add("active");
                    }
                });
        }

        function renderViewQuestion() {
            const q = currentViewQuestion;
            const typeLabel = {
                multiple: "Pilihan Ganda",
                essay: "Essay",
                truefalse: "Benar/Salah",
                checkbox: "Checkbox",
            } [q.question_type] || q.question_type;

            const diffLabel = {
                easy: "Mudah",
                medium: "Sedang",
                hard: "Sulit"
            } [q.difficulty] || q.difficulty;

            let content = `
                <div style="margin-bottom: 1.25em;">
                    <div class="view-badges">
                        <span class="question-type-badge type-${q.question_type}">⭐ ${escapeHtml(typeLabel)}</span>
                        <span class="difficulty-badge diff-${q.difficulty}">${escapeHtml(diffLabel)}</span>
                        <span class="category-label">${escapeHtml(q.category)}</span>
                    </div>
                    <div class="view-question-text">${escapeHtml(q.question_text)}</div>
                    <div class="view-meta">
                        <span>Poin: <strong>${q.points}</strong></span>
                        <span>Dibuat: ${new Date(q.created_at).toLocaleDateString("id-ID")}</span>
                    </div>
            `;

            let optionsArray = q.options;
            if (typeof optionsArray === 'string') {
                try {
                    optionsArray = JSON.parse(optionsArray);
                } catch (e) {
                    optionsArray = [];
                }
            }

            if (optionsArray && optionsArray.length > 0) {
                content += '<div class="view-options-title">Opsi Jawaban:</div>';
                optionsArray.forEach((opt, idx) => {
                    const isCorrect = q.correct_answer == idx;
                    content += `<div class="view-option ${isCorrect ? 'correct' : ''}">
                        ${isCorrect ? "✓ " : "  "}${escapeHtml(opt)}
                    </div>`;
                });
            }

            if (q.media_url) {
                content += `<p style="margin-top: 1em; font-size: 0.85rem;"><strong>Media:</strong> <a href="${escapeHtml(q.media_url)}" target="_blank">${escapeHtml(q.media_url)}</a></p>`;
            }

            content += "</div>";
            document.getElementById("viewQuestionContent").innerHTML = content;
        }

        function editQuestion() {
            if (!currentViewQuestion || !currentViewQuestion.id) {
                console.error('No question selected for editing');
                showToast('Tidak ada soal yang dipilih untuk diedit', 'error');
                closeViewModal();
                return;
            }
            
            const q = currentViewQuestion;
            closeViewModal();

            currentQuestion = q;
            document.getElementById("questionId").value = q.id;
            document.getElementById("questionText").value = q.question_text;
            document.getElementById("questionType").value = q.question_type;
            document.getElementById("difficulty").value = q.difficulty;
            document.getElementById("category").value = q.category;
            document.getElementById("points").value = q.points;
            document.getElementById("mediaUrl").value = q.media_url || "";

            document.getElementById("modalTitle").textContent = "Edit Soal";
            document.getElementById("optionsList").innerHTML = "";
            optionCounter = 0;

            updateQuestionType();

            let optionsArray = q.options;
            if (typeof optionsArray === 'string') {
                try {
                    optionsArray = JSON.parse(optionsArray);
                } catch (e) {
                    optionsArray = [];
                }
            }

            if (q.question_type === 'truefalse') {
                const val = q.correct_answer;
                document.getElementById("tfAnswer").value = val;
                document.querySelectorAll(".tf-option").forEach(opt => {
                    if (opt.dataset.value == val) opt.classList.add("selected");
                    else opt.classList.remove("selected");
                });
            } else if (optionsArray && optionsArray.length > 0) {
                optionsArray.forEach((opt) => {
                    optionCounter++;
                    const container = document.getElementById("optionsList");
                    const div = document.createElement("div");
                    div.className = "option-row";
                    div.innerHTML = `
                        <input type="text" class="form-control option-input" value="${escapeHtml(opt)}" 
                               oninput="updateCorrectAnswerOptions()" style="flex: 1;">
                        <button type="button" class="option-remove" onclick="this.parentElement.remove(); updateCorrectAnswerOptions()">✕</button>
                    `;
                    container.appendChild(div);
                });
                updateCorrectAnswerOptions();
                if (q.correct_answer !== null) {
                    document.getElementById("correctAnswer").value = q.correct_answer;
                }
            }

            document.getElementById("questionModal").classList.add("active");
        }

        function editQuestionById(id) {
            const q = allQuestions.find((x) => x.id == id);
            if (q) {
                currentViewQuestion = q;
                editQuestion();
            } else {
                showToast('Soal tidak ditemukan', 'error');
            }
        }

        function deleteQuestion(id) {
            const btn = document.getElementById(`del-${id}`);
            if (btn.classList.contains('confirming')) {
                clearTimeout(deleteTimers[id]);
                delete deleteTimers[id];
                fetch("../php/exam_api.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            action: "delete_bank_question",
                            id,
                            csrf_token: csrfToken
                        }),
                    })
                    .then((r) => r.json())
                    .then((d) => {
                        if (d.success) {
                            showToast("Soal berhasil dihapus");
                            loadQuestions();
                        } else {
                            showToast("Error: " + d.message, "error");
                        }
                    })
                    .catch(() => showToast("Terjadi kesalahan koneksi", "error"));
            } else {
                btn.classList.add('confirming');
                btn.innerHTML = 'Yakin?';
                deleteTimers[id] = setTimeout(() => {
                    btn.classList.remove('confirming');
                    btn.innerHTML = '🗑️ Hapus';
                    delete deleteTimers[id];
                }, 3000);
            }
        }

        function showCopyToExamModal(bankQuestionId) {
            currentViewQuestion = allQuestions.find((q) => q.id == bankQuestionId);

            fetch("../php/exam_api.php?action=get_exams")
                .then((r) => r.json())
                .then((d) => {
                    if (d.success && d.exams.length > 0) {
                        const examsList = document.getElementById("examsList");
                        examsList.innerHTML = d.exams.map((exam) => `
                            <div class="exam-pick-item">
                                <div class="exam-pick-header">
                                    <span class="exam-pick-name">${escapeHtml(exam.name)}</span>
                                    <span class="exam-pick-class">${escapeHtml(exam.class)}</span>
                                </div>
                                <div class="exam-pick-subject">${escapeHtml(exam.subject)}</div>
                                <button class="exam-pick-btn" onclick="confirmCopyQuestion(${bankQuestionId}, ${exam.id})">
                                    ✓ Pakai Soal Ini
                                </button>
                            </div>
                        `).join("");
                        document.getElementById("copyToExamModal").classList.add("active");
                    } else {
                        showToast("Anda belum memiliki ujian. Buat ujian terlebih dahulu.", "info");
                    }
                });
        }

        function copyToExam() {
            showCopyToExamModal(currentViewQuestion.id);
        }

        function confirmCopyQuestion(bankQuestionId, examId) {
            fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        action: "copy_question_to_exam",
                        bank_question_id: bankQuestionId,
                        exam_id: examId,
                        csrf_token: csrfToken
                    }),
                })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        showToast(d.message || "Soal berhasil ditambahkan ke ujian");
                        closeCopyModal();
                    } else {
                        showToast("Error: " + d.message, "error");
                    }
                });
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
