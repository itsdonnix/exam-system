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
// NORMAL EXAM VALIDATION
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
  <link rel="stylesheet" href="../css/exam.css" />
</head>

<body class="exam-page">
  <div id="app"></div>

  <script>
    window.EXAM_ID = <?php echo json_encode($exam_id); ?>;
    window.STUDENT_NAME = <?php echo json_encode($full_name); ?>;
  </script>
  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
  <script src="../js/api-client.js"></script>
  <script src="../js/toast.js"></script>
  <script src="../js/student-api.js"></script>
  <script src="../js/confirm-dialog.js"></script>
  <script src="../js/security.js"></script>
  <script src="../js/exam-app.js"></script>
</body>

</html>
