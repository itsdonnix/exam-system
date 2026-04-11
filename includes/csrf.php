<?php

/**
 * CSRF Protection functions
 */

function generateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Generate new token if doesn't exist
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        // error_log("CSRF: New token generated");
    }

    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token1, $token2)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // error_log("CSRF Verify - Session token: " . (isset($token2) ? substr($token2, 0, 10) . "..." : "NOT SET"));
    // error_log("CSRF Verify - Posted token: " . ($token1 ? substr($token1, 0, 10) . "..." : "NOT SET"));

    if (!isset($token2) || !isset($token1)) {
        error_log("CSRF Verify FAILED: Missing token");
        return false;
    }

    $result = hash_equals($token2, $token1);
    error_log("CSRF Verify " . ($result ? "SUCCESS" : "FAILED"));

    return $result;
}

function csrfField($token)
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
