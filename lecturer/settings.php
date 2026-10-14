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

// ---- Handle profile update (phone + email) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_email !== '' && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } else {
        $upd = $conn->prepare("UPDATE teachers SET email = ?, phone = ? WHERE teacher_id = ?");
        $upd->bind_param("ssi", $new_email, $new_phone, $staff_id);
        if ($upd->execute()) {
            // Also sync the users table by user_id
            if (!empty($user_data['user_id'])) {
                $upd_u = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $upd_u->bind_param("si", $new_email, $user_data['user_id']);
                $upd_u->execute();
                $upd_u->close();
            }
            $success_msg = 'Profile updated successfully.';
            // Refresh
            $user_data['email'] = $new_email;
            $user_data['phone'] = $new_phone;
        } else {
            $error_msg = 'Failed to update profile.';
        }
        $upd->close();
    }
}

// ---- Handle password change ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'] ?? '';
    $new_pw   = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new_pw === '' || $confirm === '') {
        $error_msg = 'All password fields are required.';
    } elseif ($new_pw !== $confirm) {
        $error_msg = 'New passwords do not match.';
    } elseif (strlen($new_pw) < 6) {
        $error_msg = 'New password must be at least 6 characters.';
    } else {
        // Fetch current hash from users table
        $user_id = (int) ($user_data['user_id'] ?? 0);
        $pw_check = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $pw_check->bind_param("i", $user_id);
        $pw_check->execute();
        $pw_row = $pw_check->get_result()->fetch_assoc();
        $pw_check->close();

        $stored = $pw_row['password'] ?? '';
        $valid  = false;

        // Support both hashed and plaintext
        if (strpos($stored, '$2y$') === 0) {
            $valid = password_verify($current, $stored);
        } else {
            $valid = ($current === $stored);
        }

        if (!$valid) {
            $error_msg = 'Current password is incorrect.';
        } else {
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
        :root { 
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --sidebar-width: 240px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { 
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); 
            color: white; position: fixed; left: 0; top: 0; overflow-y: auto; 
            z-index: 1200; transition: all 0.3s ease;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; border-radius: 5px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }

        .nav-item-container { border-bottom: 1px solid #4a5568; }
        .nav-link-custom { 
            color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; 
            display: flex; justify-content: space-between; align-items: center; 
            text-decoration: none; transition: 0.3s; cursor: pointer;
        }
        .nav-link-custom:hover, .nav-link-custom.active { background: #4a5568; color: white; }

        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .section-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem; }
        .form-label { font-size: 0.85rem; font-weight: 600; color: #334155; }
        .form-control { border-radius: 10px; padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(26,48,102,0.1); }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
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

    <div class="mb-4">
        <h3 class="fw-bold mb-1">Settings</h3>
        <p class="text-muted small mb-0">Manage your profile and account security</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile card -->
        <div class="col-lg-7">
            <div class="card p-4">
                <p class="section-title"><i class="bi bi-person-fill me-1"></i> Profile Information</p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($staff_display_name); ?>" readonly>
                        <small class="text-muted">Contact admin to change your name.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                                   placeholder="you@school.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                   placeholder="07...">
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" name="update_profile" class="btn btn-primary px-4 fw-bold rounded-3">
                            <i class="bi bi-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password card -->
        <div class="col-lg-5">
            <div class="card p-4">
                <p class="section-title"><i class="bi bi-shield-lock-fill me-1"></i> Change Password</p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">At least 6 characters.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" name="change_password" class="btn btn-dark px-4 fw-bold rounded-3">
                            <i class="bi bi-key me-2"></i>Update Password
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