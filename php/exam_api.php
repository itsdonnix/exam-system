<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Now start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once '../includes/csrf.php';

// ============================================================
// PUBLIC ENDPOINTS CONFIGURATION
// ============================================================
// Add any action names here that should be accessible WITHOUT authentication.
$public_actions = ['get_subjects', 'get_classes'];

// Detect action and input BEFORE session validation
$input = getInput();
$action = $_GET['action'] ?? $input['action'] ?? '';

// Check if current action is public
$is_public = in_array($action, $public_actions);

// ============================================================
// SESSION VALIDATION (only for non-public endpoints)
// ============================================================
if (!$is_public) {
    // Validate session has required data
    if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
        // Attempt to recover session from database
        $db = getDB();

        // Check which role this user_id belongs to
        $stmt = $db->prepare("
            SELECT 'siswa' as role, full_name, username FROM students WHERE id = ? AND is_active = 1
            UNION 
            SELECT 'guru' as role, full_name, username FROM teachers WHERE id = ? AND is_active = 1
            UNION 
            SELECT 'admin' as role, username as full_name, username FROM admins WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            error_log("[ExamAPI] Session recovered for user_id: " . $_SESSION['user_id'] . " as role: " . $user['role']);
        } else {
            error_log("[ExamAPI] Failed to recover session for user_id: " . $_SESSION['user_id']);
            session_destroy();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login kembali.']);
            exit;
        }
    }

    // Check if session is valid
    if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
        exit;
    }

    // Check session timeout (2 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sesi telah berakhir. Silakan login kembali.']);
        exit;
    }
}

// ============================================================
// ERROR HANDLING & MAIN ROUTER
// ============================================================

// Set error handling to prevent HTML error pages
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("[API Error] $errstr in $errfile:$errline");
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'API Error: ' . $errstr]);
    }
    return true; // Mark error as handled
});

try {
    switch ($action) {
        case 'get_exam':
            getExam();
            break;
        case 'get_exam_info':
            getExamInfo();
            break;
        case 'start_exam':
            startExam();
            break;
        case 'submit_answers':
            submitAnswers();
            break;
        case 'report_violation':
            reportViolation();
            break;
        case 'get_student_violations':
            getStudentViolations();
            break;
        case 'delete_violation':
            deleteViolation();
            break;
        case 'get_results':
            getResults();
            break;
        case 'create_exam':
            createExam();
            break;
        case 'get_exams':
            getExams();
            break;
        case 'activate_exam':
            activateExam();
            break;
        case 'deactivate_exam':
            deactivateExam();
            break;
        case 'delete_exam':
            deleteExam();
            break;
        case 'duplicate_exam':
            duplicateExam();
            break;
        case 'unlock_student':
            unlockStudent();
            break;
        case 'reset_student_result':
            resetStudentResult();
            break;
        case 'join_exam':
            joinExamAction();
            break;
        case 'get_student_history':
            getStudentHistory();
            break;
        case 'get_recent_violations':
            getRecentViolations();
            break;
        case 'get_exam_monitor':
            getExamMonitor();
            break;
        case 'get_students':
            getStudents();
            break;
        case 'get_profile':
            getProfile();
            break;
        case 'update_profile':
            updateProfile();
            break;
        case 'get_classes':
            getClasses();
            break;
        case 'get_subjects':
            getSubjects();
            break;
        case 'get_submission_detail':
            getSubmissionDetail();
            break;
        case 'save_manual_grade':
            saveManualGrade();
            break;
        case 'get_bank_questions':
            getBankQuestions();
            break;
        case 'save_question_to_bank':
            saveQuestionToBank();
            break;
        case 'get_bank_question':
            getBankQuestion();
            break;
        case 'update_bank_question':
            updateBankQuestion();
            break;
        case 'delete_bank_question':
            deleteBankQuestion();
            break;
        case 'copy_question_to_exam':
            copyQuestionToExam();
            break;
        case 'get_teacher_stats':
            getTeacherStats();
            break;
        case 'log_agreement':
            logAgreement();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
    }
} catch (Exception $e) {
    error_log("[API Exception] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}

function getExamInfo()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $examId = (int)($_GET['exam_id'] ?? 0);
    if (!$examId) {
        jsonResponse(['success' => false, 'message' => 'Exam ID required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, subject, class, exam_code, duration_minutes, status 
        FROM exams 
        WHERE id = ? AND teacher_id = ?
        LIMIT 1
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Exam not found or access denied'], 404);
    }

    jsonResponse(['success' => true, 'exam' => $exam]);
}

function logExamAction($level, $message, $context = [])
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/exam_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'unknown';
    $role = $_SESSION['role'] ?? 'unknown';
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] [{$level}] [User:{$userId}] [Role:{$role}] {$message}{$contextStr}" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function getSubmissionDetail()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $submissionId = (int)($_GET['id'] ?? 0);
    $db = getDB();

    $stmt = $db->prepare("
        SELECT es.*, s.full_name, s.nisn, s.class, e.name as exam_name
        FROM exam_submissions es
        JOIN students s ON s.id = es.student_id
        JOIN exams e ON e.id = es.exam_id
        WHERE es.id = ? AND e.teacher_id = ?
    ");
    $stmt->execute([$submissionId, $_SESSION['user_id']]);
    $submission = $stmt->fetch();

    if (!$submission) {
        jsonResponse(['success' => false, 'message' => 'Submission tidak ditemukan'], 404);
    }

    $examId = $submission['exam_id'];
    $stmtQ = $db->prepare("SELECT id, question_text, question_type, options, correct_answer, points FROM questions WHERE exam_id = ?");
    $stmtQ->execute([$examId]);
    $questions = $stmtQ->fetchAll();

    $submission['answers'] = json_decode($submission['answers_json'], true);

    jsonResponse(['success' => true, 'submission' => $submission, 'questions' => $questions]);
}

function saveManualGrade()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $submissionId = (int)($data['submission_id'] ?? 0);
    $manualScore = (float)($data['manual_score'] ?? 0);

    $db = getDB();

    $stmt = $db->prepare("SELECT score, exam_id FROM exam_submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    $sub = $stmt->fetch();

    if (!$sub) jsonResponse(['success' => false, 'message' => 'Submission tidak ditemukan'], 404);

    $examId = $sub['exam_id'];

    $stmtP = $db->prepare("SELECT SUM(points) as total_points FROM questions WHERE exam_id = ?");
    $stmtP->execute([$examId]);
    $totalPoints = (int)$stmtP->fetchColumn();

    if ($totalPoints <= 0) $totalPoints = 1;

    $autoPoints = ($sub['score'] / 100) * $totalPoints;
    $totalScore = (($autoPoints + $manualScore) / $totalPoints) * 100;

    if ($totalScore > 100) $totalScore = 100;

    $stmtU = $db->prepare("UPDATE exam_submissions SET manual_score = ?, total_score = ?, status = 'graded' WHERE id = ?");
    $stmtU->execute([$manualScore, $totalScore, $submissionId]);

    jsonResponse(['success' => true, 'message' => 'Nilai berhasil disimpan', 'total_score' => round($totalScore, 2)]);
}

function getClasses()
{
    $db = getDB();
    $classes = $db->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
    jsonResponse(['success' => true, 'classes' => $classes]);
}

function getSubjects()
{
    try {
        $db = getDB();
        $checkTable = $db->query("SHOW TABLES LIKE 'subjects'");
        if ($checkTable->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Subjects table not found'], 404);
        }

        $result = $db->query("SELECT id, name, category FROM subjects ORDER BY name ASC");
        if ($result === false) {
            jsonResponse(['success' => false, 'message' => 'Query failed: ' . implode(', ', $db->errorInfo())], 500);
        }

        $subjects = $result->fetchAll();
        jsonResponse(['success' => true, 'subjects' => $subjects]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Exception in getSubjects: ' . $e->getMessage()], 500);
    }
}

function getProfile()
{
    $db = getDB();
    $table = $_SESSION['role'] === 'guru' ? 'teachers' : ($_SESSION['role'] === 'admin' ? 'admins' : 'students');

    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    unset($user['password']);
    jsonResponse(['success' => true, 'user' => $user]);
}

function updateProfile()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();

    // CSRF validation - FIXED: Pass session token as second argument
    if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'], $_SESSION['csrf_token'])) {
        logExamAction('WARNING', 'CSRF token validation failed for profile update', ['ip' => $_SERVER['REMOTE_ADDR']]);
        jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Silakan refresh halaman.'], 403);
    }

    $db = getDB();

    $fullName = sanitize($data['full_name'] ?? '');
    $email    = sanitize($data['email'] ?? '');
    $phone    = sanitize($data['phone'] ?? '');
    $password = $data['new_password'] ?? '';

    if (!$fullName || !$email) {
        jsonResponse(['success' => false, 'message' => 'Nama dan Email wajib diisi'], 400);
    }

    if ($password) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE teachers SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone, $hashed, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare("UPDATE teachers SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $phone, $_SESSION['user_id']]);
    }

    $_SESSION['full_name'] = $fullName;
    jsonResponse(['success' => true, 'message' => 'Profil berhasil diperbarui']);
}

function getStudentHistory()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT e.name, e.subject, es.status, es.submitted_at, es.time_taken_seconds, e.show_results_setting
        FROM exam_submissions es
        JOIN exams e ON e.id = es.exam_id
        WHERE es.student_id = ?
        ORDER BY es.submitted_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'history' => $stmt->fetchAll()]);
}

