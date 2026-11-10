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

// Fetch school + teacher
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name    = $staff_data['full_name']        ?? 'Staff';
$assigned_form         = $staff_data['assigned_form']    ?? '';
$assigned_subject_name = $staff_data['assigned_subject'] ?? '';

// Look up subject_id
$subject_id = 0;
if ($assigned_subject_name) {
    $sub_lookup = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ? LIMIT 1");
    $sub_lookup->bind_param("s", $assigned_subject_name);
    $sub_lookup->execute();
    $sub_res = $sub_lookup->get_result()->fetch_assoc();
    $subject_id = (int) ($sub_res['subject_id'] ?? 0);
    $sub_lookup->close();
}

$success_msg = '';
$error_msg   = '';

// Handle marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $exam_type   = trim($_POST['exam_type'] ?? '');
    $marks_array = $_POST['marks'] ?? [];

    if (!$subject_id) {
        $error_msg = 'Cannot save — no subject assigned to your profile.';
    } elseif (empty($marks_array)) {
        $error_msg = 'No marks entered.';
    } else {
        $exam_id = 0;
        $exam_lookup = $conn->prepare("SELECT exam_id FROM exams WHERE exam_name = ? LIMIT 1");
        $exam_lookup->bind_param("s", $exam_type);
        $exam_lookup->execute();
        $exam_res = $exam_lookup->get_result()->fetch_assoc();
        $exam_lookup->close();
        
        if ($exam_res) {
            $exam_id = (int) $exam_res['exam_id'];
        } else {
            $error_msg = "Error: The selected Exam does not exist in the database.";
        }

        if ($exam_id > 0) {
            $saved = 0;
            foreach ($marks_array as $student_id => $score) {
                if ($score === '' || $score === null) continue;
                
                $student_id = (int) $student_id;
                $score      = (float) $score;

                if ($score >= 80)      { $grade = 'A'; $remarks = 'Excellent'; }
                elseif ($score >= 75)  { $grade = 'A-'; $remarks = 'Very Good'; }
                elseif ($score >= 70)  { $grade = 'B+'; $remarks = 'Good'; }
                elseif ($score >= 65)  { $grade = 'B'; $remarks = 'Good'; }
                elseif ($score >= 60)  { $grade = 'B-'; $remarks = 'Fair'; }
                elseif ($score >= 55)  { $grade = 'C+'; $remarks = 'Fair'; }
                elseif ($score >= 50)  { $grade = 'C'; $remarks = 'Average'; }
                elseif ($score >= 45)  { $grade = 'C-'; $remarks = 'Below Average'; }
                elseif ($score >= 40)  { $grade = 'D+'; $remarks = 'Weak'; }
                elseif ($score >= 35)  { $grade = 'D'; $remarks = 'Weak'; }
                elseif ($score >= 30)  { $grade = 'D-'; $remarks = 'Very Weak'; }
                else                   { $grade = 'E'; $remarks = 'Fail'; }

                $check = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND subject_id = ? AND exam_id = ? LIMIT 1");
                $check->bind_param("iii", $student_id, $subject_id, $exam_id);
                $check->execute();
                $exists = $check->get_result()->num_rows;
                $check->close();

                if ($exists > 0) {
                    $update = $conn->prepare("UPDATE results SET marks = ?, grade = ?, remarks = ?, recorded_by = ? WHERE student_id = ? AND subject_id = ? AND exam_id = ?");
                    $update->bind_param("dssiiii", $score, $grade, $remarks, $staff_id, $student_id, $subject_id, $exam_id);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conn->prepare("INSERT INTO results (student_id, subject_id, exam_id, marks, grade, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("iiidssi", $student_id, $subject_id, $exam_id, $score, $grade, $remarks, $staff_id);
                    $insert->execute();
                    $insert->close();
                }
                $saved++;
            }
            if ($saved > 0) {
                $success_msg = "$saved marks saved for $assigned_subject_name ($exam_type).";
            }
        }
    }
}

// Fetch students
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

// Fetch exams
$exams = [];
$exam_query = $conn->query("SELECT exam_id, exam_name FROM exams");
if ($exam_query) {
    while ($row = $exam_query->fetch_assoc()) {
        $exams[] = $row;
    }
}

