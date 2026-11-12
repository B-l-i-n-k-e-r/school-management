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

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$staff_name = $staff_data['full_name'] ?? 'Staff';

// 1. Fetch ALL unique classes this teacher is assigned to
$assigned_classes = [];
$class_query = $conn->query("
    SELECT DISTINCT class_name 
    FROM teacher_assignments 
    WHERE teacher_id = $staff_id
");
if ($class_query) {
    while ($row = $class_query->fetch_assoc()) {
        if (!empty($row['class_name'])) {
            $assigned_classes[] = $row['class_name'];
        }
    }
}

// 2. Fetch timetables for all assigned classes
$timetables = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if (!empty($assigned_classes)) {
    foreach ($assigned_classes as $class_name) {
        $timetable_data = [];
        $time_slots = [];

        $stmt = $conn->prepare("
            SELECT t.*, s.subject_name 
            FROM timetable t 
            LEFT JOIN subjects s ON t.subject_id = s.subject_id 
            WHERE t.form = ?
        ");
        $stmt->bind_param("s", $class_name);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $start = date("H:i", strtotime($row['start_time']));
            $end = date("H:i", strtotime($row['end_time']));
            $time_val = "$start - $end";
            $day_val = $row['day_of_week'];
            
            $time_slots[$time_val] = $time_val; 
            $timetable_data[$day_val][$time_val] = $row;
        }
        $stmt->close();
        ksort($time_slots);

        if (!empty($timetable_data)) {
            $timetables[] = [
                'class_name' => $class_name,
                'data' => $timetable_data,
                'slots' => $time_slots
            ];
        }
    }
}

$active_page = 'timetable';
$extra_label = 'My Timetables';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable | Staff Portal</title>
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

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); margin-bottom: 32px; }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }

        /* Custom Timetable Grid */
        .timetable-table { width: 100%; border-collapse: separate; border-spacing: 8px; table-layout: fixed; }
        .timetable-table th { 
            background: var(--primary); 
            color: var(--white); 
            padding: 14px; 
            border-radius: 12px; 
            text-align: center; 
            font-size: 0.85rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
        }
        .timetable-table td { 
            background: var(--bg-light); 
            border-radius: 12px; 
            padding: 12px; 
            text-align: center; 
            border: 1.5px solid var(--line); 
            position: relative; 
            vertical-align: middle; 
            height: 90px; 
        }
        
        /* FIX: Removed flexbox to let the table span correctly */
        .time-slot-col { 
            background: var(--primary-tint) !important; 
            font-weight: 800; 
            color: var(--primary); 
            font-size: 0.85rem; 
            text-align: center;
        }
        
        .tt-lesson { 
            background: var(--white); 
            padding: 10px; 
            border-radius: 10px; 
            border-left: 4px solid var(--accent); 
            text-align: left; 
            box-shadow: 0 4px 12px -4px rgba(0,10,102,0.15); 
            height: 100%; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
        }
        .tt-lesson .subj { font-weight: 800; color: var(--primary); display: block; font-size: 0.85rem; margin-bottom: 2px; }
        .tt-lesson .teach { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }

        /* Class Title Separator */
        .class-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--line); padding-bottom: 12px; }
        .class-header h2 { margin: 0; font-size: 1.3rem; font-weight: 800; color: var(--ink); }
        .class-header .badge { background: var(--primary-tint); color: var(--primary); font-weight: 800; padding: 6px 14px; font-size: 0.8rem; border-radius: 50px; }

        @media (max-width: 992px) {
            .main-content { padding: 20px; }
            .table-responsive-custom { overflow-x: auto; }
        }

        /* PRINT STYLES */
        @media print {
            .sidebar, .topbar, .sidebar-overlay, .no-print, footer, .alert { display: none !important; }
            body, html { background-color: #fff !important; margin: 0 !important; padding: 0 !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .panel { box-shadow: none !important; border: none !important; padding: 0 !important; margin-bottom: 40px !important; page-break-inside: avoid; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .timetable-table th { background-color: #f1f5f9 !important; color: #000 !important; border: 1px solid #000 !important; border-radius: 0 !important; }
            .timetable-table td { border: 1px solid #000 !important; border-radius: 0 !important; background: transparent !important; color: #000 !important; }
            .subject-name { color: #000 !important; }
            .time-slot-col { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .class-header { border-bottom: 2px solid #000 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>My Timetables</h1>
            <p>Master schedule for all your assigned classes</p>
        </div>
        <?php if (!empty($timetables)): ?>
            <button onclick="window.print()" class="ui-btn is-primary no-print">
                <i class="bi bi-printer-fill"></i> Print All
            </button>
        <?php endif; ?>
    </div>

    <div class="print-header" style="display: none;">
        <h3 class="fw-bold mb-1">The Michaelson School</h3>
        <h5 class="mb-0">Weekly Timetables for <?php echo htmlspecialchars($staff_name); ?></h5>
        <small>Generated on <?php echo date('d M Y'); ?></small>
    </div>

    <?php if (empty($assigned_classes)): ?>
        <div class="panel">
            <div class="ui-empty">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <p>No classes assigned</p>
                <small>Ask your admin to assign you a form or subject.</small>
            </div>
        </div>
    <?php elseif (empty($timetables)): ?>
        <div class="panel">
            <div class="ui-empty">
                <i class="bi bi-calendar-x"></i>
                <p>No timetables published yet</p>
                <small>Once the admin publishes a timetable for your assigned classes, it will appear here.</small>
            </div>
        </div>
    <?php else: ?>
        
        <!-- Loop through each class that has a timetable -->
        <?php foreach ($timetables as $t): ?>
            <div class="panel">
                <div class="class-header">
                    <h2>Timetable</h2>
                    <span class="badge"><i class="bi bi-bookmark-fill me-1"></i> <?php echo htmlspecialchars($t['class_name']); ?></span>
                </div>

                <div class="table-responsive-custom">
                    <table class="timetable-table">
                        <thead>
                            <tr>
                                <!-- Gave the first column a fixed width -->
                                <th style="width: 140px;">Time / Day</th>
                                <?php foreach ($days as $day): ?>
                                    <th><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($t['slots'] as $time): ?>
                                <tr>
                                    <td class="time-slot-col">
                                        <i class="bi bi-clock me-1"></i> <?php echo htmlspecialchars($time); ?>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <td>
                                            <?php if (isset($t['data'][$day][$time])): 
                                                $lesson = $t['data'][$day][$time];
                                                $subj = $lesson['subject_name'] ?? 'Lesson';
                                                $room = $lesson['room_no'] ?? '';
                                            ?>
                                                <div class="tt-lesson">
                                                    <span class="subj"><?php echo htmlspecialchars($subj); ?></span>
                                                    <?php if (!empty($room)): ?>
                                                        <span class="teach"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($room); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
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