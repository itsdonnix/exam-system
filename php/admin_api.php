<?php
/**
 * ExamSafe — Admin API
 */

require_once 'db.php';
session_start();

$input = getInput();
$action = $_GET['action'] ?? $input['action'] ?? '';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

switch ($action) {
    case 'get_stats':        getStats();        break;
    case 'get_pending_teachers': getPendingTeachers(); break;
    case 'get_pending_students': getPendingStudents(); break;
    case 'approve_teacher':  approveTeacher();  break;
    case 'reject_teacher':   rejectTeacher();   break;
    case 'approve_student':  approveStudent();  break;
    case 'reject_student':   rejectStudent();   break;
    case 'get_all_exams':    getAllExams();     break;
    case 'get_security_logs': getSecurityLogs(); break;
    case 'get_teachers':     getTeachers();     break;
    case 'get_all_students': getAllStudents();  break;
    case 'get_admin_profile': getAdminProfile(); break;
    case 'update_admin_profile': updateAdminProfile(); break;
    
    // Teacher CRUD
    case 'add_teacher':      addTeacher();      break;
    case 'update_teacher':   updateTeacher();   break;
    case 'delete_teacher':   deleteTeacher();   break;
    
    // Student CRUD
    case 'add_student':      addStudent();      break;
    case 'update_student':   updateStudent();   break;
    case 'delete_student':   deleteStudent();   break;
    
    // Class CRUD
    case 'get_classes':      getClassesAdmin(); break;
    case 'add_class':        addClass();        break;
    case 'delete_class':     deleteClass();     break;
    
    // Subject CRUD
    case 'get_subjects':     getSubjectsAdmin(); break;
    case 'add_subject':      addSubject();      break;
    case 'delete_subject':   deleteSubject();   break;

    default:
        jsonResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
}

function getClassesAdmin() {
    $db = getDB();
    $classes = $db->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
    jsonResponse(['success' => true, 'classes' => $classes]);
}

function addClass() {
    $data = getInput();
    $name = sanitize($data['name'] ?? '');
    if (!$name) jsonResponse(['success' => false, 'message' => 'Nama kelas wajib diisi'], 400);
    
    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO classes (name) VALUES (?)");
        $stmt->execute([$name]);
        jsonResponse(['success' => true, 'message' => 'Kelas berhasil ditambahkan']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Gagal menambah kelas. Nama mungkin sudah ada.'], 500);
    }
}

function deleteClass() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    try {
        $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Kelas berhasil dihapus']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus kelas.'], 500);
    }
}

function getSubjectsAdmin() {
    $db = getDB();
    $subjects = $db->query("SELECT * FROM subjects ORDER BY category ASC, name ASC")->fetchAll();
    jsonResponse(['success' => true, 'subjects' => $subjects]);
}

function addSubject() {
    $data = getInput();
    $name = sanitize($data['name'] ?? '');
    $category = sanitize($data['category'] ?? 'Umum');
    if (!$name) jsonResponse(['success' => false, 'message' => 'Nama mata pelajaran wajib diisi'], 400);
    
    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO subjects (name, category) VALUES (?, ?)");
        $stmt->execute([$name, $category]);
        jsonResponse(['success' => true, 'message' => 'Mata pelajaran berhasil ditambahkan']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Gagal menambah mata pelajaran. Nama mungkin sudah ada.'], 500);
    }
}

function deleteSubject() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    try {
        $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Mata pelajaran berhasil dihapus']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus mata pelajaran.'], 500);
    }
}

function addTeacher() {
    $data = getInput();
    $db = getDB();
    
    $fullName = sanitize($data['full_name'] ?? '');
    $gelar    = sanitize($data['gelar'] ?? '');
    $nip      = sanitize($data['nip'] ?? '');
    $email    = sanitize($data['email'] ?? '');
    $subject  = sanitize($data['subject'] ?? '');
    $password = password_hash($data['password'] ?? 'guru123', PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $stmt = $db->prepare("INSERT INTO teachers (full_name, gelar, nip, email, subject, password, approval_status, is_active) VALUES (?, ?, ?, ?, ?, ?, 'approved', 1)");
        $stmt->execute([$fullName, $gelar, $nip, $email, $subject, $password]);
        jsonResponse(['success' => true, 'message' => 'Guru berhasil ditambahkan']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Add Teacher failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menambah guru. Pastikan NIP atau Email belum terdaftar.'], 500);
    }
}

function updateTeacher() {
    $data = getInput();
    $db = getDB();
    
    $id       = (int)($data['id'] ?? 0);
    $fullName = sanitize($data['full_name'] ?? '');
    $gelar    = sanitize($data['gelar'] ?? '');
    $nip      = sanitize($data['nip'] ?? '');
    $email    = sanitize($data['email'] ?? '');
    $subject  = sanitize($data['subject'] ?? '');

    try {
        if (!empty($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE teachers SET full_name = ?, gelar = ?, nip = ?, email = ?, subject = ?, password = ? WHERE id = ?");
            $stmt->execute([$fullName, $gelar, $nip, $email, $subject, $password, $id]);
        } else {
            $stmt = $db->prepare("UPDATE teachers SET full_name = ?, gelar = ?, nip = ?, email = ?, subject = ? WHERE id = ?");
            $stmt->execute([$fullName, $gelar, $nip, $email, $subject, $id]);
        }
        jsonResponse(['success' => true, 'message' => 'Data guru berhasil diperbarui']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Update Teacher failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal memperbarui guru. Pastikan NIP atau Email belum terdaftar atau terjadi kesalahan server.'], 500);
    }
}

function deleteTeacher() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    
    try {
        $stmt = $db->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Guru berhasil dihapus']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Delete Teacher failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus guru. Pastikan guru tidak memiliki ujian aktif atau data terkait lainnya.'], 500);
    }
}

