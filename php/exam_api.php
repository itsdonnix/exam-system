<?php

/**
 * ExamSafe — Exam API
 * GET  /php/exam_api.php?action=get_exam&exam_id=1
 * POST /php/exam_api.php?action=submit_answers
 * POST /php/exam_api.php?action=report_violation
 * GET  /php/exam_api.php?action=get_results&exam_id=1
 */

session_start();

require_once 'db.php';

// Set error handling to prevent HTML error pages
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("[API Error] $errstr in $errfile:$errline");
    jsonResponse(['success' => false, 'message' => 'API Error: ' . $errstr], 500);
    return true; // Mark error as handled
});

try {
    $input = getInput();
    $action = $_GET['action'] ?? $input['action'] ?? '';

    switch ($action) {
        case 'get_exam':
            getExam();
            break;
        case 'submit_answers':
            submitAnswers();
            break;
        case 'report_violation':
            reportViolation();
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
        // Bank Soal (Question Bank) API
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
        default:
            jsonResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
    }
} catch (Exception $e) {
    error_log("[API Exception] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}

// ===== GET SUBMISSION DETAIL (for teacher) =====
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

// ===== SAVE MANUAL GRADE (for teacher) =====
function saveManualGrade()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $submissionId = (int)($data['submission_id'] ?? 0);
    $manualScore = (float)($data['manual_score'] ?? 0); // This is raw points from teacher

    $db = getDB();

    // Get current auto score and exam_id
    $stmt = $db->prepare("SELECT score, exam_id FROM exam_submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    $sub = $stmt->fetch();

    if (!$sub) jsonResponse(['success' => false, 'message' => 'Submission tidak ditemukan'], 404);

    $examId = $sub['exam_id'];

    // Get total possible points for this exam
    $stmtP = $db->prepare("SELECT SUM(points) as total_points FROM questions WHERE exam_id = ?");
    $stmtP->execute([$examId]);
    $totalPoints = (int)$stmtP->fetchColumn();

    if ($totalPoints <= 0) $totalPoints = 1; // Prevent division by zero

    // Recalculate total score
    // sub['score'] is already (earnedAutoPoints / totalPoints) * 100
    $autoPoints = ($sub['score'] / 100) * $totalPoints;
    $totalScore = (($autoPoints + $manualScore) / $totalPoints) * 100;

    if ($totalScore > 100) $totalScore = 100;

    $stmtU = $db->prepare("UPDATE exam_submissions SET manual_score = ?, total_score = ?, status = 'graded' WHERE id = ?");
    $stmtU->execute([$manualScore, $totalScore, $submissionId]);

    jsonResponse(['success' => true, 'message' => 'Nilai berhasil disimpan', 'total_score' => round($totalScore, 2)]);
}

// ===== GET CLASSES =====
function getClasses()
{

    $db = getDB();
    $classes = $db->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
    jsonResponse(['success' => true, 'classes' => $classes]);
}

// ===== GET SUBJECTS =====
function getSubjects()
{


    try {
        $db = getDB();
        // Check if subjects table exists
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

// ===== GET PROFILE (for teacher) =====
function getProfile()
{


    $db = getDB();
    $table = $_SESSION['role'] === 'guru' ? 'teachers' : ($_SESSION['role'] === 'admin' ? 'admins' : 'students');

    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    unset($user['password']); // Jangan kirim password
    jsonResponse(['success' => true, 'user' => $user]);
}

// ===== UPDATE PROFILE (for teacher) =====
function updateProfile()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
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

// ===== GET STUDENT HISTORY =====
function getStudentHistory()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT e.name, e.subject, es.total_score as score, es.status, es.submitted_at, es.time_taken_seconds, e.show_results_setting
        FROM exam_submissions es
        JOIN exams e ON e.id = es.exam_id
        WHERE es.student_id = ?
        ORDER BY es.submitted_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'history' => $stmt->fetchAll()]);
}

// ===== GET RECENT VIOLATIONS (for teacher) =====
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

// ===== GET EXAM MONITOR (Real-time progress) =====
function getExamMonitor()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $examId = (int)($_GET['exam_id'] ?? 0);
    $db = getDB();

    // Stats
    $totalCount = $db->prepare("SELECT COUNT(*) FROM students s JOIN exams e ON e.class = s.class WHERE e.id = ?");
    $totalCount->execute([$examId]);
    $total = $totalCount->fetchColumn();

    $finishedCount = $db->prepare("SELECT COUNT(*) FROM exam_submissions WHERE exam_id = ?");
    $finishedCount->execute([$examId]);
    $finished = $finishedCount->fetchColumn();

    $violationCount = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM violations WHERE exam_id = ?");
    $violationCount->execute([$examId]);
    $violation = $violationCount->fetchColumn();

    $stats = [
        'total' => $total ?: 0,
        'finished' => $finished ?: 0,
        'violation' => $violation ?: 0,
    ];

    // List participants
    $stmt = $db->prepare("
        SELECT s.id as student_id, s.full_name, es.total_score as score, es.status, es.submitted_at, es.is_forced,
               (SELECT COUNT(*) FROM violations WHERE exam_id = ? AND student_id = s.id) as v_count
        FROM exam_submissions es
        JOIN students s ON s.id = es.student_id
        WHERE es.exam_id = ?
    ");
    $stmt->execute([$examId, $examId]);

    jsonResponse(['success' => true, 'stats' => $stats, 'participants' => $stmt->fetchAll()]);
}

// ===== GET STUDENTS (for teacher) =====
function getStudents()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();
    // Ambil siswa yang ada di kelas yang diampu guru
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

// ===== ACTIVATE EXAM (for teacher) =====
function activateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    $stmt = $db->prepare("UPDATE exams SET status = 'active' WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => 'Ujian berhasil diaktifkan! Siswa sekarang dapat masuk.']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Gagal mengaktifkan ujian atau Anda bukan pemiliknya.'], 403);
    }
}

