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

$fee_stmt = $conn->prepare("SELECT (amount_total - amount_paid) AS balance FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fee = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

$balance = (float) ($fee['balance'] ?? 0);
$is_blocked = ($balance > 0);

$results = [];
if (!$is_blocked) {
    $r_stmt = $conn->prepare("
        SELECT r.marks, r.grade, r.remarks, s.subject_name, e.exam_name, e.term
        FROM results r
        LEFT JOIN subjects s ON s.subject_id = r.subject_id
        LEFT JOIN exams e ON e.exam_id = r.exam_id
        WHERE r.student_id = ?
        ORDER BY s.subject_name ASC
    ");
    if ($r_stmt) {
        $r_stmt->bind_param("i", $student_id);
        $r_stmt->execute();
        $r_res = $r_stmt->get_result();
        while ($row = $r_res->fetch_assoc()) $results[] = $row;
        $r_stmt->close();
    }
}

$subject_count = count($results);
$total_marks = 0;
foreach ($results as $r) $total_marks += (float) $r['marks'];
$average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'results';
$extra_label = 'Exam Results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .panel.is-flush { padding: 0; overflow: hidden; }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-danger { background: #fdeaea; color: #a42525; }
        .ui-panel-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 24px 28px; border-bottom: 1px solid var(--line); }
        .ui-panel-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ui-panel-title h2 { margin: 0; font-size: 1.15rem; font-weight: 800; letter-spacing: -0.01em; color: var(--primary); }
        .ui-count { padding: 5px 14px; border-radius: 99px; background: var(--accent); color: var(--primary); font-size: 0.8rem; font-weight: 800; white-space: nowrap; }
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
        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .summary-box { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white; border-radius: 20px; padding: 28px; position: relative; overflow: hidden; }
        .summary-box::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .summary-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; }
        .stat-card { background: var(--white); border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1.5px solid var(--line); }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.success { background: #ecfdf5; color: #10b981; }
        .stat-icon.danger { background: #fef2f2; color: #ef4444; }
        .grade-badge { padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; display: inline-block; min-width: 50px; }
        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        @media (max-width: 992px) { .main-content { padding: 20px; } }
        @media print { .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; } .main-content { margin-left: 0 !important; padding: 0 !important; } .panel, .summary-box, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head no-print">
        <div>
            <h1>Academic Performance</h1>
            <p>Official results slip for <strong><?php echo htmlspecialchars($student_display_name); ?></strong></p>
        </div>
        <?php if (!$is_blocked && !empty($results)): ?>
            <button onclick="window.print()" class="ui-btn is-primary"><i class="bi bi-printer-fill"></i> Print Transcript</button>
        <?php endif; ?>
    </div>

    <?php if ($is_blocked): ?>
        <div class="panel text-center">
            <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-4" style="width: 100px; height: 100px;">
                <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Results Withheld</h3>
            <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
                Your results for this semester have been withheld due to an outstanding fee balance of
                <strong class="text-danger fs-5">KES <?php echo number_format($balance, 2); ?></strong>.
                Please clear your dues to view your grades.
            </p>
            <a href="fees.php" class="ui-btn is-primary" style="width: fit-content; margin: 0 auto;"><i class="bi bi-wallet2"></i> View Fee Balance</a>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="summary-box h-100 d-flex flex-column justify-content-center">
                    <div class="summary-label">Average Score</div>
                    <div class="display-4 fw-bold mb-0 mt-1"><?php echo number_format($average, 1); ?>%</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi bi-journal-bookmark-fill"></i></div>
                    <div>
                        <div class="summary-label text-muted">Subjects Taken</div>
                        <div class="display-6 fw-bold mb-0 text-dark"><?php echo $subject_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $average >= 50 ? 'success' : 'danger'; ?>">
                        <i class="bi <?php echo $average >= 50 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                    </div>
                    <div>
                        <div class="summary-label text-muted">Overall Status</div>
                        <div class="display-6 fw-bold mb-0 <?php echo $average >= 50 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $average >= 50 ? 'Pass' : 'Review'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel is-flush">
            <div class="ui-panel-head">
                <div class="ui-panel-title">
                    <h2>Results Slip</h2>
                    <span class="ui-count"><?php echo $subject_count; ?> Subjects</span>
                </div>
            </div>

            <?php if (empty($results)): ?>
                <div class="ui-empty">
                    <i class="bi bi-journal-x"></i>
                    <p>No results published</p>
                    <small>Once your teachers publish marks, they'll appear here.</small>
                </div>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th class="is-center">Exam</th>
                                <th class="is-center">Marks</th>
                                <th class="is-center">Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r):
                                $marks = (float) ($r['marks'] ?? 0);
                                $grade = $r['grade'] ?: '—';
                                $badge_class = match(true) {
                                    $marks >= 80 => 'bg-success text-white',
                                    $marks >= 70 => 'bg-primary text-white',
                                    $marks >= 60 => 'bg-info text-dark',
                                    $marks >= 50 => 'bg-warning text-dark',
                                    default => 'bg-danger text-white'
                                };
                            ?>
                                <tr>
                                    <td><span class="ui-name"><?php echo htmlspecialchars($r['subject_name'] ?? '—'); ?></span></td>
                                    <td class="is-center">
                                        <span class="ui-badge is-navy"><?php echo htmlspecialchars($r['exam_name'] ?? '—'); ?></span>
                                    </td>
                                    <td class="is-center fw-bold text-dark"><?php echo number_format($marks, 1); ?></td>
                                    <td class="is-center"><span class="grade-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($grade); ?></span></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($r['remarks'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 p-4 rounded-4 no-print" style="background: var(--white); border-left: 4px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                <p class="mb-0 text-muted fw-medium">
                    <strong>Grading Key:</strong> A (70-100), B (60-69), C (50-59), D (40-49), F (0-39). Contact the Registrar's office for any discrepancies.
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