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

$adm_no     = $_SESSION['adm_no'];
$payment_id = intval($_GET['id'] ?? 0);
$auto_print = isset($_GET['print']);

if ($payment_id <= 0) {
    die("Invalid receipt ID.");
}

// Fetch the student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found.");
}

$student_id = (int) $student['student_id'];

// Fetch the payment — restricted to this student
$p_stmt = $conn->prepare("
    SELECT payment_id, amount, payment_date, method, reference, notes, created_at
    FROM payments
    WHERE payment_id = ? AND student_id = ?
    LIMIT 1
");
$p_stmt->bind_param("ii", $payment_id, $student_id);
$p_stmt->execute();
$payment = $p_stmt->get_result()->fetch_assoc();
$p_stmt->close();

if (!$payment) {
    die("Receipt not found or does not belong to your account.");
}

// Fetch school branding
$sys = $conn->query("SELECT school_name, logo_path, address, phone, email FROM system_settings LIMIT 1")->fetch_assoc();
$school_name = $sys['school_name'] ?? 'The Michaelson School';
$school_logo = $sys['logo_path']  ?? '../images/logo.jpg';
$school_addr = $sys['address']    ?? '';
$school_phone= $sys['phone']      ?? '';
$school_email= $sys['email']      ?? '';

// Formatting helpers
$receipt_no = $payment['reference'] ?: 'RCP-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);
$pay_date   = !empty($payment['payment_date'])
    ? date('d M Y', strtotime($payment['payment_date']))
    : (!empty($payment['created_at']) ? date('d M Y', strtotime($payment['created_at'])) : '—');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($receipt_no); ?> | <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            padding: 40px 20px;
            margin: 0;
        }

        .receipt-wrapper {
            max-width: 720px;
            margin: 0 auto;
        }

        .receipt-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 30px -10px rgba(15,39,68,0.2);
            overflow: hidden;
        }

        .receipt-header {
            background: linear-gradient(135deg, #1a3066 0%, #2c3a4d 100%);
            color: white;
            padding: 32px 40px;
        }
        .receipt-header img { height: 56px; object-fit: contain; }
        .receipt-header h1 { font-weight: 800; font-size: 1.35rem; margin: 0; letter-spacing: -0.02em; }
        .receipt-header .motto { font-size: 0.75rem; opacity: 0.75; }

        .receipt-body { padding: 40px; }

        .receipt-title {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .receipt-no {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .amount-display {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 22px 26px;
            text-align: center;
        }
        .amount-display .lbl { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #166534; }
        .amount-display .amt { font-size: 2rem; font-weight: 800; color: #15803d; letter-spacing: -0.02em; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 32px; }
        .info-item .lbl { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; }
        .info-item .val { font-weight: 600; color: #0f172a; font-size: 0.95rem; }

        .receipt-footer {
            background: #f8fafc;
            padding: 22px 40px;
            border-top: 1px dashed #cbd5e1;
            font-size: 0.8rem;
            color: #64748b;
            text-align: center;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .receipt-card { box-shadow: none; border-radius: 0; }
            .receipt-header { border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="receipt-wrapper">

    <!-- Action bar -->
    <div class="action-bar no-print">
        <a href="receipts.php" class="btn btn-light border rounded-3 px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to Receipts
        </a>
        <button onclick="window.print()" class="btn btn-primary rounded-3 px-4 fw-bold">
            <i class="bi bi-printer me-2"></i>Print Receipt
        </button>
    </div>

    <div class="receipt-card">

        <!-- Header -->
        <div class="receipt-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="Logo"
                         onerror="this.style.display='none'">
                    <div>
                        <h1><?php echo htmlspecialchars($school_name); ?></h1>
                        <div class="motto">Official Payment Receipt</div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="receipt-title">Receipt No.</div>
                    <div class="fw-bold">#<?php echo htmlspecialchars($receipt_no); ?></div>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="receipt-body">

            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
                <div>
                    <div class="receipt-title">Issued To</div>
                    <div class="receipt-no"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div class="text-muted small mt-1">
                        Adm No: <strong><?php echo htmlspecialchars($student['adm_no']); ?></strong>
                        <?php if (!empty($student['form'])): ?>
                            &middot; <?php echo htmlspecialchars($student['form']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-md-end">
                    <div class="receipt-title">Date</div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($pay_date); ?></div>
                </div>
            </div>

            <!-- Amount -->
            <div class="amount-display mb-4">
                <div class="lbl">Amount Received</div>
                <div class="amt">KES <?php echo number_format((float)$payment['amount'], 2); ?></div>
            </div>

            <!-- Details grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="lbl">Payment Method</div>
                    <div class="val">
                        <i class="bi bi-credit-card-2-front me-1"></i>
                        <?php echo htmlspecialchars($payment['method'] ?: 'Cash'); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="lbl">Reference</div>
                    <div class="val">
                        <?php echo htmlspecialchars($payment['reference'] ?: '—'); ?>
                    </div>
                </div>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="lbl">Description</div>
                    <div class="val">
                        <?php echo htmlspecialchars($payment['notes'] ?: 'Fee payment'); ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <p class="mb-1">
                <i class="bi bi-patch-check-fill text-success me-1"></i>
                This receipt confirms that payment has been received.
            </p>
            <?php if ($school_addr || $school_phone || $school_email): ?>
                <p class="mb-0 small">
                    <?php echo htmlspecialchars($school_addr); ?>
                    <?php if ($school_phone): ?> &middot; <?php echo htmlspecialchars($school_phone); ?><?php endif; ?>
                    <?php if ($school_email): ?> &middot; <?php echo htmlspecialchars($school_email); ?><?php endif; ?>
                </p>
            <?php endif; ?>
            <p class="mb-0 mt-2 small text-muted">
                Generated on <?php echo date('d M Y, H:i'); ?>
            </p>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($auto_print): ?>
<script>
    // Auto-open print dialog if ?print=1 is passed
    window.addEventListener('load', () => window.print());
</script>
<?php endif; ?>
</body>
</html>