// ===== DEACTIVATE EXAM (for teacher) =====
function deactivateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    $stmt = $db->prepare("UPDATE exams SET status = 'ended' WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(['success' => true, 'message' => 'Ujian telah dihentikan. Siswa tidak dapat masuk lagi.']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Gagal menghentikan ujian.']);
    }
}

// ===== DELETE EXAM (for teacher) =====
function deleteExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    // Cek status terlebih dahulu
    $check = $db->prepare("SELECT status FROM exams WHERE id = ? AND teacher_id = ?");
    $check->execute([$examId, $_SESSION['user_id']]);
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

        // 1. Hapus data pelanggaran terkait
        $stmtV = $db->prepare("DELETE FROM violations WHERE exam_id = ?");
        $stmtV->execute([$examId]);

        // 2. Hapus data submission terkait
        $stmtS = $db->prepare("DELETE FROM exam_submissions WHERE exam_id = ?");
        $stmtS->execute([$examId]);

        // 3. Hapus soal (sudah ada ON DELETE CASCADE di DB sebenarnya, tapi untuk keamanan kita biarkan query utama jalan)
        // 4. Hapus ujian
        $stmt = $db->prepare("DELETE FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);

        $db->commit();

        if ($stmt->rowCount() > 0) {
            jsonResponse(['success' => true, 'message' => 'Ujian dan seluruh data terkait berhasil dihapus selamanya.']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Gagal menghapus ujian.']);
        }
    } catch (Exception $e) {
        $db->rollBack();
        // Log internal error: error_log("Delete Exam failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus ujian. Terjadi kesalahan server.'], 500);
    }
}

