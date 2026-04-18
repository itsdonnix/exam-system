<?php

/**
 * Authentication helper functions
 */

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin($role = null)
{
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Silakan login terlebih dahulu.';
        header('Location: ../index.php');
        exit;
    }

    if ($role && $_SESSION['role'] !== $role) {
        $_SESSION['error'] = 'Akses ditolak. Anda tidak memiliki izin.';
        header('Location: ../index.php');
        exit;
    }
}

function setSession($user, $role)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $role;
    $_SESSION['username'] = $user['username'] ?? $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
}

function clearSession()
{
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

function isSessionExpired($timeout = 3600)
{
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    return (time() - $_SESSION['login_time']) > $timeout;
}

function validateSession()
{
    if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
        error_log("[Session] Recovery needed for user_id: " . $_SESSION['user_id']);
        return false;
    }

    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// ============================================================
// RATE LIMITING FOR EXAM ACCESS
// ============================================================

/**
 * Check rate limit for exam access attempts
 * Limits: 3 attempts per 1 minute per exam per student
 * 
 * @param int $examId The exam ID being accessed
 * @return bool True if allowed, False if blocked
 */
function checkExamRateLimit($examId)
{
    $key = 'exam_access_' . $_SESSION['user_id'] . '_' . $examId;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => $now,
            'blocked_until' => 0
        ];
        return true;
    }

    $data = $_SESSION[$key];

    // Check if currently blocked
    if ($data['blocked_until'] > $now) {
        $remaining = $data['blocked_until'] - $now;
        $_SESSION['rate_limit_error'] = "Terlalu banyak percobaan. Coba lagi setelah {$remaining} detik.";
        return false;
    }

    // Reset if older than 1 minute
    if ($now - $data['first_attempt'] > 60) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => $now,
            'blocked_until' => 0
        ];
        return true;
    }

    // Check attempt count
    if ($data['attempts'] >= 3) {
        // Block for 1 minute
        $_SESSION[$key]['blocked_until'] = $now + 60;
        $_SESSION['rate_limit_error'] = "Terlalu banyak percobaan. Silakan tunggu 1 menit.";
        return false;
    }

    // Increment attempts
    $data['attempts']++;
    $_SESSION[$key] = $data;
    return true;
}

/**
 * Clear rate limit for an exam (called on successful access)
 */
function clearExamRateLimit($examId)
{
    $key = 'exam_access_' . $_SESSION['user_id'] . '_' . $examId;
    unset($_SESSION[$key]);
}
