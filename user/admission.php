<?php
// 1. Database Connection with Error Handling
$db_path = '../database/db_connect.php';
if (file_exists($db_path)) {
    include($db_path);
} else {
    die("Database configuration file missing. Please check the 'database' folder.");
}

$alert = null; // ['type' => success|warning|info|error, 'html' => '...']
$fields = ['student_name', 'dob', 'gender', 'form_requested', 'parent_name', 'email', 'phone', 'address'];
$old = array_fill_keys($fields, '');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {

    foreach ($fields as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }

    try {
        // Is there already an application with this email?
        $stmt = mysqli_prepare($conn, "SELECT id FROM admission_applications WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $old['email']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $app_exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        // Is this email already a student?
        $stmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $old['email']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $user_exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if ($app_exists) {
            $alert = [
                'type' => 'warning',
                'icon' => 'bi-exclamation-triangle-fill',
                'html' => 'An application with this email is already being processed.'
            ];
        } elseif ($user_exists) {
            $alert = [
                'type' => 'info',
                'icon' => 'bi-info-circle-fill',
                'html' => 'You are already enrolled. Please <a href="../login.php">log in here</a>.'
            ];
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO admission_applications
                    (student_name, parent_name, email, phone, dob, gender, address, form_requested, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "ssssssss",
                $old['student_name'], $old['parent_name'], $old['email'], $old['phone'],
                $old['dob'], $old['gender'], $old['address'], $old['form_requested']);

            if (mysqli_stmt_execute($stmt)) {
                $alert = [
                    'type' => 'success',
                    'icon' => 'bi-check-circle-fill',
                    'html' => 'Application submitted successfully!'
                ];
                $old = array_fill_keys($fields, ''); // clear the form
            } else {
                $alert = [
                    'type' => 'error',
                    'icon' => 'bi-x-circle-fill',
                    'html' => 'We couldn\'t submit your application. Please try again.'
                ];
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        $alert = [
            'type' => 'error',
            'icon' => 'bi-x-circle-fill',
            'html' => 'We couldn\'t submit your application. Please try again.'
        ];
    }
}

// Helpers to keep what the parent typed if something goes wrong
function old_val($key) {
    global $old;
    return htmlspecialchars($old[$key] ?? '');
}
function is_selected($key, $value) {
    global $old;
    return ($old[$key] ?? '') === $value ? ' selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Request | The Michaelson School</title>
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-tint: #fff6cc;
            --accent-deep: #8a6a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 28px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            color: var(--ink);
            background-color: var(--bg-light);
            -webkit-font-smoothing: antialiased;
        }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 3px;
        }

        /* ---------- Hero ---------- */
        .adm-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .adm-hero::after {
            content: "";
            position: absolute;
            right: -120px;
            bottom: -160px;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.28), transparent 70%);
            pointer-events: none;
        }
        .adm-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: adm-rise 0.8s ease both;
        }
        .adm-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(6px);
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 26px;
        }
        .adm-tag i { color: var(--accent); }
        .adm-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .adm-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .adm-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Form card ---------- */
        .adm-wrap {
            max-width: 860px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .adm-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 48px 48px 44px;
            box-shadow: 0 30px 60px -20px rgba(0, 10, 102, 0.22);
        }
        .adm-card-head h2 {
            margin: 0 0 8px;
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .adm-card-head p {
            margin: 0 0 32px;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* Alerts */
        .adm-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 16px;
            margin-bottom: 28px;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.5;
        }
        .adm-alert i { font-size: 1.2rem; line-height: 1.3; }
        .adm-alert a { color: inherit; text-decoration: underline; }
        .adm-alert.is-success { background: #e7f7ee; color: #14633a; }
        .adm-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .adm-alert.is-info    { background: var(--primary-tint); color: var(--primary); }
        .adm-alert.is-error   { background: #fdeaea; color: #a42525; }

        /* Form sections */
        .adm-section + .adm-section {
            margin-top: 36px;
            padding-top: 32px;
            border-top: 1px solid var(--line);
        }
        .adm-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 22px;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
        }
        .adm-section-title i {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.05rem;
        }

        .adm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .adm-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink);
        }
        .adm-field input,
        .adm-field select {
            width: 100%;
            box-sizing: border-box;
            padding: 14px 16px;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--bg-light);
            font-family: inherit;
            font-size: 0.97rem;
            font-weight: 500;
            color: var(--ink);
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .adm-field select { appearance: none; background-image: none; cursor: pointer; }
        .adm-select { position: relative; }
        .adm-select::after {
            content: "\F282"; /* bootstrap-icons chevron-down */
            font-family: "bootstrap-icons";
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .adm-field input::placeholder { color: #98a0bd; }
        .adm-field input:focus,
        .adm-field select:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1);
        }

        /* Submit */
        .adm-submit {
            margin-top: 36px;
        }
        .adm-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 17px;
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
        .adm-btn:hover {
            background: #ffd93d;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -10px rgba(250, 204, 21, 0.8);
        }
        .adm-note {
            margin: 16px 0 0;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* ---------- Footer ---------- */
        .adm-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes adm-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .adm-hero-inner { animation: none; }
            .adm-btn { transition: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 720px) {
            .adm-hero { padding: 80px 20px 150px; }
            .adm-card { padding: 32px 22px; }
            .adm-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="adm-hero">
    <div class="adm-hero-inner">
        <div class="adm-tag"><i class="bi bi-person-plus-fill"></i> Admission</div>
        <h1>Join our school community</h1>
        <p>Join The Michaelson School community today. Fill in the form to request a place for your child.</p>
        <div class="adm-rule"></div>
    </div>
</section>

<main class="adm-wrap">
    <div class="adm-card">
        <div class="adm-card-head">
            <h2>Student Admission Form</h2>
            <p>All fields are required.</p>
        </div>

        <?php if ($alert): ?>
            <div class="adm-alert is-<?php echo $alert['type']; ?>" role="alert">
                <i class="bi <?php echo $alert['icon']; ?>"></i>
                <span><?php echo $alert['html']; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <section class="adm-section">
                <h3 class="adm-section-title"><i class="bi bi-mortarboard-fill"></i> Student details</h3>
                <div class="adm-grid">
                    <div class="adm-field">
                        <label for="student_name">Student full name</label>
                        <input type="text" id="student_name" name="student_name" placeholder="Full legal name"
                               autocomplete="off" value="<?php echo old_val('student_name'); ?>" required>
                    </div>
                    <div class="adm-field">
                        <label for="dob">Date of birth</label>
                        <input type="date" id="dob" name="dob" value="<?php echo old_val('dob'); ?>" required>
                    </div>
                    <div class="adm-field">
                        <label for="gender">Gender</label>
                        <div class="adm-select">
                            <select id="gender" name="gender" required>
                                <option value="" disabled<?php echo $old['gender'] === '' ? ' selected' : ''; ?>>Select gender</option>
                                <option value="Male"<?php echo is_selected('gender', 'Male'); ?>>Male</option>
                                <option value="Female"<?php echo is_selected('gender', 'Female'); ?>>Female</option>
                                <option value="Other"<?php echo is_selected('gender', 'Other'); ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="adm-field">
                        <label for="form_requested">Form applying for</label>
                        <div class="adm-select">
                            <select id="form_requested" name="form_requested" required>
                                <option value="" disabled<?php echo $old['form_requested'] === '' ? ' selected' : ''; ?>>Choose a form</option>
                                <?php foreach (['Form 1', 'Form 2', 'Form 3', 'Form 4'] as $form): ?>
                                    <option value="<?php echo $form; ?>"<?php echo is_selected('form_requested', $form); ?>><?php echo $form; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <section class="adm-section">
                <h3 class="adm-section-title"><i class="bi bi-people-fill"></i> Parent or guardian details</h3>
                <div class="adm-grid">
                    <div class="adm-field">
                        <label for="parent_name">Parent or guardian name</label>
                        <input type="text" id="parent_name" name="parent_name" placeholder="Guardian full name"
                               autocomplete="name" value="<?php echo old_val('parent_name'); ?>" required>
                    </div>
                    <div class="adm-field">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="example@mail.com"
                               autocomplete="email" value="<?php echo old_val('email'); ?>" required>
                    </div>
                    <div class="adm-field">
                        <label for="phone">Phone number</label>
                        <input type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel"
                               value="<?php echo $old['phone'] !== '' ? old_val('phone') : '+254'; ?>" required>
                    </div>
                    <div class="adm-field">
                        <label for="address">Physical address</label>
                        <input type="text" id="address" name="address" placeholder="Estate, town, street"
                               autocomplete="street-address" value="<?php echo old_val('address'); ?>" required>
                    </div>
                </div>
            </section>

            <div class="adm-submit">
                <button type="submit" name="submit_application" class="adm-btn">
                    <i class="bi bi-send-check-fill"></i> Submit application
                </button>
                <p class="adm-note">By submitting, you agree to The Michaelson School admission policies.</p>
            </div>
        </form>
    </div>
</main>

<footer class="adm-footer">
    &copy; <?php echo date('Y'); ?> The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>