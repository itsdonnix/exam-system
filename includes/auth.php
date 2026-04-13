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
{ // 1 hour default
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    return (time() - $_SESSION['login_time']) > $timeout;
}

// Session validation function
function validateSession()
{
    if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
        error_log("[Session] Recovery needed for user_id: " . $_SESSION['user_id']);
        return false;
    }

    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}
