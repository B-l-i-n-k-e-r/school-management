<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

$school_display = 'The Michaelson School';
$sys_res = $conn->query("SELECT school_name FROM system_settings LIMIT 1");
if ($sys_res && ($sys = $sys_res->fetch_assoc())) {
    $school_display = $sys['school_name'] ?? $school_display;
}

$staff_id = (int) $_SESSION['user_id'];
$staff_data = [];
$staff_res = $conn->query("SELECT full_name, assigned_form FROM teachers WHERE teacher_id = $staff_id");
if ($staff_res) $staff_data = $staff_res->fetch_assoc() ?: [];

$staff_display_name = $staff_data['full_name']     ?? 'Staff';
$assigned_form      = $staff_data['assigned_form'] ?? '';

$success_msg = '';
$error_msg   = '';
$today_date  = date('Y-m-d');
$allowed_status = ['Present', 'Absent', 'Late'];

// ---- The class register (needed first, so we only accept students from this class) ----
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}
$valid_ids = array_map(fn($s) => (int) $s['student_id'], $students);

// ---- Save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $status_array = $_POST['status'] ?? [];

    if (empty($status_array)) {
        $error_msg = 'No attendance to save.';
    } else {
        $check  = $conn->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND date = ?");
        $update = $conn->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND date = ?");
        $insert = $conn->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?)");

        $saved = 0;
        foreach ($status_array as $student_id => $status) {
            $student_id = (int) $student_id;
            // only students in this teacher's class, and only the three known statuses
            if (!in_array($student_id, $valid_ids, true) || !in_array($status, $allowed_status, true)) continue;

            $check->bind_param("is", $student_id, $today_date);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows;

            if ($exists > 0) {
                $update->bind_param("sis", $status, $student_id, $today_date);
                $update->execute();
            } else {
                $insert->bind_param("iss", $student_id, $today_date, $status);
                $insert->execute();
            }
            $saved++;
        }
        $check->close();
        $update->close();
        $insert->close();

        $success_msg = "Attendance for today (" . date('d M Y') . ") saved for $saved student" . ($saved === 1 ? '' : 's') . ".";
    }
}

