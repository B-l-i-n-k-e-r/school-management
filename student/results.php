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

        .summary-box { background: linear-gradient(135deg, #1a3066, #2c3a4d); color: white; border-radius: 16px; padding: 24px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .summary-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.85; }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

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
            <h2 class="fw-bold mb-1">Academic Performance</h2>
            <p class="text-muted small mb-0">Official results slip for <strong><?php echo htmlspecialchars($student_display_name); ?></strong></p>
        </div>
        <?php if (!$is_blocked && !empty($results)): ?>
            <button onclick="window.print()" class="btn btn-primary rounded-3 px-4 shadow-sm">
                <i class="bi bi-printer me-2"></i>Print Transcript
            </button>
        <?php endif; ?>
    </div>

    <?php if ($is_blocked): ?>

        <div class="card p-5 text-center">
            <div class="mb-3">
                <i class="bi bi-shield-lock-fill text-danger" style="font-size: 3.5rem;"></i>
            </div>
            <h3 class="fw-bold mb-2">Results Withheld</h3>
            <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
                Your results for this semester have been withheld due to an outstanding fee balance of
                <strong class="text-danger">KES <?php echo number_format($balance, 2); ?></strong>.
                Please clear your dues to view your grades.
            </p>
            <a href="fees.php" class="btn btn-primary rounded-3 px-4 mx-auto" style="width: fit-content;">
                <i class="bi bi-wallet2 me-2"></i>View Fee Balance
            </a>
        </div>

    <?php else: ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-box">
                    <div class="summary-label">Average Score</div>
                    <div class="display-5 fw-bold mb-0 mt-2"><?php echo number_format($average, 1); ?>%</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="summary-label text-muted">Subjects Taken</div>
                    <div class="display-5 fw-bold mb-0 mt-2 text-dark"><?php echo $subject_count; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="summary-label text-muted">Overall Status</div>
                    <div class="display-5 fw-bold mb-0 mt-2 <?php echo $average >= 50 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $average >= 50 ? 'Pass' : 'Review'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-0">
                <h5 class="mb-0 fw-bold">Results Slip</h5>
            </div>

            <?php if (empty($results)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                    <p class="text-muted mb-0">No results published yet.</p>
                    <small class="text-muted">Once your teachers publish marks, they'll appear here.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="small text-muted fw-bold text-uppercase">
                                <th class="ps-4 py-3">Subject</th>
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
                                    $marks >= 70 => 'bg-success-subtle text-success',
                                    $marks >= 60 => 'bg-info-subtle text-info',
                                    $marks >= 50 => 'bg-primary-subtle text-primary',
                                    $marks >= 40 => 'bg-warning-subtle text-warning',
                                    $marks > 0   => 'bg-danger-subtle text-danger',
                                    default      => 'bg-secondary-subtle text-secondary',
                                };
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?php echo htmlspecialchars($r['subject_name'] ?? '—'); ?></div>
                                    </td>
                                    <td class="text-center small text-muted">
                                        <?php echo htmlspecialchars($r['exam_name'] ?? '—'); ?>
                                        <?php if (!empty($r['term'])): ?>
                                            <br><span class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($r['term']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo number_format($marks, 1); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?php echo $grade_class; ?> px-3 py-2">
                                            <?php echo htmlspecialchars($grade); ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 small text-muted"><?php echo htmlspecialchars($r['remarks'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 p-3 bg-white rounded-3 shadow-sm border-start border-4 border-primary no-print">
            <p class="mb-0 small text-muted">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                <strong>Grading Key:</strong> A (70-100), B (60-69), C (50-59), D (40-49), F (0-39).
                Contact the Registrar's office for any discrepancies.
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