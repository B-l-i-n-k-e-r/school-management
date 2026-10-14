<?php
session_start();

// 1. Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "school_admin_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim inputs to prevent whitespace errors
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    // 2. JOIN Query: Get profile from 'staff' and password from 'users'
    // This connects 'staff.user_id' to 'users.user_id'
    $query = "SELECT s.staff_id, s.employee_id, s.full_name, u.password 
              FROM staff s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.employee_id = ? OR s.email = ? 
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $user, $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $db_password = $row['password'];
        $authenticated = false;

        // 3. Password Verification (Bcrypt + Plain Text Fallback)
        if (strpos($db_password, '$2y$') === 0) {
            if (password_verify($pass, $db_password)) {
                $authenticated = true;
            }
        } 
        else if ($pass === $db_password) {
            $authenticated = true;
        }

        if ($authenticated) {
            // 4. Success: Set Sessions
            session_regenerate_id(true);
            $_SESSION['role'] = 'staff'; 
            $_SESSION['username'] = $row['full_name'];
            $_SESSION['user_id'] = (int)$row['staff_id']; 
            $_SESSION['employee_id'] = $row['employee_id'];
            
            // Redirect to the teacher dashboard
            header("Location: ../teacher/index.php");
            exit();
        } else {
            // Wrong password
            header("Location: staffLogin.php?error=invalid_password");
            exit();
        }
    } else {
        // User not found
        header("Location: staffLogin.php?error=not_found");
        exit();
    }
    $stmt->close();
}

$conn->close();
?>