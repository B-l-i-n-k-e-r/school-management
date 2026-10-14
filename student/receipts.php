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

// 1. Fetch Student ID
$stmt = $pdo->prepare("SELECT student_id, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();
$s_id = $student['student_id'];

// 2. Fetch Payment History
// Table assumed: payments (id, student_id, receipt_no, amount, payment_mode, description, created_at)
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$s_id]);
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Receipts | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .main-content { padding: 40px; }
        .receipt-card { border: none; border-radius: 12px; transition: 0.2s; }
        .receipt-card:hover { background-color: #fcfcfc; }
        .btn-download { font-size: 0.8rem; border-radius: 20px; }
        
        @media print {
            .no-print { display: none !important; }
            .card { border: 1px solid #eee !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row mb-4 no-print">
        <div class="col-md-8">
            <h3 class="fw-bold">Payment Receipts</h3>
            <p class="text-muted">View and download your transaction history for the current academic year.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="fees.php" class="btn btn-outline-primary shadow-sm">
                <i class="bi bi-wallet2 me-2"></i> View Fee Balance
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-muted fw-bold">
                        <th class="ps-4 py-3">DATE</th>
                        <th>RECEIPT NO.</th>
                        <th>DESCRIPTION</th>
                        <th>METHOD</th>
                        <th>AMOUNT</th>
                        <th class="text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo date('d M, Y', strtotime($payment['created_at'])); ?></div>
                                <div class="small text-muted"><?php echo date('h:i A', strtotime($payment['created_at'])); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-soft-dark text-dark border">
                                    <?php echo htmlspecialchars($payment['receipt_no']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['description']); ?></td>
                            <td>
                                <small class="text-muted"><i class="bi bi-credit-card-2-front me-1"></i> <?php echo $payment['payment_mode']; ?></small>
                            </td>
                            <td class="fw-bold text-success">
                                Ksh. <?php echo number_format($payment['amount'], 2); ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-light border btn-download no-print" 
                                        onclick="printReceipt('<?php echo $payment['receipt_no']; ?>')">
                                    <i class="bi bi-printer me-1"></i> Print
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-receipt-cutoff display-4 text-light"></i>
                                <p class="text-muted mt-2">No payment records found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hidden Receipt Template for Printing -->
    <div id="print-area" class="d-none">
        <!-- This area would be populated dynamically via JS if doing a single-page print, 
             or you can link to a separate 'view_receipt.php?id=XXX' page for cleaner code. -->
    </div>

    <div class="mt-4 no-print text-center">
        <p class="small text-muted">
            <i class="bi bi-info-circle me-1"></i> 
            All payments take up to 24 hours to reflect on your official account statement.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function printReceipt(receiptNo) {
        // Option 1: Simple way - Open a new window with a dedicated receipt view page
        window.open('view_receipt.php?receipt_no=' + receiptNo, '_blank');
        
        /* 
        Option 2: If you want to keep it in one file, you'd fetch the details 
        via AJAX and trigger window.print() on a hidden div. 
        For now, Option 1 is standard for student portals.
        */
    }
</script>
</body>
</html>