<?php
/**
 * ExamSafe — Notify Supervisor API
 * POST /php/notify_supervisor.php
 * Called when a student violation is detected
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = getInput();

$studentUsername = sanitize($data['student'] ?? '');
$reason          = sanitize($data['reason'] ?? '');
$timestamp       = sanitize($data['timestamp'] ?? '');
$violationCount  = (int)($data['violationCount'] ?? 1);
$examId          = (int)($data['exam_id'] ?? 0);

$db = getDB();

// Find student
$stmt = $db->prepare("SELECT id, full_name FROM students WHERE username = ? LIMIT 1");
$stmt->execute([$studentUsername]);
$student = $stmt->fetch();

if ($student) {
    // Log violation
    $stmt = $db->prepare("
        INSERT INTO violations (exam_id, student_id, reason, violation_count, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE violation_count = violation_count + 1
    ");
    $stmt->execute([$examId, $student['id'], $reason, $violationCount]);
}

// In production: send real-time notification via WebSocket or push notification
// Example: broadcast to supervisor dashboard via SSE or WebSocket

jsonResponse([
    'success' => true,
    'message' => 'Pelanggaran dicatat dan pengawas diberitahu',
    'violation_count' => $violationCount
]);
