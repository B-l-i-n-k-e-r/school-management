<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * We check if any of the common teacher session keys exist.
 * If you know your exact key is 'employee_id', you can just use that.
 */
$is_teacher = isset($_SESSION['teacher_id']) || isset($_SESSION['employee_id']) || isset($_SESSION['staff_id']);
$is_admin = isset($_SESSION['admin_id']);

if (!$is_teacher && !$is_admin) {
    // If the system doesn't see a teacher or admin ID, it sends you to the guest/login page.
    header("Location: ../index.php?error=unauthorized");
    exit();
}
?>