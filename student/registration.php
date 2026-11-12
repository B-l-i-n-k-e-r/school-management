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

if (!$student) { die("Student record not found."); }

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_subjects'])) {
    $check_reg = $conn->prepare("SELECT COUNT(*) as c FROM student_subjects WHERE student_id = ?");
    $check_reg->bind_param("i", $student_id);
    $check_reg->execute();
    $has_registered = $check_reg->get_result()->fetch_assoc()['c'] > 0;
    $check_reg->close();

    if ($has_registered) {
        $message = 'You have already registered for your subjects. Changes are locked.';
        $message_type = 'warning';
    } else {
        $selected = $_POST['subjects'] ?? [];
        if (empty($selected)) {
            $message = 'Please select at least one subject.';
            $message_type = 'warning';
        } else {
            $conn->begin_transaction();
            try {
                $ins = $conn->prepare("INSERT IGNORE INTO student_subjects (student_id, subject_id, registration_date) VALUES (?, ?, NOW())");
                $count = 0;
                foreach ($selected as $sub_id) {
                    $sub_id = (int) $sub_id;
                    $ins->bind_param("ii", $student_id, $sub_id);
                    $ins->execute();
                    $count++;
                }
                $ins->close();
                $conn->commit();
                $message = "Registered successfully ($count subject" . ($count === 1 ? '' : 's') . " selected).";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = 'Registration failed: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch available subjects
$available_subjects = [];
$sub_stmt = $conn->prepare("SELECT subject_id, subject_name, form, term FROM subjects WHERE form = ? ORDER BY subject_name ASC");
$sub_stmt->bind_param("s", $student_form);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while ($row = $sub_res->fetch_assoc()) $available_subjects[] = $row;
$sub_stmt->close();

// Fetch registered IDs
$registered_ids = [];
$reg_stmt = $conn->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
$reg_stmt->bind_param("i", $student_id);
$reg_stmt->execute();
$reg_res = $reg_stmt->get_result();
while ($row = $reg_res->fetch_assoc()) $registered_ids[] = (int) $row['subject_id'];
$reg_stmt->close();

$is_locked = count($registered_ids) > 0;

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'registration';
$extra_label = 'Subject Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Registration | <?php echo htmlspecialchars($student_display_name); ?></title>
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
        .ui-alert.is-info    { background: var(--primary-tint); color: var(--primary); }
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
        .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        .form-check-input { cursor: pointer; width: 1.2em; height: 1.2em; }
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
            <h1>Subject Registration</h1>
            <p><?php echo $is_locked ? 'Your registered subjects for this semester' : 'Select the subjects you\'ll take this semester'; ?></p>
        </div>
        <span class="ui-badge is-navy fs-6 px-3 py-2"><i class="bi bi-person-badge me-1"></i> <?php echo htmlspecialchars($student_display_name); ?> (<?php echo htmlspecialchars($student_form); ?>)</span>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>"><i class="bi bi-info-circle-fill"></i><div><?php echo htmlspecialchars($message); ?></div></div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <div class="ui-alert is-info">
            <i class="bi bi-lock-fill"></i>
            <div><strong>Registration Complete.</strong> Your subjects have been locked. Please contact the administration if you need to make changes.</div>
        </div>
    <?php endif; ?>

    <div class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Available Subjects</h2>
                <span class="ui-count"><?php echo count($available_subjects); ?> Subjects</span>
            </div>
        </div>

        <?php if (empty($available_subjects)): ?>
            <div class="ui-empty">
                <i class="bi bi-journal-x"></i>
                <p>No subjects available</p>
                <small>There are no subjects published for <?php echo htmlspecialchars($student_form); ?> yet.</small>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <?php if (!$is_locked): ?><th class="is-center" style="width: 80px;">Select</th><?php endif; ?>
                                <th>Subject</th>
                                <th>Form</th>
                                <th>Term</th>
                                <th class="is-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_subjects as $sub):
                                $sid = (int) $sub['subject_id'];
                                $is_registered = in_array($sid, $registered_ids);
                                if ($is_locked && !$is_registered) continue;
                            ?>
                                <tr>
                                    <?php if (!$is_locked): ?>
                                        <td class="is-center">
                                            <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $sid; ?>" id="sub_<?php echo $sid; ?>">
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!$is_locked): ?>
                                            <label for="sub_<?php echo $sid; ?>" class="ui-name" style="cursor:pointer;"><?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?></label>
                                        <?php else: ?>
                                            <span class="ui-name"><i class="bi bi-book text-primary me-2"></i><?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['form'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($sub['term'] ?? '—'); ?></td>
                                    <td class="is-center">
                                        <?php if ($is_registered): ?>
                                            <span class="ui-badge is-success"><i class="bi bi-check2 me-1"></i>Registered</span>
                                        <?php else: ?>
                                            <span class="ui-badge is-navy">Not registered</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$is_locked): ?>
                    <div class="p-4 d-flex justify-content-end gap-2 flex-wrap border-top">
                        <button type="reset" class="ui-btn is-ghost border border-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        <button type="submit" name="register_subjects" class="ui-btn is-primary"><i class="bi bi-check2-circle"></i> Confirm Registration</button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
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