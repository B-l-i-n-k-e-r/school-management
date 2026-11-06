<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | The Michaelson School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .brand-side .logo-circle img { 
            width: 100%; 
            height: auto; 
        }
        .brand-side h2 { 
            font-weight: 800; 
            font-size: 1.8rem; 
            margin-bottom: 5px; 
            letter-spacing: -0.5px; 
        }
        .brand-side p { 
            opacity: 0.9; 
            font-size: 0.85rem; 
            font-weight: 500; 
        }

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
        }

        .form-control:focus {
            border-color: var(--primary-dark);
            box-shadow: none;
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

        @media (max-width: 768px) {
            .brand-side { display: none; }
            .login-wrapper { width: 450px; height: auto; }
            .form-side { padding: 40px 30px; }
        }
    </style>
</head>
<body onload="document.adminLoginForm.formuser.focus()">

<div class="login-wrapper">
    <div class="brand-side">
        <div class="logo-circle">
            <img src="../images/logo.jpg" alt="Logo">
        </div>
        <h2>The Michaelson School</h2>
        <p><i>Administrative Control Center</i></p>
    </div>

    <div class="form-side">
        <h3>Admin Portal</h3>

        <form method="post" name="adminLoginForm" action="adminLogin.php">
            <div class="mb-1">
                <label class="form-label">Username</label>
                <input type="text" name="formuser" class="form-control" placeholder="Enter admin username" required>
            </div>
            
            <div class="mb-1">
                <label class="form-label">Password</label>
                <input type="password" name="formpassword" class="form-control" placeholder="Enter password" required>
            </div>

            <button type="submit" name="submit" class="btn-signin">SIGN IN</button>
        </form>

        <div class="form-footer">
            <a href="../user/guest.php" class="back-link">
                <i class="bi bi-arrow-left me-1"></i> Back to Website
            </a>
            <a href="../forgot_password.php" class="forgot-link">Forgot Password?</a>        </div>
    </div>
</div>

</body>
</html>