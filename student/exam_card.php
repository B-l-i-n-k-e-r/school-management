<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { die("Student record not found."); }

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? '';
$student_name = $student['full_name'] ?? '';

// Check for profile photo
$avatar_path = "../images/avatars/" . ($student['avatar'] ?? '');
$has_avatar = !empty($student['avatar']) && file_exists($avatar_path);

$fee_stmt = $conn->prepare("SELECT (amount_total - amount_paid) AS balance FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fee_data = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

$balance = $fee_data ? (float) $fee_data['balance'] : 0;
$is_cleared = ($balance <= 0);

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

// Smart Exam Fetch
$exam_schedule = [];
$columns_res = $conn->query("SHOW COLUMNS FROM exams");
$exam_columns = [];
while ($col = $columns_res->fetch_assoc()) $exam_columns[] = $col['Field'];

$time_col = 'start_time'; 
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

$exam_sql = "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id = s.subject_id WHERE e.`$class_col` = ? ORDER BY e.`$date_col` ASC";
$exam_stmt = $conn->prepare($exam_sql);
if ($exam_stmt) {
    $exam_stmt->bind_param("s", $student_form);
    $exam_stmt->execute();
    $exam_res = $exam_stmt->get_result();
    while ($row = $exam_res->fetch_assoc()) $exam_schedule[] = $row;
    $exam_stmt->close();
}

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
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-warning { background: #f59e0b; color: #fff; }
        .ui-btn.is-warning:hover { background: #d97706; color: #fff; }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-alert.is-danger { background: #fdeaea; color: #a42525; }

        /* Modern Exam Card */
        .modern-exam-card { background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1); overflow: hidden; max-width: 900px; margin: 0 auto; border: 1.5px solid var(--line); }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #2c3a4d 100%);
            padding: 30px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-custom h4 { font-weight: 800; margin: 0; letter-spacing: -0.5px; }
        .card-header-custom .exam-no { font-size: 0.85rem; opacity: 0.8; font-family: monospace; letter-spacing: 1px; }

        /* Student Info Strip with Photo */
        .student-strip {
            background: #f8fafc;
            padding: 25px 40px;
            display: flex;
            align-items: center;
            gap: 30px;
            border-bottom: 1px solid var(--line);
        }
        
        .exam-avatar {
            width: 90px; height: 90px; border-radius: 12px; background: var(--primary-tint); 
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            border: 3px solid var(--white); box-shadow: 0 4px 10px rgba(0,0,0,0.08); flex-shrink: 0;
        }
        .exam-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .exam-avatar i { font-size: 2.5rem; color: var(--primary); opacity: 0.5; }

        .info-group { display: flex; flex-direction: column; }
        .info-group label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 4px; }
        .info-group span { font-size: 1.1rem; font-weight: 700; color: var(--ink); }

        .schedule-container { padding: 30px 40px; }
        .schedule-title { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 1px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .table-modern th { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 0 15px 10px; text-align: left; }
        .table-modern td { background: var(--bg-light); padding: 15px; vertical-align: middle; border: 1px solid var(--line); }
        
        .table-modern tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-right: none; }
        .table-modern tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-left: none; }
        
        .subject-pill { background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 50px; font-weight: 700; color: var(--primary); font-size: 0.85rem; display: inline-block; }
        .time-badge { background: var(--primary-tint); color: var(--primary); padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; display: inline-block; }
        
        .card-footer-custom { padding: 20px 40px 40px; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-area { text-align: center; }
        .signature-line { width: 180px; border-bottom: 2px solid #cbd5e1; margin-bottom: 8px; }
        .signature-area span { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        .state-card { max-width: 560px; margin: 40px auto; background: white; border-radius: 24px; padding: 48px 40px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1.5px solid var(--line); }
        .state-icon { width: 84px; height: 84px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.25rem; margin-bottom: 20px; }
        .state-icon.danger  { background: #fef2f2; color: #dc2626; }
        .state-icon.warning { background: #fffbeb; color: #d97706; }

        @media (max-width: 992px) { 
            .main-content { padding: 20px; } 
            .card-header-custom { flex-direction: column; align-items: flex-start; gap: 15px; } 
            .student-strip { flex-direction: column; text-align: center; } 
        }
        
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
        <div class="state-card">
            <div class="state-icon danger"><i class="bi bi-lock-fill"></i></div>
            <h3 class="fw-bold mb-2">Exam Card Blocked</h3>
            <p class="text-muted mb-4">Your exam card is unavailable because you have an outstanding fee balance of <strong class="text-danger">KES <?php echo number_format($balance, 2); ?></strong>.</p>
            <a href="fees.php" class="ui-btn is-primary"><i class="bi bi-wallet2"></i> Go to Fee Balance</a>
        </div>

    <?php elseif (empty($subjects)): ?>
        <div class="state-card">
            <div class="state-icon warning"><i class="bi bi-journal-x"></i></div>
            <h3 class="fw-bold mb-2">No Registered Subjects</h3>
            <p class="text-muted mb-4">You must register for your subjects before an exam card can be generated.</p>
            <a href="registration.php" class="ui-btn is-warning"><i class="bi bi-journal-check"></i> Register Subjects</a>
        </div>

    <?php else: ?>
        <div class="ui-head no-print">
            <div>
                <h1>Examination Admission</h1>
                <p>Your official digital exam pass</p>
            </div>
            <button onclick="window.print()" class="ui-btn is-primary"><i class="bi bi-printer-fill"></i> Print Card</button>
        </div>

        <div class="modern-exam-card">
            <div class="card-header-custom">
                <div>
                    <h4><?php echo htmlspecialchars($school_display); ?></h4>
                    <div class="exam-no mt-1">OFFICIAL EXAMINATION PASS</div>
                </div>
                <div class="text-end">
                    <div class="exam-no">ID: EX-<?php echo date('Y') ?>-<?php echo str_pad($student_id, 4, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <!-- Added Profile Photo to the Student Strip -->
            <div class="student-strip">
                <div class="exam-avatar">
                    <?php if ($has_avatar): ?>
                        <img src="<?php echo $avatar_path; ?>" alt="Profile Photo">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex flex-wrap gap-4 flex-grow-1 justify-content-around">
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
            </div>

            <div class="schedule-container">
                <div class="schedule-title"><i class="bi bi-calendar-event-fill"></i> Scheduled Examinations</div>
                <?php if (empty($exam_schedule)): ?>
                    <div class="ui-alert is-warning m-0"><i class="bi bi-info-circle-fill"></i><div>The exam schedule has not been published yet. Please check back later.</div></div>
                <?php else: ?>
                    <table class="table-modern">
                        <thead><tr><th>Date</th><th>Subject</th><th>Time</th><th>Venue</th></tr></thead>
                        <tbody>
                            <?php foreach ($exam_schedule as $exam): 
                                $date_val = $exam[$date_col] ?? 'TBA';
                                $start_val = $exam[$time_col] ?? 'TBA';
                                $end_val = $exam[$end_col] ?? '';
                                $venue_val = $exam['venue'] ?? $exam['room'] ?? 'Main Hall';
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo ($date_val !== 'TBA') ? date('d M Y', strtotime($date_val)) : 'TBA'; ?></td>
                                    <td><span class="subject-pill"><?php echo htmlspecialchars($exam['subject_name'] ?? 'General'); ?></span></td>
                                    <td><span class="time-badge"><?php echo ($start_val !== 'TBA') ? date('H:i', strtotime($start_val)) : 'TBA'; ?> <?php if (!empty($end_val)): ?> - <?php echo date('H:i', strtotime($end_val)); ?><?php endif; ?></span></td>
                                    <td class="text-muted fw-semibold"><?php echo htmlspecialchars($venue_val); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card-footer-custom">
                <div class="signature-area"><div class="signature-line"></div><span>Student Signature</span></div>
                <div class="signature-area"><div class="signature-line"></div><span>Registrar's Stamp</span></div>
            </div>
        </div>

        <p class="text-center text-muted small mt-4 no-print"><i class="bi bi-shield-check me-1"></i> This is a system-generated document. Valid without a physical stamp.</p>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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