$active_page = 'results';
$extra_label = $assigned_form ? 'Mark Entry: ' . $assigned_form : 'No class assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Results | <?php echo htmlspecialchars($school_display); ?></title>
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

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }

        .panel.is-flush { padding: 0; overflow: hidden; }
        .ui-panel-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 24px 28px; }
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

        .ui-search { position: relative; width: 260px; max-width: 100%; }
        .ui-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .ui-search input { width: 100%; box-sizing: border-box; padding: 11px 16px 11px 42px; border: 1.5px solid var(--line); border-radius: 99px; background: var(--bg-light); font-family: inherit; font-size: 0.9rem; color: var(--ink); }
        .ui-search input:focus { outline: none; border-color: var(--primary); background: var(--white); }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }

        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .mark-input { width: 90px; text-align: center; font-weight: 800; font-size: 1rem; border-radius: 12px; border: 1.5px solid var(--line); padding: 10px; background: var(--bg-light); color: var(--primary); transition: all 0.2s; }
        .mark-input:focus { outline: none; border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 4px rgba(0,10,102,0.1); }
        
        .results-table th:nth-child(1) { width: 20%; }
        .results-table th:nth-child(2) { width: 40%; }
        .results-table th:nth-child(3) { width: 40%; text-align: center; }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Mark Entry</h1>
            <p>Enter marks for <strong style="color: var(--primary);"><?php echo htmlspecialchars($assigned_subject_name ?: 'your subject'); ?></strong> in <strong style="color: var(--primary);"><?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></strong>.</p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <div class="panel is-flush">
        <?php if (!$assigned_form || !$assigned_subject_name): ?>
            <div class="ui-empty">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <p>Missing Assignment</p>
                <small>You need both an assigned class and subject to enter marks. Ask your admin.</small>
            </div>
        <?php elseif (empty($students)): ?>
            <div class="ui-empty">
                <i class="bi bi-people text-muted"></i>
                <p>No students found</p>
                <small>No students are currently enrolled in <?php echo htmlspecialchars($assigned_form); ?>.</small>
            </div>
        <?php elseif (empty($exams)): ?>
            <div class="ui-empty">
                <i class="bi bi-exclamation-octagon text-danger"></i>
                <p>No Exams Found</p>
                <small>You cannot enter results until an Admin creates an exam in the Exams module.</small>
            </div>
        <?php else: ?>
            <form method="POST">
                
                <div class="ui-panel-head">
                    <div class="ui-panel-title">
                        <h2>Student Marks</h2>
                        <span class="ui-count"><?php echo count($students); ?> Students</span>
                    </div>
                    
                    <div class="d-flex gap-3 align-items-center w-100 justify-content-end" style="max-width: 600px;">
                        <label class="ui-search w-100" style="max-width: 250px;">
                            <i class="bi bi-search"></i>
                            <input type="text" id="studentSearch" placeholder="Search student...">
                        </label>
                        
                        <select name="exam_type" class="ui-input form-select" style="width: 250px;" required>
                            <option value="" disabled selected>Select Exam...</option>
                            <?php foreach ($exams as $exam): ?>
                                <?php if (!empty($exam['exam_name'])): ?>
                                    <option value="<?php echo htmlspecialchars($exam['exam_name']); ?>">
                                        <?php echo htmlspecialchars($exam['exam_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table results-table" id="resultsTable">
                        <thead>
                            <tr>
                                <th>Adm No</th>
                                <th>Student Name</th>
                                <th class="text-center">Marks (0-100)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): ?>
                            <tr>
                                <td class="fw-bold" style="color: var(--primary); font-size: 1rem;">
                                    <?php echo htmlspecialchars($row['adm_no'] ?? ''); ?>
                                </td>
                                <td class="fw-bold text-dark" style="font-size: 1rem;">
                                    <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>
                                </td>
                                <td class="text-center">
                                    <input type="number"
                                           name="marks[<?php echo (int)$row['student_id']; ?>]"
                                           class="mark-input"
                                           min="0" max="100" step="0.1" placeholder="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 text-end border-top">
                    <button type="submit" name="save_results" class="ui-btn is-primary px-5 py-3" style="font-size: 1.05rem;">
                        <i class="bi bi-save-fill"></i> Save All Results
                    </button>
                </div>
            </form>
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

    // Live Search
    document.getElementById('studentSearch')?.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
</script>
</body>
</html>