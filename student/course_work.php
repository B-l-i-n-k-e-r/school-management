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

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { die("Student record not found."); }

$student_id = (int) $student['student_id'];

$coursework_records = [];
$cw_stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name, s.form, s.term,
           c.cat_1, c.cat_2, c.assignment
    FROM student_subjects ss
    INNER JOIN subjects s ON s.subject_id = ss.subject_id
    LEFT JOIN coursework c ON c.subject_id = ss.subject_id AND c.student_id = ss.student_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_name ASC
");
if ($cw_stmt) {
    $cw_stmt->bind_param("i", $student_id);
    $cw_stmt->execute();
    $res = $cw_stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cat1 = $row['cat_1'] !== null ? (float) $row['cat_1'] : null;
        $cat2 = $row['cat_2'] !== null ? (float) $row['cat_2'] : null;
        $asg  = $row['assignment'] !== null ? (float) $row['assignment'] : null;
        $total = ($cat1 ?? 0) + ($cat2 ?? 0) + ($asg ?? 0);
        $period = '';
        if (!empty($row['form'])) {
            $period = $row['form'];
            if (!empty($row['term'])) $period .= ' · ' . $row['term'];
        }
        $coursework_records[] = [
            'subject_name' => $row['subject_name'] ?? '',
            'academic_period' => $period,
            'cat_1' => $cat1, 'cat_2' => $cat2, 'assignment' => $asg,
            'total' => $total,
        ];
    }
    $cw_stmt->close();
}

