<?php
/**
 * ExamSafe — Student Registration API
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getInput();
$fullName = sanitize($data['full_name'] ?? '');
$nisn     = sanitize($data['nisn'] ?? '');
$class    = sanitize($data['class'] ?? '');
$username = sanitize($data['username'] ?? '');
$passwordRaw = $data['password'] ?? '';

if (!$fullName || !$nisn || !$class || !$username || !$passwordRaw) {
    jsonResponse(['success' => false, 'message' => 'Semua field wajib diisi'], 400);
}

if (strlen($passwordRaw) < 6) {
    jsonResponse(['success' => false, 'message' => 'Password minimal 6 karakter'], 400);
}

$password = password_hash($passwordRaw, PASSWORD_BCRYPT, ['cost' => 12]);
$db = getDB();

// Check if Username or NISN already exists
$stmt = $db->prepare("SELECT id FROM students WHERE username = ? OR nisn = ? LIMIT 1");
$stmt->execute([$username, $nisn]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Username atau NISN sudah terdaftar!'], 409);
}

// Insert new student with 'pending' status
$stmt = $db->prepare("
    INSERT INTO students (full_name, nisn, class, username, password, approval_status, is_active, created_at)
    VALUES (?, ?, ?, ?, ?, 'pending', 0, NOW())
");

try {
    $stmt->execute([$fullName, $nisn, $class, $username, $password]);
    jsonResponse([
        'success' => true, 
        'message' => 'Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan admin sekolah.'
    ]);
} catch (PDOException $e) {
    // Log internal error: error_log("Student registration failed: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Gagal mendaftar. Pastikan NISN atau Username belum terdaftar atau terjadi kesalahan server.'], 500);
}
