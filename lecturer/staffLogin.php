<?php
session_start();
include('../database/db_connect.php');

$error = "";

// 1. Redirect loop prevention: If already logged in, stay in this folder
if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // 2. JOIN Query: Link 'staff' (s) and 'users' (u) tables
    // Matches your DB schema: staff.user_id -> users.user_id
    $query = "SELECT s.staff_id, s.employee_id, s.full_name, u.password 
              FROM staff s 
              JOIN users u ON s.user_id = u.user_id 
              WHERE s.employee_id = ? OR s.email = ? 
              LIMIT 1";

    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $user, $user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $db_password = trim($row['password']);
            $authenticated = false;

            // 3. Password Verification (Bcrypt or Plain Text fallback)
            if (strpos($db_password, '$2y$') === 0) {
                if (password_verify($pass, $db_password)) {
                    $authenticated = true;
                }
            } 
            else if ($pass === $db_password) {
                $authenticated = true;
            }

            if ($authenticated) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'staff';
                $_SESSION['username'] = $row['full_name'];
                $_SESSION['user_id'] = (int)$row['staff_id']; 
                $_SESSION['employee_id'] = $row['employee_id'];
                
                // 4. Success Redirect: Go to the dashboard in the same folder
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid Password!";
            }
        } else {
            $error = "Staff account not found!";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Database error: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers Portal | The Michaelson School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a3066;
            --white: #ffffff;
            --text-muted: #64748b;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .login-wrapper {
            display: flex;
            width: 950px;
            height: 550px;
            max-width: 95%;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-side {
            flex: 1;
            background: var(--primary-dark);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }

        .brand-side .logo-circle {
            background: white;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            padding: 15px;
        }

        .brand-side .logo-circle img { width: 100%; height: auto; }
        .brand-side h2 { font-weight: 800; font-size: 1.8rem; margin-bottom: 5px; letter-spacing: -0.5px; }
        .brand-side p { opacity: 0.9; font-size: 0.85rem; font-weight: 500; }

        .form-side {
            flex: 1.1;
            padding: 60px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-side h3 {
            color: var(--primary-dark);
            font-weight: 800;
            margin-bottom: 35px;
            text-align: center;
            font-size: 1.6rem;
        }

        .form-label { font-weight: 600; color: var(--primary-dark); font-size: 0.8rem; margin-bottom: 8px; }

        .form-control {
            border: 1.5px solid #e2e8f0;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-dark);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.1);
        }

        .btn-signin {
            background: var(--primary-dark);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px;
            font-weight: 700;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            letter-spacing: 0.5px;
            cursor: pointer;
        }

        .btn-signin:hover { background: #111827; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

        .form-footer {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-footer a { text-decoration: none; font-size: 0.8rem; font-weight: 700; }
        .forgot-link { color: #d63031; }
        .back-link { color: var(--text-muted); transition: color 0.2s; }
        .back-link:hover { color: var(--primary-dark); }

        .alert-custom {
            background: #fef2f2;
            color: #ef4444;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fee2e2;
            text-align: center;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .brand-side { display: none; }
            .login-wrapper { width: 450px; height: auto; }
            .form-side { padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="brand-side">
        <div class="logo-circle">
            <img src="../images/logo.jpg" alt="The Michaelson School Logo">
        </div>
        <h2>The Michaelson School</h2>
        <p><i>Higher Is The Goal</i></p>
    </div>

    <div class="form-side">
        <h3>Teachers Portal</h3>

        <?php if($error): ?>
            <div class="alert-custom">
                <i class="bi bi-exclamation-circle-fill me-2"></i> 
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="staffLogin.php" method="POST">
            <div class="mb-1">
                <label class="form-label">Employee ID / Email</label>
                <input type="text" name="username" class="form-control" placeholder="Enter ID or Email" required>
            </div>
            
            <div class="mb-1">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" name="login_btn" class="btn-signin">SIGN IN</button>
        </form>

        <div class="form-footer">
            <a href="../user/guest.php" class="back-link">
                <i class="bi bi-arrow-left me-1"></i> Back to Home
            </a>
            <a href="../forgot_password.php" class="forgot-link">Forgot Password?</a>
        </div>
    </div>
</div>

</body>
</html>