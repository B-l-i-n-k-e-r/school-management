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
$message = '';
$message_type = '';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_subjects'])) {
    // Double-check they haven't already registered
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

// Fetch available subjects ONLY for the student's form
$available_subjects = [];
$sub_stmt = $conn->prepare("SELECT subject_id, subject_name, form, term FROM subjects WHERE form = ? ORDER BY subject_name ASC");
$sub_stmt->bind_param("s", $student_form);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while ($row = $sub_res->fetch_assoc()) $available_subjects[] = $row;
$sub_stmt->close();

// Fetch already registered IDs
$registered_ids = [];
$reg_stmt = $conn->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
$reg_stmt->bind_param("i", $student_id);
$reg_stmt->execute();
$reg_res = $reg_stmt->get_result();
while ($row = $reg_res->fetch_assoc()) $registered_ids[] = (int) $row['subject_id'];
$reg_stmt->close();

// Check if the student has completed registration
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
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-blue: #1a3066; --sidebar-width: 240px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
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

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .subject-row:hover { background-color: #f8fafc; }
        .form-check-input:checked { background-color: #1a3066; border-color: #1a3066; }
        .form-check-input { cursor: pointer; }

        /* Locked State Styling */
        .locked-banner { background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .locked-banner i { font-size: 1.5rem; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Subject Registration</h2>
            <p class="text-muted small mb-0">
                <?php echo $is_locked ? 'Your registered subjects for this semester' : 'Select the subjects you\'ll take this semester'; ?>
            </p>
        </div>
        <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fw-bold">
            <?php echo htmlspecialchars($student_display_name); ?>
            <?php if (!empty($student['form'])): ?>
                &middot; <?php echo htmlspecialchars($student['form']); ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 d-flex align-items-center">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'info-circle-fill'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
        <div class="locked-banner">
            <i class="bi bi-lock-fill"></i>
            <div>
                <strong>Registration Complete</strong><br>
                <span class="small">Your subjects have been locked. Please contact the administration if you need to make changes.</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <?php if (empty($available_subjects)): ?>
            <div class="text-center py-5">
                <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No subjects available</h5>
                <p class="text-muted mb-0">There are no subjects published for <strong><?php echo htmlspecialchars($student_form); ?></strong> yet.</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="small text-muted fw-bold text-uppercase">
                                <?php if (!$is_locked): ?>
                                    <th style="width: 60px;" class="text-center">Select</th>
                                <?php endif; ?>
                                <th>Subject</th>
                                <th>Form</th>
                                <th>Term</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_subjects as $sub):
                                $sid = (int) $sub['subject_id'];
                                $is_registered = in_array($sid, $registered_ids);
                                
                                // If locked, only show the subjects they actually registered
                                if ($is_locked && !$is_registered) continue;
                            ?>
                                <tr class="subject-row">
                                    <?php if (!$is_locked): ?>
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox"
                                                   name="subjects[]"
                                                   value="<?php echo $sid; ?>"
                                                   id="sub_<?php echo $sid; ?>">
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!$is_locked): ?>
                                            <label for="sub_<?php echo $sid; ?>" class="fw-bold mb-0" style="cursor:pointer;">
                                                <?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?>
                                            </label>
                                        <?php else: ?>
                                            <span class="fw-bold mb-0 text-dark">
                                                <i class="bi bi-book text-primary me-2"></i><?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($sub['form'] ?? '—'); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($sub['term'] ?? '—'); ?></td>
                                    <td class="text-center">
                                        <?php if ($is_registered): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                                <i class="bi bi-check2 me-1"></i>Registered
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-light text-muted px-3 py-2">Not registered</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$is_locked): ?>
                    <div class="mt-4 d-flex justify-content-end gap-2 flex-wrap">
                        <button type="reset" class="btn btn-light border rounded-3 px-4">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </button>
                        <button type="submit" name="register_subjects"
                                class="btn btn-primary rounded-3 px-4 fw-bold">
                            <i class="bi bi-check2-circle me-2"></i>Confirm Registration
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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