function getRecentViolations()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT v.*, s.full_name as student_name, s.class, e.name as exam_name
        FROM violations v
        JOIN students s ON s.id = v.student_id
        JOIN exams e ON e.id = v.exam_id
        WHERE e.teacher_id = ?
        ORDER BY v.created_at DESC LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'violations' => $stmt->fetchAll()]);
}

function getExamMonitor()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $examId = (int)($_GET['exam_id'] ?? 0);
    $db = getDB();

    // For admin, no teacher_id check
    if ($_SESSION['role'] !== 'admin') {
        // Verify teacher owns this exam
        $stmt = $db->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
    }

    $totalCount = $db->prepare("SELECT COUNT(*) FROM exam_submissions WHERE exam_id = ?");
    $totalCount->execute([$examId]);
    $total = $totalCount->fetchColumn();

    $finishedCount = $db->prepare("SELECT COUNT(*) FROM exam_submissions WHERE exam_id = ? AND status != 'in_progress'");
    $finishedCount->execute([$examId]);
    $finished = $finishedCount->fetchColumn();

    $activeCount = $db->prepare("SELECT COUNT(*) FROM exam_submissions WHERE exam_id = ? AND status = 'in_progress'");
    $activeCount->execute([$examId]);
    $active = $activeCount->fetchColumn();

    $violationCount = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM violations WHERE exam_id = ?");
    $violationCount->execute([$examId]);
    $violation = $violationCount->fetchColumn();

    $stats = [
        'total' => $total ?: 0,
        'active' => $active ?: 0,
        'finished' => $finished ?: 0,
        'violation' => $violation ?: 0,
    ];

    $stmt = $db->prepare("
        SELECT s.id as student_id, s.full_name, es.total_score as score, es.status, es.submitted_at, es.is_forced,
               (SELECT COUNT(*) FROM violations WHERE exam_id = ? AND student_id = s.id) as v_count
        FROM exam_submissions es
        JOIN students s ON s.id = es.student_id
        WHERE es.exam_id = ?
        ORDER BY es.started_at ASC
    ");
    $stmt->execute([$examId, $examId]);

    jsonResponse(['success' => true, 'stats' => $stats, 'participants' => $stmt->fetchAll()]);
}

