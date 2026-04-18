<?php
// Shared initialization for all teacher pages
// Handles session config, authentication, and teacher data fetching

// Set session cookie parameters BEFORE session start
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => false,
  'httponly' => true,
  'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include authentication helpers
require_once __DIR__ . '/../../includes/auth.php';

// Require login with guru role, redirects to ../index.php if fails
requireLogin('guru');

// Refresh session timer to prevent timeout while viewing dashboard
$_SESSION['login_time'] = time();

// Fetch teacher data from database
require_once __DIR__ . '/../../php/db.php';
$db = getDB();

try {
  $stmt = $db->prepare("SELECT full_name, gelar, subject FROM teachers WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $teacher = $stmt->fetch();

  if (!$teacher) {
    // Fallback to session data if database returns nothing
    error_log("[Teacher Init] Teacher not found in DB for user_id: " . $_SESSION['user_id'] . ", using session fallback");
    $teacher = [
      'full_name' => $_SESSION['full_name'] ?? 'Guru',
      'gelar' => '',
      'subject' => 'Guru'
    ];
  }
} catch (Exception $e) {
  // Log error but never break the page
  error_log("[Teacher Init] Failed to fetch teacher data: " . $e->getMessage() . " (user_id: " . $_SESSION['user_id'] . ")");
  $teacher = [
    'full_name' => $_SESSION['full_name'] ?? 'Guru',
    'gelar' => '',
    'subject' => 'Guru'
  ];
}

// Build display name with gelar (e.g., "Dr. Ahmad, M.Pd")
$fullNameWithGelar = trim($teacher['full_name'] . ($teacher['gelar'] ? ', ' . $teacher['gelar'] : ''));
// Get first character for avatar (UTF-8 safe)
$firstChar = !empty($teacher['full_name']) ? mb_strtoupper(mb_substr($teacher['full_name'], 0, 1, 'UTF-8'), 'UTF-8') : 'G';
// Subject fallback to 'Guru' if not set
$teacherSubject = !empty($teacher['subject']) ? $teacher['subject'] : 'Guru';

// Prepare teacher data array for use in includes
$teacherData = [
  'full_name' => $teacher['full_name'],
  'gelar' => $teacher['gelar'],
  'full_name_with_gelar' => $fullNameWithGelar,
  'subject' => $teacherSubject,
  'avatar_initial' => $firstChar
];

// Set default active page if not defined
if (!isset($activePage)) {
  $activePage = '';
}
