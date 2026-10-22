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

// Fee balance check — uses `amount_total`
$fee_stmt = $conn->prepare("SELECT (amount_total - amount_paid) AS balance FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fee_data = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

// If no fee record → allow (treat as cleared). Change to `999999` if you want it to block.
$balance = $fee_data ? (float) $fee_data['balance'] : 0;
$is_cleared = ($balance <= 0);

// Fetch registered subjects
$subjects = [];
$sub_stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name, s.form, s.term
    FROM student_subjects ss
    INNER JOIN subjects s ON s.subject_id = ss.subject_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_name ASC
");
if ($sub_stmt) {
    $sub_stmt->bind_param("i", $student_id);
    $sub_stmt->execute();
    $res = $sub_stmt->get_result();
    while ($row = $res->fetch_assoc()) $subjects[] = $row;
    $sub_stmt->close();
}

// School branding
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'exam_card';
$extra_label = 'Exam Card';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Card | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        /* Sidebar */
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

        /* Exam card */
        .exam-card {
            background: white;
            border: 2px solid #1a3066;
            border-radius: 12px;
            padding: 40px;
            max-width: 850px;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 10px 30px -10px rgba(15,39,68,0.15);
        }
        .exam-card .watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6rem;
            font-weight: 900;
            color: rgba(26,48,102,0.04);
            letter-spacing: 0.15em;
            pointer-events: none;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .exam-card .header {
            border-bottom: 2px solid #1a3066;
            padding-bottom: 16px;
            margin-bottom: 26px;
            position: relative;
            z-index: 2;
        }
        .exam-card .header h4 {
            color: #1a3066;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin: 0;
        }
        .exam-card .header .meta {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .exam-card .info-label {
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .exam-card .info-value {
            font-weight: 700;
            font-size: 1.05rem;
            color: #0f172a;
            text-transform: uppercase;
        }

        .exam-card .subjects-table thead th {
            background: #1a3066;
            color: white;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 14px;
            border-color: #1a3066;
        }
        .exam-card .subjects-table td {
            padding: 14px;
            vertical-align: middle;
            border-color: #e2e8f0;
            font-size: 0.9rem;
        }
        .exam-card .signature-line {
            border-top: 1px solid #94a3b8;
            width: 180px;
            margin-top: 50px;
        }
        .exam-card .instructions {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            margin-top: 26px;
        }
        .exam-card .instructions ol { padding-left: 1.25rem; margin: 0; }
        .exam-card .instructions li { font-size: 0.82rem; color: #475569; margin-bottom: 4px; }

        /* Blocked / empty states */
        .state-card {
            max-width: 560px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .state-icon {
            width: 84px; height: 84px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            margin-bottom: 20px;
        }
        .state-icon.danger  { background: #fef2f2; color: #dc2626; }
        .state-icon.warning { background: #fffbeb; color: #d97706; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }

        /* Print styles */
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            body { background: white !important; }
            .exam-card { border: 1px solid #1a3066; box-shadow: none; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <?php if (!$is_cleared): ?>

        <!-- Fee blocked state -->
        <div class="state-card">
            <div class="state-icon danger">
                <i class="bi bi-lock-fill"></i>
            </div>
            <h3 class="fw-bold mb-2">Exam Card Blocked</h3>
            <p class="text-muted mb-4">
                Your exam card is unavailable because you have an outstanding fee balance of
                <strong class="text-danger">KES <?php echo number_format($balance, 2); ?></strong>.
                Please clear your dues to access it.
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="fees.php" class="btn btn-primary rounded-3 px-4">
                    <i class="bi bi-wallet2 me-2"></i>Go to Fee Balance
                </a>
                <a href="dashboard_view.php" class="btn btn-light border rounded-3 px-4">
                    Back to Dashboard
                </a>
            </div>
        </div>

    <?php elseif (empty($subjects)): ?>

        <!-- No subjects state -->
        <div class="state-card">
            <div class="state-icon warning">
                <i class="bi bi-journal-x"></i>
            </div>
            <h3 class="fw-bold mb-2">No Registered Subjects</h3>
            <p class="text-muted mb-4">
                You must register for your subjects before an exam card can be generated.
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="registration.php" class="btn btn-warning rounded-3 px-4 fw-bold">
                    <i class="bi bi-journal-check me-2"></i>Register Subjects
                </a>
                <a href="dashboard_view.php" class="btn btn-light border rounded-3 px-4">
                    Back to Dashboard
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- Exam card view -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1">Examination Admission Card</h3>
                <p class="text-muted small mb-0">Present this card at every examination session</p>
            </div>
            <button onclick="window.print()" class="btn btn-dark rounded-3 px-4">
                <i class="bi bi-printer me-2"></i>Print / Save as PDF
            </button>
        </div>

        <div class="exam-card">
            <div class="watermark">OFFICIAL</div>

            <!-- Header -->
            <div class="header d-flex align-items-center gap-3">
                <img src="../images/logo.jpg" alt="Logo" height="64"
                     onerror="this.style.display='none'">
                <div class="flex-grow-1">
                    <h4 class="text-uppercase"><?php echo htmlspecialchars($school_display); ?></h4>
                    <p class="meta mb-0">Office of the Registrar · Academic Affairs</p>
                </div>
                <div class="text-end">
                    <div class="info-label">Exam Card No.</div>
                    <div class="fw-bold" style="color:#1a3066;">
                        EX-<?php echo date('Y') ?>-<?php echo str_pad($student_id, 5, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
            </div>

            <!-- Student info -->
            <div class="row mb-4 position-relative" style="z-index:2;">
                <div class="col-md-6 mb-3">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Admission No.</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="info-label">Class / Form</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['form'] ?? '—'); ?></div>
                </div>
            </div>

            <!-- Subjects table -->
            <div class="position-relative" style="z-index:2;">
                <h6 class="fw-bold text-uppercase mb-0" style="color:#1a3066; font-size: 0.8rem; letter-spacing: 0.05em;">
                    Approved Subjects for Examination
                </h6>
                <table class="table subjects-table mt-2 mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Subject</th>
                            <th style="width: 130px;">Form</th>
                            <th style="width: 160px;">Invigilator Sign</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $i => $sub): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?php echo $i + 1; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($sub['subject_name'] ?? ''); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($sub['form'] ?? '—'); ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Signatures -->
            <div class="row mt-4 position-relative" style="z-index:2;">
                <div class="col-md-6 mb-3">
                    <div class="signature-line"></div>
                    <small class="text-muted">Student Signature</small>
                </div>
                <div class="col-md-6 text-md-end mb-3">
                    <div class="signature-line ms-md-auto"></div>
                    <small class="text-muted">Registrar's Signature / Stamp</small>
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions position-relative" style="z-index:2;">
                <p class="mb-2 small fw-bold text-uppercase" style="color:#1a3066; font-size: 0.7rem; letter-spacing: 0.05em;">
                    Instructions to Candidate
                </p>
                <ol>
                    <li>Present this card at the entrance of the Examination Room.</li>
                    <li>This card is only valid for the registered subjects listed above.</li>
                    <li>Any form of alteration to this card will lead to automatic disqualification.</li>
                    <li>Report to the examination venue at least 15 minutes before the start time.</li>
                </ol>
            </div>
        </div>

        <p class="text-center text-muted small mt-4 no-print">
            <i class="bi bi-info-circle me-1"></i>
            Generated on <?php echo date('d M Y, H:i'); ?>
        </p>

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