<?php
// Start session at the very beginning
session_start();

// 1. Connect to your database
include('../database/db_connect.php'); 

// 2. Capture and sanitize form data
$formuser = isset($_POST['formuser']) ? trim($_POST['formuser']) : NULL;
$formpassword = isset($_POST['formpassword']) ? $_POST['formpassword'] : NULL;

if ($formuser && $formpassword) {
    // 3. Query updated to use 'user_id' to match your table
    $query = "SELECT user_id, username, password FROM users WHERE username = ? AND role = 'admin' LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    mysqli_stmt_bind_param($stmt, "s", $formuser);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // 4. Verification
        // Note: Since your screenshot shows plain text 'admin123', 
        // we use a direct comparison for now.
        if ($formpassword === $row['password']) {
            
            session_regenerate_id(true);
            
            $_SESSION['basic_is_logged_in'] = true;
            $_SESSION['admin_id'] = $row['user_id']; // Updated to match user_id
            $_SESSION['adminUser'] = $row['username'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            header("Location: index.php"); 
            exit();
        }
    }

    // 5. Login failed
    echo "<script>alert('Invalid Username or Password'); window.location='adminLoginForm.php';</script>";
    exit();

} else {
    header("Location: adminLoginForm.php");
    exit();
}
?>