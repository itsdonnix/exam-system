<?php
/**
 * ExamSafe — Login API
 * POST /php/login.php
 * Body: { role, username, password, exam_code? }
 */

require_once 'db.php';

// Global error handler to return JSON instead of HTML
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP Error [$errno]: $errstr in $errfile on line $errline"
    ]);
    exit;
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Uncaught Exception: " . $e->getMessage()
    ]);
    exit;
});

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getInput();
$role     = sanitize($data['role'] ?? '');
$username = sanitize($data['username'] ?? '');
$password = $data['password'] ?? '';
$examCode = sanitize($data['exam_code'] ?? '');

// Clear old session
session_unset();

if (!$role || !$username || !$password) {
    jsonResponse(['success' => false, 'message' => 'Username dan password wajib diisi'], 400);
}

$db = getDB();

// Rate limiting: max 5 attempts per 15 minutes
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$stmt->execute([$ip]);
$attempts = $stmt->fetch()['cnt'];
if ($attempts >= 5) {
    jsonResponse(['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.'], 429);
}

// Find user by role
$table = match($role) {
    'siswa' => 'students',
    'guru'  => 'teachers',
    'admin' => 'admins',
    default => null
};

if (!$table) {
    jsonResponse(['success' => false, 'message' => 'Role tidak valid'], 400);
}

// Prepare query based on role (teachers don't have username column, they use nip)
if ($role === 'guru') {
    $stmt = $db->prepare("SELECT * FROM teachers WHERE (nip = ? OR email = ?) AND is_active = 1 LIMIT 1");
} elseif ($role === 'siswa') {
    $stmt = $db->prepare("SELECT * FROM students WHERE (username = ? OR nisn = ? OR email = ?) AND is_active = 1 LIMIT 1");
} else {
    // Admin
    $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
}

if ($role === 'siswa') {
    $stmt->execute([$username, $username, $username]);
} else {
    $stmt->execute([$username, $username]);
}
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    // Log failed attempt
    $db->prepare("INSERT INTO login_attempts (ip, username, created_at) VALUES (?, ?, NOW())")->execute([$ip, $username]);
    jsonResponse(['success' => false, 'message' => 'Username atau password salah'], 401);
}

// For teachers: check approval status
if ($role === 'guru' && $user['approval_status'] !== 'approved') {
    jsonResponse(['success' => false, 'message' => 'Akun Anda belum disetujui admin. Harap tunggu verifikasi.'], 403);
}

// Create session
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];$_SESSION['role']      = $role;
$_SESSION['username']  = $user['username'] ?? $user['email'];
$_SESSION['full_name'] = $user['full_name'];

// Clear login attempts
$db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);

jsonResponse([
    'success'   => true,
    'message'   => 'Login berhasil',
    'role'      => $role,
    'user'      => [
        'id'        => $user['id'],
        'name'      => $user['full_name'],
        'username'  => $user['username'] ?? $user['email'],
    ],
    'redirect'  => match($role) {
        'siswa' => 'student/dashboard.html',
        'guru'  => 'teacher/dashboard.html',
        'admin' => 'admin/dashboard.html',
    }
]);
