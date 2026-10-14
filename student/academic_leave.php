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
$message = "";

// 1. Fetch Student ID
$stmt = $pdo->prepare("SELECT student_id, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();
$s_id = $student['student_id'];

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $reason     = $_POST['reason'];

    if (!empty($leave_type) && !empty($start_date) && !empty($end_date)) {
        $sql = "INSERT INTO academic_leaves (student_id, leave_type, start_date, end_date, reason, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$s_id, $leave_type, $start_date, $end_date, $reason])) {
            $message = "<div class='alert alert-success border-0 shadow-sm'>Application submitted successfully. Please wait for Dean's approval.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Something went wrong. Please try again.</div>";
        }
    }
}

// 3. Fetch Previous Leave Requests
$stmt = $pdo->prepare("SELECT * FROM academic_leaves WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$s_id]);
$leave_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Leave | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .main-content { padding: 40px; }
        .card { border: none; border-radius: 12px; }
        .status-badge { font-size: 0.75rem; padding: 5px 12px; border-radius: 20px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="fw-bold text-dark">Apply for Academic Leave</h3>
            <p class="text-muted">Formal request for temporary suspension of studies.</p>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="row g-4">
        <!-- Application Form -->
        <div class="col-lg-5">
            <div class="card shadow-sm p-4 h-100">
                <h5 class="fw-bold mb-4">New Request</h5>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Reason for Leave</label>
                        <select name="leave_type" class="form-select" required>
                            <option value="">Select Category...</option>
                            <option value="Medical">Medical Grounds</option>
                            <option value="Financial">Financial Constraints</option>
                            <option value="Personal">Personal/Compassionate</option>
                            <option value="Work">Work Commitment</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Start Date</label>
                            <input type="date" name="start_date" class="form-select" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">End Date</label>
                            <input type="date" name="end_date" class="form-select" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Detailed Explanation</label>
                        <textarea name="reason" class="form-control" rows="4" placeholder="Briefly explain your situation..." required></textarea>
                    </div>

                    <button type="submit" name="apply_leave" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="bi bi-send me-2"></i> Submit Application
                    </button>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="col-lg-7">
            <div class="card shadow-sm p-4 h-100">
                <h5 class="fw-bold mb-4">Application History</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="bg-light">
                            <tr class="small text-muted fw-bold">
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($leave_history)): ?>
                                <?php foreach ($leave_history as $leave): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($leave['leave_type']); ?></div>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block">From: <?php echo $leave['start_date']; ?></small>
                                        <small class="text-muted d-block">To: <?php echo $leave['end_date']; ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($leave['status']); ?>">
                                            <?php echo $leave['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($leave['created_at'])); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x d-block fs-2"></i>
                                        No previous leave requests found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>