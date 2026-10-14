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

// 1. Fetch Student Details
$stmt = $pdo->prepare("SELECT student_id, full_name, adm_no FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();
$student_id = $student['student_id'];

// 2. Check Fee Balance
$stmt = $pdo->prepare("SELECT (total_fees - amount_paid) as balance FROM fees WHERE student_id = ?");
$stmt->execute([$student_id]);
$fee_data = $stmt->fetch();
$balance = $fee_data ? $fee_data['balance'] : 999999; // Default to blocked if no record

// 3. Fetch Registered Subjects
$stmt = $pdo->prepare("
    SELECT s.subject_name, subject_code 
    FROM student_subjects ss
    JOIN subjects s ON ss.subject_id = id
    WHERE ss.student_id = ?
");
$stmt->execute([$student_id]);
$subjects = $stmt->fetchAll();

$is_cleared = ($balance <= 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Card | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { padding: 40px; }
        
        /* Exam Card Styling (Print Optimized) */
        .exam-card-container {
            background: white;
            border: 2px solid #333;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 5rem;
            color: rgba(0,0,0,0.05);
            font-weight: bold;
            pointer-events: none;
            text-transform: uppercase;
        }

        .card-header-logo {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .exam-card-container { border: none; width: 100%; max-width: 100%; box-shadow: none; }
        }

        .status-blocked {
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container main-content">
    
    <?php if (!$is_cleared): ?>
        <!-- Blocked View -->
        <div class="card status-blocked shadow-sm p-5 border-0 rounded-4">
            <i class="bi bi-lock-fill text-danger display-1 mb-4"></i>
            <h2 class="fw-bold">Exam Card Blocked</h2>
            <p class="text-muted">Your exam card is currently unavailable. Please clear your outstanding fee balance of <strong>Ksh. <?php echo number_format($balance, 2); ?></strong> to proceed.</p>
            <div class="mt-4">
                <a href="fees.php" class="btn btn-primary px-4">Go to Finance</a>
                <a href="dashboard_view.php" class="btn btn-outline-secondary px-4">Back to Home</a>
            </div>
        </div>

    <?php elseif (empty($subjects)): ?>
        <!-- No Subjects View -->
        <div class="card status-blocked shadow-sm p-5 border-0 rounded-4">
            <i class="bi bi-journal-x text-warning display-1 mb-4"></i>
            <h2 class="fw-bold">No Registered Subjects</h2>
            <p class="text-muted">You must complete your subject registration before an exam card can be generated.</p>
            <div class="mt-4">
                <a href="registration.php" class="btn btn-warning px-4">Register Now</a>
            </div>
        </div>

    <?php else: ?>
        <!-- Card View -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h4 class="fw-bold mb-0">Examination Admission Card</h4>
            <button class="btn btn-dark" onclick="window.print()">
                <i class="bi bi-printer me-2"></i> Print / Save as PDF
            </button>
        </div>

        <div class="exam-card-container shadow-sm">
            <div class="watermark">OFFICIAL</div>
            
            <div class="card-header-logo d-flex align-items-center gap-3">
                <img src="../images/logo.jpg" alt="School Logo" height="60">
                <div>
                    <h4 class="mb-0 fw-bold text-uppercase">BokinceX School</h4>
                    <p class="mb-0 small text-muted">Office of the Registrar (Academic Affairs)</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <small class="text-muted d-block">STUDENT NAME</small>
                    <span class="fw-bold text-uppercase"><?php echo htmlspecialchars($student['full_name']); ?></span>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">ADMISSION NUMBER</small>
                    <span class="fw-bold"><?php echo htmlspecialchars($student['adm_no']); ?></span>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="bg-light p-2 fw-bold border-start border-4 border-dark">APPROVED SUBJECTS FOR EXAMINATION</h6>
                <table class="table table-bordered mt-2">
                    <thead class="table-dark">
                        <tr>
                            <th width="150">Code</th>
                            <th>Subject Description</th>
                            <th width="150">Invigilator Sign</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $sub): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                            <td><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-5">
                <div class="col-6">
                    <div style="border-top: 1px solid #000; width: 150px; margin-top: 40px;"></div>
                    <small>Student Signature</small>
                </div>
                <div class="col-6 text-end">
                    <div style="border-top: 1px solid #000; width: 150px; margin-top: 40px; margin-left: auto;"></div>
                    <small>Registrar's Stamp/Sign</small>
                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded-2" style="border: 1px dashed #ccc;">
                <p class="mb-1 small"><strong>Instructions:</strong></p>
                <ol class="small mb-0" style="padding-left: 1.2rem;">
                    <li>Present this card at the entrance of the Examination Room.</li>
                    <li>The card is only valid for the registered subjects listed above.</li>
                    <li>Any form of alteration to this card will lead to automatic disqualification.</li>
                </ol>
            </div>
        </div>
        
        <p class="text-center text-muted small mt-4 no-print">
            Generated on: <?php echo date('d-M-Y H:i:s'); ?>
        </p>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>