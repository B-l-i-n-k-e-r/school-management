<?php
// 1. Database Connection
$db_path = '../database/db_connect.php';
if (file_exists($db_path)) {
    include($db_path);
} else {
    die("Database configuration file missing. Please check the 'database' folder.");
}

$alert = null; // ['type' => success|error, 'text' => '...']
$fields = ['fname', 'lname', 'gender', 'age', 'city', 'phone', 'email', 'comments'];
$old = array_fill_keys($fields, '');
$old['gender'] = 'male';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {

    foreach ($fields as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }
    if (!in_array($old['gender'], ['male', 'female'], true)) {
        $old['gender'] = 'male';
    }

    try {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO feedback (fname, lname, gender, age, city, phone, email, comments)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssssss",
            $old['fname'], $old['lname'], $old['gender'], $old['age'],
            $old['city'], $old['phone'], $old['email'], $old['comments']);

        if (mysqli_stmt_execute($stmt)) {
            $alert = ['type' => 'success', 'text' => 'Thank you! Your feedback has been submitted successfully.'];
            $old = array_fill_keys($fields, '');   // clear the form
            $old['gender'] = 'male';
        } else {
            $alert = ['type' => 'error', 'text' => 'We couldn\'t submit your feedback. Please try again.'];
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $alert = ['type' => 'error', 'text' => 'We couldn\'t submit your feedback. Please try again.'];
    }
}

