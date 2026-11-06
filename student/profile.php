<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

$success_msg = '';
$error_msg   = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email']   ?? '');
    $dob     = trim($_POST['dob']     ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        $error_msg = 'Please enter a valid date of birth.';
    } else {
        // Save dob only if provided; otherwise keep existing
        if ($dob !== '') {
            $update = $conn->prepare("UPDATE students SET parent_contact = ?, address = ?, email = ?, dob = ? WHERE adm_no = ?");
            $update->bind_param("sssss", $phone, $address, $email, $dob, $adm_no);
        } else {
            $update = $conn->prepare("UPDATE students SET parent_contact = ?, address = ?, email = ? WHERE adm_no = ?");
            $update->bind_param("ssss", $phone, $address, $email, $adm_no);
        }

        if ($update->execute()) {
            header("Location: profile.php?msg=updated");
            exit();
        } else {
            $error_msg = 'Failed to update profile. Please try again.';
        }
        $update->close();
    }
}

// Success flash from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $success_msg = 'Your profile has been updated successfully.';
}

// Fetch student record
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found. Please contact administration.");
}

// Format DOB for the date input (must be YYYY-MM-DD)
$dob_value = '';
if (!empty($student['dob'])) {
    $dob_value = date('Y-m-d', strtotime($student['dob']));
}

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'profile';
$extra_label = 'Edit Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
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

        .edit-card { background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 36px; max-width: 640px; }
        .form-label { font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-control { border-radius: 10px; padding: 10px 14px; border: 1px solid #e2e8f0; font-size: 0.9rem; background: #f8fafc; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(26,48,102,0.1); background: #fff; }
        .form-control[readonly] { background: #f1f5f9; color: #64748b; }

        .avatar-badge { width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #6366f1; font-weight: 800; font-size: 1.75rem; display: inline-flex; align-items: center; justify-content: center; }

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

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Edit Profile</h2>
        <p class="text-muted small mb-0">Update your personal information</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" style="max-width: 640px;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" style="max-width: 640px;">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="edit-card">

        <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom">
            <div class="avatar-badge">
                <?php echo htmlspecialchars(strtoupper(substr($student_display_name, 0, 1))); ?>
            </div>
            <div>
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></h5>
                <span class="text-muted small">
                    <i class="bi bi-hash"></i><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?>
                    <?php if (!empty($student['form'])): ?>
                        &middot; <?php echo htmlspecialchars($student['form']); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <form method="POST" action="profile.php">

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control"
                       value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" readonly>
                <small class="text-muted">Contact the administration office to change your name.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control"
                       value="<?php echo htmlspecialchars($dob_value); ?>"
                       max="<?php echo date('Y-m-d'); ?>">
                <small class="text-muted">Your date of birth as recorded in school records.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($student['parent_contact'] ?? ''); ?>"
                       placeholder="07..." required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>"
                       placeholder="you@example.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"
                          placeholder="e.g. Westlands, Nairobi"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-between mt-4 pt-4 border-top">
                <a href="dashboard_view.php" class="btn btn-light border rounded-3 px-4">
                    <i class="bi bi-arrow-left me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
            </div>
        </form>
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