$subject_count = count($coursework_records);
$grand_total = 0;
$max_possible = $subject_count * 40;
$completed = 0;
foreach ($coursework_records as $r) {
    $grand_total += $r['total'];
    if ($r['cat_1'] !== null) $completed++;
}
$avg_percent = $max_possible > 0 ? round(($grand_total / $max_possible) * 100, 1) : 0;

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'course_work';
$extra_label = 'Course Work';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Work | <?php echo htmlspecialchars($student_display_name); ?></title>
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
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); color: var(--primary); }
        
        /* Summary cards */
        .summary-box { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white; border-radius: 20px; padding: 28px; position: relative; overflow: hidden; }
        .summary-box::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .summary-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; }
        .stat-card { background: var(--white); border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1.5px solid var(--line); }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.yellow { background: #fffbeb; color: #f59e0b; }

        .ui-table-wrap { overflow-x: auto; }
        .ui-table { width: 100%; border-collapse: collapse; }
        .ui-table th { padding: 14px 18px; text-align: left; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); background: var(--bg-light); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); white-space: nowrap; }
        .ui-table td { padding: 16px 18px; border-bottom: 1px solid var(--line); font-size: 0.92rem; font-weight: 500; color: var(--text-muted); vertical-align: middle; }
        .ui-table th:first-child, .ui-table td:first-child { padding-left: 28px; }
        .ui-table th:last-child,  .ui-table td:last-child  { padding-right: 28px; }
        .ui-table tbody tr:last-child td { border-bottom: none; }
        .ui-table tbody tr:hover { background: var(--primary-tint); }
        .ui-table .is-center { text-align: center; }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }

        .score-pill { background: var(--bg-light); color: var(--ink); font-weight: 800; border-radius: 12px; padding: 6px 14px; font-size: 0.95rem; border: 1.5px solid var(--line); display: inline-block; min-width: 45px; }
        .total-pill { background: var(--primary-tint); color: var(--primary); border: 1.5px solid #bfdbfe; font-weight: 800; border-radius: 12px; padding: 8px 16px; font-size: 0.95rem; display: inline-block; }
        .status-badge { font-size: 0.85rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; }
        .status-on-track { color: #10b981; }
        .status-average { color: #f59e0b; }
        .status-low { color: #ef4444; }
        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }
        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        @media (max-width: 992px) { .main-content { padding: 20px; } }
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .panel, .summary-box, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head no-print">
        <div>
            <h1>Course Work Assessment</h1>
            <p>Continuous Assessment Tests (CATs) and assignment results</p>
        </div>
        <?php if (!empty($coursework_records)): ?>
            <button onclick="window.print()" class="ui-btn is-primary"><i class="bi bi-printer-fill"></i> Print Report</button>
        <?php endif; ?>
    </div>

    <?php if (!empty($coursework_records)): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="summary-box h-100 d-flex flex-column justify-content-center">
                    <div class="summary-label">Average Score</div>
                    <div class="display-4 fw-bold mb-0 mt-1"><?php echo $avg_percent; ?>%</div>
                    <div class="small opacity-75 mt-2">Total <?php echo number_format($grand_total, 1); ?> / <?php echo $max_possible; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi bi-journal-bookmark-fill"></i></div>
                    <div>
                        <div class="summary-label text-muted">Subjects Registered</div>
                        <div class="display-6 fw-bold mb-0 text-dark"><?php echo $subject_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="bi bi-clipboard-check-fill"></i></div>
                    <div>
                        <div class="summary-label text-muted">Scores Recorded</div>
                        <div class="display-6 fw-bold mb-0 <?php echo $completed === $subject_count ? 'text-success' : 'text-warning'; ?>">
                            <?php echo $completed; ?> <span class="fs-5 text-muted">/ <?php echo $subject_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Marks Breakdown</h2>
                <span class="ui-count"><?php echo $subject_count; ?> Subjects</span>
            </div>
        </div>

        <?php if (empty($coursework_records)): ?>
            <div class="ui-empty">
                <i class="bi bi-folder2-open"></i>
                <p>No subjects registered</p>
                <small>You need to register for subjects to view your coursework marks.</small>
                <div class="mt-4">
                    <a href="registration.php" class="ui-btn is-primary"><i class="bi bi-journal-check"></i> Register Now</a>
                </div>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th class="is-center">CAT 1 <span class="text-muted fw-normal">(15)</span></th>
                            <th class="is-center">CAT 2 <span class="text-muted fw-normal">(15)</span></th>
                            <th class="is-center">Assign <span class="text-muted fw-normal">(10)</span></th>
                            <th class="is-center">Total <span class="text-muted fw-normal">(40)</span></th>
                            <th class="is-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coursework_records as $row): ?>
                            <tr>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($row['subject_name']); ?></span>
                                    <?php if ($row['academic_period']): ?>
                                        <span class="ui-badge is-soft mt-1" style="font-size: 0.7rem;"><?php echo htmlspecialchars($row['academic_period']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="is-center"><?php echo ($row['cat_1'] !== null) ? '<span class="score-pill">'.number_format($row['cat_1'], 1).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                                <td class="is-center"><?php echo ($row['cat_2'] !== null) ? '<span class="score-pill">'.number_format($row['cat_2'], 1).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                                <td class="is-center"><?php echo ($row['assignment'] !== null) ? '<span class="score-pill">'.number_format($row['assignment'], 1).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                                <td class="is-center"><span class="total-pill"><?php echo number_format($row['total'], 1); ?></span></td>
                                <td class="is-center">
                                    <?php if ($row['cat_1'] === null): ?>
                                        <span class="text-muted small fw-bold">Pending</span>
                                    <?php elseif ($row['total'] >= 24): ?>
                                        <span class="status-badge status-on-track"><i class="bi bi-check-circle-fill"></i> On Track</span>
                                    <?php elseif ($row['total'] >= 16): ?>
                                        <span class="status-badge status-average"><i class="bi bi-dash-circle-fill"></i> Average</span>
                                    <?php else: ?>
                                        <span class="status-badge status-low"><i class="bi bi-exclamation-triangle-fill"></i> Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($coursework_records)): ?>
        <div class="mt-4 p-4 rounded-4 no-print" style="background: var(--white); border-left: 4px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                <p class="mb-0 text-muted fw-medium">
                    <strong>Note:</strong> Coursework contributes <strong>40%</strong> to your final grade. CAT 1 + CAT 2 + Assignment = up to 40 marks.
                </p>
            </div>
        </div>
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