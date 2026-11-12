<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];
$message = '';
$message_type = '';

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || $confirm === '') {
        $message = 'All fields are required.';
        $message_type = 'warning';
    } elseif ($new !== $confirm) {
        $message = 'New passwords do not match.';
        $message_type = 'danger';
    } elseif (strlen($new) < 6) {
        $message = 'New password must be at least 6 characters.';
        $message_type = 'danger';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
        $upd->bind_param("si", $hash, $student_id);
        if ($upd->execute()) {
            $message = 'Password updated successfully. You can now use your new password.';
            $message_type = 'success';
        } else {
            $message = 'Failed to update password. Please try again.';
            $message_type = 'danger';
        }
        $upd->close();
    }
}

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'reset_password';
$extra_label = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .security-card { max-width: 560px; margin: 0 auto; }
        .icon-badge { width: 80px; height: 80px; border-radius: 50%; background: var(--primary-tint); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(26,48,102,0.1); }
        
        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Account Security</h1>
            <p>Update your password to keep your account safe.</p>
        </div>
    </div>

    <div class="security-card">
        <div class="panel">
            <div class="text-center mb-4">
                <div class="icon-badge">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <h4 class="fw-bold mb-1" style="color: var(--ink);">Change Password</h4>
                <p class="text-muted small mb-0">Ensure your account stays protected.</p>
            </div>

            <?php if ($message): ?>
                <div class="ui-alert is-<?php echo $message_type; ?>"><i class="bi bi-info-circle-fill"></i><div><?php echo htmlspecialchars($message); ?></div></div>
            <?php endif; ?>

            <form method="POST">
                <div class="ui-field mb-3">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="ui-input" placeholder="Minimum 6 characters" required minlength="6">
                </div>

                <div class="ui-field mb-4">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="ui-input" placeholder="Repeat new password" required minlength="6">
                </div>

                <button type="submit" name="update_password" class="ui-btn is-primary w-100">
                    <i class="bi bi-key-fill"></i> Update Password
                </button>
            </form>
        </div>

        <div class="panel mt-4 border-start border-4" style="border-color: var(--accent) !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-lightbulb-fill fs-3" style="color: var(--accent-deep);"></i>
                <p class="mb-0 small text-muted fw-medium">
                    <strong>Security Tip:</strong> Use a strong combination of letters, numbers, and symbols to protect your account.
                </p>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if (icon) icon.classList.replace(isClosed ? 'bi-plus' : 'bi-dash', isClosed ? 'bi-dash' : 'bi-plus');
        });
    });
</script>
</body>
</html>