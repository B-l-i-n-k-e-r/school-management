<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to the login page in the root folder
header("Location: ../user/guest.php");
exit();
?>