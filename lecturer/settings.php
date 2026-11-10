<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

// Fetch school + teacher info
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT * FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];
$staff_display_name = $user_data['full_name'] ?? 'Staff';

$success_msg = '';
$error_msg   = '';

// ---- Handle profile update (phone only, email is read-only) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_phone = trim($_POST['phone'] ?? '');

    $upd = $conn->prepare("UPDATE teachers SET phone = ? WHERE teacher_id = ?");
    $upd->bind_param("si", $new_phone, $staff_id);
    if ($upd->execute()) {
        $success_msg = 'Phone number updated successfully.';
        $user_data['phone'] = $new_phone;
    } else {
        $error_msg = 'Failed to update profile.';
    }
    $upd->close();
}

// ---- Handle password change (No current password required) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_pw   = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($new_pw === '' || $confirm === '') {
        $error_msg = 'All password fields are required.';
    } elseif ($new_pw !== $confirm) {
        $error_msg = 'New passwords do not match.';
    } elseif (strlen($new_pw) < 6) {
        $error_msg = 'New password must be at least 6 characters.';
    } else {
        $user_id = (int) ($user_data['user_id'] ?? 0);
        $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
        
        $upd_pw = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd_pw->bind_param("si", $new_hash, $user_id);
        if ($upd_pw->execute()) {
            $success_msg = 'Password changed successfully.';
        } else {
            $error_msg = 'Failed to change password.';
        }
        $upd_pw->close();
    }
}

$active_page = 'settings';
$extra_label = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?php echo htmlspecialchars($school_display); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Shared styles from your sample */
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
            --radius-lg: 24px;
        }

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-navy { background: var(--primary); color: var(--white); }
        .ui-btn.is-navy:hover { background: var(--primary-soft); color: var(--white); }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-muted); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }
        .ui-input[readonly] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-color: #e2e8f0; }

        .section-title { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 992px) {
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Account Settings</h1>
            <p>Manage your contact details and account security</p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile card -->
        <div class="col-lg-7">
            <div class="panel h-100">
                <h5 class="section-title"><i class="bi bi-person-fill" style="color: var(--primary);"></i> Profile Information</h5>

                <form method="POST">
                    <div class="ui-field mb-4">
                        <label>Full Name</label>
                        <input type="text" class="ui-input" value="<?php echo htmlspecialchars($staff_display_name); ?>" readonly>
                        <small class="text-muted mt-2 d-block fw-medium">Contact admin to change your legal name.</small>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6 ui-field">
                            <label>Email Address</label>
                            <input type="email" class="ui-input" 
                                   value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" readonly>
                            <small class="text-muted mt-2 d-block fw-medium">Managed by the school administration.</small>
                        </div>
                        <div class="col-md-6 ui-field">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="ui-input"
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                   placeholder="07..." required>
                        </div>
                    </div>

                    <div class="mt-5 text-end">
                        <button type="submit" name="update_profile" class="ui-btn is-primary">
                            <i class="bi bi-save-fill"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password card -->
        <div class="col-lg-5">
            <div class="panel h-100">
                <h5 class="section-title"><i class="bi bi-shield-lock-fill" style="color: var(--primary);"></i> Security</h5>

                <form method="POST">
                    <div class="ui-field mb-4">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="ui-input" required minlength="6" placeholder="Enter new password">
                        <small class="text-muted mt-2 d-block fw-medium">At least 6 characters.</small>
                    </div>
                    <div class="ui-field mb-4">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="ui-input" required minlength="6" placeholder="Repeat new password">
                    </div>

                    <div class="mt-5 text-end">
                        <button type="submit" name="change_password" class="ui-btn is-navy">
                            <i class="bi bi-key-fill"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
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