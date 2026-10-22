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

// Fetch registered subjects + coursework scores (LEFT JOIN so subjects without scores still appear)
$coursework_records = [];
$cw_stmt = $conn->prepare("
    SELECT
        s.subject_id,
        s.subject_name,
        s.form,
        s.term,
        c.cat_1,
        c.cat_2,
        c.assignment
    FROM student_subjects ss
    INNER JOIN subjects s ON s.subject_id = ss.subject_id
    LEFT JOIN coursework c
        ON c.subject_id = ss.subject_id AND c.student_id = ss.student_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_name ASC
");
if ($cw_stmt) {
    $cw_stmt->bind_param("i", $student_id);
    $cw_stmt->execute();
    $res = $cw_stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $cat1 = $row['cat_1']      !== null ? (float) $row['cat_1']      : null;
        $cat2 = $row['cat_2']      !== null ? (float) $row['cat_2']      : null;
        $asg  = $row['assignment'] !== null ? (float) $row['assignment'] : null;
        $total = ($cat1 ?? 0) + ($cat2 ?? 0) + ($asg ?? 0);

        // Build academic period label
        $period = '';
        if (!empty($row['form'])) {
            $period = $row['form'];
            if (!empty($row['term'])) $period .= ' · ' . $row['term'];
        }

        $coursework_records[] = [
            'subject_name'    => $row['subject_name'] ?? '',
            'academic_period' => $period,
            'cat_1'           => $cat1,
            'cat_2'           => $cat2,
            'assignment'      => $asg,
            'total'           => $total,
        ];
    }
    $cw_stmt->close();
}

// Summary stats
$subject_count = count($coursework_records);
$grand_total   = 0;
$max_possible  = $subject_count * 40;
$completed     = 0;
foreach ($coursework_records as $r) {
    $grand_total += $r['total'];
    if ($r['cat_1'] !== null) $completed++;
}
$avg_percent = $max_possible > 0 ? round(($grand_total / $max_possible) * 100, 1) : 0;

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'course_work';
$extra_label = 'Course Work';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Work | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
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

        .summary-box { background: linear-gradient(135deg, #1a3066, #2c3a4d); color: white; border-radius: 16px; padding: 24px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .summary-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.85; }

        .score-pill { background: #e0e7ff; color: #3730a3; font-weight: 700; border-radius: 8px; padding: 4px 12px; font-size: 0.9rem; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 20px !important; }
            .card, .summary-box { box-shadow: none !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3 no-print">
        <div>
            <h2 class="fw-bold mb-1">Course Work Assessment</h2>
            <p class="text-muted small mb-0">Continuous Assessment Tests (CATs) and assignment results</p>
        </div>
        <?php if (!empty($coursework_records)): ?>
            <button onclick="window.print()" class="btn btn-outline-primary rounded-3 px-4">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($coursework_records)): ?>
        <!-- Summary cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-box">
                    <div class="summary-label">Average Score</div>
                    <div class="display-5 fw-bold mb-0 mt-2"><?php echo $avg_percent; ?>%</div>
                    <div class="small opacity-75 mt-1">Total <?php echo number_format($grand_total, 1); ?> / <?php echo $max_possible; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="summary-label text-muted">Subjects Registered</div>
                    <div class="display-5 fw-bold mb-0 mt-2 text-dark"><?php echo $subject_count; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="summary-label text-muted">Scores Recorded</div>
                    <div class="display-5 fw-bold mb-0 mt-2 <?php echo $completed === $subject_count ? 'text-success' : 'text-warning'; ?>">
                        <?php echo $completed; ?> / <?php echo $subject_count; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Coursework table -->
    <div class="card overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="mb-0 fw-bold">Marks Breakdown</h5>
        </div>

        <?php if (empty($coursework_records)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-folder2-open fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted mb-3">No subjects registered for this semester.</p>
                <a href="registration.php" class="btn btn-primary rounded-3 px-4">
                    <i class="bi bi-journal-check me-1"></i>Register Now
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted fw-bold text-uppercase">
                            <th class="ps-4 py-3">Subject</th>
                            <th class="text-center">CAT 1 <span class="text-muted">(15)</span></th>
                            <th class="text-center">CAT 2 <span class="text-muted">(15)</span></th>
                            <th class="text-center">Assignment <span class="text-muted">(10)</span></th>
                            <th class="text-center">Total <span class="text-muted">(40)</span></th>
                            <th class="pe-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coursework_records as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    <?php if ($row['academic_period']): ?>
                                        <div class="small text-muted text-uppercase"><?php echo htmlspecialchars($row['academic_period']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['cat_1'] !== null): ?>
                                        <span class="score-pill"><?php echo number_format($row['cat_1'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['cat_2'] !== null): ?>
                                        <span class="score-pill"><?php echo number_format($row['cat_2'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['assignment'] !== null): ?>
                                        <span class="score-pill"><?php echo number_format($row['assignment'], 1); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary-emphasis fw-bold fs-6 px-3 py-2">
                                        <?php echo number_format($row['total'], 1); ?> / 40
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <?php if ($row['cat_1'] === null): ?>
                                        <span class="text-muted small">Pending</span>
                                    <?php elseif ($row['total'] >= 24): ?>
                                        <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i>On Track</span>
                                    <?php elseif ($row['total'] >= 16): ?>
                                        <span class="text-warning small fw-bold"><i class="bi bi-dash-circle-fill me-1"></i>Average</span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($coursework_records)): ?>
        <div class="mt-4 p-3 bg-white rounded-3 shadow-sm border-start border-4 border-primary no-print">
            <p class="mb-0 small text-muted">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                <strong>Note:</strong> Coursework contributes <strong>40%</strong> to your final grade.
                CAT 1 + CAT 2 + Assignment = up to 40 marks.
            </p>
        </div>
    <?php endif; ?>

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