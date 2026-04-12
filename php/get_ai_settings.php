<?php

/**
 * Get AI settings for current teacher
 * Returns: gemini_api_key (masked), gemini_model
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDB();

    // Check if table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'teacher_settings'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'settings' => [
                'gemini_api_key' => '',
                'gemini_model' => 'gemini-2.0-flash',
                'has_key' => false
            ],
            'message' => 'Table not found, using defaults'
        ]);
        exit;
    }

    $stmt = $db->prepare("SELECT gemini_api_key, gemini_model FROM teacher_settings WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch();

    if ($settings) {
        // Mask API key for display (show first 8 and last 4 chars)
        $apiKey = $settings['gemini_api_key'];
        $maskedKey = '';
        if ($apiKey && strlen($apiKey) > 12) {
            $maskedKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
        } elseif ($apiKey) {
            $maskedKey = '••••••••';
        }

        echo json_encode([
            'success' => true,
            'settings' => [
                'gemini_api_key' => $apiKey ? $maskedKey : '',
                'gemini_api_key_raw' => $apiKey ? 'exists' : '',
                'gemini_model' => $settings['gemini_model'],
                'has_key' => !empty($apiKey)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'settings' => [
                'gemini_api_key' => '',
                'gemini_model' => 'gemini-2.0-flash',
                'has_key' => false
            ]
        ]);
    }
} catch (Exception $e) {
    error_log("get_ai_settings error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading settings: ' . $e->getMessage()
    ]);
}
