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

// ============================================================
// HANDLE POST REQUESTS (Initial exam access)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  $csrf_token = $_POST['csrf_token'] ?? '';
  if (!verifyCSRFToken($csrf_token, $_SESSION['csrf_token'] ?? null)) {
    $_SESSION['error'] = 'Token keamanan tidak valid. Silakan coba lagi.';
    header('Location: dashboard.php');
    exit;
  }

  // Get exam_id from POST
  $exam_id = (int)($_POST['exam_id'] ?? 0);
  if (!$exam_id) {
    $_SESSION['error'] = 'ID ujian tidak valid.';
    header('Location: dashboard.php');
    exit;
  }

  // Rate limiting check
  if (!checkExamRateLimit($exam_id)) {
    $error = $_SESSION['rate_limit_error'] ?? 'Terlalu banyak percobaan. Silakan tunggu.';
    unset($_SESSION['rate_limit_error']);
    $_SESSION['error'] = $error;
    header('Location: dashboard.php');
    exit;
  }

  // Store exam_id in session and clear rate limit on success
  $_SESSION['active_exam_id'] = $exam_id;
  clearExamRateLimit($exam_id);

  // Regenerate CSRF token after successful validation to prevent replay attacks
  generateCSRFToken();

  // Redirect to clean URL (removes POST data)
  header('Location: exam.php');
  exit;
}

// ============================================================
// HANDLE GET REQUESTS (Retrieve exam_id from session)
// ============================================================
$exam_id = $_SESSION['active_exam_id'] ?? 0;

// Clear from session immediately to prevent reuse
unset($_SESSION['active_exam_id']);

if (!$exam_id) {
  $_SESSION['error'] = 'Akses tidak sah. Silakan pilih ujian dari dashboard.';
  header('Location: dashboard.php');
  exit;
}

// ============================================================
// NORMAL EXAM VALIDATION (unchanged from original)
// ============================================================

// Check if logged in as student
requireLogin('siswa');

// Check session timeout (2 hours for exam)
if (isSessionExpired(7200)) {
  clearSession();
  $_SESSION['error'] = 'Sesi Anda telah berakhir. Silakan login kembali.';
  header('Location: ../index.php');
  exit;
}

// Refresh login time
$_SESSION['login_time'] = time();

