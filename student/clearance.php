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

// 2. Handle Clearance Request Initiation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_clearance'])) {
    // Check if already initiated
    $check = $pdo->prepare("SELECT id FROM student_clearance WHERE student_id = ?");
    $check->execute([$s_id]);
    
    if (!$check->fetch()) {
        $sql = "INSERT INTO student_clearance (student_id, finance_status, library_status, sports_status, hostel_status, dept_status, final_status) 
                VALUES (?, 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'In Progress')";
        $pdo->prepare($sql)->execute([$s_id]);
        $message = "<div class='alert alert-success'>Clearance process initiated successfully.</div>";
    }
}

// 3. Fetch Clearance Status
$stmt = $pdo->prepare("SELECT * FROM student_clearance WHERE student_id = ?");
$stmt->execute([$s_id]);
$clearance = $stmt->fetch();

function getStatusClass($status) {
    switch ($status) {
        case 'Cleared': return 'bg-success';
        case 'Pending': return 'bg-warning text-dark';
        case 'Blocked': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Status | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .main-content { padding: 40px; }
        .dept-card { border: none; border-radius: 12px; transition: 0.3s; }
        .dept-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-md-8">
            <h3 class="fw-bold">Final Clearance</h3>
            <p class="text-muted">Track your clearance status across all institutional departments.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if (!$clearance): ?>
                <form method="POST">
                    <button type="submit" name="initiate_clearance" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-play-circle me-2"></i> Start Clearance
                    </button>
                </form>
            <?php elseif ($clearance['final_status'] === 'Completed'): ?>
                <button class="btn btn-success px-4" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i> Print Clearance Certificate
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($clearance): ?>
    <div class="row g-4">
        <!-- Finance -->
        <div class="col-md-4">
            <div class="card dept-card shadow-sm p-4 h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="dept-icon bg-soft-primary text-primary me-3"><i class="bi bi-bank"></i></div>
                    <h6 class="mb-0 fw-bold">Finance Office</h6>
                </div>
                <div class="mt-auto">
                    <span class="badge <?php echo getStatusClass($clearance['finance_status']); ?> w-100 py-2">
                        <?php echo $clearance['finance_status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Library -->
        <div class="col-md-4">
            <div class="card dept-card shadow-sm p-4 h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="dept-icon bg-soft-info text-info me-3"><i class="bi bi-book"></i></div>
                    <h6 class="mb-0 fw-bold">Library</h6>
                </div>
                <div class="mt-auto">
                    <span class="badge <?php echo getStatusClass($clearance['library_status']); ?> w-100 py-2">
                        <?php echo $clearance['library_status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Department -->
        <div class="col-md-4">
            <div class="card dept-card shadow-sm p-4 h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="dept-icon bg-soft-dark text-dark me-3"><i class="bi bi-building"></i></div>
                    <h6 class="mb-0 fw-bold">Academic Dept</h6>
                </div>
                <div class="mt-auto">
                    <span class="badge <?php echo getStatusClass($clearance['dept_status']); ?> w-100 py-2">
                        <?php echo $clearance['dept_status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Hostel -->
        <div class="col-md-4">
            <div class="card dept-card shadow-sm p-4 h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="dept-icon bg-soft-success text-success me-3"><i class="bi bi-house"></i></div>
                    <h6 class="mb-0 fw-bold">Hostels/Housing</h6>
                </div>
                <div class="mt-auto">
                    <span class="badge <?php echo getStatusClass($clearance['hostel_status']); ?> w-100 py-2">
                        <?php echo $clearance['hostel_status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Sports -->
        <div class="col-md-4">
            <div class="card dept-card shadow-sm p-4 h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="dept-icon bg-soft-warning text-warning me-3"><i class="bi bi-trophy"></i></div>
                    <h6 class="mb-0 fw-bold">Sports & Games</h6>
                </div>
                <div class="mt-auto">
                    <span class="badge <?php echo getStatusClass($clearance['sports_status']); ?> w-100 py-2">
                        <?php echo $clearance['sports_status']; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Overall Progress -->
        <div class="col-md-4">
            <div class="card dept-card bg-dark text-white shadow-sm p-4 h-100">
                <h6 class="fw-bold mb-3">Overall Status</h6>
                <div class="display-6 fw-bold text-warning mb-2"><?php echo $clearance['final_status']; ?></div>
                <p class="small text-white-50">Once all departments are 'Cleared', you can print your official certificate.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-clipboard-check display-1 text-light"></i>
            <p class="mt-3 text-muted">You have not initiated the clearance process.</p>
        </div>
    <?php endif; ?>

    <div class="mt-5 p-4 bg-white rounded-3 shadow-sm">
        <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i> Instructions</h6>
        <ul class="small text-muted mb-0">
            <li>Ensure all library books are returned before visiting the Librarian.</li>
            <li>Finance clearance is automatic once your fee balance is Ksh 0.00.</li>
            <li>Visit your respective department head for academic clearance.</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>