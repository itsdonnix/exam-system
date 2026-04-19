<?php
require_once 'includes/init.php';
$activePage = 'create-exam';

// Generate CSRF token for publish exam
require_once '../includes/csrf.php';
$csrf_token = generateCSRFToken();

// Fetch subjects and classes server-side (reduce API calls)
$subjects = [];
$classes = [];

try {
    $db = getDB();
    // Fetch subjects
    $stmt = $db->query("SELECT id, name, category FROM subjects ORDER BY name ASC");
    $subjects = $stmt->fetchAll();
    
    // Fetch classes
    $stmt = $db->query("SELECT name FROM classes ORDER BY name ASC");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("[create-exam.php] Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buat Ujian — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        /* === STEP INDICATOR === */
        .step-indicator {
            display: flex;
            gap: 0;
            margin-bottom: 1.5em;
            overflow-x: auto;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 0.75em 0.5em;
            font-size: 0.82rem;
            font-weight: 600;
            color: #94a3b8;
            border-bottom: 3px solid #e2e8f0;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .step:hover { color: #64748b; }
        .step.active { color: var(--primary); border-bottom-color: var(--primary); }
        .step.done { color: var(--success); border-bottom-color: var(--success); }
        .step.error { color: var(--danger); border-bottom-color: var(--danger); }
        .step-content { display: none; }
        .step-content.active { display: block; }

        /* === QUESTION BUILDER === */
        .question-builder {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.5em;
            margin-bottom: 1em;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-light);
            position: relative;
        }
        .question-builder:hover { box-shadow: 0 4px 16px rgba(26, 60, 110, 0.12); }
        .q-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1em;
            gap: 0.75em;
            flex-wrap: wrap;
        }
        .q-header-left { display: flex; align-items: center; gap: 0.75em; flex-wrap: wrap; }
        .q-header-actions { display: flex; gap: 0.5em; flex-shrink: 0; }
        .q-number {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary-light);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .drag-handle { cursor: grab; color: #94a3b8; font-size: 1.2rem; padding: 0.25em; }
        .type-selector { display: flex; gap: 0.5em; flex-wrap: wrap; }
        .type-btn {
            padding: 0.375em 0.875em;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            font-family: "Poppins", sans-serif;
            transition: all 0.2s;
        }
        .type-btn:hover { border-color: #cbd5e1; }
        .type-btn.active { border-color: var(--primary-light); background: #eff6ff; color: var(--primary); }

        /* === OPTION ROWS === */
        .option-row {
            display: flex;
            align-items: center;
            gap: 0.625em;
            margin-bottom: 0.625em;
        }
        .option-row input[type="text"] { flex: 1; }
        .option-row input[type="radio"],
        .option-row input[type="checkbox"] { width: 1.125rem; height: 1.125rem; accent-color: var(--success); flex-shrink: 0; }
        .option-label { font-size: 0.85rem; font-weight: 600; color: #64748b; width: 1.5rem; flex-shrink: 0; }
        .correct-label { font-size: 0.75rem; color: var(--success); font-weight: 600; }
        .add-option-btn {
            background: none;
            border: 2px dashed #cbd5e1;
            color: #94a3b8;
            padding: 0.5em;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.82rem;
            width: 100%;
            margin-top: 0.25em;
            transition: all 0.2s;
            font-family: "Poppins", sans-serif;
        }
        .add-option-btn:hover { border-color: var(--primary-light); color: var(--primary-light); }
        .opt-image-preview-wrap { display: flex; align-items: center; gap: 0.3125em; flex-shrink: 0; }
        .opt-image-preview { max-height: 3.125rem; max-width: 3.125rem; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); }
        .opt-remove-btn { background: #fee2e2; color: #991b1b; padding: 0.375em 0.625em; flex-shrink: 0; }
        .opt-upload-label { padding: 0.375em; margin-bottom: 0; cursor: pointer; flex-shrink: 0; }

        /* === INLINE DELETE CONFIRM === */
        .btn-delete-confirm {
            background: #dc2626 !important;
            color: #fff !important;
            animation: pulse 0.8s infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }

        /* === LAYOUT === */
        .page-row { display: flex; gap: 1.5em; align-items: flex-start; }
        .questions-container { flex: 1; min-width: 0; }
        .empty-state-builder {
            text-align: center;
            padding: 3em 2em;
            background: #f8fafc;
            border-radius: var(--radius);
            border: 2px dashed #cbd5e1;
        }
        .empty-state-builder-icon { font-size: 3.5rem; margin-bottom: 1em; }
        .step-nav { display: flex; justify-content: space-between; margin-top: 1.25em; }
        .header-actions { display: flex; gap: 0.5em; }
        .exam-code-row { display: flex; gap: 0.5em; }

        /* === STICKY SIDEBAR === */
        .sticky-sidebar {
            position: sticky;
            top: 5rem;
            width: 17.5rem;
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.25em;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            flex-shrink: 0;
        }
        .sidebar-section-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75em;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .sticky-add-btns { display: flex; flex-direction: column; gap: 0.625em; }
        .sticky-add-btns button { text-align: left; justify-content: flex-start; padding: 0.75em 1em; font-weight: 600; }
        .sidebar-tips-box {
            background: #f1f5f9;
            padding: 0.9375em;
            border-radius: 10px;
        }
        .sidebar-tips-box ul {
            padding: 0 0 0 1em;
            margin: 0;
            font-size: 0.78rem;
            color: #475569;
            display: flex;
            flex-direction: column;
            gap: 0.5em;
        }
        .sidebar-divider { margin: 1.25em 0; }

        /* === SETTINGS CHECKBOXES === */
        .setting-checkbox {
            display: flex;
            align-items: center;
            gap: 0.625em;
            cursor: pointer;
            text-transform: none;
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 0.625em;
        }
        .setting-checkbox input[type="checkbox"] { width: 1.125rem; height: 1.125rem; accent-color: var(--primary); }

        /* === STEP 1 FIELD ERROR === */
        .form-control.field-error { border-color: var(--danger); }
        .field-error-msg { color: var(--danger); font-size: 0.75rem; margin-top: 0.25em; display: none; }
        .field-error-msg.visible { display: block; }

        /* === UPLOAD AREA === */
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 1.5em;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-area:hover { border-color: var(--primary-light); background: #eff6ff; }
        .upload-area input { display: none; }

        /* === PREVIEW === */
        .preview-q-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25em;
            margin-bottom: 1.25em;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .preview-q-number {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--primary-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.625em;
        }
        .preview-q-text {
            font-size: 1.05rem;
            font-weight: 500;
            margin-bottom: 0.9375em;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .preview-opt-item {
            padding: 0.625em 0.9375em;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 0.5em;
            display: flex;
            align-items: center;
            gap: 0.625em;
            font-size: 0.88rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .preview-opt-item.correct {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
            font-weight: 600;
        }
        .preview-essay-box {
            padding: 0.9375em;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            margin-top: 0.9375em;
        }
        .preview-essay-box p { white-space: pre-wrap; word-break: break-word; }
        .preview-nav { display: flex; gap: 0.5em; }

        /* === BANK SOAL ITEMS (in modal) === */
        .bank-item {
            padding: 1em;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 3px solid var(--primary-light);
            margin-bottom: 0.75em;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75em;
        }
        .bank-item-badges { display: flex; gap: 0.5em; margin-bottom: 0.5em; flex-wrap: wrap; }
        .bank-item-badge {
            font-size: 0.72rem;
            padding: 0.1875em 0.625em;
            border-radius: 12px;
            font-weight: 600;
        }
        .bank-item-badge.type { background: rgba(59, 130, 246, 0.1); color: #1e40af; }
        .bank-item-badge.cat { background: rgba(107, 114, 128, 0.1); color: #374151; }
        .bank-item-text { margin: 0 0 0.5em 0; color: #1e293b; font-weight: 500; line-height: 1.4; }
        .bank-item-meta { margin: 0; font-size: 0.78rem; color: #64748b; }
        .bank-item-use {
            background: rgba(34, 197, 94, 0.1);
            color: #15803d;
            border: none;
            cursor: pointer;
            padding: 0.5em 0.875em;
            border-radius: 6px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
            font-family: "Poppins", sans-serif;
            font-size: 0.82rem;
        }
        .bank-item-use:hover { background: rgba(34, 197, 94, 0.2); }

        /* === MEDIA PREVIEW === */
        .media-preview-grid {
            margin-top: 0.625em;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(6.25rem, 1fr));
            gap: 0.625em;
        }
        .media-preview-item { position: relative; }
        .media-preview-item img {
            width: 100%;
            height: 5rem;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .media-preview-remove {
            position: absolute;
            top: -0.3125em;
            right: -0.3125em;
            border-radius: 50%;
            width: 1.25rem;
            height: 1.25rem;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.625rem;
        }
        .q-upload-status { display: none; color: var(--primary-light); font-size: 0.78rem; }

        /* === PUBLISH LOADING === */
        .btn-loading { opacity: 0.7; pointer-events: none; }
        .btn-loading::after {
            content: "";
            display: inline-block;
            width: 1em;
            height: 1em;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 0.5em;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* === MODAL OVERLAY === */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 14px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
        }

        /* === RESPONSIVE === */
        @media (max-width: 992px) {
            .page-row { flex-direction: column; }
            .sticky-sidebar { position: static; width: 100%; margin-bottom: 1.25em; }
            .sticky-add-btns { flex-direction: row; flex-wrap: wrap; }
            .sticky-add-btns button { flex: 1; min-width: 8.75rem; }
        }
        @media (max-width: 768px) {
            .type-selector { gap: 0.375em; }
            .type-btn { font-size: 0.72rem; padding: 0.3125em 0.625em; }
            .q-header { flex-direction: column; align-items: flex-start; }
            .q-header-actions { width: 100%; }
            .q-header-actions .btn { flex: 1; justify-content: center; }
            .form-row { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 480px) {
            .step { font-size: 0.72rem; padding: 0.625em 0.25em; }
            .question-builder { padding: 1em; }
            .option-row { flex-wrap: wrap; }
            .option-row input[type="text"] { min-width: 0; }
            .opt-image-preview-wrap,
            .opt-upload-label { display: none !important; }
        }

        /* === PRINT === */
        @media print {
            .step-indicator, .step-nav, .header-actions,
            .sticky-sidebar, .q-header-actions, .type-selector,
            .empty-state-builder, .add-option-btn, .upload-area,
            .form-group:has(.upload-area), #bankSoalModal { display: none !important; }
            .question-builder { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
            .main-content { padding: 0 !important; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">➕ Buat Ujian Baru</div>
                <div class="page-subtitle">Isi informasi ujian dan tambahkan soal</div>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="saveDraft()">💾 Simpan Draft</button>
                <button class="btn btn-success" id="publishBtn" onclick="publishExam()">🚀 Publikasikan</button>
            </div>
        </div>

        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" id="subjects-data" value='<?php echo htmlspecialchars(json_encode($subjects)); ?>'>
        <input type="hidden" id="classes-data" value='<?php echo htmlspecialchars(json_encode($classes)); ?>'>

        <div class="step-indicator">
            <div class="step active" id="step-1-tab" onclick="goStep(1)">1. Info Ujian</div>
            <div class="step" id="step-2-tab" onclick="goStep(2)">2. Soal Ujian</div>
            <div class="step" id="step-3-tab" onclick="goStep(3)">3. Pengaturan</div>
            <div class="step" id="step-4-tab" onclick="goStep(4)">4. Preview</div>
        </div>

        <!-- STEP 1 -->
        <div class="step-content active" id="step-1">
            <div class="card">
                <div class="page-title" style="font-size: 1.1rem; margin-bottom: 1.25em">📋 Informasi Ujian</div>
                <div class="form-group">
                    <label>Nama Ujian *</label>
                    <input type="text" class="form-control" id="exam-name" placeholder="Contoh: Matematika — Bab 5: Integral" />
                    <div class="field-error-msg" id="err-exam-name">Nama ujian wajib diisi</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Target Jenjang *</label>
                        <select class="form-control" id="exam-class">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori Mapel *</label>
                        <select class="form-control" id="exam-category" onchange="filterSubjects()">
                            <option value="Umum">Mapel Umum</option>
                            <option value="IPA">Mapel Pilihan (IPA)</option>
                            <option value="IPS">Mapel Pilihan (IPS)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Mata Pelajaran *</label>
                        <select class="form-control" id="exam-subject">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                        </select>
                        <div class="field-error-msg" id="err-exam-subject">Pilih mata pelajaran</div>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Ujian *</label>
                        <input type="date" class="form-control" id="exam-date" />
                        <div class="field-error-msg" id="err-exam-date">Tanggal ujian wajib diisi</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Waktu Mulai *</label>
                        <input type="time" class="form-control" id="exam-start" value="08:00" />
                    </div>
                    <div class="form-group">
                        <label>Durasi (Menit) *</label>
                        <input type="number" class="form-control" id="exam-duration" value="90" min="10" max="300" />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jumlah Soal Ditampilkan</label>
                        <input type="number" class="form-control" id="exam-qcount" value="40" min="1" max="100" />
                    </div>
                    <div class="form-group"></div>
                </div>
                <div class="form-group">
                    <label>Deskripsi / Petunjuk Ujian</label>
                    <textarea class="form-control" id="exam-desc" rows="3" placeholder="Petunjuk pengerjaan ujian untuk siswa...">Kerjakan soal berikut dengan teliti. Pilih jawaban yang paling tepat. Dilarang menggunakan kalkulator.</textarea>
                </div>
                <div class="form-group">
                    <label>Kode Ujian (otomatis)</label>
                    <div class="exam-code-row">
                        <input type="text" class="form-control" id="exam-code" readonly style="background: #f1f5f9; font-weight: 700; letter-spacing: 2px;" />
                        <button class="btn btn-outline" id="generate-code-btn">🔄 Generate</button>
                    </div>
                </div>
                <div class="step-nav">
                    <span></span>
                    <button class="btn btn-primary" onclick="goStep(2)">Lanjut: Tambah Soal →</button>
                </div>
            </div>
        </div>

        <!-- STEP 2 -->
        <div class="step-content" id="step-2">
            <div class="page-row">
                <div class="questions-container">
                    <div style="margin-bottom: 1.25em; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div class="page-title" style="font-size: 1.1rem">📝 Soal Ujian</div>
                            <div class="page-subtitle" id="q-count-label">0 soal ditambahkan</div>
                        </div>
                        <p style="font-size: 0.8rem; color: #94a3b8; font-weight: 500">Soal terbaru akan muncul di paling atas</p>
                    </div>
                    <div id="questions-builder"></div>
                    <div class="empty-state-builder" id="empty-state">
                        <div class="empty-state-builder-icon">✍️</div>
                        <h3 style="margin-bottom: 0.5em; color: #1e293b">Mulai membuat soal!</h3>
                        <p style="color: #64748b; font-weight: 400; max-width: 18.75rem; margin: 0 auto;">Pilih tipe soal di panel kanan untuk menambahkan soal baru ke ujian ini.</p>
                    </div>
                    <div class="step-nav">
                        <button class="btn btn-outline" onclick="goStep(1)">← Kembali</button>
                        <button class="btn btn-primary" onclick="goStep(3)">Lanjut: Pengaturan →</button>
                    </div>
                </div>
                <aside class="sticky-sidebar">
                    <div class="sidebar-section-title">
                        <span>➕ Tambah Soal Baru</span>
                    </div>
                    <div class="sticky-add-btns">
                        <button class="btn btn-primary btn-sm" onclick="addQuestion('multiple')"><span style="margin-right: 8px">🔘</span> Pilihan Ganda</button>
                        <button class="btn btn-info btn-sm" onclick="addQuestion('checkbox')"><span style="margin-right: 8px">☑️</span> PG Kompleks</button>
                        <button class="btn btn-secondary btn-sm" onclick="addQuestion('essay')"><span style="margin-right: 8px">📝</span> Esai / Uraian</button>
                        <button class="btn btn-outline btn-sm" onclick="addQuestion('truefalse')" style="color: var(--success); border-color: #d1fae5; background: #f0fdf4;">
                            <span style="margin-right: 8px">✔️</span> Benar/Salah
                        </button>
                    </div>
                    <hr class="sidebar-divider" />
                    <div class="sidebar-section-title">
                        <span>📚 Bank Soal</span>
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="openBankSoalModal()" style="width: 100%; text-align: left; justify-content: flex-start; border-color: var(--primary-light); color: var(--primary-light); background: #eff6ff;">
                        <span style="margin-right: 8px">📋</span> Ambil dari Bank Soal
                    </button>
                    <hr class="sidebar-divider" />
                    <div class="sidebar-tips-box">
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 10px;">Tips Penggunaan:</div>
                        <ul>
                            <li>Gunakan <b>Duplikat</b> untuk membuat soal serupa.</li>
                            <li>Soal baru selalu muncul di <b>paling atas</b> untuk mempermudah fokus.</li>
                            <li>Gambar soal bisa lebih dari satu (multiple upload).</li>
                        </ul>
                        <hr class="sidebar-divider" />
                        <div class="sidebar-section-title">
                            <span>🤖 AI Import</span>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="handleAIImportClick()" style="width: 100%; text-align: left; justify-content: flex-start; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                            <span style="margin-right: 8px">✨</span> Impor Soal dengan AI
                        </button>
                        <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 8px">Ekstrak otomatis soal dari PDF, DOCX, atau teks</p>
                    </div>
                </aside>
            </div>
        </div>

        <!-- STEP 3 -->
        <div class="step-content" id="step-3">
            <div class="card">
                <div class="page-title" style="font-size: 1.1rem; margin-bottom: 1.25em">⚙️ Pengaturan Keamanan & Ujian</div>
                <div class="form-group">
                    <label>Pengacakan Soal</label>
                    <div style="margin-top: 0.5em;">
                        <label class="setting-checkbox"><input type="checkbox" id="shuffle-questions" checked /> Acak urutan soal untuk setiap siswa</label>
                        <label class="setting-checkbox"><input type="checkbox" id="shuffle-options" checked /> Acak urutan pilihan jawaban</label>
                    </div>
                </div>
                <hr class="divider" />
                <div class="form-group">
                    <label>Fitur Keamanan Anti-Menyontek</label>
                    <div style="margin-top: 0.5em;">
                        <label class="setting-checkbox"><input type="checkbox" id="sec-fullscreen" checked /> Wajib mode layar penuh (fullscreen)</label>
                        <label class="setting-checkbox"><input type="checkbox" id="sec-shortcuts" checked /> Blokir keyboard shortcut (Ctrl+T, Ctrl+N, dll.)</label>
                        <label class="setting-checkbox"><input type="checkbox" id="sec-copy" checked /> Blokir copy-paste</label>
                        <label class="setting-checkbox"><input type="checkbox" id="sec-tab" checked /> Deteksi perpindahan tab/jendela</label>
                        <label class="setting-checkbox"><input type="checkbox" id="sec-notify" checked /> Notifikasi pengawas jika ada pelanggaran</label>
                        <label class="setting-checkbox"><input type="checkbox" id="sec-autostop" checked /> Hentikan ujian otomatis setelah 3 pelanggaran</label>
                    </div>
                </div>
                <hr class="divider" />
                <div class="form-row">
                    <div class="form-group">
                        <label>Nilai Kelulusan (KKM)</label>
                        <input type="number" class="form-control" id="passing-grade" value="75" min="0" max="100" />
                    </div>
                    <div class="form-group">
                        <label>Maks. Pelanggaran</label>
                        <input type="number" class="form-control" id="max-violations" value="3" min="1" max="10" />
                    </div>
                </div>
                <div class="form-group">
                    <label>Tampilkan Hasil Setelah Ujian</label>
                    <select class="form-control" id="show-results-setting">
                        <option value="direct_submit">Langsung setelah submit</option>
                        <option value="after_all_students">Setelah semua siswa selesai</option>
                        <option value="manual_by_teacher">Manual oleh guru</option>
                        <option value="never">Jangan tampilkan</option>
                    </select>
                </div>
                <div class="step-nav">
                    <button class="btn btn-outline" onclick="goStep(2)">← Kembali</button>
                    <button class="btn btn-primary" onclick="goStep(4)">Lanjut: Preview →</button>
                </div>
            </div>
        </div>

        <!-- STEP 4 -->
        <div class="step-content" id="step-4">
            <div class="card">
                <div class="page-title" style="font-size: 1.1rem; margin-bottom: 1.25em">👁️ Preview Ujian</div>
                <div id="preview-content"></div>
                <div class="step-nav">
                    <button class="btn btn-outline" onclick="goStep(3)">← Kembali</button>
                    <div class="preview-nav">
                        <button class="btn btn-outline" onclick="saveDraft()">💾 Simpan Draft</button>
                        <button class="btn btn-success" id="publishBtn2" onclick="publishExam()">🚀 Publikasikan Ujian</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal: Bank Soal -->
    <div id="bankSoalModal" class="modal-overlay">
        <div class="modal" style="max-width: 50rem; padding: 0;">
            <div class="modal-header" style="padding: 1.25em 1.5em; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
                <div class="modal-title" style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">📚 Ambil dari Bank Soal</div>
                <button class="modal-close" onclick="closeBankSoalModal()" style="background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #64748b;">×</button>
            </div>
            <div style="padding: 0 1.5em; margin-top: 1em;">
                <div style="margin-bottom: 1em; display: flex; gap: 0.75em; flex-wrap: wrap;">
                    <input type="text" id="bankSearchInput" class="form-control" placeholder="Cari soal..." oninput="debouncedBankSearch()" style="flex: 1; min-width: 12.5rem;" />
                    <select id="bankCategoryFilter" class="form-control" onchange="loadBankQuestions()" style="max-width: 12.5rem;">
                        <option value="">Semua Kategori</option>
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
            <div id="bankQuestionsList" style="padding: 0 1.5em; max-height: 31.25rem; overflow-y: auto;">
                <div style="text-align: center; color: #94a3b8; padding: 2em;"><p>Memuat soal...</p></div>
            </div>
            <div style="padding: 1.25em 1.5em; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                <button class="btn btn-outline" onclick="closeBankSoalModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="../js/teacher-layout.js"></script>
    <script src="../js/toast.js"></script>
    <script defer src="js/ai-import.js"></script>
    <script>
        const csrfToken = document.getElementById('csrf-token').value;
        const subjectsData = JSON.parse(document.getElementById('subjects-data').value);
        const classesData = JSON.parse(document.getElementById('classes-data').value);
        let allSubjects = subjectsData;
        let allClasses = classesData;
        let questionCount = 0;
        let questions = [];
        let bankQuestions = [];
        let deleteTimers = {};
        let bankDebounceTimer = null;

        // === GENERATE CODE ===
        function generateCode() {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            let code = "";
            for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
            document.getElementById("exam-code").value = code;
        }

        function filterSubjects() {
            const catEl = document.getElementById("exam-category");
            const select = document.getElementById("exam-subject");
            if (!catEl || !select) return;
            const category = catEl.value;
            const filtered = allSubjects.filter((s) => s.category === category);

            select.innerHTML = filtered.map((s) => `<option value="${s.name}">${s.name}</option>`).join("");
            if (filtered.length === 0) {
                select.innerHTML = '<option value="">Tidak ada mapel di kategori ini</option>';
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const dateEl = document.getElementById("exam-date");
            if (dateEl) dateEl.valueAsDate = new Date();
            document.getElementById("generate-code-btn").addEventListener("click", generateCode);
            generateCode();
            filterSubjects();
        });

        // === STEP NAVIGATION WITH VALIDATION ===
        function validateStep(n) {
            if (n <= 1) return true;
            const fields = [
                { id: 'exam-name', errId: 'err-exam-name' },
                { id: 'exam-subject', errId: 'err-exam-subject' },
                { id: 'exam-date', errId: 'err-exam-date' },
            ];
            let valid = true;
            fields.forEach(f => {
                const el = document.getElementById(f.id);
                const err = document.getElementById(f.errId);
                if (!el || !err) return;
                if (!el.value.trim()) {
                    el.classList.add('field-error');
                    err.classList.add('visible');
                    valid = false;
                } else {
                    el.classList.remove('field-error');
                    err.classList.remove('visible');
                }
            });
            if (!valid) {
                showToast("Lengkapi semua field wajib terlebih dahulu", "error");
                return false;
            }
            return true;
        }

        function goStep(n) {
            const currentStep = [...document.querySelectorAll('.step-content.active')][0];
            const currentIdx = currentStep ? parseInt(currentStep.id.split('-')[1]) : 1;
            if (n > currentIdx && !validateStep(n)) return;

            for (let i = 1; i <= 4; i++) {
                document.getElementById(`step-${i}`).classList.remove("active");
                document.getElementById(`step-${i}-tab`).classList.remove("active", "done", "error");
            }
            document.getElementById(`step-${n}`).classList.add("active");
            document.getElementById(`step-${n}-tab`).classList.add("active");
            for (let i = 1; i < n; i++) {
                document.getElementById(`step-${i}-tab`).classList.add("done");
            }
            if (n === 4) renderPreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function reindexQuestions() {
            const cards = document.querySelectorAll(".question-builder");
            cards.forEach((card, index) => {
                const numLabel = card.querySelector(".q-number");
                if (numLabel) numLabel.textContent = `Soal ${index + 1}`;
            });
            const count = cards.length;
            document.getElementById("q-count-label").textContent = count + " soal ditambahkan";
            document.getElementById("empty-state").style.display = count === 0 ? "block" : "none";
        }

        function addQuestion(type, existingData = null) {
            questionCount++;
            const qId = "q-" + questionCount;

            const newQuestion = existingData ? {
                ...JSON.parse(JSON.stringify(existingData)),
                id: qId,
            } : {
                id: qId,
                type: type,
                text: "",
                media_url: [],
                options: [],
                correct: "",
                correct_answers_checkbox: [],
                points: 1,
                difficulty: "sedang",
            };

            questions.unshift(newQuestion);

            const div = document.createElement("div");
            div.className = "question-builder";
            div.id = qId;
            div.innerHTML = `
                <div class="q-header">
                    <div class="q-header-left">
                        <span class="drag-handle">⠿</span>
                        <span class="q-number">Soal</span>
                        <div class="type-selector">
                            <button class="type-btn ${newQuestion.type === "multiple" ? "active" : ""}" onclick="changeType('${qId}','multiple')" data-type="multiple">Pilihan Ganda</button>
                            <button class="type-btn ${newQuestion.type === "checkbox" ? "active" : ""}" onclick="changeType('${qId}','checkbox')" data-type="checkbox">PG Kompleks</button>
                            <button class="type-btn ${newQuestion.type === "essay" ? "active" : ""}" onclick="changeType('${qId}','essay')" data-type="essay">Esai</button>
                            <button class="type-btn ${newQuestion.type === "truefalse" ? "active" : ""}" onclick="changeType('${qId}','truefalse')" data-type="truefalse">Benar/Salah</button>
                        </div>
                    </div>
                    <div class="q-header-actions">
                        <button class="btn btn-outline btn-sm" onclick="duplicateQuestion('${qId}')" title="Duplikat Soal">👯 Duplikat</button>
                        <button class="btn btn-danger btn-sm" id="del-btn-${qId}" onclick="removeQuestion('${qId}')">🗑️ Hapus</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Teks Soal *</label>
                    <textarea class="form-control" placeholder="Tulis pertanyaan di sini..." rows="3" oninput="updateQuestionData('${qId}', 'text', this.value)">${newQuestion.text || ''}</textarea>
                </div>

                <div class="form-group">
                    <label>Gambar Soal (Opsional)</label>
                    <div style="display:flex; gap:0.625em; align-items:center">
                        <input type="file" class="form-control" accept="image/*" onchange="uploadQuestionMedia(this, 0)" style="flex:1" multiple>
                        <input type="hidden" class="q-media" value='${JSON.stringify(newQuestion.media_url)}'>
                    </div>
                    <div class="media-preview-grid q-media-preview"></div>
                    <small class="q-upload-status">⌛ Sedang mengunggah...</small>
                </div>

                <div id="${qId}-options">
                    ${newQuestion.type === "multiple" || newQuestion.type === "checkbox" ? renderMultipleOptions(qId, newQuestion.type, newQuestion.options, newQuestion.correct, newQuestion.correct_answers_checkbox) : ''}
                    ${newQuestion.type === "essay" ? renderEssayOptions(qId, newQuestion.correct) : ''}
                    ${newQuestion.type === "truefalse" ? renderTrueFalseOptions(qId, newQuestion.correct) : ''}
                </div>

                <div class="form-row" style="margin-top:0.75em">
                    <div class="form-group">
                        <label>Bobot Nilai</label>
                        <input type="number" class="form-control q-points" value="${parseInt(newQuestion.points) || 1}" min="1" max="100" oninput="updateQuestionData('${qId}', 'points', parseInt(this.value) || 1)">
                    </div>
                    <div class="form-group">
                        <label>Tingkat Kesulitan</label>
                        <select class="form-control" onchange="updateQuestionData('${qId}', 'difficulty', this.value)">
                            <option value="mudah" ${newQuestion.difficulty === "mudah" ? "selected" : ""}>Mudah</option>
                            <option value="sedang" ${newQuestion.difficulty === "sedang" ? "selected" : ""}>Sedang</option>
                            <option value="sulit" ${newQuestion.difficulty === "sulit" ? "selected" : ""}>Sulit</option>
                        </select>
                    </div>
                </div>
            `;

            const builder = document.getElementById("questions-builder");
            builder.prepend(div);

            if (existingData && (newQuestion.type === "multiple" || newQuestion.type === "checkbox")) {
                const optContainer = div.querySelector(".options-container");
                if (optContainer) {
                    optContainer.innerHTML = "";
                    const letters = ["A", "B", "C", "D", "E", "F", "G", "H"];
                    newQuestion.options.forEach((opt, i) => {
                        const row = document.createElement("div");
                        row.className = "option-row";
                        row.dataset.index = i;
                        const inputType = newQuestion.type === "checkbox" ? "checkbox" : "radio";
                        const isChecked = newQuestion.type === "checkbox"
                            ? newQuestion.correct_answers_checkbox.includes(i.toString())
                            : newQuestion.correct === i.toString();
                        const optText = opt && opt.text ? opt.text : "";
                        const optImg = opt && opt.image ? opt.image : "";
                        const imgStyle = optImg ? "display:flex" : "display:none";

                        row.innerHTML = `
                            <input type="${inputType}" name="${qId}-correct" value="${i}" ${isChecked ? "checked" : ""} onchange="updateOptionSelection('${qId}', ${i}, this.checked)">
                            <span class="option-label">${letters[i] || "?"}.</span>
                            <input type="text" class="form-control option-text" placeholder="Pilihan" value="${optText.replace(/"/g, '&quot;')}" oninput="updateOptionText('${qId}', ${i}, this.value)">
                            <div class="opt-image-preview-wrap" style="${imgStyle}">
                                <img src="${optImg ? "../" + optImg : ""}" class="opt-image-preview" alt="">
                                <button type="button" class="opt-remove-btn" onclick="removeOptionMedia(this)">✕</button>
                            </div>
                            <input type="hidden" class="opt-image-url" value="${optImg || ""}">
                            <label class="opt-upload-label btn-sm btn-outline">
                                🖼️
                                <input type="file" accept="image/*" style="display:none" onchange="uploadOptionMedia(this, '${qId}', ${i})">
                            </label>
                            <button class="opt-remove-btn" onclick="this.closest('.option-row').remove()">✕</button>
                        `;
                        optContainer.appendChild(row);
                    });
                }
            }

            renderMediaPreviews(div);
            reindexQuestions();
            if (div.querySelector("textarea")) div.querySelector("textarea").focus();
        }

        function renderMultipleOptions(qId, type = "multiple", existingOptions = [], correctAnswer = "", correctAnswersCheckbox = []) {
            const letters = ["A", "B", "C", "D", "E", "F", "G", "H"];
            const inputType = type === "checkbox" ? "checkbox" : "radio";
            const optionsToRender = existingOptions && existingOptions.length > 0 ? existingOptions : [{ text: "", image: null }, { text: "", image: null }, { text: "", image: null }, { text: "", image: null }];

            return `
                <div class="form-group">
                    <label>Pilihan Jawaban <span class="correct-label">(${inputType === "radio" ? "●" : "■"} = Jawaban Benar)</span></label>
                    <div class="options-container">
                        ${optionsToRender.map((opt, i) => {
                            const optText = typeof opt === "string" ? opt : opt && opt.text ? opt.text : "";
                            const optImage = opt && opt.image ? opt.image : "";
                            let isChecked = false;
                            if (type === "checkbox") {
                                isChecked = correctAnswersCheckbox && Array.isArray(correctAnswersCheckbox) && correctAnswersCheckbox.includes(i.toString());
                            } else {
                                isChecked = correctAnswer === i.toString() || correctAnswer === i;
                            }
                            const imgDisplay = optImage ? "flex" : "none";
                            const imgSrc = optImage ? (optImage.startsWith("..") ? optImage : "../" + optImage) : "";
                            return `
                                <div class="option-row" data-index="${i}">
                                    <input type="${inputType}" name="${qId}-correct" value="${i}" title="Tandai sebagai jawaban benar" ${isChecked ? "checked" : ""} onchange="updateOptionSelection('${qId}', ${i}, this.checked)">
                                    <span class="option-label">${letters[i] || "?"}.</span>
                                    <input type="text" class="form-control option-text" placeholder="Pilihan ${letters[i] || "?"}" value="${optText.replace(/"/g, '&quot;')}" oninput="updateOptionText('${qId}', ${i}, this.value)">
                                    <div class="opt-image-preview-wrap" style="display:${imgDisplay}">
                                        <img src="${imgSrc}" class="opt-image-preview" alt="">
                                        <button type="button" class="opt-remove-btn" onclick="removeOptionMedia(this)">✕</button>
                                    </div>
                                    <input type="hidden" class="opt-image-url" value="${optImage || ""}">
                                    <label class="opt-upload-label btn-sm btn-outline" title="Upload Gambar Pilihan">
                                        🖼️
                                        <input type="file" accept="image/*" style="display:none" onchange="uploadOptionMedia(this, '${qId}', ${i})">
                                    </label>
                                    <button class="opt-remove-btn" onclick="this.closest('.option-row').remove()">✕</button>
                                </div>
                            `;
                        }).join("")}
                    </div>
                    <button class="add-option-btn" onclick="addOption(this, '${qId}', '${type}')">+ Tambah Pilihan</button>
                </div>
            `;
        }

        function renderEssayOptions(qId, currentAnswer = "") {
            return `
                <div class="form-group">
                    <label>Kunci Jawaban / Rubrik Penilaian</label>
                    <textarea class="form-control essay-answer" placeholder="Tulis kunci jawaban atau rubrik penilaian..." rows="3" oninput="updateQuestionData('${qId}', 'correct', this.value)">${currentAnswer}</textarea>
                </div>
                <div class="form-group">
                    <label>Panjang Jawaban Minimum (kata)</label>
                    <input type="number" class="form-control" value="50" min="0">
                </div>
            `;
        }

        function renderTrueFalseOptions(qId, correct = "") {
            return `
                <div class="form-group">
                    <label>Jawaban Benar <span class="correct-label">(● = Jawaban Benar)</span></label>
                    <div class="option-row">
                        <input type="radio" name="${qId}-correct" value="0" ${correct === "0" ? "checked" : ""} onchange="updateOptionSelection('${qId}', 0, this.checked)">
                        <span class="option-label">✓</span>
                        <span style="font-weight:600;color:var(--success)">BENAR</span>
                    </div>
                    <div class="option-row">
                        <input type="radio" name="${qId}-correct" value="1" ${correct === "1" ? "checked" : ""} onchange="updateOptionSelection('${qId}', 1, this.checked)">
                        <span class="option-label">✗</span>
                        <span style="font-weight:600;color:var(--danger)">SALAH</span>
                    </div>
                </div>
            `;
        }

        function duplicateQuestion(qId) {
            collectOptionsFromDOM(qId);
            const original = getQuestionById(qId);
            if (original) {
                addQuestion(original.type, original);
            }
        }

        function removeQuestion(qId) {
            const btn = document.getElementById(`del-btn-${qId}`);
            if (!btn) return;
            if (btn.classList.contains('btn-delete-confirm')) {
                clearTimeout(deleteTimers[qId]);
                delete deleteTimers[qId];
                const index = questions.findIndex((q) => q.id === qId);
                if (index > -1) questions.splice(index, 1);
                document.getElementById(qId).remove();
                reindexQuestions();
                showToast("Soal dihapus");
            } else {
                btn.classList.add('btn-delete-confirm');
                btn.innerHTML = 'Yakin?';
                deleteTimers[qId] = setTimeout(() => {
                    btn.classList.remove('btn-delete-confirm');
                    btn.innerHTML = '🗑️ Hapus';
                    delete deleteTimers[qId];
                }, 3000);
            }
        }

        function addOption(btn, qId, type = "multiple") {
            const letters = ["A", "B", "C", "D", "E", "F", "G", "H"];
            const container = btn.closest(".form-group").querySelector(".options-container");
            const rows = container.querySelectorAll(".option-row").length;
            const letter = letters[rows] || String.fromCharCode(65 + rows);
            const row = document.createElement("div");
            row.className = "option-row";
            row.dataset.index = rows;
            const inputType = type === "checkbox" ? "checkbox" : "radio";
            row.innerHTML = `
                <input type="${inputType}" name="${qId}-correct" value="${rows}" title="Tandai sebagai jawaban benar" onchange="updateOptionSelection('${qId}', ${rows}, this.checked)">
                <span class="option-label">${letter}.</span>
                <input type="text" class="form-control option-text" placeholder="Pilihan ${letter}" oninput="updateOptionText('${qId}', ${rows}, this.value)">
                <div class="opt-image-preview-wrap" style="display:none">
                    <img src="" class="opt-image-preview">
                    <button type="button" class="opt-remove-btn" onclick="removeOptionMedia(this)">✕</button>
                </div>
                <input type="hidden" class="opt-image-url" value="">
                <label class="opt-upload-label btn-sm btn-outline" title="Upload Gambar Pilihan">
                    🖼️
                    <input type="file" accept="image/*" style="display:none" onchange="uploadOptionMedia(this, '${qId}', ${rows})">
                </label>
                <button class="opt-remove-btn" onclick="this.closest('.option-row').remove()">✕</button>
            `;
            container.appendChild(row);
        }

        function updateOptionText(qId, optIndex, text) {
            const question = getQuestionById(qId);
            if (!question) return;
            while (question.options.length <= optIndex) {
                question.options.push({ text: "", image: "" });
            }
            if (typeof question.options[optIndex] === "string") {
                question.options[optIndex] = { text: text, image: "" };
            } else {
                question.options[optIndex].text = text;
            }
        }

        function updateOptionSelection(qId, optIndex, isChecked) {
            const question = getQuestionById(qId);
            if (!question) return;
            if (question.type === "checkbox") {
                if (!Array.isArray(question.correct_answers_checkbox)) {
                    question.correct_answers_checkbox = [];
                }
                const indexStr = optIndex.toString();
                if (isChecked && !question.correct_answers_checkbox.includes(indexStr)) {
                    question.correct_answers_checkbox.push(indexStr);
                } else if (!isChecked) {
                    question.correct_answers_checkbox = question.correct_answers_checkbox.filter((i) => i !== indexStr);
                }
            } else {
                if (isChecked) {
                    question.correct = optIndex.toString();
                }
            }
        }

        function collectOptionsFromDOM(qId) {
            const card = document.getElementById(qId);
            const question = getQuestionById(qId);
            if (!card || !question) return;
            const mediaInput = card.querySelector(".q-media");
            if (mediaInput) {
                try {
                    question.media_url = JSON.parse(mediaInput.value) || [];
                } catch (e) {
                    question.media_url = [];
                }
            }
            if (question.type !== "essay") {
                question.options = [];
                question.correct_answers_checkbox = [];
            }
            if (question.type === "essay") {
                const essayTextarea = card.querySelector(".essay-answer");
                if (essayTextarea) {
                    question.correct = essayTextarea.value;
                }
                return;
            }
            question.correct = "";
            if (question.type === "truefalse") {
                question.options = [{ text: "Benar", image: "" }, { text: "Salah", image: "" }];
                const radios = card.querySelectorAll(`input[name="${qId}-correct"]`);
                radios.forEach((radio, index) => {
                    if (radio.checked) {
                        question.correct = index.toString();
                    }
                });
                return;
            }
            const optContainer = card.querySelector(".options-container");
            if (!optContainer) return;
            const rows = optContainer.querySelectorAll(".option-row");
            rows.forEach((row, index) => {
                const textInput = row.querySelector(".option-text");
                const imageUrl = row.querySelector(".opt-image-url")?.value || "";
                const radioInput = row.querySelector(`input[type="radio"], input[type="checkbox"]`);
                const optText = textInput ? textInput.value : "";
                question.options.push({ text: optText, image: imageUrl });
                if (radioInput && radioInput.checked) {
                    if (question.type === "checkbox") {
                        if (!question.correct_answers_checkbox.includes(index.toString())) {
                            question.correct_answers_checkbox.push(index.toString());
                        }
                    } else {
                        question.correct = index.toString();
                    }
                }
            });
        }

        async function uploadQuestionMedia(input, questionIndex) {
            if (!input.files || !input.files[0]) return;
            const files = input.files;
            const questionCard = input.closest(".question-builder");
            const statusEl = questionCard.querySelector(".q-upload-status");
            const hiddenInput = questionCard.querySelector(".q-media");
            let currentMedia = [];
            try {
                currentMedia = JSON.parse(hiddenInput.value || "[]");
                if (!Array.isArray(currentMedia)) currentMedia = [];
            } catch (e) {
                currentMedia = [];
            }
            statusEl.style.display = "block";
            statusEl.textContent = `⌛ Mengunggah ${files.length} file...`;
            for (const file of files) {
                const formData = new FormData();
                formData.append("file", file);
                formData.append("csrf_token", csrfToken);
                try {
                    const response = await fetch("../php/upload_media.php", {
                        method: "POST",
                        body: formData,
                        credentials: "include",
                    });
                    const result = await response.json();
                    if (result.success) {
                        currentMedia.push(result.url);
                    } else {
                        showToast("Gagal mengunggah " + file.name + ": " + result.message, "error");
                    }
                } catch (error) {
                    console.error("Upload error:", error);
                    showToast("Error koneksi saat upload", "error");
                }
            }
            hiddenInput.value = JSON.stringify(currentMedia);
            renderMediaPreviews(questionCard);
            statusEl.textContent = "✅ Berhasil diunggah";
            setTimeout(() => {
                statusEl.style.display = "none";
            }, 2000);
            input.value = "";
        }

        function renderMediaPreviews(questionCard) {
            const hiddenInput = questionCard.querySelector(".q-media");
            const previewEl = questionCard.querySelector(".media-preview-grid");
            let media = [];
            try {
                media = JSON.parse(hiddenInput.value);
            } catch (e) {
                media = [];
            }
            previewEl.innerHTML = "";
            media.forEach((url, index) => {
                const div = document.createElement("div");
                div.className = "media-preview-item";
                div.innerHTML = `
                    <img src="../${url}" style="width:100%; height:5rem; object-fit:cover; border-radius:8px; border:1px solid var(--border)">
                    <button type="button" class="btn btn-danger media-preview-remove" onclick="removeMedia(this, ${index})">✕</button>
                `;
                previewEl.appendChild(div);
            });
        }

        function removeMedia(btn, index) {
            const questionCard = btn.closest(".question-builder");
            const qId = questionCard.id;
            const question = questions.find((q) => q.id === qId);
            if (!question) return;
            question.media_url.splice(index, 1);
            questionCard.querySelector(".q-media").value = JSON.stringify(question.media_url);
            renderMediaPreviews(questionCard);
            updateQuestionData(qId, "media_url", question.media_url);
        }

        function getQuestionById(qId) {
            return questions.find((q) => q.id === qId);
        }

        function updateQuestionData(qId, field, value) {
            const question = getQuestionById(qId);
            if (question) {
                question[field] = value;
            }
        }

        function changeType(qId, newType) {
            const card = document.getElementById(qId);
            const question = getQuestionById(qId);
            if (!question) return;
            question.type = newType;
            question.correct = "";
            question.correct_answers_checkbox = [];
            question.options = [];
            card.querySelectorAll(".type-btn").forEach((b) => {
                b.classList.remove("active");
                if (b.dataset.type === newType) {
                    b.classList.add("active");
                }
            });
            const optContainer = document.getElementById(qId + "-options");
            if (newType === "multiple" || newType === "checkbox") {
                optContainer.innerHTML = renderMultipleOptions(qId, newType, question.options, question.correct, question.correct_answers_checkbox);
            } else if (newType === "essay") {
                optContainer.innerHTML = renderEssayOptions(qId, question.correct);
            } else if (newType === "truefalse") {
                optContainer.innerHTML = renderTrueFalseOptions(qId, question.correct);
            }
            updateQuestionData(qId, "options", question.options);
        }

        async function uploadOptionMedia(input, qId, optIndex) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append("file", file);
            formData.append("csrf_token", csrfToken);
            try {
                const response = await fetch("../php/upload_media.php", {
                    method: "POST",
                    body: formData,
                    credentials: "include",
                });
                const result = await response.json();
                if (result.success) {
                    const row = input.closest(".option-row");
                    const previewWrap = row.querySelector(".opt-image-preview-wrap");
                    const previewImg = row.querySelector(".opt-image-preview");
                    const hiddenInput = row.querySelector(".opt-image-url");
                    hiddenInput.value = result.url;
                    previewImg.src = "../" + result.url;
                    previewWrap.style.display = "flex";
                    const q = questions.find((q) => q.id === qId);
                    if (q && q.options[optIndex]) {
                        if (typeof q.options[optIndex] === "string") {
                            q.options[optIndex] = { text: q.options[optIndex], image: result.url };
                        } else {
                            q.options[optIndex].image = result.url;
                        }
                    }
                } else {
                    showToast("Gagal mengunggah gambar: " + result.message, "error");
                }
            } catch (error) {
                console.error("Upload error:", error);
                showToast("Error koneksi saat upload", "error");
            }
        }

        function removeOptionMedia(btn) {
            const row = btn.closest(".option-row");
            row.querySelector(".opt-image-url").value = "";
            row.querySelector(".opt-image-preview-wrap").style.display = "none";
        }

        function renderPreview() {
            const preview = document.getElementById("preview-content");
            if (questions.length === 0) {
                preview.innerHTML = '<p class="text-center" style="color:#64748b">Belum ada soal untuk ditampilkan dalam preview.</p>';
                return;
            }
            questions.forEach((q) => { collectOptionsFromDOM(q.id); });
            const letters = ["A", "B", "C", "D", "E", "F"];
            preview.innerHTML = questions.map((q, idx) => {
                const qText = q.text || "(Teks soal tidak tersedia)";
                const qType = q.type || "multiple";
                let mediaHtml = "";
                if (q.media_url && q.media_url.length > 0) {
                    const finalMedia = q.media_url.filter((url) => typeof url === "string" && url.length > 5 && (url.includes("uploads/") || url.startsWith("http")));
                    if (finalMedia.length > 0) {
                        const imgCount = finalMedia.length;
                        const gridCols = imgCount === 1 ? "1fr" : imgCount === 2 ? "1fr 1fr" : "repeat(auto-fit, minmax(100px, 1fr))";
                        const maxHeight = imgCount === 1 ? "160px" : "120px";
                        const maxWidth = imgCount === 1 ? "320px" : "100%";
                        mediaHtml = `<div class="question-media-container" style="display: grid; grid-template-columns: ${gridCols}; gap: 10px; margin-bottom: 20px; justify-items: center; width: 100%; max-width: ${maxWidth}; margin-left: auto; margin-right: auto;">
                            ${finalMedia.map((url) => {
                                const finalUrl = url.startsWith("http") ? url : "../" + url;
                                return `<div class="media-item" style="width: 100%; text-align: center;"><img src="${finalUrl}" alt="Gambar Soal" style="max-width: 100%; max-height: ${maxHeight}; object-fit: contain; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);"></div>`;
                            }).join("")}
                        </div>`;
                    }
                }
                let optionsHtml = "";
                if (qType === "multiple" || qType === "checkbox" || qType === "truefalse") {
                    let currentOptions = q.options || [];
                    if (qType === "truefalse" && currentOptions.length === 0) {
                        currentOptions = [{ text: "Benar" }, { text: "Salah" }];
                    }
                    optionsHtml = `<ul class="options-list" style="list-style:none; padding:0; margin-top:15px">
                        ${currentOptions.map((opt, optIdx) => {
                            const optText = typeof opt === "object" ? opt.text : opt;
                            const optImg = typeof opt === "object" ? opt.image : null;
                            if (!optText || optText.trim() === "") return "";
                            let isCorrect = false;
                            if (qType === "multiple" || qType === "truefalse") {
                                isCorrect = q.correct !== undefined && q.correct !== null && q.correct.toString() === optIdx.toString();
                            } else if (qType === "checkbox") {
                                isCorrect = Array.isArray(q.correct_answers_checkbox) && q.correct_answers_checkbox.includes(optIdx.toString());
                            }
                            return `<li class="preview-opt-item ${isCorrect ? 'correct' : ''}">
                                <strong>${letters[optIdx]}.</strong> <span style="flex:1;">${escapeHtml(optText)}</span>
                                ${optImg ? `<div style="margin-left:auto; text-align:right;"><img src="../${escapeHtml(optImg)}" style="max-height:60px; border-radius:4px; border:1px solid #e2e8f0;"></div>` : ""}
                                ${isCorrect ? `<span style="margin-left:10px; color:#10b981; font-weight:700">✓ BENAR</span>` : ""}
                            </li>`;
                        }).filter(html => html !== "").join("")}
                    </ul>`;
                } else if (qType === "essay") {
                    optionsHtml = `<div class="preview-essay-box">
                        <p style="font-size:0.9rem; color:#64748b; margin-bottom:8px"><em>Ini adalah soal esai. Siswa akan mengetik jawaban di sini.</em></p>
                        <p style="font-size:0.85rem; color:#475569; font-weight:600; white-space: pre-wrap; word-break: break-word;">Kunci Jawaban/Rubrik: ${q.correct || "(Belum ada kunci jawaban)"}</p>
                    </div>`;
                }
                return `<div class="preview-q-card">
                    <div class="preview-q-number">Soal ${idx + 1}${qType === "checkbox" ? " (PG Kompleks)" : ""}</div>
                    ${mediaHtml}
                    <div class="preview-q-text">${escapeHtml(qText)}</div>
                    ${optionsHtml}
                </div>`;
            }).join("");
        }

        function saveDraft() {
            questions.forEach((q) => collectOptionsFromDOM(q.id));
            const draft = {
                name: document.getElementById("exam-name").value,
                subject: document.getElementById("exam-subject").value,
                class: document.getElementById("exam-class").value,
                date: document.getElementById("exam-date").value,
                start: document.getElementById("exam-start").value,
                duration: document.getElementById("exam-duration").value,
                desc: document.getElementById("exam-desc").value,
                code: document.getElementById("exam-code").value,
                questions: questions,
                savedAt: new Date().toISOString(),
            };
            try {
                localStorage.setItem('exam_draft', JSON.stringify(draft));
                showToast("Draft disimpan secara lokal");
            } catch (e) {
                showToast("Gagal menyimpan draft", "error");
            }
        }

        async function publishExam() {
            if (questions.length === 0) {
                showToast("Tambahkan minimal 1 soal sebelum mempublikasikan", "error");
                return;
            }
            if (!validateStep(2)) { goStep(1); return; }

            questions.forEach((q) => collectOptionsFromDOM(q.id));
            const processedQuestions = questions.map((q) => {
                let correctAnswerValue = "";
                if (q.type === "multiple" || q.type === "truefalse" || q.type === "essay") {
                    correctAnswerValue = q.correct !== undefined && q.correct !== null ? q.correct.toString() : "";
                } else if (q.type === "checkbox") {
                    correctAnswerValue = JSON.stringify(q.correct_answers_checkbox || []);
                }
                return { ...q, correct_answer: correctAnswerValue, media_url: JSON.stringify(q.media_url || []) };
            });

            const btn1 = document.getElementById("publishBtn");
            const btn2 = document.getElementById("publishBtn2");
            if (btn1) btn1.classList.add("btn-loading");
            if (btn2) btn2.classList.add("btn-loading");

            const examData = {
                action: "create_exam",
                csrf_token: csrfToken,
                name: document.getElementById("exam-name").value,
                subject: document.getElementById("exam-subject").value,
                class: document.getElementById("exam-class").value,
                duration: parseInt(document.getElementById("exam-duration").value),
                question_count: questions.length,
                description: document.getElementById("exam-desc").value,
                exam_code: document.getElementById("exam-code").value,
                start_time: document.getElementById("exam-date").value + " " + document.getElementById("exam-start").value + ":00",
                end_time: document.getElementById("exam-date").value + " 23:59:59",
                show_results_setting: document.getElementById("show-results-setting").value,
                passing_grade: parseInt(document.getElementById("passing-grade").value) || 75,
                max_violations: parseInt(document.getElementById("max-violations").value) || 3,
                shuffle_questions: document.getElementById("shuffle-questions").checked,
                shuffle_options: document.getElementById("shuffle-options").checked,
                security: {
                    fullscreen: document.getElementById("sec-fullscreen").checked,
                    block_shortcuts: document.getElementById("sec-shortcuts").checked,
                    block_copy: document.getElementById("sec-copy").checked,
                    tab_detection: document.getElementById("sec-tab").checked,
                    notify_proctor: document.getElementById("sec-notify").checked,
                    auto_stop: document.getElementById("sec-autostop").checked,
                },
                questions: processedQuestions,
            };
            try {
                const response = await fetch("../php/exam_api.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(examData),
                    credentials: "include",
                });
                const result = await response.json();
                if (result.success) {
                    localStorage.removeItem('exam_draft');
                    showToast("Ujian berhasil dipublikasikan!");
                    setTimeout(() => { window.location.href = "dashboard.php"; }, 1000);
                } else {
                    showToast("Gagal: " + result.message, "error");
                }
            } catch (error) {
                console.error("Publish error:", error);
                showToast("Terjadi kesalahan koneksi", "error");
            } finally {
                if (btn1) btn1.classList.remove("btn-loading");
                if (btn2) btn2.classList.remove("btn-loading");
            }
        }

        // === BANK SOAL ===
        function openBankSoalModal() {
            document.getElementById("bankSoalModal").classList.add("active");
            loadBankQuestions();
        }

        function closeBankSoalModal() {
            document.getElementById("bankSoalModal").classList.remove("active");
        }

        function debouncedBankSearch() {
            clearTimeout(bankDebounceTimer);
            bankDebounceTimer = setTimeout(() => loadBankQuestions(), 300);
        }

        function loadBankQuestions() {
            let url = "../php/exam_api.php?action=get_bank_questions";
            const search = document.getElementById("bankSearchInput").value;
            const category = document.getElementById("bankCategoryFilter").value;
            if (search) url += "&search=" + encodeURIComponent(search);
            if (category) url += "&category=" + encodeURIComponent(category);
            fetch(url).then(r => r.json()).then(d => {
                if (d.success) {
                    bankQuestions = d.questions || [];
                    renderBankQuestions();
                } else {
                    showToast("Gagal memuat bank soal", "error");
                }
            }).catch(e => console.error("Error loading bank questions:", e));
        }

        function renderBankQuestions() {
            const container = document.getElementById("bankQuestionsList");
            if (bankQuestions.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:#94a3b8; padding:2em;">Tidak ada soal di bank.</div>';
                return;
            }
            container.innerHTML = bankQuestions.map((q) => {
                const typeLabel = { multiple: "🔘 PG", checkbox: "☑️ Multi-jawab", essay: "📝 Essay", truefalse: "✔️ B/S" }[q.question_type] || q.question_type;
                const diffLabel = { easy: "📗 Mudah", medium: "📙 Sedang", hard: "📕 Sulit" }[q.difficulty] || q.difficulty;
                return `<div class="bank-item">
                    <div style="flex:1;">
                        <div class="bank-item-badges">
                            <span class="bank-item-badge type">${typeLabel}</span>
                            <span class="bank-item-badge cat">${escapeHtml(q.category || "Umum")}</span>
                            <span class="bank-item-badge" style="background:rgba(107,114,128,0.1);">${diffLabel}</span>
                        </div>
                        <p class="bank-item-text">${escapeHtml(q.question_text)}</p>
                        <p class="bank-item-meta">Poin: <strong>${q.points}</strong> | ${new Date(q.created_at).toLocaleDateString("id-ID")}</p>
                    </div>
                    <button class="bank-item-use" onclick="addQuestionFromBank(${q.id})">✓ Pakai</button>
                </div>`;
            }).join("");
        }

        function addQuestionFromBank(bankQuestionId) {
            const bankQuestion = bankQuestions.find((q) => q.id === bankQuestionId);
            if (!bankQuestion) {
                showToast("Error: Soal tidak ditemukan", "error");
                return;
            }
            const type = bankQuestion.question_type;
            let parsedOptions = [];
            if (bankQuestion.options) {
                if (typeof bankQuestion.options === "string") {
                    try { parsedOptions = JSON.parse(bankQuestion.options); } catch (e) { parsedOptions = [bankQuestion.options]; }
                } else if (Array.isArray(bankQuestion.options)) {
                    parsedOptions = bankQuestion.options;
                }
            }
            let normalizedOptions = [];
            if (parsedOptions && Array.isArray(parsedOptions)) {
                normalizedOptions = parsedOptions.map((opt) => {
                    if (typeof opt === "string") return { text: opt, image: null };
                    else if (typeof opt === "object" && opt !== null) return { text: opt.text || opt, image: opt.image || null };
                    return { text: "", image: null };
                }).filter((opt) => opt.text && opt.text.trim() !== "");
            }
            let correctAnswer = bankQuestion.correct_answer || "";
            let correctAnswersCheckbox = [];
            if (type === "checkbox" && bankQuestion.correct_answer) {
                if (typeof bankQuestion.correct_answer === "string") {
                    try { correctAnswersCheckbox = JSON.parse(bankQuestion.correct_answer); if (!Array.isArray(correctAnswersCheckbox)) correctAnswersCheckbox = [correctAnswersCheckbox]; } catch (e) { correctAnswersCheckbox = [bankQuestion.correct_answer]; }
                } else if (Array.isArray(bankQuestion.correct_answer)) {
                    correctAnswersCheckbox = bankQuestion.correct_answer;
                }
            } else {
                correctAnswer = bankQuestion.correct_answer;
            }
            const newQuestion = {
                type: type,
                text: bankQuestion.question_text || "",
                media_url: [],
                options: normalizedOptions,
                correct: correctAnswer,
                correct_answers_checkbox: correctAnswersCheckbox,
                points: parseInt(bankQuestion.points) || 1,
                difficulty: bankQuestion.difficulty === "easy" ? "mudah" : bankQuestion.difficulty === "hard" ? "sulit" : "sedang",
                category: bankQuestion.category || "Umum",
            };
            addQuestion(type, newQuestion);
            closeBankSoalModal();
            setTimeout(() => {
                const firstQuestion = document.querySelector(".question-builder");
                if (firstQuestion) firstQuestion.scrollIntoView({ behavior: "smooth", block: "start" });
            }, 100);
            showToast("Soal berhasil ditambahkan ke ujian!");
        }

        // === AI IMPORT ===
        function handleAIImportClick() {
            if (typeof window.openAIImportModal === 'function' && window.openAIImportModal !== handleAIImportClick) {
                window.openAIImportModal();
            } else {
                showToast("Fitur AI Import akan segera hadir", "info");
            }
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
