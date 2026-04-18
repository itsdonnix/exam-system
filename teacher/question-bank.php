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
        .bank-header {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-filter {
            display: flex;
            gap: 12px;
            flex: 1;
            min-width: 300px;
            flex-wrap: wrap;
        }

        .search-filter input,
        .search-filter select {
            min-width: 200px;
        }

        .question-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(26, 60, 110, 0.06);
            border-left: 4px solid var(--primary-light);
            transition: all 0.2s;
        }

        .question-card:hover {
            box-shadow: 0 4px 16px rgba(26, 60, 110, 0.12);
            transform: translateY(-2px);
        }

        .question-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .question-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
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

        .difficulty-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
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

        .question-text {
            font-size: 1rem;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .question-meta {
            display: flex;
            gap: 16px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .question-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-btn {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
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

        .btn-add-to-exam {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
        }

        .btn-add-to-exam:hover {
            background: rgba(34, 197, 94, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #475569;
        }

        .category-label {
            display: inline-block;
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-top: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 640px) {
            .bank-header {
                flex-direction: column;
            }

            .search-filter {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .question-actions {
                flex-direction: column;
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
                <div class="page-subtitle">
                    Kelola dan kelompokkan soal-soal Anda untuk digunakan kembali
                </div>
            </div>
            <button class="btn btn-primary" onclick="openNewQuestionModal()">
                + Tambah Soal Baru
            </button>
        </div>

        <!-- Search & Filter -->
        <div class="card">
            <div class="bank-header">
                <div class="search-filter">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-control"
                        placeholder="Cari soal..."
                        onkeyup="filterQuestions()" />
                    <select
                        id="categoryFilter"
                        class="form-control"
                        onchange="filterQuestions()">
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
                </div>
                <div
                    id="questionCount"
                    style="
                        text-align: right;
                        color: #64748b;
                        font-size: 0.9rem;
                        min-width: 120px;
                    ">
                    Total: 0 soal
                </div>
            </div>
        </div>

        <!-- Questions List -->
        <div id="questionsList"></div>

        <!-- Empty State -->
        <div id="emptyState" class="card" style="display: none">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>Belum ada soal</h3>
                <p>Mulai dengan membuat soal baru untuk bank Anda</p>
                <button
                    class="btn btn-primary"
                    style="margin-top: 16px"
                    onclick="openNewQuestionModal()">
                    + Buat Soal Pertama
                </button>
            </div>
        </div>
    </main>
    </div>

    <!-- Modal: Add/Edit Question -->
    <div id="questionModal" class="modal-overlay">
        <div class="modal" style="max-width: 600px">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Tambah Soal Baru</div>
                <button class="modal-close" onclick="closeQuestionModal()">×</button>
            </div>

            <form id="questionForm" onsubmit="saveQuestion(event)">
                <input type="hidden" id="questionId" />
                <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label>Pertanyaan / Soal *</label>
                    <textarea
                        id="questionText"
                        class="form-control"
                        required
                        placeholder="Masukkan teks pertanyaan..."
                        rows="4"></textarea>
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
                        <input
                            type="number"
                            id="points"
                            class="form-control"
                            value="1"
                            min="1"
                            max="100"
                            required />
                    </div>
                </div>

                <!-- Options for Multiple Choice & Checkbox -->
                <div id="optionsContainer">
                    <div class="form-group">
                        <label>Opsi Jawaban</label>
                        <div id="optionsList"></div>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline"
                            style="margin-top: 8px"
                            onclick="addOption()">
                            + Tambah Opsi
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Jawaban Benar (Pilih dari opsi di atas)</label>
                        <select id="correctAnswer" class="form-control" required></select>
                    </div>
                </div>

                <div class="form-group">
                    <label>URL Media (Gambar/Video) - Opsional</label>
                    <input type="url" id="mediaUrl" class="form-control" placeholder="https://..." />
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px">
                    <button type="button" class="btn btn-outline" onclick="closeQuestionModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Question -->
    <div id="viewQuestionModal" class="modal-overlay">
        <div class="modal" style="max-width: 600px">
            <div class="modal-header">
                <div class="modal-title">Detail Soal</div>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>

            <div id="viewQuestionContent" style="padding: 20px"></div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; flex-wrap: wrap; padding: 20px; border-top: 1px solid #eee">
                <button type="button" class="btn btn-sm" style="background: #eff6ff; color: #1e40af" onclick="editQuestion()">
                    ✏️ Edit
                </button>
                <button type="button" class="btn btn-sm" style="background: rgba(34, 197, 94, 0.1); color: #15803d" onclick="copyToExam()">
                    📋 Pakai di Ujian
                </button>
                <button type="button" class="btn btn-outline" onclick="closeViewModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal: Copy to Exam -->
    <div id="copyToExamModal" class="modal-overlay">
        <div class="modal" style="max-width: 500px">
            <div class="modal-header">
                <div class="modal-title">Pilih Ujian</div>
                <button class="modal-close" onclick="closeCopyModal()">×</button>
            </div>

            <div style="padding: 20px">
                <p style="margin-bottom: 16px; color: #64748b">
                    Pilih ujian yang ingin ditambahi dengan soal ini:
                </p>
                <div id="examsList"></div>
            </div>

            <div style="padding: 20px; border-top: 1px solid #eee; text-align: right">
                <button type="button" class="btn btn-outline" onclick="closeCopyModal()">Batal</button>
            </div>
        </div>
    </div>

    <script src="../js/teacher-layout.js"></script>
    <script>
        const csrfToken = document.getElementById('csrf-token')?.value || '<?php echo $csrf_token; ?>';
        let allQuestions = [];
        let currentQuestion = null;
        let currentViewQuestion = null;
        let optionCounter = 0;

        document.addEventListener("DOMContentLoaded", function() {
            loadQuestions();
        });

        function loadQuestions() {
            const searchQuery = document.getElementById("searchInput").value;
            const categoryFilter = document.getElementById("categoryFilter").value;

            let url = "../php/exam_api.php?action=get_bank_questions";
            if (searchQuery) url += "&search=" + encodeURIComponent(searchQuery);
            if (categoryFilter) url += "&category=" + encodeURIComponent(categoryFilter);

            fetch(url)
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        allQuestions = d.questions || [];
                        renderQuestions();
                    }
                })
                .catch((e) => console.error("Error loading questions:", e));
        }

        function renderQuestions() {
            const container = document.getElementById("questionsList");
            const emptyState = document.getElementById("emptyState");

            if (allQuestions.length === 0) {
                container.innerHTML = "";
                emptyState.style.display = "block";
                document.getElementById("questionCount").textContent = "Total: 0 soal";
                return;
            }

            emptyState.style.display = "none";

            container.innerHTML = allQuestions.map((q) => {
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
                    <div class="card question-card">
                        <div class="question-card-header">
                            <div style="flex: 1;">
                                <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                                    <span class="question-type-badge ${typeClass}">⭐ ${escapeHtml(typeLabel)}</span>
                                    <span class="difficulty-badge ${diffClass}">${escapeHtml(diffLabel)}</span>
                                    <span class="category-label">${escapeHtml(q.category)}</span>
                                </div>
                            </div>
                            <div class="question-meta" style="margin: 0;">
                                <span>Poin: <strong>${q.points}</strong></span>
                                <span>${new Date(q.created_at).toLocaleDateString("id-ID")}</span>
                            </div>
                        </div>
                        
                        <div class="question-text">${escapeHtml(q.question_text)}</div>
                        
                        <div class="question-actions">
                            <button class="action-btn btn-view" onclick="viewQuestion(${q.id})">👁️ Lihat</button>
                            <button class="action-btn btn-edit" onclick="editQuestionById(${q.id})">✏️ Edit</button>
                            <button class="action-btn btn-add-to-exam" onclick="showCopyToExamModal(${q.id})">📋 Pakai di Ujian</button>
                            <button class="action-btn btn-delete" onclick="deleteQuestion(${q.id})">🗑️ Hapus</button>
                        </div>
                    </div>
                `;
            }).join("");

            document.getElementById("questionCount").textContent = `Total: ${allQuestions.length} soal`;
        }

        function filterQuestions() {
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
            currentViewQuestion = null;
        }

        function closeCopyModal() {
            document.getElementById("copyToExamModal").classList.remove("active");
            currentViewQuestion = null;
        }

        function updateQuestionType() {
            const type = document.getElementById("questionType").value;
            const container = document.getElementById("optionsContainer");

            if (type === "essay") {
                container.style.display = "none";
            } else {
                container.style.display = "block";
            }
        }

        function addOption() {
            optionCounter++;
            const container = document.getElementById("optionsList");
            const div = document.createElement("div");
            div.style.cssText = "display: flex; gap: 8px; margin-bottom: 8px; align-items: center;";
            div.innerHTML = `
                <input type="text" class="form-control option-input" placeholder="Opsi ${optionCounter}" 
                       onchange="updateCorrectAnswerOptions()" style="flex: 1;">
                <button type="button" class="btn btn-sm" style="background: #fee2e2; color: #991b1b; padding: 6px 8px;" 
                        onclick="this.parentElement.remove(); updateCorrectAnswerOptions()">✕</button>
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
            const options = type !== "essay" ? getOptions() : [];
            const correctAnswer = type !== "essay" ? document.getElementById("correctAnswer").value : "";

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
                        alert(d.message || "Soal berhasil disimpan");
                        closeQuestionModal();
                        loadQuestions();
                    } else {
                        alert("Error: " + d.message);
                    }
                })
                .catch(() => alert("Terjadi kesalahan koneksi."));
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
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                        <span class="question-type-badge type-${q.question_type}">⭐ ${escapeHtml(typeLabel)}</span>
                        <span class="difficulty-badge diff-${q.difficulty}">${escapeHtml(diffLabel)}</span>
                        <span class="category-label">${escapeHtml(q.category)}</span>
                    </div>
                    
                    <h3 style="color: #1e293b; font-size: 1.1rem; margin-bottom: 12px;">${escapeHtml(q.question_text)}</h3>
                    
                    <div style="display: flex; gap: 16px; color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">
                        <span>Poin: <strong>${q.points}</strong></span>
                        <span>Dibuat: ${new Date(q.created_at).toLocaleDateString("id-ID")}</span>
                    </div>
            `;

            // Parse options if it's a string (JSON from database)
            let optionsArray = q.options;
            if (typeof optionsArray === 'string') {
                try {
                    optionsArray = JSON.parse(optionsArray);
                } catch (e) {
                    optionsArray = [];
                }
            }

            if (optionsArray && optionsArray.length > 0) {
                content += '<h4 style="color: #475569; margin-bottom: 10px;">Opsi Jawaban:</h4><ul style="list-style: none; padding: 0;">';
                optionsArray.forEach((opt, idx) => {
                    const isCorrect = q.correct_answer == idx;
                    content += `<li style="padding: 8px; background: ${isCorrect ? "#dcfce7" : "#f8fafc"}; margin-bottom: 6px; border-radius: 6px; border-left: 3px solid ${isCorrect ? "#22c55e" : "#e2e8f0"};">
                        ${isCorrect ? "✓ " : "  "}${escapeHtml(opt)}
                    </li>`;
                });
                content += "</ul>";
            }

            if (q.media_url) {
                content += `<p style="margin-top: 16px;"><strong>Media:</strong> <a href="${escapeHtml(q.media_url)}" target="_blank">${escapeHtml(q.media_url)}</a></p>`;
            }

            content += "</div>";
            document.getElementById("viewQuestionContent").innerHTML = content;
        }

        function editQuestion() {
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

            // Clear and re-populate options
            optionCounter = 0;
            document.getElementById("optionsList").innerHTML = "";

            // Parse options if it's a string (JSON from database)
            let optionsArray = q.options;
            if (typeof optionsArray === 'string') {
                try {
                    optionsArray = JSON.parse(optionsArray);
                } catch (e) {
                    optionsArray = [];
                }
            }

            if (optionsArray && optionsArray.length > 0) {
                optionsArray.forEach((opt) => {
                    optionCounter++;
                    const container = document.getElementById("optionsList");
                    const div = document.createElement("div");
                    div.style.cssText = "display: flex; gap: 8px; margin-bottom: 8px; align-items: center;";
                    div.innerHTML = `
                        <input type="text" class="form-control option-input" value="${escapeHtml(opt)}" 
                               onchange="updateCorrectAnswerOptions()" style="flex: 1;">
                        <button type="button" class="btn btn-sm" style="background: #fee2e2; color: #991b1b; padding: 6px 8px;" 
                                onclick="this.parentElement.remove(); updateCorrectAnswerOptions()">✕</button>
                    `;
                    container.appendChild(div);
                });
            }

            updateQuestionType();
            updateCorrectAnswerOptions();

            if (q.correct_answer !== null && q.correct_answer !== undefined) {
                document.getElementById("correctAnswer").value = q.correct_answer;
            }

            document.getElementById("questionModal").classList.add("active");
        }

        function editQuestionById(id) {
            const q = allQuestions.find((x) => x.id == id);
            if (q) {
                currentViewQuestion = q;
                editQuestion();
            }
        }

        function deleteQuestion(id) {
            if (confirm("Apakah Anda yakin ingin menghapus soal ini?")) {
                fetch("../php/exam_api.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            action: "delete_bank_question",
                            id: id,
                            csrf_token: csrfToken
                        }),
                    })
                    .then((r) => r.json())
                    .then((d) => {
                        if (d.success) {
                            loadQuestions();
                        } else {
                            alert("Error: " + d.message);
                        }
                    });
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
                            <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid var(--primary-light);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <strong style="color: #1e293b;">${escapeHtml(exam.name)}</strong>
                                    <span style="font-size: 0.8rem; color: #64748b;">${escapeHtml(exam.class)}</span>
                                </div>
                                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">${escapeHtml(exam.subject)}</p>
                                <button class="btn btn-sm" style="background: rgba(34, 197, 94, 0.1); color: #15803d; width: 100%;" 
                                        onclick="confirmCopyQuestion(${bankQuestionId}, ${exam.id})">
                                    ✓ Pakai Soal Ini
                                </button>
                            </div>
                        `).join("");
                        document.getElementById("copyToExamModal").classList.add("active");
                    } else {
                        alert("Anda belum memiliki ujian. Buat ujian terlebih dahulu.");
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
                        alert(d.message || "Soal berhasil ditambahkan ke ujian");
                        closeCopyModal();
                    } else {
                        alert("Error: " + d.message);
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