// ---- What is already saved for today, so the register opens with those marks ----
$today_map = [];
if ($assigned_form) {
    $stmt = $conn->prepare("
        SELECT a.student_id, a.status
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE s.form = ? AND a.date = ?
    ");
    $stmt->bind_param("ss", $assigned_form, $today_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $today_map[(int) $r['student_id']] = $r['status'];
    $stmt->close();
}
$already_marked = count($today_map);

$active_page = 'attendance';
$extra_label = $assigned_form ? 'Class: ' . $assigned_form : 'No class assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance | <?php echo htmlspecialchars($school_display); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Totals ---------- */
        .att-totals { display: flex; flex-wrap: wrap; gap: 10px; }
        .att-total {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .att-total b { font-size: 1rem; }
        .att-total.is-present { background: #e7f7ee; color: #14633a; }
        .att-total.is-absent  { background: #fdeaea; color: #a42525; }
        .att-total.is-late    { background: var(--accent-tint); color: var(--accent-deep); }

        .att-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; }

        /* ---------- Status buttons ---------- */
        .status-btn-group { position: relative; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .status-btn-group input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        .status-btn {
            display: inline-block;
            min-width: 92px;
            padding: 8px 18px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background: var(--white);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-align: center;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s;
        }
        .status-btn:hover { border-color: var(--primary); color: var(--primary); }
        .present-check:checked + .btn-p { background: #ecfdf5; color: #047857; border-color: #10b981; }
        .absent-check:checked + .btn-a  { background: #fef2f2; color: #b91c1c; border-color: #ef4444; }
        .late-check:checked + .btn-l    { background: #fffbeb; color: #b45309; border-color: #f59e0b; }
        .status-btn-group input:focus-visible + .status-btn { outline: 3px solid var(--accent); outline-offset: 2px; }

        .att-save-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            padding: 20px 28px;
            border-top: 1px solid var(--line);
            background: var(--bg-light);
        }
        .att-save-bar p { margin: 0; font-size: 0.88rem; font-weight: 600; color: var(--text-muted); }

        .attendance-table td:first-child { width: 15%; }
        .attendance-table td:nth-child(2) { width: 35%; }

    /* Shared look for buttons, tables, badges, messages and forms.
       Colour names match the admin portal, so every portal looks the same. */
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

    .panel {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 28px;
        box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
    }

    .main-content a:focus-visible,
    .main-content button:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 2px;
    }

    /* =====================================================
       Shared page components (used by every admin page)
       ===================================================== */

    /* Page header */
    .ui-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 28px;
    }
    .ui-head h1 {
        margin: 0 0 4px;
        font-size: 1.7rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--primary);
    }
    .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
    .ui-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    /* Buttons */
    .ui-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 22px;
        border: 1.5px solid transparent;
        border-radius: 99px;
        font-family: inherit;
        font-size: 0.9rem;
        font-weight: 800;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s, box-shadow 0.2s, color 0.2s;
    }
    .ui-btn:hover { transform: translateY(-1px); }
    .ui-btn.is-sm { padding: 8px 16px; font-size: 0.82rem; }
    .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
    .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
    .ui-btn.is-navy { background: var(--primary); color: var(--white); }
    .ui-btn.is-navy:hover { background: var(--primary-soft); color: var(--white); }
    .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
    .ui-btn.is-ghost:hover { background: var(--primary-tint); color: var(--primary); }
    .ui-btn.is-success { background: #14633a; color: var(--white); }
    .ui-btn.is-success:hover { background: #0f4f2e; color: var(--white); }
    .ui-btn.is-danger { background: var(--white); border-color: #f3c7c7; color: #b42318; }
    .ui-btn.is-danger:hover { background: #fdeaea; color: #b42318; }

    /* Messages */
    .ui-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px;
        border-radius: 16px;
        margin-bottom: 24px;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.5;
    }
    .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
    .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
    .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
    .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
    .ui-alert.is-info    { background: var(--primary-tint); color: var(--primary); }

    /* Panels that hold a table */
    .panel.is-flush { padding: 0; overflow: hidden; }
    .ui-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 24px 28px;
    }
    .ui-panel-head h2 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: var(--primary);
    }
    .ui-panel-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .ui-count {
        padding: 5px 14px;
        border-radius: 99px;
        background: var(--accent);
        color: var(--primary);
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
    }

    /* Tables */
    .ui-table-wrap { overflow-x: auto; }
    .ui-table { width: 100%; border-collapse: collapse; }
    .ui-table th {
        padding: 14px 18px;
        text-align: left;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--bg-light);
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        white-space: nowrap;
    }
    .ui-table td {
        padding: 16px 18px;
        border-bottom: 1px solid var(--line);
        font-size: 0.92rem;
        font-weight: 500;
        color: var(--text-muted);
        vertical-align: middle;
    }
    .ui-table th:first-child, .ui-table td:first-child { padding-left: 28px; }
    .ui-table th:last-child,  .ui-table td:last-child  { padding-right: 28px; }
    .ui-table tbody tr:last-child td { border-bottom: none; }
    .ui-table tbody tr { transition: background-color 0.2s; }
    .ui-table tbody tr:hover { background: var(--primary-tint); }
    .ui-table .is-right  { text-align: right; }
    .ui-table .is-center { text-align: center; }
    .ui-table .nowrap    { white-space: nowrap; }
    .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
    .ui-sub  { display: block; font-size: 0.82rem; color: var(--text-muted); font-weight: 500; }
    .ui-row-actions { display: flex; gap: 8px; justify-content: flex-end; align-items: center; }

    /* Badges */
    .ui-badge {
        display: inline-flex;
        padding: 5px 12px;
        border-radius: 99px;
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
    .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
    .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }

    /* Search box */
    .ui-search { position: relative; width: 260px; max-width: 100%; }
    .ui-search i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }
    .ui-search input {
        width: 100%;
        box-sizing: border-box;
        padding: 11px 16px 11px 42px;
        border: 1.5px solid var(--line);
        border-radius: 99px;
        background: var(--bg-light);
        font-family: inherit;
        font-size: 0.9rem;
        color: var(--ink);
    }
    .ui-search input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--white);
    }

    /* Empty state */
    .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
    .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
    .ui-empty p { margin: 0; font-weight: 600; }
    .ui-empty small { display: block; margin-top: 6px; }

    /* Round icon buttons (edit / delete) */
    .ui-icon {
        width: 38px;
        height: 38px;
        display: inline-grid;
        place-items: center;
        border: none;
        border-radius: 12px;
        background: var(--bg-light);
        color: var(--primary);
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .ui-icon:hover { background: var(--primary-tint); }
    .ui-icon.is-danger { color: #b42318; }
    .ui-icon.is-danger:hover { background: #fdeaea; }

    /* Forms */
    .ui-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .ui-form-grid .is-full { grid-column: 1 / -1; }
    .ui-field label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--ink);
    }
    .ui-input {
        width: 100%;
        padding: 13px 16px;
        border: 1.5px solid var(--line);
        border-radius: 14px;
        background-color: var(--bg-light);
        font-family: inherit;
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--ink);
        transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
    }
    .ui-input:focus {
        outline: none;
        border-color: var(--primary);
        background-color: var(--white);
        box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1);
    }

    /* Pop-up windows */
    .modal-content.ui-modal {
        border: none;
        border-radius: 28px;
        box-shadow: 0 40px 80px -20px rgba(0, 10, 102, 0.45);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .ui-modal .modal-header { border: none; padding: 28px 32px 8px; }
    .ui-modal .modal-title { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em; color: var(--primary); }
    .ui-modal .modal-body { padding: 16px 32px 8px; }
    .ui-modal .modal-footer { border: none; padding: 16px 32px 28px; gap: 10px; }

    @media (max-width: 640px) {
        .ui-form-grid { grid-template-columns: 1fr; }
        .ui-modal .modal-header, .ui-modal .modal-body, .ui-modal .modal-footer { padding-left: 22px; padding-right: 22px; }
    }

    /* Printing a page leaves out the menus and buttons */
    @media print {
        .sidebar, .topbar, .sidebar-overlay, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0; }
        .panel { box-shadow: none; }
    }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>
