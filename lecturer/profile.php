<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT * FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];
$staff_display_name = $user_data['full_name'] ?? 'Staff';

$active_page = 'profile';
$extra_label = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | <?php echo htmlspecialchars($school_display); ?></title>
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

        .ui-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 99px; font-size: 0.8rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }

        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: var(--primary); color: var(--accent); font-weight: 800; font-size: 2.5rem; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 20px -5px rgba(0,10,102,0.3); }
        
        .info-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 6px; }
        .info-value { font-weight: 700; color: var(--ink); font-size: 1.05rem; margin-bottom: 0; }

        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="ui-head">
                <div>
                    <h1>My Profile</h1>
                    <p>View your account details and assignments</p>
                </div>
            </div>

            <!-- Profile Header Card -->
            <div class="panel mb-4">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4 text-center text-md-start">
                    <div class="profile-avatar">
                        <?php echo htmlspecialchars(strtoupper(substr($staff_display_name, 0, 1))); ?>
                    </div>
                    <div class="flex-grow-1">
                        <h3 class="fw-bold mb-1" style="color: var(--ink);"><?php echo htmlspecialchars($staff_display_name); ?></h3>
                        <p class="text-muted mb-3 fw-medium"><?php echo htmlspecialchars($user_data['qualification'] ?? 'Faculty member'); ?></p>
                        
                        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                            <?php if (!empty($user_data['employee_id'])): ?>
                                <span class="ui-badge is-navy">
                                    <i class="bi bi-person-badge"></i><?php echo htmlspecialchars($user_data['employee_id']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($user_data['assigned_form'])): ?>
                                <span class="ui-badge is-success">
                                    <i class="bi bi-mortarboard"></i><?php echo htmlspecialchars($user_data['assigned_form']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($user_data['assigned_subject'])): ?>
                                <span class="ui-badge is-gold">
                                    <i class="bi bi-journal-bookmark"></i><?php echo htmlspecialchars($user_data['assigned_subject']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Info Card -->
            <div class="panel">
                <h5 class="fw-bold mb-4" style="color: var(--primary);">
                    <i class="bi bi-info-circle me-2"></i>Personal Information
                </h5>

                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Employee ID</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['employee_id'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Email</p>
                        <p class="info-value">
                            <?php if (!empty($user_data['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($user_data['email']); ?>" class="text-decoration-none" style="color: var(--primary);">
                                    <?php echo htmlspecialchars($user_data['email']); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Phone</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Assigned Form</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['assigned_form'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Assigned Subject</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['assigned_subject'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Experience</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['experience'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Qualification</p>
                        <p class="info-value"><?php echo htmlspecialchars($user_data['qualification'] ?? '—'); ?></p>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <p class="info-label">Status</p>
                        <p class="info-value">
                            <?php $status = $user_data['status'] ?? 'Active'; ?>
                            <span class="ui-badge <?php echo $status === 'Active' ? 'is-success' : 'is-navy'; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="border-top mt-5 pt-4 text-muted small fw-medium">
                    <i class="bi bi-shield-check me-1 text-primary"></i>
                    To update your profile or change your password, go to the <a href="settings.php" class="text-decoration-none fw-bold" style="color: var(--primary);">Settings</a> page.
                </div>
            </div>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle
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