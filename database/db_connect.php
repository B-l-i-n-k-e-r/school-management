<?php
/**
 * SCEM Pro - Database Connection Center
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host    = "localhost";
$db_user = "root";
$db_pass = "";
$dbname  = "school_admin_db";   // ← was "school_admin"

try {
    $conn = new mysqli($host, $db_user, $db_pass, $dbname);
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Africa/Nairobi');
} catch (Exception $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("<div style='font-family:sans-serif; text-align:center; padding-top:50px;'>
            <h2>Database Connection Error</h2>
            <p>We are having trouble reaching our servers. Please contact the administrator.</p>
         </div>");
}

$GLOBALS['db'] = $conn;