<?php
/**
 * ExamSafe — Media Upload API
 * Handles image uploads for exam questions
 */

require_once 'db.php';
// Prevent PHP warnings from being rendered as HTML in responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start session once
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
}

$file = $_FILES['file'];
// Use absolute path to uploads directory for reliability
$targetDir = __DIR__ . '/../uploads/';

// Create directory if not exists
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    jsonResponse(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.'], 400);
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.'], 400);
}

// Generate safe filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = "q_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
$targetFilePath = $targetDir . $fileName;

if (is_uploaded_file($file['tmp_name']) && move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    // Return a relative URL path that matches how front-end expects it
    $publicUrl = 'uploads/' . $fileName;
    jsonResponse([
        'success' => true,
        'message' => 'File berhasil diunggah',
        'url' => $publicUrl
    ]);
} else {
    // Log details for debugging
    error_log('[upload_media] move_uploaded_file failed. tmp_name=' . ($file['tmp_name'] ?? ''));
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file di server.'], 500);
}
