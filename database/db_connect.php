<?php
/**
 * SCEM Pro - Database Connection Center
 *
 * Provides TWO database interfaces:
 *   $conn  — mysqli  (used by admin + lecturer portals)
 *   $pdo   — PDO     (used by student portal)
 */

// Set error reporting for development (Turn this OFF in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host    = "localhost";
$db_user = "root";
$db_pass = "";
$dbname  = "school_admin_db";

try {
    // 1. mysqli connection (for admin + lecturer)
    $conn = new mysqli($host, $db_user, $db_pass, $dbname);
    $conn->set_charset("utf8mb4");
    date_default_timezone_set('Africa/Nairobi');

    // 2. PDO connection (for student portal)
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Exception $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("<div style='font-family:sans-serif; text-align:center; padding-top:50px;'>
            <h2>Database Connection Error</h2>
            <p>We are having trouble reaching our servers. Please contact the administrator.</p>
         </div>");
}

// Global references for legacy scripts
$GLOBALS['db']  = $conn;
$GLOBALS['pdo'] = $pdo;
?>