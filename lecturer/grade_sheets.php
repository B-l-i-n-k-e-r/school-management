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

$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name    = $staff_data['full_name']        ?? 'Staff';
$assigned_form         = $staff_data['assigned_form']    ?? '';
$assigned_subject_name = $staff_data['assigned_subject'] ?? '';

// --- Fetch ONLY the pairs this teacher is assigned to ---
$teacher_assignments = [];
$assign_query = $conn->query("
    SELECT ta.class_name, ta.subject_id, s.subject_name 
    FROM teacher_assignments ta 
    JOIN subjects s ON ta.subject_id = s.subject_id 
    WHERE ta.teacher_id = $staff_id
    ORDER BY ta.class_name ASC, s.subject_name ASC
");
if ($assign_query) {
    while ($row = $assign_query->fetch_assoc()) {
        $teacher_assignments[] = $row;
    }
}

// Extract unique classes and subjects for the dropdowns
$assigned_classes = [];
$assigned_subjects = [];
foreach ($teacher_assignments as $ta) {
    $assigned_classes[$ta['class_name']] = $ta['class_name'];
    $assigned_subjects[$ta['subject_name']] = $ta['subject_name'];
}
$assigned_classes = array_values($assigned_classes);
$assigned_subjects = array_values($assigned_subjects);

// Filter defaults
$form_filter    = $_GET['form']    ?? ($assigned_classes[0] ?? '');
$subject_filter = $_GET['subject'] ?? ($assigned_subjects[0] ?? '');

// Fetch students with existing grades
$students = [];
if ($form_filter && $subject_filter) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.full_name, s.adm_no,
               g.cat1, g.cat2, g.exam, g.total, g.grade
        FROM students s
        LEFT JOIN grades g ON s.student_id = g.student_id AND g.subject_name = ?
        WHERE TRIM(s.form) = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->bind_param("ss", $subject_filter, $form_filter);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

$active_page = 'grade_sheets';
$extra_label = 'Grade Sheets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Sheets | <?php echo htmlspecialchars($school_display); ?></title>
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

        .panel {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
        }
        .panel.is-flush { padding: 0; overflow: hidden; }

        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px;
            font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2;
            text-decoration: none; cursor: pointer; transition: background 0.2s, transform 0.2s, box-shadow 0.2s, color 0.2s;
        }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); color: var(--primary); }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }

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
        .ui-table tbody tr { transition: background-color 0.2s; }
        .ui-table tbody tr:hover { background: var(--primary-tint); }
        .ui-table .is-center { text-align: center; }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }

        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .mark-input { width: 75px; text-align: center; font-weight: 700; font-size: 0.95rem; border-radius: 10px; border: 1.5px solid var(--line); padding: 8px; background: var(--bg-light); color: var(--primary); transition: all 0.2s; }
        .mark-input:focus { outline: none; border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 4px rgba(0,10,102,0.1); }
        
        .grade-badge { padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; display: inline-block; min-width: 50px; }

        /* Modern Filter Card */
        .filter-card { background: var(--white); border-radius: 20px; padding: 20px 24px; box-shadow: 0 10px 30px -10px rgba(0,10,102,0.1); display: flex; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; border-left: 5px solid var(--accent); }
        
        .grade-table th:nth-child(1) { width: 15%; }
        .grade-table th:nth-child(2) { width: 30%; }
        .grade-table th:nth-child(3), 
        .grade-table th:nth-child(4), 
        .grade-table th:nth-child(5) { width: 12%; text-align: center; }
        .grade-table th:nth-child(6) { width: 10%; text-align: center; }
        .grade-table th:nth-child(7) { width: 9%; text-align: center; }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Grade Sheets</h1>
            <p>Enter and review CAT + exam marks per student.</p>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div>Marks updated successfully!</div></div>
    <?php endif; ?>

    <!-- Filter Panel -->
    <div class="filter-card">
        <form method="GET" class="row g-3 w-100 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted text-uppercase">Academic Level</label>
                <select name="form" class="ui-input form-select" required>
                    <option value="">Choose Form...</option>
                    <?php foreach ($assigned_classes as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"
                            <?php echo ($form_filter == $cname) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted text-uppercase">Subject</label>
                <select name="subject" class="ui-input form-select" required>
                    <option value="">Choose Subject...</option>
                    <?php foreach ($assigned_subjects as $subj): ?>
                        <option value="<?php echo htmlspecialchars($subj); ?>"
                            <?php echo ($subject_filter == $subj) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subj); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="ui-btn is-primary w-100" style="height: 48px;">
                    <i class="bi bi-funnel-fill"></i> Load
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($assigned_classes)): ?>
        <div class="panel">
            <div class="ui-empty">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <p>No classes assigned</p>
                <small>You need to be assigned to a class and subject by the Admin to view grades.</small>
            </div>
        </div>
    <?php elseif ($form_filter && $subject_filter && !empty($students)): ?>
    <form action="save_grades.php" method="POST">
        <input type="hidden" name="form" value="<?php echo htmlspecialchars($form_filter); ?>">
        <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($subject_filter); ?>">

        <div class="panel is-flush">
            <div class="ui-panel-head">
                <div class="ui-panel-title">
                    <h2>Student Grades</h2>
                    <span class="ui-count"><?php echo count($students); ?> Students</span>
                </div>
            </div>
            
            <div class="ui-table-wrap">
                <table class="ui-table grade-table">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Student Name</th>
                            <th>CAT 1 <br><span class="text-muted" style="font-size:0.7rem;">(30)</span></th>
                            <th>CAT 2 <br><span class="text-muted" style="font-size:0.7rem;">(30)</span></th>
                            <th>Exam <br><span class="text-muted" style="font-size:0.7rem;">(40)</span></th>
                            <th>Total <br><span class="text-muted" style="font-size:0.7rem;">(100)</span></th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $g = $student['grade'] ?? 'F';
                            $badge_class = match($g) {
                                'A','A-' => 'bg-success text-white',
                                'B+','B','B-' => 'bg-primary text-white',
                                'C+','C','C-' => 'bg-info text-dark',
                                'D+','D','D-' => 'bg-warning text-dark',
                                default => 'bg-danger text-white'
                            };
                        ?>
                        <tr>
                            <td class="fw-bold" style="color: var(--primary); font-size: 1rem;"><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></td>
                            <td class="fw-bold text-dark" style="font-size: 1rem;">
                                <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                <input type="hidden" name="student_id[]" value="<?php echo (int)$student['student_id']; ?>">
                            </td>
                            <td class="text-center"><input type="number" name="cat1[]" class="mark-input" max="30" min="0" value="<?php echo (float)($student['cat1'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center"><input type="number" name="cat2[]" class="mark-input" max="30" min="0" value="<?php echo (float)($student['cat2'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center"><input type="number" name="exam[]" class="mark-input" max="40" min="0" value="<?php echo (float)($student['exam'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center fw-bold total-val" style="font-size: 1.1rem; color: var(--ink);"><?php echo (float)($student['total'] ?? 0); ?></td>
                            <td class="text-center">
                                <span class="grade-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($g); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 text-end border-top">
                <button type="submit" name="save_marks" class="ui-btn is-primary px-5 py-3" style="font-size: 1.05rem;">
                    <i class="bi bi-save-fill"></i> Save All Grades
                </button>
            </div>
        </div>
    </form>
    <?php elseif ($form_filter && $subject_filter): ?>
        <div class="panel">
            <div class="ui-empty">
                <i class="bi bi-people"></i>
                <p>No students found</p>
                <small>No students found in <strong><?php echo htmlspecialchars($form_filter); ?></strong> for this subject.</small>
            </div>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live total + grade calculator
    function calc(el) {
        if (el.value > el.max) el.value = el.max;
        const row = el.closest('tr');
        const inputs = row.querySelectorAll('input[type="number"]');
        let t = 0;
        inputs.forEach(i => t += parseFloat(i.value) || 0);
        row.querySelector('.total-val').innerText = t;
        
        const badge = row.querySelector('.grade-badge');
        let g = 'F', cls = 'bg-danger text-white';
        
        if (t >= 80)      { g = 'A'; cls = 'bg-success text-white'; }
        else if (t >= 70) { g = 'B'; cls = 'bg-primary text-white'; }
        else if (t >= 60) { g = 'C'; cls = 'bg-info text-dark'; }
        else if (t >= 50) { g = 'D'; cls = 'bg-warning text-dark'; }
        
        badge.innerText = g;
        badge.className = `grade-badge ${cls}`;
    }

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