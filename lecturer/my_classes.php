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

// Fetch school + teacher
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $user_data['full_name']        ?? 'Staff';
$assigned_form      = $user_data['assigned_form']    ?? '';
$assigned_subject   = $user_data['assigned_subject'] ?? '';

// Fetch students in the teacher's class
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no, gender FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

$active_page = 'my_classes';
$extra_label = 'My Classes — ' . ($assigned_form ?: 'Not Assigned');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes | <?php echo htmlspecialchars($school_display); ?></title>
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
        .panel.is-flush { padding: 0; overflow: hidden; }
        
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-panel-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 24px 28px; }
        .ui-panel-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ui-panel-title h2 { margin: 0; font-size: 1.15rem; font-weight: 800; letter-spacing: -0.01em; color: var(--primary); }
        .ui-count { padding: 5px 14px; border-radius: 99px; background: var(--accent); color: var(--primary); font-size: 0.8rem; font-weight: 800; white-space: nowrap; }

        .ui-table-wrap { overflow-x: auto; }
        .ui-table { width: 100%; border-collapse: collapse; }
        .ui-table th { padding: 14px 18px; text-align: left; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); background: var(--bg-light); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); white-space: nowrap; }
        .ui-table td { padding: 16px 18px; border-bottom: 1px solid var(--line); font-size: 0.92rem; font-weight: 500; color: var(--text-muted); vertical-align: middle; }
        .ui-table th:first-child, .ui-table td:first-child { padding-left: 28px; }
        .ui-table th:last-child, .ui-table td:last-child { padding-right: 28px; }
        .ui-table tbody tr:last-child td { border-bottom: none; }
        .ui-table tbody tr { transition: background-color 0.2s; }
        .ui-table tbody tr:hover { background: var(--primary-tint); }
        .ui-table .is-center { text-align: center; }

        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-male { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .ui-badge.is-female { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        
        .sf-avatar { flex-shrink: 0; width: 40px; height: 40px; display: grid; place-items: center; border-radius: 50%; background: var(--primary); color: var(--accent); font-size: 0.9rem; font-weight: 800; }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>My Classes</h1>
            <p>Students in <strong style="color: var(--primary);"><?php echo htmlspecialchars($assigned_form ?: 'your assigned form'); ?></strong>
            <?php if ($assigned_subject): ?> &middot; Teaching <strong style="color: var(--primary);"><?php echo htmlspecialchars($assigned_subject); ?></strong><?php endif; ?>
            </p>
        </div>
    </div>

    <div class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Class Roster</h2>
                <span class="ui-count"><?php echo count($students); ?> Student<?php echo count($students) === 1 ? '' : 's'; ?></span>
            </div>
        </div>

        <?php if (!$assigned_form): ?>
            <div class="ui-empty">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <p>No class assigned</p>
                <small>Ask your admin to assign you a form.</small>
            </div>
        <?php elseif (empty($students)): ?>
            <div class="ui-empty">
                <i class="bi bi-people"></i>
                <p>No students yet</p>
                <small>Once students are enrolled in <?php echo htmlspecialchars($assigned_form); ?>, they'll appear here.</small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="is-center" style="width: 60px;">#</th>
                            <th>Student</th>
                            <th>Adm No</th>
                            <th class="is-center">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): 
                            $gender = $s['gender'] ?? '—';
                            $gender_class = match($gender) {
                                'Male'   => 'is-male',
                                'Female' => 'is-female',
                                default  => 'is-soft',
                            };
                        ?>
                            <tr>
                                <td class="is-center text-muted fw-bold"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="sf-avatar"><?php echo htmlspecialchars(strtoupper(substr($s['full_name'] ?? '?', 0, 1))); ?></span>
                                        <span class="ui-name"><?php echo htmlspecialchars($s['full_name'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($s['adm_no'] ?? ''); ?></span></td>
                                <td class="is-center">
                                    <span class="ui-badge <?php echo $gender_class; ?>"><?php echo htmlspecialchars($gender); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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