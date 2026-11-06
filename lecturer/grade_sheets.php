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

// Fetch school + teacher info
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name    = $staff_data['full_name']        ?? 'Staff';
$assigned_form         = $staff_data['assigned_form']    ?? '';
$assigned_subject_name = $staff_data['assigned_subject'] ?? '';

// Filters — default to teacher's own class + subject
$form_filter    = $_GET['form']    ?? $assigned_form;
$subject_filter = $_GET['subject'] ?? $assigned_subject_name;

// Fetch all classes
$forms_result = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order");

// Fetch all subjects
$subjects_result = mysqli_query($conn, "SELECT subject_name FROM subjects ORDER BY subject_name ASC");
$subjects_list = [];
while ($s = mysqli_fetch_assoc($subjects_result)) $subjects_list[] = $s['subject_name'];

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

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .mark-input { width: 75px; border-radius: 8px; border: 1px solid #e2e8f0; padding: 5px; text-align: center; font-weight: 600; }
        .grade-badge { padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; display: inline-block; }

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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Grade Sheets</h3>
            <p class="text-muted small mb-0">Enter and review CAT + exam marks per student</p>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> Marks updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Academic Level</label>
                <select name="form" class="form-select border-0 bg-light" required>
                    <option value="">Choose Form...</option>
                    <?php while ($f = mysqli_fetch_assoc($forms_result)): ?>
                        <option value="<?php echo htmlspecialchars($f['name']); ?>"
                            <?php echo ($form_filter == $f['name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Subject</label>
                <select name="subject" class="form-select border-0 bg-light" required>
                    <option value="">Choose Subject...</option>
                    <?php foreach ($subjects_list as $subj): ?>
                        <option value="<?php echo htmlspecialchars($subj); ?>"
                            <?php echo ($subject_filter == $subj) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subj); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-3 shadow-sm fw-bold">Load Students</button>
            </div>
        </form>
    </div>

    <?php if ($form_filter && $subject_filter && !empty($students)): ?>
    <form action="save_grades.php" method="POST">
        <input type="hidden" name="form" value="<?php echo htmlspecialchars($form_filter); ?>">
        <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($subject_filter); ?>">

        <div class="card shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Adm No</th>
                            <th>Student Name</th>
                            <th class="text-center">CAT 1 (30)</th>
                            <th class="text-center">CAT 2 (30)</th>
                            <th class="text-center">Exam (40)</th>
                            <th class="text-center">Total (100)</th>
                            <th class="text-center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="ps-4 small text-muted"><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span>
                                <input type="hidden" name="student_id[]" value="<?php echo (int)$student['student_id']; ?>">
                            </td>
                            <td class="text-center"><input type="number" name="cat1[]" class="mark-input" max="30" min="0" value="<?php echo (float)($student['cat1'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center"><input type="number" name="cat2[]" class="mark-input" max="30" min="0" value="<?php echo (float)($student['cat2'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center"><input type="number" name="exam[]" class="mark-input" max="40" min="0" value="<?php echo (float)($student['exam'] ?? 0); ?>" oninput="calc(this)"></td>
                            <td class="text-center fw-bold total-val"><?php echo (float)($student['total'] ?? 0); ?></td>
                            <td class="text-center">
                                <?php
                                    $g = $student['grade'] ?? 'F';
                                    $c = in_array($g, ['A','B']) ? 'bg-success' : (in_array($g, ['C','D']) ? 'bg-warning' : 'bg-danger');
                                ?>
                                <span class="grade-badge text-white <?php echo $c; ?>"><?php echo htmlspecialchars($g); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white p-4 text-end">
                <button type="submit" name="save_marks" class="btn btn-primary rounded-pill px-5 shadow fw-bold">
                    <i class="bi bi-save me-2"></i>Save All Grades
                </button>
            </div>
        </div>
    </form>
    <?php elseif ($form_filter && $subject_filter): ?>
        <div class="card p-5 text-center">
            <i class="bi bi-people fs-1 text-muted mb-3"></i>
            <p class="text-muted mb-0">No students found in <strong><?php echo htmlspecialchars($form_filter); ?></strong> for this subject.</p>
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
        let g = 'F', cls = 'bg-danger';
        if (t >= 80)      { g = 'A'; cls = 'bg-success'; }
        else if (t >= 70) { g = 'B'; cls = 'bg-primary'; }
        else if (t >= 60) { g = 'C'; cls = 'bg-info'; }
        else if (t >= 50) { g = 'D'; cls = 'bg-warning'; }
        badge.innerText = g;
        badge.className = `grade-badge text-white ${cls}`;
    }

    // Sidebar toggle
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