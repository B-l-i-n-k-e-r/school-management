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

// Fee balance check — uses actual column name `amount_total`
$fee_stmt = $conn->prepare("SELECT (amount_total - amount_paid) AS balance FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fee = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

$balance = (float) ($fee['balance'] ?? 0);
$is_blocked = ($balance > 0);

// Fetch results
$results = [];
if (!$is_blocked) {
    $r_stmt = $conn->prepare("
        SELECT r.marks, r.grade, r.remarks,
               s.subject_name,
               e.exam_name,
               e.term,
               e.academic_year
        FROM results r
        LEFT JOIN subjects s ON s.subject_id = r.subject_id
        LEFT JOIN exams e    ON e.exam_id    = r.exam_id
        WHERE r.student_id = ?
        ORDER BY s.subject_name ASC
    ");
    if ($r_stmt) {
        $r_stmt->bind_param("i", $student_id);
        $r_stmt->execute();
        $r_res = $r_stmt->get_result();
        while ($row = $r_res->fetch_assoc()) $results[] = $row;
        $r_stmt->close();
    }
}

// Summary
$subject_count = count($results);
$total_marks   = 0;
foreach ($results as $r) $total_marks += (float) $r['marks'];
$average = $subject_count > 0 ? round($total_marks / $subject_count, 2) : 0;

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'results';
$extra_label = 'Exam Results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results | <?php echo htmlspecialchars($student_display_name); ?></title>
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
        /* Decorative circle */
        .summary-box::after {
            content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }

        .summary-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; }

        /* Modern Stat Cards */
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.success { background: #ecfdf5; color: #10b981; }
        .stat-icon.danger { background: #fef2f2; color: #ef4444; }

        /* Table Styles */
        .table th { font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; font-weight: 800; padding: 20px 15px; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 20px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:hover { background-color: #f8fafc; }

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
            .summary-box { background: #fff !important; color: #000 !important; border: 2px solid #1a3066 !important; }
            .summary-box::after { display: none; }
            .table td, .table th { border-color: #ddd !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3 no-print">
        <div>
            <h2 class="fw-bold mb-1 text-dark">Academic Performance</h2>
            <p class="text-muted small mb-0">Official results slip for <strong><?php echo htmlspecialchars($student_display_name); ?></strong></p>
        </div>
        <?php if (!$is_blocked && !empty($results)): ?>
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-2"></i>Print Transcript
            </button>
        <?php endif; ?>
    </div>

    <?php if ($is_blocked): ?>

        <!-- Blocked State -->
        <div class="card p-5 text-center bg-white">
            <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-4" style="width: 100px; height: 100px;">
                <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Results Withheld</h3>
            <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
                Your results for this semester have been withheld due to an outstanding fee balance of
                <strong class="text-danger fs-5">KES <?php echo number_format($balance, 2); ?></strong>.
                Please clear your dues to view your grades.
            </p>
            <a href="fees.php" class="btn btn-primary rounded-pill px-4 mx-auto fw-bold shadow-sm" style="width: fit-content;">
                <i class="bi bi-wallet2 me-2"></i>View Fee Balance
            </a>
        </div>

    <?php else: ?>

        <!-- Modern Summary Row -->
        <div class="row g-4 mb-4">
            <!-- Average Score (Hero) -->
            <div class="col-md-4">
                <div class="summary-box h-100 d-flex flex-column justify-content-center">
                    <div class="summary-label">Average Score</div>
                    <div class="display-4 fw-bold mb-0 mt-1"><?php echo number_format($average, 1); ?>%</div>
                </div>
            </div>

            <!-- Subjects Taken -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                    <div>
                        <div class="summary-label text-muted">Subjects Taken</div>
                        <div class="display-6 fw-bold mb-0 text-dark"><?php echo $subject_count; ?></div>
                    </div>
                </div>
            </div>

            <!-- Overall Status -->
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon <?php echo $average >= 50 ? 'success' : 'danger'; ?>">
                        <i class="bi <?php echo $average >= 50 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                    </div>
                    <div>
                        <div class="summary-label text-muted">Overall Status</div>
                        <div class="display-6 fw-bold mb-0 <?php echo $average >= 50 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $average >= 50 ? 'Pass' : 'Review'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="card bg-white">
            <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text-fill me-2 text-primary"></i>Results Slip</h5>
            </div>

            <?php if (empty($results)): ?>
                <div class="p-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-journal-x fs-1 text-muted"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No results published</h5>
                    <p class="text-muted mb-0">Once your teachers publish marks, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Subject</th>
                                <th class="text-center">Exam</th>
                                <th class="text-center">Marks</th>
                                <th class="text-center">Grade</th>
                                <th class="pe-4">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r):
                                $marks = (float) ($r['marks'] ?? 0);
                                $grade = $r['grade'] ?: '—';
                                $grade_class = match(true) {
                                    $marks >= 70 => 'bg-success-subtle text-success border border-success-subtle',
                                    $marks >= 60 => 'bg-info-subtle text-info border border-info-subtle',
                                    $marks >= 50 => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    $marks >= 40 => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    $marks > 0   => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default      => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                };
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($r['subject_name'] ?? '—'); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold small text-dark"><?php echo htmlspecialchars($r['exam_name'] ?? '—'); ?></span>
                                        <?php if (!empty($r['term'])): ?>
                                            <br><span class="badge bg-light text-secondary border mt-1"><?php echo htmlspecialchars($r['term']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="fs-5 fw-bold text-dark"><?php echo number_format($marks, 1); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?php echo $grade_class; ?> px-3 py-2 fs-6">
                                            <?php echo htmlspecialchars($grade); ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 small text-muted fw-medium"><?php echo htmlspecialchars($r['remarks'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer Note -->
        <div class="mt-4 p-4 bg-white rounded-4 shadow-sm border-start border-4 border-primary no-print">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                <p class="mb-0 text-muted fw-medium">
                    <strong>Grading Key:</strong> A (70-100), B (60-69), C (50-59), D (40-49), F (0-39).
                    Contact the Registrar's office for any discrepancies.
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