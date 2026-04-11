<?php
session_start();
require_once '../includes/auth.php';

// Clear session
clearSession();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');

    // Also delete from database
    try {
        require_once 'db.php';
        $db = getDB();
        $db->prepare("DELETE FROM user_tokens WHERE token = ?")->execute([$_COOKIE['remember_token']]);
    } catch (Exception $e) {
        error_log("Logout token deletion error: " . $e->getMessage());
    }
}

// Redirect to login
session_start(); // Start new session for message
$_SESSION['success'] = 'Anda telah berhasil logout.';
header('Location: ../index.php');
exit;
