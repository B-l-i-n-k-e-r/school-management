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

// Fetch current teacher info
$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT * FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $user_data['full_name'] ?? 'Staff';
$employee_id        = $user_data['employee_id'] ?? '—';

// FETCH ALL ASSIGNMENTS from the teacher_assignments table
$assigned_classes = [];
$assigned_subjects = [];

$assign_query = $conn->query("
    SELECT ta.class_name, s.subject_name 
    FROM teacher_assignments ta 
    JOIN subjects s ON ta.subject_id = s.subject_id 
    WHERE ta.teacher_id = $staff_id
");

if ($assign_query) {
    while ($row = $assign_query->fetch_assoc()) {
        if (!empty($row['class_name'])) {
            $assigned_classes[$row['class_name']] = $row['class_name'];
        }
        if (!empty($row['subject_name'])) {
            $assigned_subjects[$row['subject_name']] = $row['subject_name'];
        }
    }
}
// Convert to simple indexed arrays
$assigned_classes = array_values($assigned_classes);
$assigned_subjects = array_values($assigned_subjects);

$first_name = explode(' ', trim($staff_display_name))[0];

$active_page = 'dashboard';
$extra_label = 'Staff Dashboard';
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
        /* --- Dashboard Specific UI matching the Admin Sample --- */
        .dash-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            padding: 40px 44px;
            border-radius: 28px;
            color: var(--white);
            background: linear-gradient(115deg, var(--primary) 0%, var(--primary-soft) 100%);
            box-shadow: 0 30px 60px -28px rgba(0, 10, 102, 0.55);
        }
        .dash-hero::after {
            content: "";
            position: absolute;
            right: -90px;
            bottom: -140px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.32), transparent 70%);
        }
        .dash-hero > * { position: relative; z-index: 1; }
        
        .dash-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 16px;
            padding: 7px 14px;
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.82rem;
            font-weight: 600;
        }
        .dash-date i { color: var(--accent); }
        
        .dash-hero h1 {
            margin: 0 0 8px;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .dash-hero p.sub {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 1rem;
        }

        /* Stat cards */
        .dash-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .dash-stat {
            background: var(--white);
            border-radius: 24px;
            padding: 26px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
        }
        .dash-stat:hover { transform: translateY(-4px); }
        .dash-stat-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 22px;
        }
        .dash-stat span {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        /* Badges for classes and subjects */
        .dash-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .dash-pill {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            background: var(--bg-light);
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .dash-pill.is-gold {
            background: var(--accent-tint);
            color: var(--accent-deep);
            border-color: var(--accent);
        }

        /* Quick Actions */
        .dash-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .dash-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 24px 16px;
            border-radius: 20px;
            background: var(--bg-light);
            color: var(--ink);
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            text-align: center;
        }
        .dash-action i.lead {
            width: 50px;
            height: 50px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--white);
            color: var(--primary);
            font-size: 1.3rem;
            transition: background 0.2s;
        }
        .dash-action:hover { background: var(--primary-tint); transform: translateY(-3px); color: var(--primary); }
        .dash-action:hover i.lead { background: var(--accent); color: var(--primary); }

        @media (max-width: 992px) {
            .dash-stats { grid-template-columns: 1fr 1fr; }
            .dash-actions { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 520px) {
            .dash-stats { grid-template-columns: 1fr; }
            .dash-hero { padding: 30px 24px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <!-- Hero Banner -->
    <section class="dash-hero">
        <p class="dash-date"><i class="bi bi-calendar3"></i> <?php echo date('l, j F Y'); ?></p>
        <h1>Hello, <?php echo htmlspecialchars($first_name); ?>!</h1>
        <p class="sub">Welcome back to your <?php echo htmlspecialchars($school_display); ?> staff workstation.</p>
    </section>

    <!-- Stat Cards -->
    <section class="dash-stats">
        
        <!-- Assigned Subjects -->
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            <span>Assigned Subjects</span>
            <div class="dash-pills">
                <?php if (empty($assigned_subjects)): ?>
                    <span class="text-muted small fst-italic">No subjects assigned</span>
                <?php else: ?>
                    <?php foreach ($assigned_subjects as $sub): ?>
                        <span class="dash-pill is-gold"><?php echo htmlspecialchars($sub); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Classes -->
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-people-fill"></i></div>
            <span>Assigned Classes</span>
            <div class="dash-pills">
                <?php if (empty($assigned_classes)): ?>
                    <span class="text-muted small fst-italic">No classes assigned</span>
                <?php else: ?>
                    <?php foreach ($assigned_classes as $cls): ?>
                        <span class="dash-pill"><?php echo htmlspecialchars($cls); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Staff ID -->
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-person-vcard-fill"></i></div>
            <span>Staff ID</span>
            <strong style="font-size: 1.6rem; font-weight: 800; color: var(--ink); margin-top: 10px;">
                <?php echo htmlspecialchars($employee_id ?: '—'); ?>
            </strong>
        </div>

    </section>

    <!-- Quick Shortcuts Panel -->
    <section class="panel">
        <div class="ui-panel-head" style="padding: 0 0 20px 0;">
            <div class="ui-panel-title">
                <h2>Quick Shortcuts</h2>
            </div>
        </div>
        
        <div class="dash-actions">
            <a href="attendance.php" class="dash-action">
                <i class="bi bi-calendar2-check lead"></i> Attendance
            </a>
            <a href="results.php" class="dash-action">
                <i class="bi bi-file-earmark-plus lead"></i> Enter Marks
            </a>
            <a href="timetable.php" class="dash-action">
                <i class="bi bi-clock lead"></i> Timetable
            </a>
            <a href="messaging.php" class="dash-action">
                <i class="bi bi-chat-dots lead"></i> Messages
            </a>
        </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
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

    // Submenu toggles
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