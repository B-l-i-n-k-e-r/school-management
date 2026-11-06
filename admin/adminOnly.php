<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enhanced Security Check
 * 1. Verifies the login flag.
 * 2. Checks for Session Hijacking by verifying the User Agent (Browser).
 */
$is_authorized = true;

// Basic Login Check
if (!isset($_SESSION['basic_is_logged_in']) || $_SESSION['basic_is_logged_in'] !== true) {
    $is_authorized = false;
}

// Session Hijacking Protection: Verify the browser hasn't changed since login
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    $is_authorized = false;
}

if (!$is_authorized) {
    // Clear any partial session data
    session_unset();
    session_destroy();
    
    // Redirect with a 'msg' parameter so the login page can show why they were kicked out
    header("Location: adminLoginForm.php?error=session_expired");
    exit;
}

// Update 'last activity' timestamp to keep the session alive
$_SESSION['last_activity'] = time();
?>