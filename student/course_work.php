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

// Fetch registered subjects + coursework scores
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

        /* Sidebar Styles */
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

        /* --- MODERN UI STYLES --- */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
        
        /* Modern Hero Card */
        .summary-box { 
            background: linear-gradient(135deg, #1a3066 0%, #2d4a8a 100%); 
            color: white; 
            border-radius: 20px; 
            padding: 30px; 
            position: relative; 
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(26, 48, 102, 0.2);
        }
        /* Decorative circle for the hero card */
        .summary-box::after {
            content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }

        .summary-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; }

        /* Modern Stat Cards */
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.yellow { background: #fffbeb; color: #f59e0b; }

        /* Table Styles */
        .table th { font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; font-weight: 800; padding: 20px 15px; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 20px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:hover { background-color: #f8fafc; }

        /* Score Pills */
        .score-pill { background: #f1f5f9; color: #334155; font-weight: 700; border-radius: 12px; padding: 6px 14px; font-size: 0.95rem; border: 1px solid #e2e8f0; display: inline-block; min-width: 45px; }
        .total-pill { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: 800; border-radius: 12px; padding: 8px 16px; font-size: 0.95rem; display: inline-block; }

        /* Status Icons */
        .status-badge { font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-on-track { color: #10b981; }
        .status-average { color: #f59e0b; }
        .status-low { color: #ef4444; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
        }
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card, .summary-box, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3 no-print">
        <div>
            <h2 class="fw-bold mb-1 text-dark">Course Work Assessment</h2>
            <p class="text-muted small mb-0">Continuous Assessment Tests (CATs) and assignment results</p>
        </div>
        <?php if (!empty($coursework_records)): ?>
            <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer me-2"></i>Print Report
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($coursework_records)): ?>
        <!-- Modern Summary Cards -->
        <div class="row g-4 mb-4">
            <!-- Average Score (Hero) -->
            <div class="col-md-4">
                <div class="summary-box h-100 d-flex flex-column justify-content-center">
                    <div class="summary-label">Average Score</div>
                    <div class="display-4 fw-bold mb-0 mt-1"><?php echo $avg_percent; ?>%</div>
                    <div class="small opacity-75 mt-2">Total <?php echo number_format($grand_total, 1); ?> / <?php echo $max_possible; ?></div>
                </div>
            </div>
            
            <!-- Subjects Registered -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                    <div>
                        <div class="summary-label text-muted">Subjects Registered</div>
                        <div class="display-6 fw-bold mb-0 text-dark"><?php echo $subject_count; ?></div>
                    </div>
                </div>
            </div>

            <!-- Scores Recorded -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="bi bi-clipboard-check-fill"></i>
                    </div>
                    <div>
                        <div class="summary-label text-muted">Scores Recorded</div>
                        <div class="display-6 fw-bold mb-0 <?php echo $completed === $subject_count ? 'text-success' : 'text-warning'; ?>">
                            <?php echo $completed; ?> <span class="fs-5 text-muted">/ <?php echo $subject_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Coursework Table -->
    <div class="card bg-white">
        <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-task me-2 text-primary"></i>Marks Breakdown</h5>
        </div>

        <?php if (empty($coursework_records)): ?>
            <div class="p-5 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-folder2-open fs-1 text-muted"></i>
                </div>
                <h5 class="fw-bold text-dark">No subjects registered</h5>
                <p class="text-muted mb-4">You need to register for subjects to view your coursework marks.</p>
                <a href="registration.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-journal-check me-2"></i>Register Now
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Subject</th>
                            <th class="text-center">CAT 1 <span class="text-muted fw-normal">(15)</span></th>
                            <th class="text-center">CAT 2 <span class="text-muted fw-normal">(15)</span></th>
                            <th class="text-center">Assign <span class="text-muted fw-normal">(10)</span></th>
                            <th class="text-center">Total <span class="text-muted fw-normal">(40)</span></th>
                            <th class="pe-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coursework_records as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    <?php if ($row['academic_period']): ?>
                                        <span class="badge bg-light text-secondary border mt-1"><?php echo htmlspecialchars($row['academic_period']); ?></span>
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
                                    <span class="total-pill">
                                        <?php echo number_format($row['total'], 1); ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <?php if ($row['cat_1'] === null): ?>
                                        <span class="text-muted small fw-bold">Pending</span>
                                    <?php elseif ($row['total'] >= 24): ?>
                                        <span class="status-badge status-on-track"><i class="bi bi-check-circle-fill"></i> On Track</span>
                                    <?php elseif ($row['total'] >= 16): ?>
                                        <span class="status-badge status-average"><i class="bi bi-dash-circle-fill"></i> Average</span>
                                    <?php else: ?>
                                        <span class="status-badge status-low"><i class="bi bi-exclamation-triangle-fill"></i> Low</span>
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
        <div class="mt-4 p-4 bg-white rounded-4 shadow-sm border-start border-4 border-primary no-print">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                <p class="mb-0 text-muted fw-medium">
                    <strong>Note:</strong> Coursework contributes <strong>40%</strong> to your final grade.
                    CAT 1 + CAT 2 + Assignment = up to 40 marks.
                </p>
            </div>
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