// ===== DUPLICATE EXAM (for teacher) =====
function duplicateExam()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $db = getDB();

    try {
        $db->beginTransaction();

        // 1. Ambil data ujian lama
        $stmt = $db->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        $oldExam = $stmt->fetch();

        if (!$oldExam) throw new Exception("Ujian tidak ditemukan.");

        // 2. Buat ujian baru (Copy)
        $newCode = strtoupper(bin2hex(random_bytes(4)));
        $stmtInsert = $db->prepare("
            INSERT INTO exams (teacher_id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
        ");
        $stmtInsert->execute([
            $oldExam['teacher_id'],
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

        // 3. Salin semua soal
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
        // Log internal error: error_log("Duplicate Exam failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menduplikasi ujian. Terjadi kesalahan server.']);
    }
}

// ===== UNLOCK STUDENT (Tolerance) =====
function unlockStudent()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId = (int)($data['exam_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);
    $db = getDB();

    // Pastikan guru ini adalah pemilik ujian
    $stmt = $db->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Akses ditolak.']);
    }

    try {
        $db->beginTransaction();

        // 1. Hapus submission yang bersifat forced/sementara
        $stmt1 = $db->prepare("DELETE FROM exam_submissions WHERE exam_id = ? AND student_id = ?");
        $stmt1->execute([$examId, $studentId]);

        // 2. Hapus catatan pelanggaran agar counter keamanan kembali ke 0
        $stmt2 = $db->prepare("DELETE FROM violations WHERE exam_id = ? AND student_id = ?");
        $stmt2->execute([$examId, $studentId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Toleransi diberikan. Siswa dapat login kembali ke ujian.']);
    } catch (Exception $e) {
        $db->rollBack();
        // Log internal error: error_log("Unlock Student failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal memberikan toleransi. Terjadi kesalahan server.']);
    }
}

// ===== JOIN EXAM (by code) =====
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

    // Check if already submitted
    $stmt = $db->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$exam['id'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Anda sudah mengerjakan ujian ini sebelumnya'], 409);
    }

    // Authorize this exam for this session
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

// ===== GET EXAM (for student and teacher) =====
function getExam()
{


    $examId = (int)($_GET['exam_id'] ?? 0);
    $db = getDB();
    $role = $_SESSION['role'];

    // Security Check for Students
    if ($role === 'siswa') {
        if (!isset($_SESSION['authorized_exams']) || !in_array($examId, $_SESSION['authorized_exams'])) {
            jsonResponse(['success' => false, 'message' => 'Silakan masukkan kode ujian terlebih dahulu'], 403);
        }
    }

    // Prepare Query
    if ($role === 'guru') {
        $stmt = $db->prepare("SELECT id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting FROM exams WHERE id = ? AND teacher_id = ? LIMIT 1");
        $stmt->execute([$examId, $_SESSION['user_id']]);
    } else {
        // Admin or Student
        $stmt = $db->prepare("SELECT id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting FROM exams WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$examId]);
    }

    $exam = $stmt->fetch();

    if (!$exam) {
        jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan atau Anda tidak memiliki akses'], 404);
    }

    // Security Check for Students (Duplicate Submission)
    if ($role === 'siswa') {
        $stmt = $db->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
        $stmt->execute([$examId, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah mengerjakan ujian ini'], 409);
        }
    }

    // Get questions
    $stmt = $db->prepare("SELECT id, question_text, question_text as text, question_type, question_type as type, options, correct_answer, points, media_url FROM questions WHERE exam_id = ? ORDER BY id ASC");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll();

    // Prepare questions for frontend
    foreach ($questions as &$q) {
        if ($q['options']) {
            $decoded = json_decode($q['options'], true);
            $q['options'] = is_array($decoded) ? $decoded : [];
        } else {
            $q['options'] = [];
        }

        // Security: Don't send correct answers to students!
        if ($role === 'siswa') {
            unset($q['correct_answer']);
        }
    }

    jsonResponse([
        'success'   => true,
        'exam'      => [
            'id'          => $exam['id'],
            'name'        => $exam['name'],
            'subject'     => $exam['subject'],
            'duration'    => $exam['duration_minutes'],
            'description' => $exam['description'],
        ],
        'questions' => $questions
    ]);
}

// ===== SUBMIT ANSWERS =====
function submitAnswers()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $data = getInput();
    $examId  = (int)($data['exam_id'] ?? 0);
    $answers = $data['answers'] ?? []; // Associative array { "qId": "answer" }
    $forced  = (bool)($data['forced'] ?? false);
    $timeTaken = (int)($data['time_taken'] ?? 0);

    $db = getDB();

    // Prevent double submission
    $stmt = $db->prepare("SELECT id FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Jawaban sudah dikumpulkan sebelumnya'], 409);
    }

    // Get all questions for this exam
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

    // Normalize student answers: ensure all keys are strings for reliable lookup
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

        // Find student answer by question ID
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
                // Correct answers for checkbox should be stored as JSON array string in DB
                $cAnsRaw = html_entity_decode((string)$q['correct_answer']);
                $cAns = json_decode($cAnsRaw, true);

                if (!is_array($cAns)) {
                    // Fallback if not JSON (maybe comma separated)
                    $cAns = explode(',', $cAnsRaw);
                }

                // Sort both to compare
                sort($studentAnswer);
                sort($cAns);

                // Remove empty values and trim
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

    // Calculate score as percentage
    $scorePercentage = ($totalPointsPossible > 0) ? ($earnedPointsAuto / $totalPointsPossible) * 100 : 0;
    $status = $hasEssay ? 'pending' : 'graded';

    // Insert into DB
    // Columns: 1:exam_id, 2:student_id, 3:answers_json, 4:score, 5:manual_score, 6:total_score, 7:status, 8:time_taken_seconds, 9:is_forced, 10:submitted_at
    $stmt = $db->prepare("
        INSERT INTO exam_submissions (exam_id, student_id, answers_json, score, manual_score, total_score, status, time_taken_seconds, is_forced, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $examId,
        $_SESSION['user_id'],
        json_encode($answerLog),
        (float)round($scorePercentage, 2),
        0.00, // manual_score
        (float)round($scorePercentage, 2), // total_score
        $status,
        $timeTaken,
        $forced ? 1 : 0
    ]);

    jsonResponse([
        'success'    => true,
        'message'    => 'Jawaban berhasil dikumpulkan',
        'score'      => round($scorePercentage, 1),
        'correct'    => $correctCount,
        'total'      => count($questions),
        'status'     => $status,
        'has_essay'  => $hasEssay,
        'time_taken' => $timeTaken
    ]);
}

// ===== REPORT VIOLATION =====
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

// ===== GET RESULTS (for teacher) =====
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

// ===== CREATE EXAM (for teacher) =====
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

    // Validate numeric inputs
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
        sanitize($data['description'] ?? ''),
        $showResultsSetting,
    ]);

    $examId = $db->lastInsertId();

    // Insert questions
    if (!empty($data['questions'])) {
        $qStmt = $db->prepare("
            INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer, points, difficulty, media_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['questions'] as $q) {
            $points = isset($q['points']) ? (int)$q['points'] : 1;
            if ($points <= 0) $points = 1;

            $correctAnswer = $q['correct_answer'] ?? ''; // Already JSON string or plain string from frontend
            $mediaUrl = $q['media_url'] ?? '[]'; // Already JSON string from frontend

            $qStmt->execute([
                $examId,
                sanitize($q['text'] ?? ''),
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

// ===== GET EXAMS LIST =====
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
        // Role Siswa: Hanya lihat ujian aktif yang sesuai level & jurusan
        // 1. Ambil data kelas siswa (contoh: 'XII IPA 1')
        $stmtS = $db->prepare("SELECT class FROM students WHERE id = ?");
        $stmtS->execute([$_SESSION['user_id']]);
        $studentClass = $stmtS->fetchColumn();

        // 2. Tentukan jenjang (X, XI, XII) dan jurusan (IPA, IPS)
        $level = '';
        if (strpos($studentClass, 'XII') !== false) $level = 'Kelas XII';
        elseif (strpos($studentClass, 'XI') !== false) $level = 'Kelas XI';
        elseif (strpos($studentClass, 'X') !== false) $level = 'Kelas X';

        $major = '';
        if (strpos($studentClass, 'IPA') !== false) $major = 'IPA';
        elseif (strpos($studentClass, 'IPS') !== false) $major = 'IPS';

        // 3. Ambil ujian yang statusnya active
        // Filter: exam.class match student level AND (subject category is 'Umum' OR match student major)
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

            // Check if already submitted
            $stmtS = $db->prepare("SELECT id, is_forced FROM exam_submissions WHERE exam_id = ? AND student_id = ? LIMIT 1");
            $stmtS->execute([$exam['id'], $_SESSION['user_id']]);
            $sub = $stmtS->fetch();
            $exam['is_submitted'] = (bool)$sub;
            $exam['is_forced'] = $sub ? (bool)$sub['is_forced'] : false;
        }

        jsonResponse(['success' => true, 'exams' => $exams]);
        return;
    }

    jsonResponse(['success' => true, 'exams' => $stmt->fetchAll()]);
}

// ===== GET TEACHER STATS (Total Siswa & Rata-rata Nilai) =====
function getTeacherStats()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDB();

    try {
        // Total Siswa - hitung distinct siswa di kelas yang ada ujian guru
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

        // Rata-rata Nilai - average dari semua submissions di ujian guru
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

// ===== BANK SOAL (QUESTION BANK) FUNCTIONS =====

// Get all questions from teacher's question bank
function getBankQuestions()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $db = getDB();

        // Check if table exists
        $checkTable = $db->query("SHOW TABLES LIKE 'question_bank'");
        if ($checkTable->rowCount() === 0) {
            // Table doesn't exist - return empty list rather than error
            // This allow page to load, but tell user to create questions
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

// Get detail of single question from bank
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

// Save new question to bank
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

// Update question in bank
function updateBankQuestion()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $data = getInput();
        $questionId = (int)($data['id'] ?? 0);
        $db = getDB();

        // Verify ownership
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

// Delete question from bank
function deleteBankQuestion()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        $questionId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $db = getDB();

        // Verify ownership
        $stmt = $db->prepare("SELECT teacher_id FROM question_bank WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare ownership check failed");

        if (!$stmt->execute([$questionId])) {
            throw new Exception("Execute ownership check failed");
        }

        $question = $stmt->fetch();

        if (!$question || $question['teacher_id'] != $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki akses ke soal ini'], 403);
        }

        $delStmt = $db->prepare("DELETE FROM question_bank WHERE id = ?");
        if (!$delStmt) throw new Exception("Prepare delete failed");

        if (!$delStmt->execute([$questionId])) {
            throw new Exception("Execute delete failed: " . implode(", ", $delStmt->errorInfo()));
        }

        jsonResponse(['success' => true, 'message' => 'Soal berhasil dihapus']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error deleting question: ' . $e->getMessage()], 500);
    }
}

// Copy question from bank to exam
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

        // Verify bank question ownership
        $stmt = $db->prepare("SELECT * FROM question_bank WHERE id = ? AND teacher_id = ?");
        if (!$stmt) throw new Exception("Prepare bank question check failed");

        if (!$stmt->execute([$bankQuestionId, $_SESSION['user_id']])) {
            throw new Exception("Execute bank question check failed");
        }

        $bankQuestion = $stmt->fetch();

        if (!$bankQuestion) {
            jsonResponse(['success' => false, 'message' => 'Soal dari bank tidak ditemukan'], 404);
        }

        // Verify exam ownership
        $examStmt = $db->prepare("SELECT teacher_id FROM exams WHERE id = ?");
        if (!$examStmt) throw new Exception("Prepare exam check failed");

        if (!$examStmt->execute([$examId])) {
            throw new Exception("Execute exam check failed");
        }

        $exam = $examStmt->fetch();

        if (!$exam || $exam['teacher_id'] != $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Ujian tidak ditemukan atau Anda tidak memiliki akses'], 403);
        }

        // Copy question to exam
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
