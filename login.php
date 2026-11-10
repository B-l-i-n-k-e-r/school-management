<?php
session_start();
include('database/db_connect.php');

$error = "";
$entered_user = "";

// 1. If already logged in as a student, redirect to the dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: ./student/dashboard_view.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $entered_user = $user;

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // 2. Query the students table using Prepared Statements
    $stmt = mysqli_prepare($conn, "SELECT student_id, adm_no, password, full_name FROM students WHERE adm_no = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $user, $user);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $authenticated = false;

    if ($row = mysqli_fetch_assoc($result)) {
        $db_password = trim($row['password']);

        // 3. Password Verification Logic
        // Checks if it's a secure hash
        if (strpos($db_password, '$2y$') === 0) {
            if (password_verify($pass, $db_password)) {
                $authenticated = true;
            }
        }
        // Fallback for plain text passwords (like '123456') seen in your DB
        else if ($pass === $db_password) {
            $authenticated = true;
        }
    }

    if ($authenticated) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'student';
        $_SESSION['username'] = $row['full_name'];
        $_SESSION['user_id'] = (int)$row['student_id'];
        $_SESSION['adm_no'] = $row['adm_no'];

        header("Location: student/dashboard_view.php");
        exit();
    } else {
        // One message for both cases, so nobody can use the form to find out which admission numbers exist
        $error = "Incorrect admission number or password.";
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | The Michaelson School</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--ink);
            background:
                linear-gradient(rgba(0, 10, 102, 0.82), rgba(0, 10, 102, 0.9)),
                url('images/school_bg.jpg') center / cover no-repeat fixed;
            -webkit-font-smoothing: antialiased;
        }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 3px;
        }

        /* ---------- Card ---------- */
        .auth-card {
            width: 100%;
            max-width: 960px;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            background: var(--white);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.55);
            animation: auth-rise 0.7s ease both;
        }

        /* ---------- Brand side ---------- */
        .auth-brand {
            position: relative;
            background: var(--primary);
            color: var(--white);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }
        .auth-brand::before {
            content: "";
            position: absolute;
            right: -110px;
            bottom: -130px;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.28), transparent 70%);
        }
        .auth-logo {
            position: relative;
            width: 128px;
            height: 128px;
            display: grid;
            place-items: center;
            background: var(--white);
            border-radius: 50%;
            padding: 16px;
            margin-bottom: 26px;
            box-shadow: 0 0 0 6px rgba(250, 204, 21, 0.9);
        }
        .auth-logo img { width: 100%; height: auto; display: block; }
        .auth-brand h1 {
            position: relative;
            margin: 0 0 10px;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .auth-motto {
            position: relative;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            font-style: italic;
            color: var(--accent);
        }

        /* ---------- Form side ---------- */
        .auth-form {
            padding: 56px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-mobile-brand {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .auth-mobile-brand img { height: 44px; width: auto; border-radius: 10px; }
        .auth-mobile-brand span {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary);
        }

        .auth-form h2 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--primary);
        }
        .auth-sub {
            margin: 0 0 30px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .auth-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 22px;
            background: #fdeaea;
            color: #a42525;
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1.5;
        }
        .auth-alert i { font-size: 1.1rem; line-height: 1.4; }

        .auth-field { margin-bottom: 20px; }
        .auth-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink);
        }
        .auth-input { position: relative; }
        .auth-input > i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .auth-input input {
            width: 100%;
            padding: 14px 48px 14px 44px;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--bg-light);
            font-family: inherit;
            font-size: 0.97rem;
            font-weight: 500;
            color: var(--ink);
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .auth-input input::placeholder { color: #98a0bd; }
        .auth-input input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1);
        }
        .auth-eye {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: var(--text-muted);
            font-size: 1.05rem;
            cursor: pointer;
        }
        .auth-eye:hover { background: var(--primary-tint); color: var(--primary); }

        .auth-btn {
            width: 100%;
            margin-top: 8px;
            padding: 16px;
            border: none;
            border-radius: 99px;
            background: var(--accent);
            color: var(--primary);
            font-family: inherit;
            font-size: 1.02rem;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .auth-btn:hover {
            background: #ffd93d;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -10px rgba(250, 204, 21, 0.8);
        }

        .auth-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 26px;
            font-size: 0.88rem;
            font-weight: 700;
        }
        .auth-links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
        }
        .auth-links a:hover { color: var(--primary); text-decoration: underline; }
        .auth-links a.is-strong { color: var(--primary); }

        @keyframes auth-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-card { animation: none; }
            .auth-btn { transition: none; }
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 800px) {
            .auth-card { grid-template-columns: 1fr; max-width: 480px; }
            .auth-brand { display: none; }
            .auth-mobile-brand { display: flex; }
            .auth-form { padding: 36px 26px; }
        }
    </style>
</head>
<body>

<div class="auth-card">
    <aside class="auth-brand">
        <div class="auth-logo">
            <img src="images/logo.jpg" alt="The Michaelson School Logo">
        </div>
        <h1>The Michaelson School</h1>
        <p class="auth-motto">Higher Is The Goal</p>
    </aside>

    <main class="auth-form">
        <div class="auth-mobile-brand">
            <img src="images/logo.jpg" alt="">
            <span>The Michaelson School</span>
        </div>

        <h2>Student Portal</h2>
        <p class="auth-sub">Welcome back. Sign in to continue.</p>

        <?php if ($error): ?>
            <div class="auth-alert" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="auth-field">
                <label for="username">Admission number or email</label>
                <div class="auth-input">
                    <i class="bi bi-person-badge"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your admission number"
                           autocomplete="username" value="<?php echo htmlspecialchars($entered_user); ?>" required autofocus>
                </div>
            </div>

            <div class="auth-field">
                <label for="password">Password</label>
                <div class="auth-input">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button" class="auth-eye" id="togglePassword" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login_btn" class="auth-btn">Sign in</button>
        </form>

        <div class="auth-links">
            <a href="user/guest.php"><i class="bi bi-arrow-left"></i> Back to home</a>
            <a href="forgot_password.php" class="is-strong">Forgot password?</a>
        </div>
    </main>
</div>

<script>
// Show / hide the password
document.getElementById("togglePassword").addEventListener("click", function () {
    const input = document.getElementById("password");
    const showing = input.type === "text";
    input.type = showing ? "password" : "text";
    this.setAttribute("aria-label", showing ? "Show password" : "Hide password");
    this.querySelector("i").className = showing ? "bi bi-eye" : "bi bi-eye-slash";
});
</script>

</body>
</html>