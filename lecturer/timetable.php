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

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$assigned_form = $staff_data['assigned_form'] ?? '';

$timetable_data = [];
$time_slots = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($assigned_form) {
    // Fetch data and join subjects
    $stmt = $conn->prepare("
        SELECT t.*, s.subject_name 
        FROM timetable t 
        LEFT JOIN subjects s ON t.subject_id = s.subject_id 
        WHERE t.form = ?
    ");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        // Combine start_time and end_time into a single string
        $start = date("H:i", strtotime($row['start_time']));
        $end = date("H:i", strtotime($row['end_time']));
        $time_val = "$start - $end";
        
        $day_val = $row['day_of_week'];
        
        $time_slots[$time_val] = $time_val; 
        $timetable_data[$day_val][$time_val] = $row;
    }
    $stmt->close();
    
    // Sort time slots chronologically
    ksort($time_slots);
}

$active_page = 'timetable';
$extra_label = $assigned_form ? 'Class Schedule: ' . $assigned_form : 'No class assigned';
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
        :root { 
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --sidebar-width: 240px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        /* Sidebar Styles */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
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
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        /* Modern Card & Table Styles */
        .modern-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; overflow: hidden; }
        
        .timetable-table { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .timetable-table th { background: var(--primary-blue); color: white; padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .timetable-table td { background: #f8fafc; border-radius: 8px; padding: 15px; text-align: center; vertical-align: middle; border: 1px solid #e2e8f0; transition: 0.2s; }
        .timetable-table td:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        
        .time-slot-col { background: #e2e8f0 !important; font-weight: 700; color: #475569; white-space: nowrap; width: 150px; }
        
        .subject-name { font-weight: 700; color: var(--primary-blue); display: block; margin-bottom: 4px; font-size: 0.95rem; }
        .room-name { font-size: 0.75rem; color: #64748b; display: block; margin-top: 4px; }

        .empty-slot { color: #94a3b8; font-size: 0.8rem; font-style: italic; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
            .table-responsive-custom { overflow-x: auto; }
        }

        /* --- PRINT STYLES --- */
        @media print {
            /* Hide non-printable elements */
            .sidebar, .topbar, .sidebar-overlay, .no-print, footer, .alert {
                display: none !important;
            }
            /* Reset layout constraints */
            body, html {
                background-color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            /* Make the card flat */
            .modern-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
            /* Print header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }
            /* Table border fixes for printing */
            .timetable-table th {
                background-color: #f1f5f9 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .timetable-table td {
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                background: transparent !important;
                color: #000 !important;
            }
            .subject-name { color: #000 !important; }
            .time-slot-col { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1 no-print">Weekly Timetable</h2>
            <p class="text-muted small mb-0 no-print">Master schedule for <strong><?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></strong></p>
        </div>
        
        <!-- Print Button -->
        <?php if (!empty($timetable_data)): ?>
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm no-print">
                <i class="bi bi-printer-fill me-2"></i> Print Timetable
            </button>
        <?php endif; ?>
    </div>

    <!-- Header shown ONLY when printing -->
    <div class="print-header" style="display: none;">
        <h3 class="fw-bold mb-1">The Michaelson School</h3>
        <h5 class="mb-0">Weekly Timetable: <?php echo htmlspecialchars($assigned_form); ?></h5>
        <small>Generated on <?php echo date('d M Y'); ?></small>
    </div>

    <div class="modern-card">
        <?php if (!$assigned_form): ?>
            <div class="alert alert-warning border-0 rounded-4 p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill fs-2 text-warning mb-3 d-block"></i>
                <h5 class="fw-bold">No Class Assigned</h5>
                <p class="mb-0 text-muted">You need to be assigned to a class by the Admin to view a timetable.</p>
            </div>
        <?php elseif (empty($timetable_data)): ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                </div>
                <h5 class="fw-bold text-dark">No timetable yet</h5>
                <p class="text-muted mb-0">Once the admin publishes a timetable for <strong><?php echo htmlspecialchars($assigned_form); ?></strong>, lessons will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive-custom">
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time / Day</th>
                            <?php foreach ($days as $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_slots as $time): ?>
                            <tr>
                                <!-- Time Column -->
                                <td class="time-slot-col">
                                    <i class="bi bi-clock me-1"></i> <?php echo htmlspecialchars($time); ?>
                                </td>
                                
                                <!-- Days Loop -->
                                <?php foreach ($days as $day): ?>
                                    <td>
                                        <?php 
                                        // Check if a lesson exists for this day and time
                                        if (isset($timetable_data[$day][$time])): 
                                            $lesson = $timetable_data[$day][$time];
                                            $subj = $lesson['subject_name'] ?? 'Lesson';
                                            $room = $lesson['room_no'] ?? '';
                                        ?>
                                            <span class="subject-name"><?php echo htmlspecialchars($subj); ?></span>
                                            
                                            <?php if (!empty($room)): ?>
                                                <span class="room-name"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($room); ?></span>
                                            <?php endif; ?>
                                            
                                        <?php else: ?>
                                            <span class="empty-slot">- Free -</span>
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
    // Sidebar Toggle Logic
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

    // Submenu Toggle Logic
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