// Security headers
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$full_name = $_SESSION['full_name'] ?? 'Siswa';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-select:none" />
  <title>Ujian — ExamSafe</title>
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    body {
      background: #f8fafc;
      user-select: none;
      -webkit-user-select: none;
      overflow-x: hidden;
      font-family: "Poppins", sans-serif;
    }

    /* AGREEMENT MODAL */
    .agreement-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      z-index: 10001;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      backdrop-filter: blur(4px);
    }

    .agreement-content {
      background: #fff;
      border-radius: 24px;
      max-width: 700px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .agreement-header {
      background: linear-gradient(135deg, #1a3c6e 0%, #2563eb 100%);
      color: #fff;
      padding: 1.5rem;
      border-radius: 24px 24px 0 0;
    }

    .agreement-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0 0 0.25rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .agreement-header p {
      opacity: 0.9;
      font-size: 0.85rem;
      margin: 0;
    }

    .rules-scroll-container {
      max-height: 300px;
      overflow-y: auto;
      padding: 1.5rem;
      border-bottom: 1px solid #e2e8f0;
      scroll-behavior: smooth;
    }

    .rules-scroll-container::-webkit-scrollbar {
      width: 8px;
    }

    .rules-scroll-container::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 10px;
    }

    .rules-scroll-container::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 10px;
    }

    .rules-scroll-container::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }

    .rule-section {
      margin-bottom: 1.75rem;
    }

    .rule-section-title {
      font-weight: 700;
      font-size: 1rem;
      color: #1e293b;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .rule-item {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      margin-bottom: 1rem;
      padding: 0.5rem;
      border-radius: 8px;
      transition: background 0.2s;
    }

    .rule-item:hover {
      background: #f8fafc;
    }

    .rule-item input[type="checkbox"] {
      width: 1.25rem;
      height: 1.25rem;
      margin-top: 0.125rem;
      cursor: pointer;
      flex-shrink: 0;
    }

    .rule-text {
      font-size: 0.9rem;
      color: #334155;
      line-height: 1.5;
      flex: 1;
    }

    .agreement-footer {
      padding: 1.5rem;
      background: #f8fafc;
      border-radius: 0 0 24px 24px;
    }

    .countdown-timer {
      text-align: center;
      padding: 0.75rem;
      background: #dbeafe;
      border-radius: 12px;
      margin-bottom: 1rem;
      font-weight: 700;
      font-size: 1.25rem;
      color: #1e40af;
      display: none;
    }

    .countdown-timer.active {
      display: block;
      animation: pulse 1s infinite;
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

    .btn-start-exam {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      font-weight: 700;
      background: #cbd5e1;
      color: #64748b;
      border: none;
      border-radius: 12px;
      cursor: not-allowed;
      transition: all 0.2s;
    }

    .btn-start-exam.enabled {
      background: #16a34a;
      color: #fff;
      cursor: pointer;
    }

    .btn-start-exam.enabled:hover {
      background: #15803d;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }

    .exam-header {
      background: #fff;
      color: #1e293b;
      padding: 0 0.75rem;
      height: 3.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 200;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      border-bottom: 1px solid #e2e8f0;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 0.625em;
      min-width: 0;
    }

    .header-logo {
      width: 2.25rem;
      height: 2.25rem;
      background: #2563eb;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 800;
      font-size: 1.1rem;
    }

    .header-info h1 {
      font-size: 0.85rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 11.25rem;
    }

    .header-meta {
      display: flex;
      align-items: center;
      gap: 0.25em;
      font-size: 0.65rem;
      flex-wrap: wrap;
      margin-top: 0.125em;
    }

    .meta-badge {
      background: #f1f5f9;
      padding: 0.1em 0.5em;
      border-radius: 4px;
      font-size: 0.6rem;
    }

    .meta-separator {
      color: #cbd5e1;
    }

    .student-name {
      font-weight: 600;
      color: #334155;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 0.5em;
    }

    .timer-pill {
      background: #fee2e2;
      color: #dc2626;
      padding: 0.3125em 0.625em;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.375em;
      border: 1px solid #fecaca;
    }

    .timer-pill.warning {
      background: #fef3c7;
      color: #d97706;
      border-color: #fde68a;
      animation: pulse 1s infinite;
    }

    .nav-grid-trigger {
      cursor: pointer;
      background: #f1f5f9;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #475569;
    }

    .q-nav-container {
      position: fixed;
      top: 3.75rem;
      left: 0;
      right: 0;
      background: #fff;
      padding: 0.75em 0;
      z-index: 150;
      border-bottom: 1px solid #e2e8f0;
    }

    .q-nav-scroll {
      display: flex;
      gap: 0.625em;
      overflow-x: auto;
      padding: 0 1em;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .q-nav-scroll::-webkit-scrollbar {
      display: none;
    }

    .q-nav-btn {
      flex: 0 0 2.625rem;
      height: 2.625rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
      border: 2px solid #e2e8f0;
      background: #f8fafc;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
    }

    .q-nav-btn.answered {
      background: #dcfce7;
      color: #16a34a;
      border-color: #bbf7d0;
    }

    .q-nav-btn.current {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .q-nav-btn.marked {
      background: #fef3c7;
      color: #d97706;
      border-color: #fde68a;
    }

    .exam-container {
      margin-top: 8rem;
      margin-bottom: 5rem;
      padding: 1em;
      max-width: 50rem;
      margin-left: auto;
      margin-right: auto;
    }

    .question-card {
      background: #fff;
      border-radius: 16px;
      padding: 1.5em;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      margin-bottom: 1.5em;
    }

    .q-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1em;
    }

    .q-number {
      font-weight: 800;
      color: #2563eb;
      font-size: 1.1rem;
    }

    .q-type {
      font-size: 0.75rem;
      color: #64748b;
      background: #f1f5f9;
      padding: 0.333em 0.833em;
      border-radius: 50px;
      font-weight: 600;
    }

    .q-text {
      font-size: 1.05rem;
      font-weight: 500;
      color: #1e293b;
      line-height: 1.6;
      margin-bottom: 1.5em;
      white-space: pre-wrap;
      word-break: break-word;
    }

    .q-text ol, .q-text ul {
      margin-left: 2rem;
    }

    .options-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.75em;
    }

    .option-item {
      display: flex;
      align-items: center;
      gap: 0.875em;
      padding: 1em;
      border: 2px solid #e2e8f0;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.2s;
      background: #fff;
    }

    .option-item:hover {
      border-color: #cbd5e1;
      background: #f8fafc;
    }

    .option-item.selected {
      border-color: #2563eb;
      background: #eff6ff;
    }

    .opt-letter {
      width: 2rem;
      height: 2rem;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f1f5f9;
      color: #64748b;
      font-weight: 700;
      font-size: 0.9rem;
      transition: all 0.2s;
    }

    .option-item.selected .opt-letter {
      background: #2563eb;
      color: #fff;
    }

    .opt-text {
      flex: 1;
      font-size: 0.95rem;
      color: #334155;
    }

    .option-item input {
      display: none;
    }

    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #fff;
      height: 4.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1em;
      z-index: 200;
      box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.05);
      border-top: 1px solid #e2e8f0;
    }

    .nav-btn {
      height: 2.875rem;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5em;
      font-weight: 600;
      font-size: 0.95rem;
      padding: 0 1.25em;
      cursor: pointer;
      transition: all 0.2s;
      border: none;
    }

    .btn-prev {
      background: #f1f5f9;
      color: #475569;
    }

    .btn-next {
      background: #2563eb;
      color: #fff;
    }

    .btn-next:disabled {
      background: #94a3b8;
      opacity: 0.7;
    }

    .btn-finish {
      background: #16a34a;
      color: #fff;
    }

    .btn-ragu {
      background: #fef3c7;
      color: #d97706;
      padding: 0 1em;
    }

    #violation-overlay {
      position: fixed;
      inset: 0;
      background: rgba(239, 68, 68, 0.98);
      color: #fff;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      text-align: center;
      padding: 2.5em;
    }

    #violation-overlay.show {
      display: flex;
    }

    #fs-prompt {
      position: fixed;
      inset: 0;
      background: #fff;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 2em;
    }

    .nav-grid-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 300;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1.25em;
    }

    .nav-grid-content {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 25rem;
      padding: 1.5em;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .grid-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.25em;
    }

    .grid-title {
      font-weight: 700;
      font-size: 1.1rem;
    }

    .grid-items {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 0.625em;
    }

    .fs-start-btn {
      max-width: max-content;
    }

    @media (max-width: 480px) {
      .header-info h1 {
        max-width: 8.75rem;
      }

      .grid-items {
        grid-template-columns: repeat(4, 1fr);
      }

      .nav-btn {
        padding: 0 1em;
      }

      .fs-start-btn {
        width: 100%;
        max-width: initial;
        justify-content: center;
      }

      .agreement-content {
        max-height: 95vh;
      }

      .rules-scroll-container {
        max-height: 250px;
      }
    }

    @media (max-width: 360px) {
      .header-info h1 {
        max-width: 6.875rem;
      }

      .header-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125em;
      }
    }
  </style>
