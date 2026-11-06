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

// Fetch student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found.");
}

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? '';

// Fee balance check
$fee_stmt = $conn->prepare("SELECT (amount_total - amount_paid) AS balance FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fee_data = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

$balance = $fee_data ? (float) $fee_data['balance'] : 0;
$is_cleared = ($balance <= 0);

// Fetch registered subjects
$subjects = [];
$sub_stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name, s.form, s.term
    FROM student_subjects ss
    INNER JOIN subjects s ON s.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_name ASC
");
if ($sub_stmt) {
    $sub_stmt->bind_param("i", $student_id);
    $sub_stmt->execute();
    $res = $sub_stmt->get_result();
    while ($row = $res->fetch_assoc()) $subjects[] = $row;
    $sub_stmt->close();
}

// ==========================================
// SMART FETCH EXAM SCHEDULE
// ==========================================
$exam_schedule = [];

// First, let's find out what columns exist in the exams table to prevent crashes
$columns_res = $conn->query("SHOW COLUMNS FROM exams");
$exam_columns = [];
while ($col = $columns_res->fetch_assoc()) {
    $exam_columns[] = $col['Field'];
}

// Guess the correct names for time and date
$time_col = 'start_time'; // Default
if (in_array('start', $exam_columns)) $time_col = 'start';
elseif (in_array('time', $exam_columns)) $time_col = 'time';
elseif (in_array('exam_time', $exam_columns)) $time_col = 'exam_time';

$end_col = 'end_time';
if (in_array('end', $exam_columns)) $end_col = 'end';
elseif (in_array('duration', $exam_columns)) $end_col = 'duration';

$date_col = 'exam_date';
if (in_array('date', $exam_columns)) $date_col = 'date';
elseif (in_array('exam_day', $exam_columns)) $date_col = 'exam_day';

$class_col = 'form';
if (in_array('class_name', $exam_columns)) $class_col = 'class_name';
elseif (in_array('class', $exam_columns)) $class_col = 'class';

// Now, fetch the schedule using the corrected column names
$exam_sql = "
    SELECT e.*, s.subject_name 
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.`$class_col` = ? 
    ORDER BY e.`$date_col` ASC, e.`$time_col` ASC
";

$exam_stmt = $conn->prepare($exam_sql);
if ($exam_stmt) {
    $exam_stmt->bind_param("s", $student_form);
    $exam_stmt->execute();
    $exam_res = $exam_stmt->get_result();
    while ($row = $exam_res->fetch_assoc()) $exam_schedule[] = $row;
    $exam_stmt->close();
}

// School branding
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'exam_card';
$extra_label = 'Exam Card';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Card | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        /* --- MODERN EXAM CARD --- */
        .modern-exam-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Card Header */
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #2c3a4d 100%);
            padding: 30px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-custom h4 { font-weight: 800; margin: 0; letter-spacing: -0.5px; }
        .card-header-custom .exam-no { font-size: 0.85rem; opacity: 0.8; font-family: monospace; letter-spacing: 1px; }

        /* Student Info Strip */
        .student-strip {
            background: #f8fafc;
            padding: 25px 40px;
            display: flex;
            gap: 40px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-group { display: flex; flex-direction: column; }
        .info-group label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px; margin-bottom: 4px; }
        .info-group span { font-size: 1.1rem; font-weight: 700; color: #1e293b; }

        /* Schedule Table */
        .schedule-container { padding: 30px 40px; }
        .schedule-title { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--primary-blue); letter-spacing: 1px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .table-modern th { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: 0 15px 10px; text-align: left; }
        .table-modern td { background: #f8fafc; padding: 15px; vertical-align: middle; border: 1px solid #e2e8f0; }
        
        .table-modern tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-right: none; }
        .table-modern tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-left: none; }
        
        .subject-pill { background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 50px; font-weight: 700; color: var(--primary-blue); font-size: 0.85rem; display: inline-block; }
        .time-badge { background: #eff6ff; color: #1d4ed8; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; display: inline-block; }

        /* Footer / Signature Area */
        .card-footer-custom {
            padding: 20px 40px 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .signature-area { text-align: center; }
        .signature-line { width: 180px; border-bottom: 2px solid #cbd5e1; margin-bottom: 8px; }
        .signature-area span { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; }

        /* Blocked / empty states */
        .state-card { max-width: 560px; margin: 40px auto; background: white; border-radius: 24px; padding: 48px 40px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .state-icon { width: 84px; height: 84px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.25rem; margin-bottom: 20px; }
        .state-icon.danger  { background: #fef2f2; color: #dc2626; }
        .state-icon.warning { background: #fffbeb; color: #d97706; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
            .card-header-custom { flex-direction: column; align-items: flex-start; gap: 15px; }
            .student-strip { flex-direction: column; gap: 15px; }
        }

        /* Print styles */
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            body { background: white !important; }
            .modern-exam-card { box-shadow: none !important; border: 1px solid #ccc; }
            .card-header-custom { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-modern td { background: #fff !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <?php if (!$is_cleared): ?>
        <!-- Fee blocked state -->
        <div class="state-card">
            <div class="state-icon danger"><i class="bi bi-lock-fill"></i></div>
            <h3 class="fw-bold mb-2">Exam Card Blocked</h3>
            <p class="text-muted mb-4">
                Your exam card is unavailable because you have an outstanding fee balance of
                <strong class="text-danger">KES <?php echo number_format($balance, 2); ?></strong>.
            </p>
            <a href="fees.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-wallet2 me-2"></i>Go to Fee Balance
            </a>
        </div>

    <?php elseif (empty($subjects)): ?>
        <!-- No subjects state -->
        <div class="state-card">
            <div class="state-icon warning"><i class="bi bi-journal-x"></i></div>
            <h3 class="fw-bold mb-2">No Registered Subjects</h3>
            <p class="text-muted mb-4">You must register for your subjects before an exam card can be generated.</p>
            <a href="registration.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-journal-check me-2"></i>Register Subjects
            </a>
        </div>

    <?php else: ?>
        <!-- Header & Print Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Examination Admission</h3>
                <p class="text-muted small mb-0">Your official digital exam pass</p>
            </div>
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-2"></i>Print Card
            </button>
        </div>

        <!-- Modern Exam Card -->
        <div class="modern-exam-card">
            
            <!-- Header -->
            <div class="card-header-custom">
                <div>
                    <h4><?php echo htmlspecialchars($school_display); ?></h4>
                    <div class="exam-no mt-1">OFFICIAL EXAMINATION PASS</div>
                </div>
                <div class="text-end">
                    <div class="exam-no">ID: EX-<?php echo date('Y') ?>-<?php echo str_pad($student_id, 4, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <!-- Student Info -->
            <div class="student-strip">
                <div class="info-group">
                    <label>Student Name</label>
                    <span><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <label>Admission No</label>
                    <span><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <label>Class / Form</label>
                    <span><?php echo htmlspecialchars($student['form'] ?? '—'); ?></span>
                </div>
            </div>

            <!-- Exam Schedule -->
            <div class="schedule-container">
                <div class="schedule-title">
                    <i class="bi bi-calendar-event-fill"></i> Scheduled Examinations
                </div>

                <?php if (empty($exam_schedule)): ?>
                    <div class="alert alert-warning border-0 rounded-3 mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i> The exam schedule has not been published yet. Please check back later.
                    </div>
                <?php else: ?>
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th>Venue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_schedule as $exam): 
                                // Dynamically grab the correct values based on our earlier checks
                                $date_val = $exam[$date_col] ?? 'TBA';
                                $start_val = $exam[$time_col] ?? 'TBA';
                                $end_val = $exam[$end_col] ?? '';
                                $venue_val = $exam['venue'] ?? $exam['room'] ?? 'Main Hall';
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <?php echo ($date_val !== 'TBA') ? date('d M Y', strtotime($date_val)) : 'TBA'; ?>
                                    </td>
                                    <td>
                                        <span class="subject-pill">
                                            <?php echo htmlspecialchars($exam['subject_name'] ?? 'General'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="time-badge">
                                            <?php echo ($start_val !== 'TBA') ? date('H:i', strtotime($start_val)) : 'TBA'; ?> 
                                            <?php if (!empty($end_val)): ?>
                                                - <?php echo date('H:i', strtotime($end_val)); ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="text-muted fw-semibold">
                                        <?php echo htmlspecialchars($venue_val); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="card-footer-custom">
                <div class="signature-area">
                    <div class="signature-line"></div>
                    <span>Student Signature</span>
                </div>
                <div class="signature-area">
                    <div class="signature-line"></div>
                    <span>Registrar's Stamp</span>
                </div>
            </div>

        </div>

        <p class="text-center text-muted small mt-4 no-print">
            <i class="bi bi-shield-check me-1"></i> This is a system-generated document. Valid without a physical stamp.
        </p>

    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Logic
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