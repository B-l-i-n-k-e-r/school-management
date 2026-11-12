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
$message = '';
$message_type = '';

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');
    $reason     = trim($_POST['reason']     ?? '');

    if ($leave_type === '' || $start_date === '' || $end_date === '' || $reason === '') {
        $message = 'All fields are required.';
        $message_type = 'warning';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $message = 'End date cannot be before the start date.';
        $message_type = 'danger';
    } else {
        $ins = $conn->prepare("INSERT INTO academic_leaves (student_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
        $ins->bind_param("issss", $student_id, $leave_type, $start_date, $end_date, $reason);
        if ($ins->execute()) {
            $message = 'Application submitted successfully. Awaiting approval.';
            $message_type = 'success';
        } else {
            $message = 'Failed to submit: ' . $conn->error;
            $message_type = 'danger';
        }
        $ins->close();
    }
}

$leave_history = [];
$h_stmt = $conn->prepare("SELECT * FROM academic_leaves WHERE student_id = ? ORDER BY created_at DESC");
$h_stmt->bind_param("i", $student_id);
$h_stmt->execute();
$h_res = $h_stmt->get_result();
while ($row = $h_res->fetch_assoc()) $leave_history[] = $row;
$h_stmt->close();

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'academic_leave';
$extra_label = 'Academic Leave';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Leave | <?php echo htmlspecialchars($student_display_name); ?></title>
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
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }
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
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }
        .ui-badge.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-danger { background: #fdeaea; color: #a42525; }
        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Academic Leave</h1>
            <p>Request a formal temporary suspension of studies</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>"><i class="bi bi-info-circle-fill"></i><div><?php echo htmlspecialchars($message); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Application Form -->
        <div class="col-lg-5">
            <div class="panel h-100">
                <h5 class="fw-bold mb-4" style="color: var(--primary);"><i class="bi bi-pencil-square me-2"></i>New Request</h5>
                
                <form method="POST">
                    <div class="ui-field mb-3">
                        <label>Reason for Leave</label>
                        <select name="leave_type" class="ui-input form-select" required>
                            <option value="" disabled selected>Select a category...</option>
                            <option value="Medical">Medical Grounds</option>
                            <option value="Financial">Financial Constraints</option>
                            <option value="Personal">Personal / Compassionate</option>
                            <option value="Work">Work Commitment</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6 ui-field">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="ui-input" required>
                        </div>
                        <div class="col-6 ui-field">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="ui-input" required>
                        </div>
                    </div>

                    <div class="ui-field mb-4">
                        <label>Detailed Explanation</label>
                        <textarea name="reason" class="ui-input" rows="4" placeholder="Briefly explain your situation..." required></textarea>
                    </div>

                    <button type="submit" name="apply_leave" class="ui-btn is-primary w-100"><i class="bi bi-send-fill"></i> Submit Application</button>
                </form>
            </div>
        </div>

        <!-- History -->
        <div class="col-lg-7">
            <div class="panel is-flush h-100">
                <div class="ui-panel-head">
                    <div class="ui-panel-title">
                        <h2>Application History</h2>
                        <span class="ui-count"><?php echo count($leave_history); ?> Requests</span>
                    </div>
                </div>

                <?php if (empty($leave_history)): ?>
                    <div class="ui-empty">
                        <i class="bi bi-calendar-x"></i>
                        <p>No requests found</p>
                        <small>You haven't submitted any leave requests yet.</small>
                    </div>
                <?php else: ?>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th class="is-center">Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_history as $leave): 
                                    $status = strtolower($leave['status'] ?? 'pending');
                                    $badge = $status === 'approved' ? 'is-success' : ($status === 'rejected' ? 'is-danger' : 'is-warning');
                                ?>
                                    <tr>
                                        <td><span class="ui-name"><?php echo htmlspecialchars($leave['leave_type'] ?? ''); ?></span></td>
                                        <td>
                                            <small class="text-muted d-block fw-medium"><i class="bi bi-calendar-event me-1"></i> <?php echo !empty($leave['start_date']) ? htmlspecialchars(date('d M Y', strtotime($leave['start_date']))) : '—'; ?></small>
                                            <small class="text-muted d-block fw-medium mt-1"><i class="bi bi-calendar-check me-1"></i> <?php echo !empty($leave['end_date']) ? htmlspecialchars(date('d M Y', strtotime($leave['end_date']))) : '—'; ?></small>
                                        </td>
                                        <td class="is-center">
                                            <span class="ui-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($leave['status'] ?? 'Pending'); ?></span>
                                        </td>
                                        <td class="text-muted small fw-medium">
                                            <?php echo !empty($leave['created_at']) ? htmlspecialchars(date('d M Y', strtotime($leave['created_at']))) : '—'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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