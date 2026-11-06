<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | BokinceX School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a3066;
            --white: #ffffff;
            --text-muted: #64748b;
            --accent-yellow: #facc15;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('images/school_bg.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .reset-wrapper {
            display: flex;
            width: 900px;
            height: 500px;
            max-width: 95%;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Brand Side */
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

        .icon-box {
            background: rgba(255, 255, 255, 0.1);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 2.5rem;
            border: 2px solid var(--accent-yellow);
        }

        .brand-side h2 { font-weight: 800; font-size: 1.6rem; margin-bottom: 10px; }
        .brand-side p { opacity: 0.8; font-size: 0.85rem; line-height: 1.6; }

        /* Form Side */
        .form-side {
            flex: 1.2;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-side h3 {
            color: var(--primary-dark);
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .instruction-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 30px;
        }

        .form-label { font-weight: 700; color: var(--primary-dark); font-size: 0.8rem; margin-bottom: 8px; display: block; }

        .form-control {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-dark);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.1);
        }

        .btn-reset {
            background: var(--primary-dark);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 30px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .btn-reset:hover { background: #111827; transform: translateY(-1px); }

        .form-footer {
            margin-top: 25px;
            text-align: center;
        }

        .back-link {
            text-decoration: none;
            color: var(--primary-dark);
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .back-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .brand-side { display: none; }
            .reset-wrapper { width: 400px; height: auto; padding: 20px 0; }
        }
    </style>
</head>
<body>

<div class="reset-wrapper">
    <div class="brand-side">
        <div class="icon-box">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h2>Security First</h2>
        <p>Your account security is our priority. Enter your registered email to receive recovery instructions.</p>
    </div>

    <div class="form-side">
        <h3>Forgot Password?</h3>
        <p class="instruction-text">No worries! It happens to the best of us.</p>

        <form action="process_recovery.php" method="POST">
            <label class="form-label">Registered Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="example@school.com" required>
            
            <button type="submit" class="btn-reset">Send Recovery Link</button>
        </form>

        <div class="form-footer">
            <a href="javascript:history.back()" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</div>

</body>
</html>