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

// Fetch student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $message = 'All fields are required.';
        $message_type = 'warning';
    } elseif ($new !== $confirm) {
        $message = 'New passwords do not match.';
        $message_type = 'danger';
    } elseif (strlen($new) < 6) {
        $message = 'New password must be at least 6 characters.';
        $message_type = 'danger';
    } else {
        // Fetch current password
        $p_stmt = $conn->prepare("SELECT password FROM students WHERE student_id = ? LIMIT 1");
        $p_stmt->bind_param("i", $student_id);
        $p_stmt->execute();
        $p_row = $p_stmt->get_result()->fetch_assoc();
        $p_stmt->close();

        $stored = $p_row['password'] ?? '';
        $valid = false;

        if (strpos($stored, '$2y$') === 0) {
            $valid = password_verify($current, $stored);
        } else {
            $valid = ($current === $stored);
        }

        if (!$valid) {
            $message = 'Current password is incorrect.';
            $message_type = 'danger';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
            $upd->bind_param("si", $hash, $student_id);
            if ($upd->execute()) {
                $message = 'Password updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update password.';
                $message_type = 'danger';
            }
            $upd->close();
        }
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
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-blue: #1a3066; --sidebar-width: 240px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: #dee2e6; padding: 10px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; }
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .user-dropdown { background: var(--primary-blue); color: white; padding: 6px 15px; font-size: 0.8rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 700; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .security-card { max-width: 560px; margin: 0 auto; }
        .form-label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-control { border-radius: 10px; padding: 12px 16px; border: 1px solid #e2e8f0; font-size: 0.9rem; background: #f8fafc; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(26,48,102,0.1); background: #fff; }
        .icon-badge { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #dbeafe, #e0e7ff); color: #4f46e5; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="security-card">
        <div class="text-center mb-4">
            <div class="icon-badge mb-3">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2 class="fw-bold mb-1">Security Settings</h2>
            <p class="text-muted small mb-0">Update your password to keep your account secure</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 d-flex align-items-center">
                <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card p-4 p-md-5">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control"
                           placeholder="Enter current password" required>
                </div>

                <hr class="my-4 opacity-25">

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           placeholder="Minimum 6 characters" required minlength="6">
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           placeholder="Repeat new password" required minlength="6">
                </div>

                <button type="submit" name="update_password"
                        class="btn btn-primary w-100 py-3 fw-bold rounded-3">
                    <i class="bi bi-key me-2"></i>Update Password
                </button>
            </form>
        </div>

        <div class="card p-3 mt-4 border-start border-4 border-info">
            <p class="mb-0 small text-muted">
                <i class="bi bi-lightbulb-fill me-2 text-info"></i>
                <strong>Tip:</strong> Use a combination of letters, numbers, and symbols for a strong password.
            </p>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            } else {
                document.body.classList.toggle('sidebar-toggled');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

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