function addStudent() {
    $data = getInput();
    $db = getDB();
    
    $fullName = sanitize($data['full_name'] ?? '');
    $nisn     = sanitize($data['nisn'] ?? '');
    $username = sanitize($data['username'] ?? '');
    $class    = sanitize($data['class'] ?? '');
    $password = password_hash($data['password'] ?? 'siswa123', PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $stmt = $db->prepare("INSERT INTO students (full_name, nisn, username, class, password, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$fullName, $nisn, $username, $class, $password]);
        jsonResponse(['success' => true, 'message' => 'Siswa berhasil ditambahkan']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Add Student failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menambah siswa. Pastikan NISN atau Username belum terdaftar.'], 500);
    }
}

function updateStudent() {
    $data = getInput();
    $db = getDB();
    
    $id       = (int)($data['id'] ?? 0);
    $fullName = sanitize($data['full_name'] ?? '');
    $nisn     = sanitize($data['nisn'] ?? '');
    $username = sanitize($data['username'] ?? '');
    $class    = sanitize($data['class'] ?? '');

    try {
        if (!empty($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE students SET full_name = ?, nisn = ?, username = ?, class = ?, password = ? WHERE id = ?");
            $stmt->execute([$fullName, $nisn, $username, $class, $password, $id]);
        } else {
            $stmt = $db->prepare("UPDATE students SET full_name = ?, nisn = ?, username = ?, class = ? WHERE id = ?");
            $stmt->execute([$fullName, $nisn, $username, $class, $id]);
        }
        jsonResponse(['success' => true, 'message' => 'Data siswa berhasil diperbarui']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Update Student failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal memperbarui siswa. Pastikan NISN atau Username belum terdaftar atau terjadi kesalahan server.'], 500);
    }
}

function deleteStudent() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    
    try {
        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Siswa berhasil dihapus']);
    } catch (PDOException $e) {
        // Log internal error: error_log("Delete Student failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus siswa. Pastikan siswa tidak memiliki data ujian atau pelanggaran.'], 500);
    }
}

function getTeachers() {
    $db = getDB();
    $teachers = $db->query("SELECT id, full_name, gelar, nip, email, subject, approval_status, is_active FROM teachers ORDER BY full_name ASC")->fetchAll();
    jsonResponse(['success' => true, 'teachers' => $teachers]);
}

function getAllStudents() {
    $db = getDB();
    $students = $db->query("SELECT id, full_name, nisn, class, is_active FROM students ORDER BY class ASC, full_name ASC")->fetchAll();
    jsonResponse(['success' => true, 'students' => $students]);
}

function getStats() {
    $db = getDB();
    $avgScore = $db->query("SELECT AVG(score) FROM exam_submissions")->fetchColumn();
    $stats = [
        'teachers' => $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'exams'    => $db->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
        'pending'  => $db->query("SELECT COUNT(*) FROM teachers WHERE approval_status = 'pending'")->fetchColumn(),
        'avg_score' => $avgScore ? round($avgScore, 1) : 0
    ];
    jsonResponse(['success' => true, 'stats' => $stats]);
}

function getAdminProfile() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'user' => $stmt->fetch()]);
}

function updateAdminProfile() {
    $data = getInput();
    $db = getDB();
    
    $fullName = sanitize($data['full_name'] ?? '');
    $email    = sanitize($data['email'] ?? '');
    $password = $data['new_password'] ?? '';

    if ($password) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE admins SET full_name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $hashed, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare("UPDATE admins SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $_SESSION['user_id']]);
    }

    jsonResponse(['success' => true, 'message' => 'Profil admin diperbarui']);
}

function getPendingStudents() {
    $db = getDB();
    $students = $db->query("SELECT id, full_name, nisn, class, username, created_at FROM students WHERE approval_status = 'pending' ORDER BY created_at DESC")->fetchAll();
    jsonResponse(['success' => true, 'students' => $students]);
}

function approveStudent() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("UPDATE students SET approval_status = 'approved', is_active = 1 WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Siswa berhasil disetujui']);
}

function rejectStudent() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("UPDATE students SET approval_status = 'rejected', is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Siswa berhasil ditolak']);
}

function getPendingTeachers() {
    $db = getDB();
    $teachers = $db->query("SELECT id, full_name, nip, email, phone, subject, school, created_at FROM teachers WHERE approval_status = 'pending' ORDER BY created_at DESC")->fetchAll();
    jsonResponse(['success' => true, 'teachers' => $teachers]);
}

function approveTeacher() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE teachers SET approval_status = 'approved', is_active = 1, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Guru berhasil disetujui']);
}

function rejectTeacher() {
    $data = getInput();
    $id = (int)($data['id'] ?? 0);
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE teachers SET approval_status = 'rejected', is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Guru berhasil ditolak']);
}

function getAllExams() {
    $db = getDB();
    $exams = $db->query("
        SELECT e.*, t.full_name as teacher_name, 
               (SELECT COUNT(*) FROM exam_submissions es WHERE es.exam_id = e.id) as participants
        FROM exams e 
        JOIN teachers t ON t.id = e.teacher_id 
        ORDER BY e.status ASC, e.start_time DESC
    ")->fetchAll();
    jsonResponse(['success' => true, 'exams' => $exams]);
}

function getSecurityLogs() {
    $db = getDB();
    $logs = $db->query("
        SELECT v.*, s.full_name as student_name, e.name as exam_name
        FROM violations v
        JOIN students s ON s.id = v.student_id
        JOIN exams e ON e.id = v.exam_id
        ORDER BY v.created_at DESC LIMIT 20
    ")->fetchAll();
    jsonResponse(['success' => true, 'logs' => $logs]);
}
