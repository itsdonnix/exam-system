<?php

/**
 * Save AI settings for current teacher
 * POST: gemini_api_key, gemini_model
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$apiKey = trim($input['gemini_api_key'] ?? '');
$model = trim($input['gemini_model'] ?? 'gemini-2.0-flash');

// Validate model (Gemini 2.0 and above)
$allowedModels = [
    'gemini-3-flash-preview',
    'gemini-2.5-flash-lite'
];
if (!in_array($model, $allowedModels)) {
    $model = 'gemini-2.5-flash-lite';
}

try {
    $db = getDB();

    // Check if teacher_settings table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'teacher_settings'");
    if ($checkTable->rowCount() === 0) {
        // Create table if not exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS teacher_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                gemini_api_key VARCHAR(500) DEFAULT NULL,
                gemini_model VARCHAR(50) DEFAULT 'gemini-2.0-flash',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_teacher_settings (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // Insert or update
    $stmt = $db->prepare("
        INSERT INTO teacher_settings (teacher_id, gemini_api_key, gemini_model) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            gemini_api_key = VALUES(gemini_api_key),
            gemini_model = VALUES(gemini_model)
    ");

    // Only update API key if provided (not empty)
    if (empty($apiKey)) {
        // Keep existing API key
        $stmt = $db->prepare("
            INSERT INTO teacher_settings (teacher_id, gemini_api_key, gemini_model) 
            VALUES (?, (SELECT gemini_api_key FROM (SELECT gemini_api_key FROM teacher_settings WHERE teacher_id = ?) AS tmp), ?)
            ON DUPLICATE KEY UPDATE gemini_model = VALUES(gemini_model)
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $model]);
    } else {
        $stmt->execute([$_SESSION['user_id'], $apiKey, $model]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pengaturan AI berhasil disimpan'
    ]);
} catch (Exception $e) {
    error_log("save_ai_settings error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving settings: ' . $e->getMessage()
    ]);
}
