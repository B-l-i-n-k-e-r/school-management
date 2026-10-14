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
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-blue: #1a3066; --sidebar-width: 240px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; border-radius: 5px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }
        .nav-item-container { border-bottom: 1px solid #4a5568; }
        .nav-link-custom { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }
        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .profile-avatar { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #6366f1; font-weight: 800; font-size: 2rem; display: inline-flex; align-items: center; justify-content: center; }
        .info-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.25rem; }
        .info-value { font-weight: 600; color: #0f172a; margin-bottom: 0; }
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
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="card p-4 p-md-5 mb-4">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4 text-center text-md-start">
                    <div class="profile-avatar">
                        <?php echo htmlspecialchars(strtoupper(substr($staff_display_name, 0, 1))); ?>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($staff_display_name); ?></h3>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($user_data['qualification'] ?? 'Faculty member'); ?></p>
                        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                            <?php if (!empty($user_data['employee_id'])): ?>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                                    <i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($user_data['employee_id']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($user_data['assigned_form'])): ?>
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                                    <i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($user_data['assigned_form']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($user_data['assigned_subject'])): ?>
                                <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2">
                                    <i class="bi bi-journal-bookmark me-1"></i><?php echo htmlspecialchars($user_data['assigned_subject']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-4 p-md-5">
                <h5 class="fw-bold mb-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Personal Information
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
                                <a href="mailto:<?php echo htmlspecialchars($user_data['email']); ?>" class="text-decoration-none">
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
                            <span class="badge <?php echo $status === 'Active' ? 'bg-success' : 'bg-secondary'; ?> rounded-pill px-3 py-2">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="border-top mt-5 pt-4 text-muted small">
                    <i class="bi bi-shield-check me-1"></i>
                    To update your profile or change your password, contact the school administrator.
                </div>
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