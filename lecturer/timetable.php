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
$staff_query = $conn->query("SELECT full_name, assigned_form FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $staff_data['full_name']     ?? 'Staff';
$assigned_form      = $staff_data['assigned_form'] ?? '';

// Fetch timetable rows for this teacher's class
$schedule = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [],
    'Thursday' => [], 'Friday' => [], 'Saturday' => []
];
$slots = [];

if ($assigned_form) {
    $tt_stmt = $conn->prepare("
        SELECT t.*, s.subject_name, te.full_name AS teacher_name
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.subject_id
        LEFT JOIN teachers te ON te.teacher_id = t.teacher_id
        WHERE t.form = ?
        ORDER BY t.start_time ASC
    ");
    $tt_stmt->bind_param("s", $assigned_form);
    $tt_stmt->execute();
    $tt_res = $tt_stmt->get_result();
    while ($row = $tt_res->fetch_assoc()) {
        $schedule[$row['day_of_week']][] = $row;
        $slotKey = $row['start_time'] . '|' . $row['end_time'];
        $slots[$slotKey] = ['start_time' => $row['start_time'], 'end_time' => $row['end_time']];
    }
    $tt_stmt->close();
    // Sort slots by start time
    usort($slots, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
}

$active_page = 'timetable';
$extra_label = $assigned_form ? 'Class Schedule: ' . $assigned_form : 'Timetable';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Timetable | <?php echo htmlspecialchars($school_display); ?></title>
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

        /* Timetable grid */
        .tt-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        .tt-table { margin: 0; border-collapse: separate; border-spacing: 0; width: 100%; }
        .tt-table th, .tt-table td { padding: 14px 12px; text-align: center; vertical-align: middle; border-color: #f1f5f9; }
        .tt-table thead th { border-bottom: 2px solid #e2e8f0; background: #f8fafc; }

        .tt-time-col { background: #1a3066; color: white; font-weight: 700; font-size: 0.8rem; width: 130px; white-space: nowrap; }
        .tt-day-header { font-weight: 800; color: var(--primary-blue); text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem; }

        .time-badge { font-size: 0.75rem; background: #e2e8f0; color: #475569; padding: 4px 8px; border-radius: 6px; font-weight: 600; }

        .subject-name { font-weight: 700; color: var(--primary-blue); display: block; margin-bottom: 2px; font-size: 0.9rem; }
        .room-text { font-size: 0.72rem; color: #64748b; display: block; }
        .teacher-mini { font-size: 0.68rem; color: #94a3b8; display: block; margin-top: 2px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .tt-table th, .tt-table td { padding: 10px 8px; font-size: 0.82rem; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1">Weekly Timetable</h3>
            <p class="text-muted small mb-0">
                Master schedule for <strong><?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></strong>
            </p>
        </div>
        <?php if (!empty($slots)): ?>
            <span class="badge rounded-pill bg-primary px-3 py-2 fw-bold">
                <i class="bi bi-clock me-1"></i>
                <?php echo count($slots); ?> time slot<?php echo count($slots) === 1 ? '' : 's'; ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="tt-card">
        <?php if (!$assigned_form): ?>
            <div class="p-5 text-center">
                <i class="bi bi-mortarboard fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No class assigned</h5>
                <p class="text-muted mb-0">Ask your admin to assign you a form to see its timetable.</p>
            </div>
        <?php elseif (empty($slots)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No timetable yet</h5>
                <p class="text-muted mb-0">
                    Once the admin publishes a timetable for <strong><?php echo htmlspecialchars($assigned_form); ?></strong>, lessons will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tt-table">
                    <thead>
                        <tr>
                            <th class="tt-time-col">Time Slot</th>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                                <th class="tt-day-header"><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot):
                            $start = date("h:i A", strtotime($slot['start_time']));
                            $end   = date("h:i A", strtotime($slot['end_time']));
                        ?>
                            <tr>
                                <td class="tt-time-col">
                                    <span class="time-badge"><?php echo $start; ?><br><?php echo $end; ?></span>
                                </td>
                                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day):
                                    $lesson = null;
                                    foreach ($schedule[$day] as $item) {
                                        if ($item['start_time'] === $slot['start_time']) {
                                            $lesson = $item;
                                            break;
                                        }
                                    }
                                ?>
                                    <td>
                                        <?php if ($lesson): ?>
                                            <span class="subject-name"><?php echo htmlspecialchars($lesson['subject_name'] ?? ''); ?></span>
                                            <?php if (!empty($lesson['room_no'])): ?>
                                                <span class="room-text"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($lesson['room_no']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($lesson['teacher_name'])): ?>
                                                <span class="teacher-mini"><i class="bi bi-person"></i> <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
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