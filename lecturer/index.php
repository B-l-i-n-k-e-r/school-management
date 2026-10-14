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

// Fetch school name
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

// Fetch current teacher
$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT * FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $user_data['full_name'] ?? 'Staff';
$assigned_form      = $user_data['assigned_form']    ?? '';
$assigned_subject   = $user_data['assigned_subject'] ?? '';
$employee_id        = $user_data['employee_id']      ?? '';

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo htmlspecialchars($school_display); ?></title>
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

        /* Sidebar */
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

        .welcome-banner {
            background: linear-gradient(135deg, #1a3066 0%, #2c3a4d 100%);
            border-radius: 20px; color: white; padding: 40px; margin-bottom: 40px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .stat-card { background: white; border-radius: 20px; padding: 25px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); height: 100%; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

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
    <div class="welcome-banner">
        <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars(explode(' ', trim($staff_display_name))[0]); ?>!</h2>
        <p class="mb-0 opacity-75 fw-500">Welcome to your <?php echo htmlspecialchars($school_display); ?> staff workstation.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card">
                <p class="text-muted small mb-1 fw-bold text-uppercase">Primary Subject</p>
                <h3 class="fw-bold mb-0 text-primary"><?php echo htmlspecialchars($assigned_subject ?: 'Not Assigned'); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <p class="text-muted small mb-1 fw-bold text-uppercase">Assigned Class</p>
                <h3 class="fw-bold mb-0 text-success"><?php echo htmlspecialchars($assigned_form ?: 'General'); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <p class="text-muted small mb-1 fw-bold text-uppercase">Staff ID</p>
                <h3 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($employee_id ?: '—'); ?></h3>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <h5 class="fw-bold mb-4">Quick Shortcuts</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="attendance.php" class="btn btn-light w-100 py-3 border-0 rounded-4">
                    <i class="bi bi-calendar2-check d-block fs-3 mb-2 text-primary"></i>Attendance
                </a>
            </div>
            <div class="col-md-3">
                <a href="results.php" class="btn btn-light w-100 py-3 border-0 rounded-4">
                    <i class="bi bi-file-earmark-plus d-block fs-3 mb-2 text-success"></i>Enter Marks
                </a>
            </div>
            <div class="col-md-3">
                <a href="timetable.php" class="btn btn-light w-100 py-3 border-0 rounded-4">
                    <i class="bi bi-clock d-block fs-3 mb-2 text-warning"></i>Timetable
                </a>
            </div>
            <div class="col-md-3">
                <a href="messaging.php" class="btn btn-light w-100 py-3 border-0 rounded-4">
                    <i class="bi bi-chat-dots d-block fs-3 mb-2 text-info"></i>Messages
                </a>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            // On mobile, use the overlay pattern
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

    // Submenu toggles
    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            
            sub.style.display = isClosed ? "block" : "none";
            
            if (icon) {
                if (isClosed) icon.classList.replace('bi-plus', 'bi-dash');
                else icon.classList.replace('bi-dash', 'bi-plus');
            }
        });
    });
</script>
</body>
</html>