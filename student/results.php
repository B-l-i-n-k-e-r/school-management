<?php
session_start();

// 1. Role and Session Validation
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../dashboard.php");
    exit();
}

if (!isset($_SESSION['adm_no'])) {
    die("Session error: Admission number not found.");
}

include('../database/db_connect.php');

// Database Configuration (Refining to use the existing $pdo if available in db_connect, 
// but keeping your local setup for consistency)
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

// 2. Fetch Student ID
$stmt = $pdo->prepare("SELECT student_id, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}

$s_id = $student['student_id'];

// 3. Security Check: Check Fee Balance
$stmt = $pdo->prepare("SELECT (total_fees - amount_paid) as balance FROM fees WHERE student_id = ?");
$stmt->execute([$s_id]);
$fee = $stmt->fetch();
$is_blocked = ($fee && $fee['balance'] > 0);

// 4. Fetch Results
$query = "
    SELECT 
        s.subject_name, 
        subject_code,
        er.coursework_score,
        er.exam_score,
        er.total,
        er.grade,
        er.remarks
    FROM results er
    JOIN subjects s ON er.subject_id = id
    WHERE er.student_id = ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$s_id]);
$results = $stmt->fetchAll();

// 5. Calculate Summary Stats
$total_points = 0;
$subject_count = count($results);
foreach ($results as $res) {
    $total_points += $res['total'];
}
$average = $subject_count > 0 ? round($total_points / $subject_count, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-dark: #1a3066; --accent-gold: #f6e05e; }
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .main-content { padding: 40px; }
        .result-card { border: none; border-radius: 15px; overflow: hidden; }
        
        /* 
           Constraint Implementation: 
           Ensuring columns fit content regardless of window size 
        */
        .table { width: 100%; table-layout: auto; }
        .table th, .table td { white-space: nowrap; width: 1%; }
        /* Allow the course title column to take up remaining space if needed */
        .table td.course-title { white-space: normal; width: auto; }

        .summary-box {
            background: var(--primary-dark);
            color: white;
            border-radius: 12px;
            padding: 20px;
        }

        .bg-soft-success { background-color: #d1e7dd; }
        .bg-soft-danger { background-color: #f8d7da; }

        @media print {
            .no-print { display: none; }
            .main-content { padding: 0; }
            .result-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row mb-4 no-print">
        <div class="col-md-6">
            <a href="dashboard_view.php" class="btn btn-link text-decoration-none p-0 text-muted">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <h3 class="fw-bold mt-2">Academic Performance</h3>
        </div>
        <div class="col-md-6 text-end align-self-end">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i> Print Transcript
            </button>
        </div>
    </div>

    <?php if ($is_blocked): ?>
        <div class="card border-0 shadow-sm p-5 text-center rounded-4">
            <div class="mb-4">
                <i class="bi bi-shield-lock text-danger" style="font-size: 4rem;"></i>
            </div>
            <h2 class="fw-bold">Results Withheld</h2>
            <p class="text-muted mx-auto" style="max-width: 500px;">
                Your official results for this semester have been withheld due to an outstanding fee balance of 
                <span class="text-danger fw-bold">Ksh. <?php echo number_format($fee['balance'], 2); ?></span>. 
                Please clear your dues to view your grades.
            </p>
            <div class="mt-4">
                <a href="fees.php" class="btn btn-dark px-4">Pay via M-Pesa</a>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-box shadow-sm">
                    <small class="opacity-75 text-uppercase fw-bold">Average Score</small>
                    <h2 class="mb-0"><?php echo htmlspecialchars($average); ?>%</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <small class="text-muted text-uppercase fw-bold">Units Completed</small>
                    <h2 class="mb-0"><?php echo htmlspecialchars($subject_count); ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <small class="text-muted text-uppercase fw-bold">Academic Status</small>
                    <h2 class="mb-0 text-success">Pass</h2>
                </div>
            </div>
        </div>

        <div class="card result-card shadow-sm bg-white">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Official Results Slip - <?php echo htmlspecialchars($student['full_name']); ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted fw-bold">
                            <th class="ps-4 course-title">COURSE CODE & TITLE</th>
                            <th class="text-center">CW (40)</th>
                            <th class="text-center">EXAM (60)</th>
                            <th class="text-center">TOTAL (100)</th>
                            <th class="text-center">GRADE</th>
                            <th class="pe-4">REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $res): ?>
                            <tr>
                                <td class="ps-4 course-title">
                                    <span class="d-block fw-bold"><?php echo htmlspecialchars($res['subject_code']); ?></span>
                                    <span class="small text-muted"><?php echo htmlspecialchars($res['subject_name']); ?></span>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($res['coursework_score']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($res['exam_score']); ?></td>
                                <td class="text-center fw-bold"><?php echo htmlspecialchars($res['total']); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo ($res['total'] >= 40) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?> fs-6">
                                        <?php echo htmlspecialchars($res['grade']); ?>
                                    </span>
                                </td>
                                <td class="pe-4 small fst-italic"><?php echo htmlspecialchars($res['remarks']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No results published yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 p-3 bg-white rounded-3 shadow-sm border-start border-4 border-primary no-print">
            <p class="mb-0 small text-muted">
                <i class="bi bi-info-circle-fill me-2 text-primary"></i>
                <strong>Grading Key:</strong> A (70-100), B (60-69), C (50-59), D (40-49), F (0-39).
                For any discrepancies, please contact the Registrar's office.
            </p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>