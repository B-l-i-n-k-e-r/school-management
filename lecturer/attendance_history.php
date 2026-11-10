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

// Only accept a real date (YYYY-MM-DD); anything else shows all dates
$filter_date = trim($_GET['view_date'] ?? '');
if ($filter_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = '';
}
$is_view_all = ($filter_date === '');

$records = [];
if ($assigned_form) {
    if ($is_view_all) {
        $stmt = $conn->prepare("
            SELECT a.date, a.status, s.full_name, s.adm_no
            FROM attendance a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.form = ?
            ORDER BY a.date DESC, s.full_name ASC
        ");
        $stmt->bind_param("s", $assigned_form);
    } else {
        $stmt = $conn->prepare("
            SELECT a.date, a.status, s.full_name, s.adm_no
            FROM attendance a
            JOIN students s ON a.student_id = s.student_id
            WHERE s.form = ? AND a.date = ?
            ORDER BY s.full_name ASC
        ");
        $stmt->bind_param("ss", $assigned_form, $filter_date);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $records[] = $row;
    $stmt->close();
}

// Totals for the records on screen
$counts = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($records as $r) {
    $k = strtolower($r['status'] ?? '');
    if (isset($counts[$k])) $counts[$k]++;
}
$total_records = count($records);
// Late students were at school, so they count as attended
$attended = $counts['present'] + $counts['late'];
$rate = $total_records > 0 ? round($attended / $total_records * 100) : 0;

$active_page = 'attendance_history';
$extra_label = $assigned_form ? 'Class: ' . $assigned_form : 'No class assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History | <?php echo htmlspecialchars($school_display); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

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
        /* ---------- Summary ---------- */
        .hs-stats {
            display: grid;
            grid-template-columns: 1.2fr repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .hs-stat {
            padding: 20px 22px;
            border-radius: 22px;
            background: var(--white);
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
        }
        .hs-stat span { display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); }
        .hs-stat strong { display: block; margin-top: 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); line-height: 1.1; }
        .hs-stat.is-rate { background: var(--primary); }
        .hs-stat.is-rate span { color: rgba(255, 255, 255, 0.75); }
        .hs-stat.is-rate strong { color: var(--accent); }
        .hs-stat.is-present strong { color: #14633a; }
        .hs-stat.is-absent strong  { color: #a42525; }
        .hs-stat.is-late strong    { color: var(--accent-deep); }
        .hs-bar { height: 8px; margin-top: 12px; border-radius: 99px; background: rgba(255, 255, 255, 0.18); overflow: hidden; }
        .hs-bar i { display: block; height: 100%; border-radius: 99px; background: var(--accent); }

        /* ---------- Filter ---------- */
        .filter-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
            padding: 20px 24px;
            border-left: 5px solid var(--accent);
            border-radius: 20px;
            background: var(--white);
            box-shadow: 0 10px 30px -10px rgba(0, 10, 102, 0.1);
        }
        .filter-title { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--primary); }
        .date-filter-wrapper { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .premium-date-input {
            height: 44px;
            padding: 10px 16px;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            background: var(--bg-light);
            font-family: inherit;
            font-weight: 700;
            color: var(--primary);
        }
        .premium-date-input:focus { outline: none; border-color: var(--primary); }

        /* status filter chips */
        .hs-chips { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 28px 18px; }
        .hs-chip {
            padding: 8px 16px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background: var(--white);
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hs-chip:hover { border-color: var(--primary); color: var(--primary); }
        .hs-chip.is-active { background: var(--primary); border-color: var(--primary); color: var(--white); }

        /* ---------- Status badges ---------- */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .status-present { background: #ecfdf5; color: #047857; border: 1px solid #10b981; }
        .status-absent  { background: #fef2f2; color: #b91c1c; border: 1px solid #ef4444; }
        .status-late    { background: #fffbeb; color: #b45309; border: 1px solid #f59e0b; }

        @media (max-width: 992px) {
            .hs-stats { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .hs-stats { grid-template-columns: 1fr; }
        }
        @media print {
            .filter-card, .hs-chips { display: none !important; }
            .hs-stat { box-shadow: none; border: 1px solid #ddd; background: #fff !important; }
            .hs-stat strong, .hs-stat span { color: #000 !important; }
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
            <h1>Attendance history</h1>
            <p>Showing records for <strong style="color: var(--primary);"><?php echo $is_view_all ? 'all dates' : date('d M Y', strtotime($filter_date)); ?></strong></p>
        </div>
        <div class="ui-actions no-print">
            <a href="attendance.php" class="ui-btn is-ghost"><i class="bi bi-check2-square"></i> Mark today</a>
            <button type="button" class="ui-btn is-ghost" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <?php if ($assigned_form && $total_records > 0): ?>
        <section class="hs-stats">
            <div class="hs-stat is-rate">
                <span>Attendance rate</span>
                <strong><?php echo $rate; ?>%</strong>
                <div class="hs-bar" aria-hidden="true"><i style="width: <?php echo $rate; ?>%;"></i></div>
            </div>
            <div class="hs-stat is-present"><span>Present</span><strong><?php echo $counts['present']; ?></strong></div>
            <div class="hs-stat is-absent"><span>Absent</span><strong><?php echo $counts['absent']; ?></strong></div>
            <div class="hs-stat is-late"><span>Late</span><strong><?php echo $counts['late']; ?></strong></div>
        </section>
    <?php endif; ?>

    <!-- Filter by date -->
    <div class="filter-card">
        <h2 class="filter-title">
            <i class="bi bi-funnel-fill me-2" style="color: var(--accent-deep);"></i> Filter records
        </h2>

        <form method="GET" class="date-filter-wrapper">
            <input type="date" name="view_date" class="premium-date-input" value="<?php echo htmlspecialchars($filter_date); ?>" aria-label="Choose a date">
            <button type="submit" class="ui-btn is-navy" style="height: 44px; padding: 0 20px;">
                <i class="bi bi-search"></i> View
            </button>
            <?php if (!$is_view_all): ?>
                <a href="attendance_history.php" class="ui-btn is-ghost" style="height: 44px; padding: 0 20px;">View all</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Records -->
    <div class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Log records</h2>
                <span class="ui-count"><?php echo $total_records; ?> entr<?php echo $total_records === 1 ? 'y' : 'ies'; ?></span>
            </div>
            <?php if (!empty($records)): ?>
                <label class="ui-search no-print">
                    <i class="bi bi-search"></i>
                    <input type="text" id="attendanceSearch" placeholder="Search name or adm no..." aria-label="Search records">
                </label>
            <?php endif; ?>
        </div>

        <?php if (!$assigned_form): ?>
            <div class="ui-empty">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <p>No class assigned</p>
                <small>Ask your admin to assign you a form.</small>
            </div>
        <?php elseif (empty($records)): ?>
            <div class="ui-empty">
                <i class="bi bi-calendar-x"></i>
                <p>No records found</p>
                <small><?php echo $is_view_all ? 'No attendance has been marked yet.' : 'No attendance was marked for this date.'; ?></small>
            </div>
        <?php else: ?>
            <div class="hs-chips no-print" role="group" aria-label="Filter by status">
                <button type="button" class="hs-chip is-active" data-status="all">All <?php echo $total_records; ?></button>
                <button type="button" class="hs-chip" data-status="present">Present <?php echo $counts['present']; ?></button>
                <button type="button" class="hs-chip" data-status="absent">Absent <?php echo $counts['absent']; ?></button>
                <button type="button" class="hs-chip" data-status="late">Late <?php echo $counts['late']; ?></button>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <?php if ($is_view_all): ?><th>Date</th><?php endif; ?>
                            <th>Adm no</th>
                            <th>Student name</th>
                            <th class="is-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row):
                            $status_key = strtolower($row['status'] ?? 'absent');
                            $icon = match ($status_key) {
                                'present' => 'bi-check-circle-fill',
                                'absent'  => 'bi-x-circle-fill',
                                'late'    => 'bi-clock-fill',
                                default   => 'bi-question-circle-fill'
                            };
                        ?>
                            <tr data-status="<?php echo htmlspecialchars($status_key); ?>">
                                <?php if ($is_view_all): ?>
                                    <td class="nowrap"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <?php endif; ?>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span></td>
                                <td><span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span></td>
                                <td class="is-center">
                                    <span class="status-badge status-<?php echo htmlspecialchars($status_key); ?>">
                                        <i class="bi <?php echo $icon; ?>"></i>
                                        <?php echo htmlspecialchars($row['status'] ?? ''); ?>
                                    </span>
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

    // Search + status filter work together
    let statusFilter = 'all';
    function applyFilters() {
        const term = (document.getElementById('attendanceSearch')?.value || '').toLowerCase();
        document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
            const okStatus = statusFilter === 'all' || row.dataset.status === statusFilter;
            const okText   = row.innerText.toLowerCase().includes(term);
            row.style.display = (okStatus && okText) ? '' : 'none';
        });
    }
    document.getElementById('attendanceSearch')?.addEventListener('input', applyFilters);
    document.querySelectorAll('.hs-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            statusFilter = chip.dataset.status;
            document.querySelectorAll('.hs-chip').forEach(c => c.classList.toggle('is-active', c === chip));
            applyFilters();
        });
    });
</script>
</body>
</html>