<?php include('includes/ui.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Daily attendance</h1>
            <p>Mark the attendance register for <strong><?php echo date('l, d M Y'); ?></strong></p>
        </div>
        <div class="ui-actions">
            <a href="attendance_history.php" class="ui-btn is-ghost"><i class="bi bi-clock-history"></i> History</a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success" role="alert"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($already_marked > 0 && !$success_msg): ?>
        <div class="ui-alert is-info" role="status">
            <i class="bi bi-info-circle-fill"></i>
            <div>Today's register has already been saved for <?php echo $already_marked; ?> student<?php echo $already_marked === 1 ? '' : 's'; ?>. The marks below are the saved ones. Change any and save again to update.</div>
        </div>
    <?php endif; ?>

    <div class="panel is-flush">

        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Student register</h2>
                <span class="ui-count"><?php echo count($students); ?> student<?php echo count($students) === 1 ? '' : 's'; ?></span>
            </div>

            <?php if (!empty($students)): ?>
                <div class="att-tools">
                    <button type="button" class="ui-btn is-ghost is-sm" id="allPresent"><i class="bi bi-check2-all"></i> All present</button>
                    <button type="button" class="ui-btn is-ghost is-sm" id="allAbsent"><i class="bi bi-x-lg"></i> All absent</button>
                    <label class="ui-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="studentSearch" placeholder="Search student..." aria-label="Search student">
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" id="attendanceForm">
            <?php if (!$assigned_form): ?>
                <div class="ui-alert is-warning m-4"><i class="bi bi-exclamation-triangle-fill"></i><div>No class assigned. Please contact admin.</div></div>
            <?php elseif (empty($students)): ?>
                <div class="ui-alert is-info m-4"><i class="bi bi-info-circle-fill"></i><div>No students enrolled in this class yet.</div></div>
            <?php else: ?>
                <div style="padding: 0 28px 18px;">
                    <div class="att-totals" aria-live="polite">
                        <span class="att-total is-present"><i class="bi bi-check-circle-fill"></i> Present <b id="cntPresent">0</b></span>
                        <span class="att-total is-absent"><i class="bi bi-x-circle-fill"></i> Absent <b id="cntAbsent">0</b></span>
                        <span class="att-total is-late"><i class="bi bi-clock-fill"></i> Late <b id="cntLate">0</b></span>
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table attendance-table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Adm no</th>
                                <th>Student name</th>
                                <th class="is-center">Mark status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row):
                                $sid = (int) $row['student_id'];
                                $current = $today_map[$sid] ?? 'Present';
                            ?>
                            <tr>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span></td>
                                <td><span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span></td>
                                <td>
                                    <div class="status-btn-group" role="radiogroup" aria-label="Status for <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>">
                                        <input type="radio" name="status[<?php echo $sid; ?>]" value="Present" id="p_<?php echo $sid; ?>" class="present-check"<?php echo $current === 'Present' ? ' checked' : ''; ?>>
                                        <label for="p_<?php echo $sid; ?>" class="status-btn btn-p">Present</label>

                                        <input type="radio" name="status[<?php echo $sid; ?>]" value="Absent" id="a_<?php echo $sid; ?>" class="absent-check"<?php echo $current === 'Absent' ? ' checked' : ''; ?>>
                                        <label for="a_<?php echo $sid; ?>" class="status-btn btn-a">Absent</label>

                                        <input type="radio" name="status[<?php echo $sid; ?>]" value="Late" id="l_<?php echo $sid; ?>" class="late-check"<?php echo $current === 'Late' ? ' checked' : ''; ?>>
                                        <label for="l_<?php echo $sid; ?>" class="status-btn btn-l">Late</label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="att-save-bar">
                    <p>Saving marks the register for today, <?php echo date('d M Y'); ?>.</p>
                    <button type="submit" name="save_attendance" class="ui-btn is-primary">
                        <i class="bi bi-cloud-upload-fill"></i> Save attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
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

    // Live search
    document.getElementById('studentSearch')?.addEventListener('input', function (e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    // Live totals
    function updateTotals() {
        document.getElementById('cntPresent').textContent = document.querySelectorAll('.present-check:checked').length;
        document.getElementById('cntAbsent').textContent  = document.querySelectorAll('.absent-check:checked').length;
        document.getElementById('cntLate').textContent    = document.querySelectorAll('.late-check:checked').length;
    }
    document.getElementById('attendanceForm')?.addEventListener('change', updateTotals);

    // Mark everyone in one tap (only the students the search is currently showing)
    function markAll(selector) {
        document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
            if (row.style.display === 'none') return;
            const radio = row.querySelector(selector);
            if (radio) radio.checked = true;
        });
        updateTotals();
    }
    document.getElementById('allPresent')?.addEventListener('click', () => markAll('.present-check'));
    document.getElementById('allAbsent')?.addEventListener('click',  () => markAll('.absent-check'));

    if (document.getElementById('cntPresent')) updateTotals();
</script>
</body>
</html>