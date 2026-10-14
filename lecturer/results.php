<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
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
        $saved = 0;
        foreach ($marks_array as $student_id => $score) {
            if ($score === '' || $score === null) continue;
            $student_id = (int) $student_id;
            $score      = (float) $score;

            // ── NOTE: this uses `exam_type` which doesn't exist in your schema.
            //    See the migration options at the bottom of this message.
            $check = $conn->prepare("SELECT result_id FROM results WHERE student_id = ? AND subject_id = ? LIMIT 1");
            $check->bind_param("ii", $student_id, $subject_id);
            $check->execute();
            $exists = $check->get_result()->num_rows;
            $check->close();

            if ($exists > 0) {
                $update = $conn->prepare("UPDATE results SET marks = ?, recorded_by = ? WHERE student_id = ? AND subject_id = ?");
                $update->bind_param("diii", $score, $staff_id, $student_id, $subject_id);
                $update->execute();
                $update->close();
            } else {
                $insert = $conn->prepare("INSERT INTO results (student_id, subject_id, marks, recorded_by) VALUES (?, ?, ?, ?)");
                $insert->bind_param("iidi", $student_id, $subject_id, $score, $staff_id);
                $insert->execute();
                $insert->close();
            }
            $saved++;
        }
        $success_msg = "$saved marks saved for $assigned_subject_name ($exam_type).";
    }
}

// Fetch students in this teacher's class
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
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
        :root { 
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --sidebar-width: 240px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { 
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); 
            color: white; position: fixed; left: 0; top: 0; overflow-y: auto; 
            z-index: 1200; transition: all 0.3s ease;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; border-radius: 5px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }

        .nav-item-container { border-bottom: 1px solid #4a5568; }
        .nav-link-custom { 
            color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; 
            display: flex; justify-content: space-between; align-items: center; 
            text-decoration: none; transition: 0.3s; cursor: pointer;
        }
        .nav-link-custom:hover, .nav-link-custom.active { background: #4a5568; color: white; }

        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .results-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table th, .table td { white-space: nowrap; padding: 12px 20px; }
        .table th:nth-child(2), .table td:nth-child(2) { white-space: normal; }
        .mark-input { width: 90px; text-align: center; font-weight: 700; border-radius: 8px; border: 1px solid #dee2e6; padding: 6px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
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
    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="results-card p-4">
        <div class="mb-4">
            <h4 class="fw-bold mb-1">
                Subject: <?php echo htmlspecialchars($assigned_subject_name ?: 'Not Assigned'); ?>
            </h4>
            <p class="text-muted small mb-0">
                Enter marks for students in <strong><?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></strong>
            </p>
        </div>

        <?php if (!$assigned_form || !$assigned_subject_name): ?>
            <div class="alert alert-warning rounded-4">
                <i class="bi bi-exclamation-triangle me-2"></i>
                You need both an assigned <strong>class</strong> and <strong>subject</strong> to enter marks.
                Ask your admin to update your profile.
            </div>
        <?php elseif (empty($students)): ?>
            <div class="alert alert-info rounded-4">
                <i class="bi bi-info-circle me-2"></i>
                No students found in <strong><?php echo htmlspecialchars($assigned_form); ?></strong>.
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Exam Type</label>
                        <select name="exam_type" class="form-select border-0 bg-light rounded-3" required>
                            <option value="Opener">Opener Exam</option>
                            <option value="Mid-Term">Mid-Term Exam</option>
                            <option value="End-Term">End-Term Exam</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="text-muted small text-uppercase">
                                <th class="border-0">Adm No</th>
                                <th class="border-0">Student Name</th>
                                <th class="border-0 text-center">Marks (0-100)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): ?>
                            <tr class="border-bottom">
                                <td class="fw-bold"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></td>
                                <td class="fw-500"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
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

                <div class="mt-4 text-center">
                    <button type="submit" name="save_results" class="btn btn-primary px-5 py-2 rounded-pill fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i> Save All Results
                    </button>
                </div>
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