// Helpers that keep what the visitor typed if something goes wrong
function fbk_val($key) {
    global $old;
    return htmlspecialchars($old[$key] ?? '');
}
function fbk_checked($value) {
    global $old;
    return ($old['gender'] ?? 'male') === $value ? ' checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | The Michaelson School</title>
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
        .fbk-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .fbk-hero::after {
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
        .fbk-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: fbk-rise 0.8s ease both;
        }
        .fbk-tag {
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
        .fbk-tag i { color: var(--accent); }
        .fbk-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .fbk-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .fbk-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Form card ---------- */
        .fbk-wrap {
            max-width: 860px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .fbk-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 48px 48px 44px;
            box-shadow: 0 30px 60px -20px rgba(0, 10, 102, 0.22);
        }
        .fbk-card-head h2 {
            margin: 0 0 8px;
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .fbk-card-head p {
            margin: 0 0 32px;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* Messages */
        .fbk-alert {
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
        .fbk-alert i { font-size: 1.2rem; line-height: 1.3; }
        .fbk-alert.is-success { background: #e7f7ee; color: #14633a; }
        .fbk-alert.is-error   { background: #fdeaea; color: #a42525; }

        /* Fields */
        .fbk-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .fbk-full { grid-column: 1 / -1; }

        .fbk-field > label,
        .fbk-legend {
            display: block;
            margin-bottom: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink);
        }
        .fbk-field input[type="text"],
        .fbk-field input[type="email"],
        .fbk-field textarea {
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
        .fbk-field textarea { resize: vertical; min-height: 130px; line-height: 1.6; }
        .fbk-field input::placeholder,
        .fbk-field textarea::placeholder { color: #98a0bd; }
        .fbk-field input:focus,
        .fbk-field textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1);
        }

        /* Gender: two pill options */
        .fbk-choice {
            display: flex;
            gap: 10px;
            border: 0;
            margin: 0;
            padding: 0;
        }
        .fbk-choice label {
            flex: 1;
            position: relative;
            margin: 0;
        }
        .fbk-choice input {
            position: absolute;
            opacity: 0;
            inset: 0;
            cursor: pointer;
        }
        .fbk-choice span {
            display: block;
            padding: 13px 16px;
            text-align: center;
            border: 1.5px solid var(--line);
            border-radius: 14px;
            background: var(--bg-light);
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .fbk-choice input:checked + span {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }
        .fbk-choice input:focus-visible + span {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }

        /* Buttons */
        .fbk-actions {
            display: flex;
            gap: 14px;
            margin-top: 34px;
        }
        .fbk-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 28px;
            border: none;
            border-radius: 99px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .fbk-btn-main {
            flex: 1;
            background: var(--accent);
            color: var(--primary);
        }
        .fbk-btn-main:hover {
            background: #ffd93d;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -10px rgba(250, 204, 21, 0.8);
        }
        .fbk-btn-quiet {
            background: var(--primary-tint);
            color: var(--primary);
        }
        .fbk-btn-quiet:hover { background: #dfe3ff; }

        /* ---------- Footer ---------- */
        .fbk-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes fbk-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .fbk-hero-inner { animation: none; }
            .fbk-btn { transition: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 720px) {
            .fbk-hero { padding: 80px 20px 150px; }
            .fbk-card { padding: 32px 22px; }
            .fbk-grid { grid-template-columns: 1fr; }
            .fbk-actions { flex-direction: column-reverse; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="fbk-hero">
    <div class="fbk-hero-inner">
        <div class="fbk-tag"><i class="bi bi-chat-heart-fill"></i> Your voice</div>
        <h1>Feedback</h1>
        <p>We value your experience and suggestions.</p>
        <div class="fbk-rule"></div>
    </div>
</section>

<main class="fbk-wrap">
    <div class="fbk-card">
        <div class="fbk-card-head">
            <h2>Share your thoughts</h2>
            <p>Tell us about your experience with The Michaelson School. First name and comments are required.</p>
        </div>

        <?php if ($alert): ?>
            <div class="fbk-alert is-<?php echo $alert['type']; ?>" role="alert">
                <i class="bi <?php echo $alert['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                <span><?php echo htmlspecialchars($alert['text']); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="feedbackForm">
            <div class="fbk-grid">
                <div class="fbk-field">
                    <label for="fname">First name</label>
                    <input type="text" id="fname" name="fname" placeholder="Enter first name"
                           autocomplete="given-name" value="<?php echo fbk_val('fname'); ?>" required>
                </div>
                <div class="fbk-field">
                    <label for="lname">Last name</label>
                    <input type="text" id="lname" name="lname" placeholder="Enter last name"
                           autocomplete="family-name" value="<?php echo fbk_val('lname'); ?>">
                </div>

                <div class="fbk-field">
                    <span class="fbk-legend" id="genderLabel">Gender</span>
                    <div class="fbk-choice" role="radiogroup" aria-labelledby="genderLabel">
                        <label>
                            <input type="radio" name="gender" value="male"<?php echo fbk_checked('male'); ?>>
                            <span>Male</span>
                        </label>
                        <label>
                            <input type="radio" name="gender" value="female"<?php echo fbk_checked('female'); ?>>
                            <span>Female</span>
                        </label>
                    </div>
                </div>
                <div class="fbk-field">
                    <label for="age">Age</label>
                    <input type="text" id="age" name="age" inputmode="numeric" placeholder="Years"
                           value="<?php echo fbk_val('age'); ?>">
                </div>

                <div class="fbk-field">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" autocomplete="address-level2"
                           value="<?php echo fbk_val('city'); ?>">
                </div>
                <div class="fbk-field">
                    <label for="phone">Phone number</label>
                    <input type="text" id="phone" name="phone" inputmode="tel" autocomplete="tel"
                           value="<?php echo fbk_val('phone'); ?>">
                </div>

                <div class="fbk-field fbk-full">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="example@mail.com"
                           autocomplete="email" value="<?php echo fbk_val('email'); ?>">
                </div>

                <div class="fbk-field fbk-full">
                    <label for="comments">Your comments</label>
                    <textarea id="comments" name="comments" rows="5"
                              placeholder="Share your thoughts with us..." required><?php echo fbk_val('comments'); ?></textarea>
                </div>
            </div>

            <div class="fbk-actions">
                <button type="submit" name="submit_feedback" class="fbk-btn fbk-btn-main">
                    <i class="bi bi-send-fill"></i> Submit feedback
                </button>
                <button type="button" class="fbk-btn fbk-btn-quiet" id="clearForm">Clear form</button>
            </div>
        </form>
    </div>
</main>

<footer class="fbk-footer">
    &copy; <?php echo date('Y'); ?> The Michaelson School, Kenya. All Rights Reserved.
</footer>

<script>
// "Clear form" empties every field (a normal reset would bring back any saved text)
document.getElementById("clearForm").addEventListener("click", function () {
    const form = document.getElementById("feedbackForm");
    form.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(function (el) {
        el.value = "";
    });
    form.querySelector('input[name="gender"][value="male"]').checked = true;
    document.getElementById("fname").focus();
});
</script>

</body>
</html>