function getStudents()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT s.* FROM students s
        JOIN exams e ON e.class = s.class
        WHERE e.teacher_id = ?
        ORDER BY s.class ASC, s.full_name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $students = $stmt->fetchAll();

    jsonResponse(['success' => true, 'students' => $students]);
}

function activateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    if ($_SESSION['role'] === 'admin') {
        // Admin can activate any exam
        $stmt = $db->prepare("UPDATE exams SET status = 'active' WHERE id = ?");
        $stmt->execute([$examId]);
    } else {
        // Teacher can only activate their own exams
        $stmt = $db->prepare("UPDATE exams SET status = 'active' WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    }

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => 'Ujian berhasil diaktifkan! Siswa sekarang dapat masuk.']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Gagal mengaktifkan ujian atau Anda bukan pemiliknya.'], 403);
    }
}

function deactivateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    if ($_SESSION['role'] === 'admin') {
        // Admin can deactivate any exam
        $stmt = $db->prepare("UPDATE exams SET status = 'ended' WHERE id = ?");
        $stmt->execute([$examId]);
    } else {
        // Teacher can only deactivate their own exams
        $stmt = $db->prepare("UPDATE exams SET status = 'ended' WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    }

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => 'Ujian telah dihentikan. Siswa tidak dapat masuk lagi.']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Gagal menghentikan ujian.']);
    }
}

function deleteExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    // Check exam exists and get its status
    if ($_SESSION['role'] === 'admin') {
        $check = $db->prepare("SELECT status FROM exams WHERE id = ?");
        $check->execute([$examId]);
    } else {
        $check = $db->prepare("SELECT status FROM exams WHERE id = ? AND teacher_id = ?");
        $check->execute([$examId, $_SESSION['user_id']]);
    }

    $exam = $check->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan.']);
    }

    if ($exam['status'] === 'active') {
        jsonResponse(['success' => false, 'message' => 'Ujian yang sedang aktif tidak boleh dihapus! Nonaktifkan (Stop) terlebih dahulu.']);
        return;
    }

    try {
        $db->beginTransaction();
        $stmtV = $db->prepare("DELETE FROM violations WHERE exam_id = ?");
        $stmtV->execute([$examId]);
        $stmtS = $db->prepare("DELETE FROM exam_submissions WHERE exam_id = ?");
        $stmtS->execute([$examId]);

        if ($_SESSION['role'] === 'admin') {
            $stmt = $db->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->execute([$examId]);
        } else {
            $stmt = $db->prepare("DELETE FROM exams WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$examId, $_SESSION['user_id']]);
        }

        $db->commit();

        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Ujian dan seluruh data terkait berhasil dihapus selamanya.']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Gagal menghapus ujian.']);
        }
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus ujian. Terjadi kesalahan server.'], 500);
    }
}

function duplicateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    try {
        $db->beginTransaction();

        // Get the exam to duplicate
        if ($_SESSION['role'] === 'admin') {
            $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
            $stmt->execute([$examId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$examId, $_SESSION['user_id']]);
        }

        $oldExam = $stmt->fetch();

        if (!$oldExam) throw new Exception("Ujian tidak ditemukan.");

        $newCode = strtoupper(bin2hex(random_bytes(4)));

        // For admin, preserve original teacher_id; for teacher, use their own ID
        $teacherId = ($_SESSION['role'] === 'admin') ? $oldExam['teacher_id'] : $_SESSION['user_id'];

        $stmtInsert = $db->prepare("
            INSERT INTO exams (teacher_id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
        ");
        $stmtInsert->execute([
            $teacherId,
            $oldExam['name'] . ' (Copy)',
            $oldExam['subject'],
            $oldExam['class'],
            $newCode,
            $oldExam['start_time'],
            $oldExam['end_time'],
            $oldExam['duration_minutes'],
            $oldExam['question_count'],
            $oldExam['description']
        ]);
        $newExamId = $db->lastInsertId();

        $stmtQuestions = $db->prepare("SELECT * FROM questions WHERE exam_id = ?");
        $stmtQuestions->execute([$examId]);
        $questions = $stmtQuestions->fetchAll();

        $stmtQInsert = $db->prepare("
            INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer, points, difficulty, media_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questions as $q) {
            $stmtQInsert->execute([
                $newExamId,
                $q['question_text'],
                $q['question_type'],
                $q['options'],
                $q['correct_answer'],
                $q['points'],
                $q['difficulty'],
                $q['media_url']
            ]);
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Ujian berhasil diduplikasi! Cek daftar ujian Anda.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Gagal menduplikasi ujian. Terjadi kesalahan server.']);
    }
}

function unlockStudent()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        // Allow admin access
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);
    $db = getDB();

    // Verify exam exists
    if ($_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("SELECT id FROM exams WHERE id = ?");
        $stmt->execute([$examId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    }

    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Akses ditolak.']);
    }

    try {
        $db->beginTransaction();
        $stmt1 = $db->prepare("DELETE FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
        $stmt1->execute([$examId, $studentId]);
        $stmt2 = $db->prepare("DELETE FROM violations WHERE exam_id = ? AND student_id = ?");
        $stmt2->execute([$examId, $studentId]);
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Toleransi diberikan. Siswa dapat login kembali ke ujian.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Gagal memberikan toleransi. Terjadi kesalahan server.']);
    }
}

function resetStudentResult()
{
    // Check authentication - allow both admin and teacher
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
        logExamAction('WARNING', 'Unauthorized reset attempt', ['ip' => $_SERVER['REMOTE_ADDR']]);
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);
    $db = getDB();

    // Verify exam exists and get details
    if ($_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("SELECT e.*, t.full_name as teacher_name FROM exams e JOIN teachers t ON t.id = e.teacher_id WHERE e.id = ?");
        $stmt->execute([$examId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    }

    $exam = $stmt->fetch();

    if (!$exam) {
        logExamAction('WARNING', 'Reset failed - exam not found', ['exam_id' => $examId]);
        jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan.']);
    }

    // Get student info for logging
    $stmtStudent = $db->prepare("SELECT full_name FROM students WHERE id = ?");
    $stmtStudent->execute([$studentId]);
    $student = $stmtStudent->fetch();

    if (!$student) {
        logExamAction('WARNING', 'Reset failed - student not found', ['student_id' => $studentId]);
        jsonResponse(['success' => false, 'message' => 'Siswa tidak ditemukan.']);
    }

    // Get submission details before deletion for logging
    $stmtSub = $db->prepare("SELECT total_score, status, submitted_at FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
    $stmtSub->execute([$examId, $studentId]);
    $submission = $stmtSub->fetch();

    if (!$submission) {
        jsonResponse(['success' => false, 'message' => 'Data submission tidak ditemukan.']);
    }

    // Get violation count before deletion for logging
    $stmtViolationCount = $db->prepare("SELECT COUNT(*) as count FROM violations WHERE exam_id = ? AND student_id = ?");
    $stmtViolationCount->execute([$examId, $studentId]);
    $violationCount = $stmtViolationCount->fetchColumn();

    try {
        // Start transaction
        $db->beginTransaction();

        // Delete violations first
        $stmtViolations = $db->prepare("DELETE FROM violations WHERE exam_id = ? AND student_id = ?");
        $stmtViolations->execute([$examId, $studentId]);
        $violationsDeleted = $stmtViolations->rowCount();

        // Delete exam_submissions record
        $stmtDelete = $db->prepare("DELETE FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
        $stmtDelete->execute([$examId, $studentId]);

        // Commit transaction
        $db->commit();

        // Log successful reset with violation info
        logExamAction('INFO', 'Student result reset and violations cleared', [
            'exam_id' => $examId,
            'exam_name' => $exam['name'],
            'student_id' => $studentId,
            'student_name' => $student['full_name'],
            'previous_score' => $submission['total_score'],
            'previous_status' => $submission['status'],
            'submitted_at' => $submission['submitted_at'],
            'violations_cleared' => $violationsDeleted,
            'total_violations_before' => $violationCount
        ]);

        jsonResponse(['success' => true, 'message' => 'Hasil ujian dan ' . $violationsDeleted . ' catatan pelanggaran berhasil direset. Siswa dapat mengerjakan ulang.']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();

        logExamAction('ERROR', 'Reset failed - database error', [
            'exam_id' => $examId,
            'student_id' => $studentId,
            'error' => $e->getMessage()
        ]);
        jsonResponse(['success' => false, 'message' => 'Gagal mereset hasil. Terjadi kesalahan server.'], 500);
    }
}

function joinExamAction()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $code = strtoupper(sanitize($data['exam_code'] ?? ''));

    if (!$code) {
        jsonResponse(['success' => false, 'message' => 'Masukkan kode ujian'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, status, start_time, end_time FROM exams 
        WHERE exam_code = ? LIMIT 1
    ");
    $stmt->execute([$code]);
    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Kode ujian tidak valid'], 404);
    }

    if ($exam['status'] !== 'active') {
        jsonResponse(['success' => false, 'message' => 'Ujian ini tidak sedang aktif'], 403);
    }

    $stmt = $db->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$exam['id'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Anda sudah mengerjakan ujian ini sebelumnya'], 409);
    }

    if (!isset($_SESSION['authorized_exams'])) {
        $_SESSION['authorized_exams'] = [];
    }
    if (!in_array($exam['id'], $_SESSION['authorized_exams'])) {
        $_SESSION['authorized_exams'][] = $exam['id'];
    }

    jsonResponse([
        'success' => true,
        'message' => 'Kode valid. Selamat mengerjakan!',
        'exam_id' => $exam['id']
    ]);
}

function startExam()
{
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);

    if (!$examId) {
        jsonResponse(['success' => false, 'message' => 'Exam ID required'], 400);
    }

    $db = getDB();
    $studentId = $_SESSION['user_id'];

    // Verify exam exists and is active
    $stmt = $db->prepare("SELECT id, name FROM exams WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan atau tidak aktif'], 404);
    }

    // Check if already started or completed
    $stmt = $db->prepare("
        SELECT id, status, submitted_at FROM exam_submissions 
        WHERE exam_id = ? AND student_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$examId, $studentId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['submitted_at']) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah menyelesaikan ujian ini sebelumnya'], 409);
        }
        if ($existing['status'] === 'in_progress') {
            jsonResponse(['success' => true, 'message' => 'Ujian sudah dimulai sebelumnya', 'already_started' => true]);
        }
    }

    // Insert new record - removed created_at column
    $stmt = $db->prepare("
        INSERT INTO exam_submissions (exam_id, student_id, started_at, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ");
    $stmt->execute([$examId, $studentId]);

    logExamAction('INFO', 'Student started exam', [
        'exam_id' => $examId,
        'exam_name' => $exam['name'],
        'student_id' => $studentId
    ]);

    jsonResponse(['success' => true, 'message' => 'Ujian dimulai']);
}

function getExam()
{
    $examId = (int)($_GET['exam_id'] ?? 0);
    $db = getDB();
    $role = $_SESSION['role'];

    if ($role === 'siswa') {
        if (!isset($_SESSION['authorized_exams']) || !in_array($examId, $_SESSION['authorized_exams'])) {
            jsonResponse(['success' => false, 'message' => 'Silakan masukkan kode ujian terlebih dahulu'], 403);
        }
    }

    if ($role === 'guru') {
        $stmt = $db->prepare("SELECT id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting FROM exams WHERE id = ? AND teacher_id = ? LIMIT 1");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare("SELECT id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting FROM exams WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$examId]);
    }

    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan atau Anda tidak memiliki akses'], 404);
    }

    if ($role === 'siswa') {
        $stmt = $db->prepare("SELECT id, status FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        $submission = $stmt->fetch();
        if ($submission && $submission['status'] === 'submitted') {
            jsonResponse(['success' => false, 'message' => 'Anda sudah mengerjakan ujian ini'], 409);
        }
    }

    $stmt = $db->prepare("SELECT id, question_text, question_text as text, question_type, question_type as type, options, correct_answer, points, media_url FROM questions WHERE exam_id = ? ORDER BY id ASC");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll();

    foreach ($questions as &$q) {
        if ($q['options']) {
            $decoded = json_decode($q['options'], true);
            $q['options'] = is_array($decoded) ? $decoded : [];
        } else {
            $q['options'] = [];
        }

        if ($role === 'siswa') {
            unset($q['correct_answer']);
        }
    }

    jsonResponse([
        'success'   => true,
        'exam'      => [
            'id'              => $exam['id'],
            'name'            => $exam['name'],
            'subject'         => $exam['subject'],
            'class'           => $exam['class'],
            'duration'        => $exam['duration_minutes'],
            'question_count'  => $exam['question_count'],
            'description'     => $exam['description'],
            'show_results_setting' => $exam['show_results_setting'] ?? 'direct_submit'
        ],
        'questions' => $questions
    ]);
}

function submitAnswers()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId  = (int)($data['exam_id'] ?? 0);
    $answers = $data['answers'] ?? [];
    $forced  = (bool)($data['forced'] ?? false);
    $timeTaken = (int)($data['time_taken'] ?? 0);

    $db = getDB();

    // Check if submission exists
    $stmt = $db->prepare("
        SELECT id, status FROM exam_submissions 
        WHERE exam_id = ? AND student_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $submission = $stmt->fetch();

    if (!$submission) {
        jsonResponse(['success' => false, 'message' => 'Session ujian tidak ditemukan. Silakan mulai ujian terlebih dahulu.'], 400);
    }

    if ($submission['status'] === 'submitted') {
        jsonResponse(['success' => false, 'message' => 'Jawaban sudah dikumpulkan sebelumnya'], 409);
    }

    $stmt = $db->prepare("SELECT id, correct_answer, points, question_type FROM questions WHERE exam_id = ?");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        jsonResponse(['success' => false, 'message' => 'Soal tidak ditemukan untuk ujian ini'], 404);
    }

    $totalPointsPossible = 0;
    $earnedPointsAuto = 0;
    $correctCount = 0;
    $answerLog = [];
    $hasEssay = false;

    $normalizedAnswers = [];
    if (is_array($answers)) {
        foreach ($answers as $key => $val) {
            $normalizedAnswers[(string)$key] = $val;
        }
    }

    foreach ($questions as $q) {
        $qId = (string)$q['id'];
        $qType = $q['question_type'];
        $points = (int)($q['points'] > 0 ? $q['points'] : 1);
        $totalPointsPossible += $points;

        $studentAnswer = null;
        if (array_key_exists($qId, $normalizedAnswers)) {
            $studentAnswer = $normalizedAnswers[$qId];
        }

        $isCorrect = false;
        if ($qType === 'multiple' || $qType === 'truefalse') {
            if ($studentAnswer !== null && $studentAnswer !== '') {
                $sAns = trim((string)$studentAnswer);
                $cAns = trim(html_entity_decode((string)$q['correct_answer']));

                if ($sAns === $cAns) {
                    $earnedPointsAuto += $points;
                    $correctCount++;
                    $isCorrect = true;
                }
            }
        } elseif ($qType === 'checkbox') {
            if ($studentAnswer !== null && is_array($studentAnswer)) {
                $cAnsRaw = html_entity_decode((string)$q['correct_answer']);
                $cAns = json_decode($cAnsRaw, true);

                if (!is_array($cAns)) {
                    $cAns = explode(',', $cAnsRaw);
                }

                sort($studentAnswer);
                sort($cAns);

                $studentAnswer = array_filter(array_map('trim', $studentAnswer), fn($v) => $v !== '');
                $cAns = array_filter(array_map('trim', $cAns), fn($v) => $v !== '');

                if (count($studentAnswer) === count($cAns) && array_diff($studentAnswer, $cAns) === array_diff($cAns, $studentAnswer)) {
                    $earnedPointsAuto += $points;
                    $correctCount++;
                    $isCorrect = true;
                }
            }
        } elseif ($qType === 'essay') {
            $hasEssay = true;
        }

        $answerLog[] = [
            'question_id'    => (int)$qId,
            'student_answer' => $studentAnswer,
            'is_correct'     => $isCorrect,
            'type'           => $qType,
            'points_earned'  => $isCorrect ? $points : 0
        ];
    }

    $scorePercentage = ($totalPointsPossible > 0) ? ($earnedPointsAuto / $totalPointsPossible) * 100 : 0;
    $newStatus = $hasEssay ? 'pending' : 'graded';

    // UPDATE instead of INSERT
    $stmt = $db->prepare("
        UPDATE exam_submissions 
        SET answers_json = ?, 
            score = ?, 
            manual_score = ?, 
            total_score = ?, 
            status = ?, 
            submitted_at = NOW(), 
            time_taken_seconds = ?, 
            is_forced = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($answerLog),
        (float)round($scorePercentage, 2),
        0.00,
        (float)round($scorePercentage, 2),
        $newStatus,
        $timeTaken,
        $forced ? 1 : 0,
        $submission['id']
    ]);

    jsonResponse([
        'success'    => true,
        'message'    => 'Jawaban berhasil dikumpulkan',
        'status'     => $newStatus,
        'has_essay'  => $hasEssay,
        'time_taken' => $timeTaken
    ]);
}

function reportViolation()
{
    $data = getInput();
    $examId    = (int)($data['exam_id'] ?? 0);
    $studentId = $_SESSION['user_id'] ?? 0;
    $reason    = sanitize($data['reason'] ?? '');
    $count     = (int)($data['violation_count'] ?? 1);

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO violations (exam_id, student_id, reason, violation_count, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$examId, $studentId, $reason, $count]);

    jsonResponse(['success' => true, 'message' => 'Pelanggaran dicatat']);
}

function getStudentViolations()
{
    // Check authentication (teacher or admin)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin', 'siswa'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $studentId = (int)($_GET['student_id'] ?? 0);
    $examId = (int)($_GET['exam_id'] ?? 0);

    if (!$studentId || !$examId) {
        jsonResponse(['success' => false, 'message' => 'Student ID and Exam ID required'], 400);
    }

    $db = getDB();

    // For teachers, verify they own this exam
    if ($_SESSION['role'] === 'guru') {
        $stmt = $db->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
    }

    $stmt = $db->prepare("
        SELECT id, reason, violation_count, created_at 
        FROM violations 
        WHERE student_id = ? AND exam_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$studentId, $examId]);
    $violations = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'violations' => $violations,
        'total_count' => count($violations)
    ]);
}

function deleteViolation()
{
    // Check authentication (teacher or admin only)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $violationId = (int)($data['violation_id'] ?? 0);

    if (!$violationId) {
        jsonResponse(['success' => false, 'message' => 'Violation ID required'], 400);
    }

    $db = getDB();

    // Get violation details to verify ownership
    $stmt = $db->prepare("
        SELECT v.*, e.teacher_id 
        FROM violations v
        JOIN exams e ON e.id = v.exam_id
        WHERE v.id = ?
    ");
    $stmt->execute([$violationId]);
    $violation = $stmt->fetch();

    if (!$violation) {
        jsonResponse(['success' => false, 'message' => 'Violation not found'], 404);
    }

    // Check permission
    if ($_SESSION['role'] === 'guru' && $violation['teacher_id'] != $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Delete the violation
    $stmt = $db->prepare("DELETE FROM violations WHERE id = ?");
    $stmt->execute([$violationId]);

    // Log the deletion
    logExamAction('INFO', 'Violation deleted', [
        'violation_id' => $violationId,
        'student_id' => $violation['student_id'],
        'exam_id' => $violation['exam_id'],
        'reason' => $violation['reason']
    ]);

    jsonResponse(['success' => true, 'message' => 'Violation deleted successfully']);
}

function getResults()
{
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $examId = (int)($_GET['exam_id'] ?? 0);
    $classFilter = sanitize($_GET['class'] ?? '');
    $db = getDB();

    $query = "
        SELECT es.id as submission_id, s.full_name, s.nisn, s.class, 
               es.score as auto_score, es.manual_score, es.total_score, 
               es.status, es.time_taken_seconds, es.is_forced, es.submitted_at,
               (SELECT COUNT(*) FROM violations WHERE exam_id = es.exam_id AND student_id = es.student_id) as violation_count
        FROM exam_submissions es
        JOIN students s ON s.id = es.student_id
        WHERE es.exam_id = ?
    ";

    $params = [$examId];
    if ($classFilter) {
        $query .= " AND s.class LIKE ?";
        $params[] = "$classFilter%";
    }

    $query .= " ORDER BY es.total_score DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    $scores = array_column($results, 'total_score');
    $stats = [
        'total'   => count($results),
        'average' => count($scores) ? round(array_sum($scores) / count($scores), 1) : 0,
        'highest' => count($scores) ? max($scores) : 0,
        'lowest'  => count($scores) ? min($scores) : 0,
        'passed'  => count(array_filter($scores, fn($s) => $s >= 75)),
    ];

    jsonResponse(['success' => true, 'results' => $results, 'stats' => $stats]);
}

function createExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $db = getDB();

    $examCode = strtoupper(bin2hex(random_bytes(4)));

    $duration       = (int)($data['duration'] ?? 90);
    $questionCount  = (int)($data['question_count'] ?? 40);

    if ($duration <= 0 || $duration > 240) {
        jsonResponse(['success' => false, 'message' => 'Durasi ujian harus antara 1 dan 240 menit.'], 400);
    }
    if ($questionCount <= 0 || $questionCount > 200) {
        jsonResponse(['success' => false, 'message' => 'Jumlah soal harus antara 1 dan 200.'], 400);
    }

    $showResultsSetting = sanitize($data['show_results_setting'] ?? 'direct_submit');

    $stmt = $db->prepare("
        INSERT INTO exams (teacher_id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        sanitize($data['name'] ?? ''),
        sanitize($data['subject'] ?? ''),
        sanitize($data['class'] ?? ''),
        $examCode,
        $data['start_time'] ?? null,
        $data['end_time'] ?? null,
        $duration,
        $questionCount,
        sanitizeHTML($data['description'] ?? ''),
        $showResultsSetting,
    ]);

    $examId = $db->lastInsertId();

    if (!empty($data['questions'])) {
        $qStmt = $db->prepare("
            INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer, points, difficulty, media_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['questions'] as $q) {
            $points = isset($q['points']) ? (int)$q['points'] : 1;
            if ($points <= 0) $points = 1;

            $correctAnswer = $q['correct_answer'] ?? '';
            $mediaUrl = $q['media_url'] ?? '[]';

            $qStmt->execute([
                $examId,
                sanitizeHTML($q['text'] ?? ''),
                sanitize($q['type'] ?? 'multiple'),
                json_encode($q['options'] ?? []),
                $correctAnswer,
                $points,
                sanitize($q['difficulty'] ?? 'medium'),
                $mediaUrl
            ]);
        }
    }

    jsonResponse(['success' => true, 'exam_id' => $examId, 'exam_code' => $examCode]);
}

function getExams()
{
    $db = getDB();
    $role = $_SESSION['role'];

    if ($role === 'guru') {
        $stmt = $db->prepare("SELECT * FROM exams WHERE teacher_id = ? ORDER BY class ASC, created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
    } elseif ($role === 'admin') {
        $stmt = $db->query("SELECT e.*, t.full_name as teacher_name FROM exams e JOIN teachers t ON t.id = e.teacher_id ORDER BY e.created_at DESC");
    } else {
        $stmtS = $db->prepare("SELECT class FROM students WHERE id = ?");
        $stmtS->execute([$_SESSION['user_id']]);
        $studentClass = $stmtS->fetchColumn();

        $level = '';
        if (strpos($studentClass, 'XII') !== false) $level = 'Kelas XII';
        elseif (strpos($studentClass, 'XI') !== false) $level = 'Kelas XI';
        elseif (strpos($studentClass, 'X') !== false) $level = 'Kelas X';

        $major = '';
        if (strpos($studentClass, 'IPA') !== false) $major = 'IPA';
        elseif (strpos($studentClass, 'IPS') !== false) $major = 'IPS';

        $stmt = $db->prepare("
            SELECT e.*, s.category as subject_category 
            FROM exams e
            JOIN subjects s ON s.name = e.subject
            WHERE e.status = 'active' 
            AND e.class = ?
            AND (s.category = 'Umum' " . ($major ? "OR s.category = ?" : "") . ")
        ");

        $params = [$level];
        if ($major) $params[] = $major;

        $stmt->execute($params);
        $exams = $stmt->fetchAll();

        $authorized = $_SESSION['authorized_exams'] ?? [];
        foreach ($exams as &$exam) {
            $exam['is_authorized'] = in_array($exam['id'], $authorized);

            $stmtS = $db->prepare("SELECT id, is_forced, status FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
            $stmtS->execute([$exam['id'], $_SESSION['user_id']]);
            $sub = $stmtS->fetch();
            $exam['is_submitted'] = (bool)$sub && $sub['status'] === 'submitted';
            $exam['is_forced'] = $sub ? (bool)$sub['is_forced'] : false;
            $exam['is_in_progress'] = (bool)$sub && $sub['status'] === 'in_progress';
        }

        jsonResponse(['success' => true, 'exams' => $exams]);
        return;
    }

    jsonResponse(['success' => true, 'exams' => $stmt->fetchAll()]);
}

function getTeacherStats()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();

    try {
        $stmt1 = $db->prepare("
            SELECT COUNT(DISTINCT s.id) as total_students
            FROM students s
            WHERE s.class IN (
                SELECT DISTINCT class FROM exams WHERE teacher_id = ?
            )
        ");
        $stmt1->execute([$_SESSION['user_id']]);
        $result1 = $stmt1->fetch();
        $totalStudents = (int)($result1['total_students'] ?? 0);

        $stmt2 = $db->prepare("
            SELECT AVG(COALESCE(es.total_score, 0)) as avg_score
            FROM exam_submissions es
            JOIN exams e ON e.id = es.exam_id
            WHERE e.teacher_id = ?
        ");
        $stmt2->execute([$_SESSION['user_id']]);
        $result2 = $stmt2->fetch();
        $avgScore = (float)($result2['avg_score'] ?? 0);
        $avgScore = round($avgScore, 1);

        jsonResponse([
            'success' => true,
            'total_students' => $totalStudents,
            'average_score' => $avgScore
        ]);
    } catch (Exception $e) {
        error_log("[getTeacherStats] " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Error getting stats'], 500);
    }
}

function getBankQuestions()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $db = getDB();
        $checkTable = $db->query("SHOW TABLES LIKE 'question_bank'");
        if ($checkTable->rowCount() === 0) {
            jsonResponse(['success' => true, 'questions' => [], 'message' => 'Bank Soal belum diinisialisasi']);
            return;
        }

        $category = sanitize($_GET['category'] ?? '');
        $search = sanitize($_GET['search'] ?? '');

        $query = "SELECT id, question_text, question_type, options, correct_answer, points, difficulty, category, media_url, created_at FROM question_bank WHERE teacher_id = ?";
        $params = [$_SESSION['user_id']];

        if (!empty($category)) {
            $query .= " AND category = ?";
            $params[] = $category;
        }

        if (!empty($search)) {
            $query .= " AND (question_text LIKE ? OR category LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . implode(", ", $db->errorInfo()));
        }

        if (!$stmt->execute($params)) {
            throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
        }

        $questions = $stmt->fetchAll();

        jsonResponse(['success' => true, 'questions' => $questions]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error loading bank questions: ' . $e->getMessage()], 500);
    }
}

function getBankQuestion()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $questionId = (int)($_GET['id'] ?? 0);
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM question_bank WHERE id = ? AND teacher_id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . implode(", ", $db->errorInfo()));

        if (!$stmt->execute([$questionId, $_SESSION['user_id']])) {
            throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
        }

        $question = $stmt->fetch();

        if (!$question) {
            jsonResponse(['success' => false, 'message' => 'Soal tidak ditemukan'], 404);
        }

        $question['options'] = json_decode($question['options'], true);
        jsonResponse(['success' => true, 'question' => $question]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

function saveQuestionToBank()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $data = getInput();
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO question_bank (teacher_id, question_text, question_type, options, correct_answer, points, difficulty, category, media_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) throw new Exception("Prepare failed: " . implode(", ", $db->errorInfo()));

        $success = $stmt->execute([
            $_SESSION['user_id'],
            sanitize($data['question_text'] ?? ''),
            sanitize($data['question_type'] ?? 'multiple'),
            json_encode($data['options'] ?? []),
            $data['correct_answer'] ?? '',
            (int)($data['points'] ?? 1),
            sanitize($data['difficulty'] ?? 'medium'),
            sanitize($data['category'] ?? 'Umum'),
            $data['media_url'] ?? ''
        ]);

        if (!$success) {
            throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
        }

        $questionId = $db->lastInsertId();
        jsonResponse(['success' => true, 'message' => 'Soal berhasil disimpan', 'question_id' => $questionId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error saving question: ' . $e->getMessage()], 500);
    }
}

function updateBankQuestion()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $data = getInput();
        $questionId = (int)($data['id'] ?? 0);
        $db = getDB();

        $stmt = $db->prepare("SELECT teacher_id FROM question_bank WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare ownership check failed");

        if (!$stmt->execute([$questionId])) {
            throw new Exception("Execute ownership check failed");
        }

        $question = $stmt->fetch();

        if (!$question || $question['teacher_id'] != $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki akses ke soal ini'], 403);
        }

        $updateStmt = $db->prepare("
            UPDATE question_bank SET 
                question_text = ?, 
                question_type = ?, 
                options = ?, 
                correct_answer = ?, 
                points = ?, 
                difficulty = ?, 
                category = ?, 
                media_url = ?
            WHERE id = ?
        ");

        if (!$updateStmt) throw new Exception("Prepare update failed");

        $success = $updateStmt->execute([
            sanitize($data['question_text'] ?? ''),
            sanitize($data['question_type'] ?? 'multiple'),
            json_encode($data['options'] ?? []),
            $data['correct_answer'] ?? '',
            (int)($data['points'] ?? 1),
            sanitize($data['difficulty'] ?? 'medium'),
            sanitize($data['category'] ?? 'Umum'),
            $data['media_url'] ?? '',
            $questionId
        ]);

        if (!$success) {
            throw new Exception("Execute update failed: " . implode(", ", $updateStmt->errorInfo()));
        }

        jsonResponse(['success' => true, 'message' => 'Soal berhasil diubah']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error updating question: ' . $e->getMessage()], 500);
    }
}

function deleteBankQuestion()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $input = getInput();

        $questionId = (int)($input['id'] ?? 0);
        if (!$questionId) {
            jsonResponse(['success' => false, 'message' => 'ID soal tidak valid'], 400);
        }

        if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])) {
            logExamAction('WARNING', 'CSRF token validation failed for delete bank question', [
                'question_id' => $questionId,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            jsonResponse(['success' => false, 'message' => 'Token keamanan tidak valid. Silakan refresh halaman.'], 403);
        }

        $db = getDB();

        $stmt = $db->prepare("SELECT teacher_id FROM question_bank WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare ownership check failed");

        if (!$stmt->execute([$questionId])) {
            throw new Exception("Execute ownership check failed");
        }

        $question = $stmt->fetch();

        if (!$question) {
            jsonResponse(['success' => false, 'message' => 'Soal tidak ditemukan'], 404);
        }

        if ($question['teacher_id'] != $_SESSION['user_id']) {
            logExamAction('WARNING', 'Delete bank question failed - ownership mismatch', [
                'question_id' => $questionId,
                'teacher_id' => $_SESSION['user_id'],
                'owner_id' => $question['teacher_id']
            ]);
            jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki akses ke soal ini'], 403);
        }

        // Delete the question
        $delStmt = $db->prepare("DELETE FROM question_bank WHERE id = ?");
        if (!$delStmt) throw new Exception("Prepare delete failed");

        if (!$delStmt->execute([$questionId])) {
            throw new Exception("Execute delete failed: " . implode(", ", $delStmt->errorInfo()));
        }

        // Log successful deletion
        logExamAction('INFO', 'Bank question deleted successfully', [
            'question_id' => $questionId,
            'teacher_id' => $_SESSION['user_id']
        ]);

        jsonResponse(['success' => true, 'message' => 'Soal berhasil dihapus']);
    } catch (Exception $e) {
        error_log("[deleteBankQuestion] Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus soal: ' . $e->getMessage()], 500);
    }
}

function copyQuestionToExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $data = getInput();
        $bankQuestionId = (int)($data['bank_question_id'] ?? 0);
        $examId = (int)($data['exam_id'] ?? 0);
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM question_bank WHERE id = ? AND teacher_id = ?");
        if (!$stmt) throw new Exception("Prepare bank question check failed");

        if (!$stmt->execute([$bankQuestionId, $_SESSION['user_id']])) {
            throw new Exception("Execute bank question check failed");
        }

        $bankQuestion = $stmt->fetch();

        if (!$bankQuestion) {
            jsonResponse(['success' => false, 'message' => 'Soal dari bank tidak ditemukan'], 404);
        }

        $examStmt = $db->prepare("SELECT teacher_id FROM exams WHERE id = ?");
        if (!$examStmt) throw new Exception("Prepare exam check failed");

        if (!$examStmt->execute([$examId])) {
            throw new Exception("Execute exam check failed");
        }

        $exam = $examStmt->fetch();

        if (!$exam || $exam['teacher_id'] != $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan atau Anda tidak memiliki akses'], 403);
        }

        $copyStmt = $db->prepare("
            INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer, points, difficulty, media_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$copyStmt) throw new Exception("Prepare copy statement failed");

        $success = $copyStmt->execute([
            $examId,
            $bankQuestion['question_text'],
            $bankQuestion['question_type'],
            $bankQuestion['options'],
            $bankQuestion['correct_answer'],
            $bankQuestion['points'],
            $bankQuestion['difficulty'],
            $bankQuestion['media_url']
        ]);

        if (!$success) {
            throw new Exception("Execute copy statement failed: " . implode(", ", $copyStmt->errorInfo()));
        }

        $questionId = $db->lastInsertId();
        jsonResponse(['success' => true, 'message' => 'Soal berhasil disalin', 'question_id' => $questionId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error copying question: ' . $e->getMessage()], 500);
    }
}

function logAgreement()
{
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);

    if (!$examId) {
        jsonResponse(['success' => false, 'message' => 'Exam ID required'], 400);
    }

    $db = getDB();

    // Verify exam exists and is active
    $stmt = $db->prepare("SELECT id, name FROM exams WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Exam not found or not active'], 404);
    }

    // Log to file
    logExamAction('INFO', 'Student agreed to exam rules', [
        'exam_id' => $examId,
        'exam_name' => $exam['name'],
        'student_id' => $_SESSION['user_id'],
        'agreed_at' => date('Y-m-d H:i:s')
    ]);

    jsonResponse(['success' => true, 'message' => 'Agreement recorded']);
}
