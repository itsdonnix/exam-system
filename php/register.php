<?php
/**
 * ExamSafe — Teacher Registration API
 * POST /php/register.php
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getInput();
$fname    = sanitize($data['fname'] ?? '');
$lname    = sanitize($data['lname'] ?? '');
$gelar    = sanitize($data['gelar'] ?? '');
$fullName = $fname . ' ' . $lname;
$email    = sanitize($data['email'] ?? '');
$phone    = sanitize($data['phone'] ?? '');
$subject  = sanitize($data['subject'] ?? '');
$school   = sanitize($data['school'] ?? '');
$passwordRaw = $data['password'] ?? '';

if (!$fname || !$email || !$passwordRaw) {
    jsonResponse(['success' => false, 'message' => 'Nama, Email, dan Password wajib diisi'], 400);
}

$password = password_hash($passwordRaw, PASSWORD_BCRYPT, ['cost' => 12]);
$db = getDB();

// Check if Email already exists
$stmt = $db->prepare("SELECT id FROM teachers WHERE email = ? LIMIT 1");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Email sudah terdaftar!'], 409);
}

// Generate NIP acak 8 digit oleh sistem
$nip = 'NIP' . bin2hex(random_bytes(4));

// Insert new teacher dengan NIP yang digenerate sistem
$stmt = $db->prepare("
    INSERT INTO teachers (full_name, gelar, nip, email, phone, subject, school, password, approval_status, is_active, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
");

try {
    $stmt->execute([$fullName, $gelar, $nip, $email, $phone, $subject, $school, $password]);
    jsonResponse([
        'success' => true, 
        'message' => 'Pendaftaran berhasil! Akun Anda sedang menunggu persetujuan admin sekolah.',
        'email'   => $email
    ]);
} catch (PDOException $e) {
    // Log internal error: error_log("Teacher registration failed: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Gagal mendaftar. Pastikan NIP atau Email belum terdaftar atau terjadi kesalahan server.'], 500);
}
