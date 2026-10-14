<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../dashboard.php");
    exit();
}

include('../database/db_connect.php');

// Database Configuration
$host = 'localhost';
$db   = 'school_admin_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

$adm_no = $_SESSION['adm_no'];

// 1. Fetch current logged-in Student's ID
$stmt = $pdo->prepare("SELECT student_id, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();
$s_id = $student['student_id'] ?? 0;

// 2. Fetch Coursework ONLY for subjects registered by this current student
$coursework_records = [];
try {
    // This query explicitly filters student_subjects by the active student_id
    $query = "
        SELECT 
            s.subject_name, 
            s.subject_id,
            CONCAT('Form ', s.form, ' - T', s.term) AS academic_period
        FROM student_subjects ss
        JOIN subjects s ON ss.subject_id = s.subject_id
        WHERE ss.student_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$s_id]);
    $subjects = $stmt->fetchAll();

    foreach ($subjects as $sub) {
        $sub_id = $sub['subject_id'];
        
        $cat_1 = '-';
        $cat_2 = '-';
        $assignment = '-';
        $total = 0;

        try {
            // Fetch scores from coursework table matching this specific student and subject
            $cw_stmt = $pdo->prepare("SELECT * FROM coursework WHERE subject_id = ? AND student_id = ?");
            $cw_stmt->execute([$sub_id, $s_id]);
            $cw_data = $cw_stmt->fetch();

            if ($cw_data) {
                $cat_1 = $cw_data['cat_1'] ?? '-';
                $cat_2 = $cw_data['cat_2'] ?? '-';
                $assignment = $cw_data['assignment'] ?? '-';
                $total = (int)($cw_data['cat_1'] ?? 0) + (int)($cw_data['cat_2'] ?? 0) + (int)($cw_data['assignment'] ?? 0);
            }
        } catch (\PDOException $ex) {
            // Fallback stays active if coursework columns are unpopulated
        }

        $coursework_records[] = [
            'subject_name' => $sub['subject_name'],
            'academic_period' => $sub['academic_period'],
            'cat_1' => $cat_1,
            'cat_2' => $cat_2,
            'assignment' => $assignment,
            'total' => $total
        ];
    }

} catch (\PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Work | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-bg: #34445c;
            --accent-blue: #3182ce;
        }
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; margin: 0; }
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 100; }
        .main-content { margin-left: 240px; padding: 40px; min-height: 100vh; box-sizing: border-box; }
        
        .stats-card {
            border: none;
            border-left: 5px solid var(--accent-blue);
            border-radius: 8px;
            transition: transform 0.2s;
        }
        
        .grade-badge {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
        }

        /* Enforce exact column content-fitting sizing rules */
        table.table { width: 100%; table-layout: auto; }
        table.table th, table.table td { white-space: nowrap; width: 1%; }
        table.table th:first-child, table.table td:first-child { width: auto; white-space: normal; }

        @media (max-width: 992px) {
            .sidebar { display: none !important; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar d-none d-lg-block">
    <div class="p-3 text-center">
        <h6 class="text-uppercase fw-bold opacity-75">Student Portal</h6>
    </div>
    <div class="list-group list-group-flush mt-4">
        <a href="dashboard_view.php" class="list-group-item list-group-item-action bg-transparent text-white border-0"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="registration.php" class="list-group-item list-group-item-action bg-transparent text-white border-0"><i class="bi bi-journal-check me-2"></i> Registration</a>
        <a href="course_work.php" class="list-group-item list-group-item-action bg-transparent text-warning border-0"><i class="bi bi-pencil-square me-2"></i> Course Work</a>
        <a href="fees.php" class="list-group-item list-group-item-action bg-transparent text-white border-0"><i class="bi bi-credit-card me-2"></i> Finance</a>
    </div>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-8">
                <h3 class="fw-bold text-dark">Course Work Assessment</h3>
                <p class="text-muted">Continuous Assessment Tests (CATs) and Assignment results</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Report
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small fw-bold">
                            <th class="ps-4 py-3">SUBJECT DETAILS</th>
                            <th class="text-center">CAT 1 (15%)</th>
                            <th class="text-center">CAT 2 (15%)</th>
                            <th class="text-center">ASSIGNMENT (10%)</th>
                            <th class="text-center">TOTAL (40%)</th>
                            <th class="pe-4 text-center">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($coursework_records)): ?>
                            <?php foreach ($coursework_records as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></div>
                                    <div class="small text-muted text-uppercase"><?php echo htmlspecialchars($row['academic_period'] ?? ''); ?></div>
                                </td>
                                <td class="text-center fw-medium"><?php echo htmlspecialchars($row['cat_1']); ?></td>
                                <td class="text-center fw-medium"><?php echo htmlspecialchars($row['cat_2']); ?></td>
                                <td class="text-center fw-medium"><?php echo htmlspecialchars($row['assignment']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary-emphasis fw-bold fs-6">
                                        <?php echo htmlspecialchars($row['total']); ?> / 40
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <?php if ($row['total'] >= 20): ?>
                                        <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i> On Track</span>
                                    <?php elseif ($row['total'] == 0 && $row['cat_1'] === '-'): ?>
                                        <span class="text-muted small">Pending</span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Low Marks</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-folder2-open display-4 text-muted opacity-50"></i>
                                    <p class="text-muted mt-2">No subjects registered for this semester.</p>
                                    <a href="registration.php" class="btn btn-primary btn-sm">Register Now</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card stats-card p-3 shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="grade-badge bg-primary text-white me-3">i</div>
                        <div>
                            <p class="mb-0 text-muted small">Information</p>
                            <p class="mb-0 fw-bold">Coursework contributes 40% to your final grade.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>