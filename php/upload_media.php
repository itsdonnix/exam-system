<?php

/**
 * ExamSafe — Media Upload API (Security Hardened)
 * Handles image uploads for exam questions with CSRF, rate limiting, and strict validation
 */

require_once 'db.php';
require_once '../includes/csrf.php';

// Prevent PHP warnings from being rendered as HTML in responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start session once
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * Check rate limit for uploads
 * Limits: 50 uploads per hour per user
 */
function checkUploadRateLimit($userId)
{
    $key = 'upload_count_' . $userId;
    $now = time();
    $window = 3600; // 1 hour
    $limit = 50; // 50 uploads per hour

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'reset_at' => $now + $window];
        return true;
    }

    $data = $_SESSION[$key];

    // Reset if window expired
    if ($now > $data['reset_at']) {
        $_SESSION[$key] = ['count' => 1, 'reset_at' => $now + $window];
        return true;
    }

    // Check limit
    if ($data['count'] >= $limit) {
        return false;
    }

    // Increment count
    $data['count']++;
    $_SESSION[$key] = $data;
    return true;
}

// === AUTHENTICATION ===
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guru', 'admin'])) {
    error_log("[upload_media] Unauthorized access attempt");
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// === CSRF PROTECTION ===
$input = $_POST;
if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])) {
    error_log("[upload_media] CSRF validation failed for user_id: " . $_SESSION['user_id']);
    jsonResponse(['success' => false, 'message' => 'Validasi keamanan gagal. Silakan refresh halaman.'], 403);
}

// === RATE LIMITING ===
if (!checkUploadRateLimit($_SESSION['user_id'])) {
    error_log("[upload_media] Rate limit exceeded for user_id: " . $_SESSION['user_id']);
    jsonResponse(['success' => false, 'message' => 'Batas upload tercapai (50 file per jam). Silakan coba lagi nanti.'], 429);
}

// === REQUEST VALIDATION ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isset($_FILES['file'])) {
    jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
}

$file = $_FILES['file'];

// === FILE UPLOAD ERROR CHECK ===
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File terlalu besar (max ' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'Upload diblokir oleh ekstensi PHP'
    ];
    $message = $errors[$file['error']] ?? 'Unknown upload error';
    jsonResponse(['success' => false, 'message' => $message], 400);
}

// === FILE SIZE VALIDATION (2MB) ===
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    jsonResponse(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.'], 400);
}

// === FILE EXTENSION VALIDATION ===
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    jsonResponse(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.'], 400);
}

// === MIME TYPE VALIDATION (using finfo, not client-supplied) ===
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} else {
    // Fallback to getimagesize if finfo not available
    $imageInfo = @getimagesize($file['tmp_name']);
    $mimeType = $imageInfo['mime'] ?? '';
}

$allowedMimes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/jpg',
    'image/pjpeg',
    'image/x-png'
];

if (!in_array($mimeType, $allowedMimes)) {
    error_log("[upload_media] Invalid MIME type: {$mimeType} for user_id: " . $_SESSION['user_id']);
    jsonResponse(['success' => false, 'message' => 'File bukan gambar yang valid.'], 400);
}

// === IMAGE INTEGRITY VALIDATION (getimagesize) ===
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    error_log("[upload_media] getimagesize failed for user_id: " . $_SESSION['user_id']);
    jsonResponse(['success' => false, 'message' => 'File gambar rusak atau tidak valid.'], 400);
}

// Check dimensions (prevent DOS with huge images)
list($width, $height) = $imageInfo;
$maxDimension = 4096; // 4K max
if ($width > $maxDimension || $height > $maxDimension) {
    jsonResponse(['success' => false, 'message' => 'Dimensi gambar terlalu besar (maksimal 4096x4096 piksel).'], 400);
}

// === OPTIONAL: Strip EXIF metadata (if GD available) ===
$shouldStripMetadata = extension_loaded('gd');
$processedFile = $file['tmp_name'];
$tempFile = null;

if ($shouldStripMetadata && in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
    try {
        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
            case 'image/pjpeg':
                $src = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
            case 'image/x-png':
                $src = @imagecreatefrompng($file['tmp_name']);
                break;
            default:
                $src = null;
        }

        if ($src) {
            // Create new image without metadata
            $tempFile = sys_get_temp_dir() . '/upload_' . uniqid() . '.' . $extension;

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                case 'image/pjpeg':
                    imagejpeg($src, $tempFile, 85);
                    break;
                case 'image/png':
                case 'image/x-png':
                    imagepng($src, $tempFile, 8);
                    break;
            }
            imagedestroy($src);
            $processedFile = $tempFile;
            error_log("[upload_media] Metadata stripped successfully for user_id: " . $_SESSION['user_id']);
        }
    } catch (Exception $e) {
        error_log("[upload_media] Metadata stripping failed: " . $e->getMessage());
        // Continue with original file
    }
}

// === GENERATE SAFE FILENAME ===
$fileName = "q_" . time() . "_" . bin2hex(random_bytes(8)) . "." . $extension;
$targetDir = __DIR__ . '/../uploads/';

// Create directory if not exists with secure permissions
if (!file_exists($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        error_log("[upload_media] Failed to create directory: {$targetDir}");
        jsonResponse(['success' => false, 'message' => 'Gagal membuat direktori upload.'], 500);
    }
}

$targetFilePath = $targetDir . $fileName;

// === MOVE AND SECURE THE FILE ===
if (move_uploaded_file($processedFile, $targetFilePath)) {
    // Set secure permissions
    chmod($targetFilePath, 0644);

    // Clean up temporary file if created
    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }

    // Return relative URL
    $publicUrl = 'uploads/' . $fileName;

    // Log success
    error_log("[upload_media] SUCCESS: user_id={$_SESSION['user_id']}, file={$fileName}, size={$file['size']}, mime={$mimeType}");

    jsonResponse([
        'success' => true,
        'message' => 'File berhasil diunggah',
        'url' => $publicUrl
    ]);
} else {
    // Log failure details
    error_log("[upload_media] FAILED: move_uploaded_file error. user_id={$_SESSION['user_id']}, tmp_name={$file['tmp_name']}, target={$targetFilePath}");

    // Clean up temporary file if created
    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }

    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file di server.'], 500);
}
