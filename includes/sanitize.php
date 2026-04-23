<?php

/**
 * ExamSafe — Sanitization Helpers
 * Extracted from php/db.php for separation of concerns
 */

function sanitize($str)
{
    return htmlspecialchars(strip_tags(trim($str)));
}

function sanitizeHTML($str)
{
    $str = trim($str ?? '');
    // Allow only safe formatting tags, strip everything else (scripts, iframes, etc.)
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><sub><sup><h1><h2><h3><span>';
    return strip_tags($str, $allowed);
}