</head>

<body>
  <!-- ZOOM MODAL -->
  <div id="zoom-modal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.85); z-index: 10000; align-items: center; justify-content: center; cursor: zoom-out;" onclick="ExamEngine.closeZoom()">
    <img id="zoom-img" src="" style="max-width: 95%; max-height: 95%; object-fit: contain; border-radius: 8px;" />
  </div>

  <!-- AGREEMENT MODAL -->
  <div id="agreement-modal" class="agreement-modal">
    <div class="agreement-content">
      <div class="agreement-header">
        <h2><span>📋</span> Peraturan Ujian Online</h2>
        <p>Bacalah dengan teliti sebelum memulai ujian</p>
      </div>

      <div class="rules-scroll-container" id="rules-scroll-container">
        <div class="rule-section">
          <div class="rule-section-title"><span>🔒</span><span>Aturan Keamanan Ujian</span></div>
          <div class="rule-item" id="rule-1"><input type="checkbox" id="chk1" /><label class="rule-text">Saya tidak akan membuka tab atau jendela browser lain selama ujian berlangsung</label></div>
          <div class="rule-item" id="rule-2"><input type="checkbox" id="chk2" /><label class="rule-text">Saya tidak akan melakukan copy-paste (Ctrl+C, Ctrl+V, Ctrl+X)</label></div>
          <div class="rule-item" id="rule-3"><input type="checkbox" id="chk3" /><label class="rule-text">Saya tidak akan menggunakan klik kanan (right-click)</label></div>
          <div class="rule-item" id="rule-4"><input type="checkbox" id="chk4" /><label class="rule-text">Saya tidak akan keluar dari mode layar penuh</label></div>
          <div class="rule-item" id="rule-5"><input type="checkbox" id="chk5" /><label class="rule-text">Saya tidak akan membuka Developer Tools (F12 / Ctrl+Shift+I)</label></div>
          <div class="rule-item" id="rule-6"><input type="checkbox" id="chk6" /><label class="rule-text">Saya tidak akan menggunakan tombol pintas browser (Ctrl+T, Ctrl+W, Ctrl+R, F5)</label></div>
          <div class="rule-item" id="rule-7"><input type="checkbox" id="chk7" /><label class="rule-text">Saya tidak akan berpindah ke aplikasi lain (Alt+Tab)</label></div>
          <div class="rule-item" id="rule-8"><input type="checkbox" id="chk8" /><label class="rule-text">Saya memahami bahwa pelanggaran maksimal 3 kali akan mengakhiri ujian secara paksa</label></div>
          <div class="rule-item" id="rule-9"><input type="checkbox" id="chk9" /><label class="rule-text">Jawaban hanya dapat dikirim satu kali dan tidak dapat diubah</label></div>
        </div>

        <div class="rule-section">
          <div class="rule-section-title"><span>📱</span><span>Persyaratan Perangkat</span></div>
          <div class="rule-item" id="rule-10"><input type="checkbox" id="chk10" /><label class="rule-text">Saya telah mengatur screen timeout perangkat minimal 30 menit (atau mengaktifkan fitur "Never Sleep")</label></div>
          <div class="rule-item" id="rule-11"><input type="checkbox" id="chk11" /><label class="rule-text">Saya telah mengaktifkan mode Jangan Ganggu / Do Not Disturb (DND) pada perangkat saya</label></div>
          <div class="rule-item" id="rule-12"><input type="checkbox" id="chk12" /><label class="rule-text">Saya memastikan koneksi internet stabil selama ujian berlangsung</label></div>
        </div>

        <div class="rule-section">
          <div class="rule-section-title"><span>✅</span><span>Persetujuan Akhir</span></div>
          <div class="rule-item" id="rule-13"><input type="checkbox" id="chk13" /><label class="rule-text">Saya telah membaca dan memahami seluruh peraturan ujian</label></div>
          <div class="rule-item" id="rule-14"><input type="checkbox" id="chk14" /><label class="rule-text">Saya bersedia menerima sanksi jika terbukti melanggar peraturan</label></div>
        </div>
      </div>

      <div class="agreement-footer">
        <div class="countdown-timer" id="countdown-timer">Silakan baca dengan teliti... <span id="countdown-seconds">10</span> detik</div>
        <button class="btn-start-exam" id="btn-start-exam" disabled>Mulai Ujian</button>
      </div>
    </div>
  </div>

  <!-- FULLSCREEN PROMPT -->
  <div id="fs-prompt" style="display: none">
    <div style="font-size: 4rem; margin-bottom: 1.5em">🔒</div>
    <h2 style="font-weight: 800; margin-bottom: 0.75em">Mode Ujian Aman</h2>
    <p style="color: #64748b; margin-bottom: 2em">Ujian ini memerlukan mode layar penuh untuk menjaga integritas dan keamanan.</p>
    <button class="btn btn-primary btn-lg btn-block fs-start-btn" onclick="startExam()">Mulai Ujian</button>
  </div>

  <!-- VIOLATION OVERLAY -->
  <div id="violation-overlay">
    <div style="font-size: 4rem; margin-bottom: 1em">⚠️</div>
    <h2>Pelanggaran Terdeteksi!</h2>
    <p id="violation-msg">Aktivitas mencurigakan terdeteksi</p>
    <div id="violation-count" style="margin: 1.25em 0; background: rgba(255, 255, 255, 0.2); padding: 0.625em 1.5em; border-radius: 50px; font-weight: 700;">Pelanggaran 1/3</div>
    <p style="font-size: 0.85rem; opacity: 0.8">Pengawas telah diberitahu. Jangan ulangi!</p>
  </div>

  <!-- EXAM HEADER -->
  <div class="exam-header" id="exam-header" style="display: none">
    <div class="header-left">
      <div class="header-logo">📝</div>
      <div class="header-info">
        <h1 id="exam-name-display">Memuat...</h1>
        <div class="header-meta">
          <span class="meta-badge" id="exam-subject-display">-</span>
          <span class="meta-separator">•</span>
          <span class="meta-badge" id="exam-class-display">-</span>
          <span class="meta-separator">•</span>
          <span class="meta-badge" id="exam-question-count">0</span>
          <span class="meta-text">soal</span>
          <span class="meta-separator">|</span>
          <span class="student-name" id="exam-student-display">Siswa: ---</span>
        </div>
      </div>
    </div>
    <div class="header-right">
      <div id="exam-timer" class="timer-pill timer-normal">⏱️ 00:00</div>
      <div onclick="toggleGrid()" class="nav-grid-trigger" title="Navigasi Soal">☰</div>
    </div>
  </div>

  <!-- TOP NAV SCROLL -->
  <div class="q-nav-container" id="q-nav-container" style="display: none">
    <div class="q-nav-scroll" id="q-nav-scroll"></div>
  </div>

  <!-- EXAM CONTENT -->
  <div class="exam-container" id="exam-content" style="display: none">
    <div id="questions-container"></div>
  </div>

  <!-- BOTTOM NAV -->
  <div class="bottom-nav" id="bottom-nav" style="display: none">
    <button class="nav-btn btn-prev" onclick="ExamEngine.prevQuestion()"><span>Sebelumnya</span></button>
    <button class="nav-btn btn-ragu" id="btn-mark" onclick="toggleMark()"><span>Ragu-ragu</span></button>
    <button class="nav-btn btn-next" id="btn-next-main" onclick="ExamEngine.nextQuestion()"><span>Selanjutnya</span></button>
  </div>

  <!-- NAV GRID MODAL -->
  <div class="nav-grid-modal" id="nav-grid-modal" onclick="toggleGrid()">
    <div class="nav-grid-content" onclick="event.stopPropagation()">
      <div class="grid-header">
        <div class="grid-title">Navigasi Soal</div>
        <div onclick="toggleGrid()" style="cursor: pointer; font-size: 1.5rem">&times;</div>
      </div>
      <div class="grid-items" id="modal-nav-grid"></div>
      <div style="margin-top: 1.5em; border-top: 1px solid #eee; padding-top: 1.25em;">
        <button class="btn btn-success btn-block" onclick="ExamEngine.submitExam()">Kumpulkan Ujian</button>
      </div>
    </div>
  </div>

  <!-- RESULT SCREEN -->
  <div id="exam-result" style="display: none; position: fixed; inset: 0; background: #fff; z-index: 5000; flex-direction: column; align-items: center; justify-content: center; padding: 2em; text-align: center;">
    <div style="font-size: 4rem; margin-bottom: 1.25em">✅</div>
    <h2 style="font-weight: 800; font-size: 2rem; margin-bottom: 0.5em">Ujian Selesai</h2>
    <div style="font-size: 1.5rem; font-weight: 600; color: #16a34a; margin-bottom: 1em;">Terima kasih telah mengerjakan ujian</div>
    <p style="color: #64748b; margin-bottom: 2em; max-width: 300px">Jawaban Anda telah disimpan. Hasil ujian akan diumumkan oleh guru.</p>
    <a href="../student/dashboard.php" class="btn btn-primary" style="max-width: 18.75rem; display: inline-block; padding: 12px 24px">Kembali ke Dashboard</a>
  </div>

  <script src="../js/security.js"></script>
  <script src="../js/exam.js"></script>
  <script>
    // Pass PHP variables to JavaScript
    const examIdFromUrl = <?php echo json_encode($exam_id); ?>;
    const studentName = <?php echo json_encode($full_name); ?>;

    // Use examIdFromUrl instead of URL parameter
    const examId = examIdFromUrl;

    if (!examId) {
      alert("ID Ujian tidak ditemukan!");
      window.location.href = "dashboard.php";
    }

    let allCheckboxes = [];
    let countdownActive = false;
    let countdownInterval = null;

    const countdownTimer = document.getElementById("countdown-timer");
    const countdownSeconds = document.getElementById("countdown-seconds");
    const startBtn = document.getElementById("btn-start-exam");

    function initCheckboxes() {
      for (let i = 1; i <= 14; i++) {
        const chk = document.getElementById(`chk${i}`);
        const ruleItem = document.getElementById(`rule-${i}`);
        if (chk && ruleItem) {
          allCheckboxes.push(chk);
          chk.disabled = false;
          ruleItem.classList.remove("disabled");
          chk.addEventListener("change", validateAllChecked);
        }
      }
    }

    function validateAllChecked() {
      if (countdownActive) return;
      const allChecked = allCheckboxes.every((chk) => chk.checked);
      if (allChecked && !countdownActive) {
        startCountdown(10);
      }
    }

    function startCountdown(seconds) {
      countdownActive = true;
      let remaining = seconds;
      countdownTimer.classList.add("active");
      countdownSeconds.textContent = remaining;

      countdownInterval = setInterval(() => {
        remaining--;
        countdownSeconds.textContent = remaining;
        if (remaining <= 0) {
          clearInterval(countdownInterval);
          countdownTimer.classList.remove("active");
          countdownTimer.style.display = "none";
          enableStartButton();
        }
      }, 1000);
    }

    function enableStartButton() {
      startBtn.disabled = false;
      startBtn.classList.add("enabled");
      startBtn.textContent = "✓ Mulai Ujian";
      startBtn.onclick = handleStartExam;
    }

    async function handleStartExam() {
      if (startBtn.disabled) return;
      try {
        await ExamEngine.logAgreement(examId);
        console.log("[Agreement] Successfully logged to server");
      } catch (error) {
        console.error("[Agreement] Failed to log:", error);
      }
      document.getElementById("agreement-modal").style.display = "none";
      document.getElementById("fs-prompt").style.display = "flex";
    }

    let markedQuestions = new Set();

    function toggleMark() {
      const idx = ExamEngine.currentQuestion;
      if (markedQuestions.has(idx)) {
        markedQuestions.delete(idx);
      } else {
        markedQuestions.add(idx);
      }
      updateNavUI();
    }

    function toggleGrid() {
      const modal = document.getElementById("nav-grid-modal");
      modal.style.display = modal.style.display === "flex" ? "none" : "flex";
    }

    function startExam() {
      document.getElementById("fs-prompt").style.display = "none";
      document.getElementById("exam-header").style.display = "flex";
      document.getElementById("q-nav-container").style.display = "block";
      document.getElementById("exam-content").style.display = "block";
      document.getElementById("bottom-nav").style.display = "flex";

      if (typeof ExamSecurity !== "undefined" && ExamSecurity.start) {
        ExamSecurity.start();
      }

      try {
        const el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen().catch(() => {});
      } catch (e) {}

      ExamEngine.startExam(examId).then((success) => {
        if (success) {
          ExamEngine.init(examId).then(() => {
            const subDisp = document.getElementById("exam-subject-display");
            const stdDisp = document.getElementById("exam-student-display");
            const count = document.getElementById("exam-question-count");
            const nameDisp = document.getElementById("exam-name-display");

            if (nameDisp && ExamEngine.examData)
              nameDisp.textContent = ExamEngine.examData.name || "Ujian";
            if (subDisp && ExamEngine.examData)
              subDisp.textContent = ExamEngine.examData.subject || "-";
            if (stdDisp && ExamEngine.studentData)
              stdDisp.textContent = ExamEngine.studentData.full_name;
            if (count) count.textContent = ExamEngine.questions.length;

            buildNav();
          });
        } else {
          window.location.href = "dashboard.php";
        }
      });
    }

    function buildNav() {
      const scrollContainer = document.getElementById("q-nav-scroll");
      const gridContainer = document.getElementById("modal-nav-grid");
      scrollContainer.innerHTML = "";
      gridContainer.innerHTML = "";

      ExamEngine.questions.forEach((_, i) => {
        const btn = document.createElement("button");
        btn.className = "q-nav-btn";
        btn.id = `nav-q${i}`;
        btn.textContent = i + 1;
        btn.onclick = () => {
          ExamEngine.goToQuestion(i);
          if (document.getElementById("nav-grid-modal").style.display === "flex")
            toggleGrid();
        };
        scrollContainer.appendChild(btn);

        const gridBtn = btn.cloneNode(true);
        gridBtn.id = `modal-nav-q${i}`;
        gridBtn.onclick = btn.onclick;
        gridContainer.appendChild(gridBtn);
      });
      updateNavUI();
    }

    function updateNavUI() {
      if (!ExamEngine.questions) return;
      ExamEngine.questions.forEach((q, i) => {
        const isAnswered = ExamEngine.answers[q.id] !== undefined;
        const isCurrent = ExamEngine.currentQuestion === i;
        const isMarked = markedQuestions.has(i);

        const btns = [
          document.getElementById(`nav-q${i}`),
          document.getElementById(`modal-nav-q${i}`),
        ];

        btns.forEach((btn) => {
          if (!btn) return;
          btn.className = "q-nav-btn";
          if (isAnswered) btn.classList.add("answered");
          if (isMarked) btn.classList.add("marked");
          if (isCurrent) btn.classList.add("current");
        });
      });

      const nextBtn = document.getElementById("btn-next-main");
      const markBtn = document.getElementById("btn-mark");

      if (ExamEngine.currentQuestion === ExamEngine.questions.length - 1) {
        nextBtn.innerHTML = "<span>Selesai</span>";
        nextBtn.className = "nav-btn btn-finish";
        nextBtn.disabled = false;
        nextBtn.style.opacity = "1";
        nextBtn.style.cursor = "pointer";
        nextBtn.onclick = () => ExamEngine.submitExam();
      } else {
        nextBtn.innerHTML = "<span>Selanjutnya</span>";
        nextBtn.className = "nav-btn btn-next";
        nextBtn.disabled = false;
        nextBtn.style.opacity = "1";
        nextBtn.style.cursor = "pointer";
        nextBtn.onclick = () => ExamEngine.nextQuestion();
      }

      if (markedQuestions.has(ExamEngine.currentQuestion)) {
        markBtn.style.background = "#fde68a";
        markBtn.style.borderColor = "#f59e0b";
      } else {
        markBtn.style.background = "#fef3c7";
        markBtn.style.borderColor = "transparent";
      }

      const curBtn = document.getElementById(`nav-q${ExamEngine.currentQuestion}`);
      if (curBtn) {
        curBtn.scrollIntoView({
          behavior: "smooth",
          block: "nearest",
          inline: "center",
        });
      }
    }

    const originalGoTo = ExamEngine.goToQuestion;
    ExamEngine.goToQuestion = function(idx) {
      originalGoTo.call(this, idx);
      updateNavUI();
    };

    const originalUpdateProgress = ExamEngine.updateProgress;
    ExamEngine.updateProgress = function() {
      originalUpdateProgress.call(this);
      updateNavUI();
    };

    initCheckboxes();
  </script>
</body>

</html>
