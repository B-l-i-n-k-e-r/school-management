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
    die("Student record not found. Please contact administration.");
}

$student_id = (int) $student['student_id'];

// Fetch payment records
$payments = [];
$p_stmt = $conn->prepare("
    SELECT payment_id, amount, payment_date, method, reference, notes, created_at
    FROM payments
    WHERE student_id = ?
    ORDER BY payment_date DESC, payment_id DESC
");
if ($p_stmt) {
    $p_stmt->bind_param("i", $student_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    while ($row = $p_res->fetch_assoc()) $payments[] = $row;
    $p_stmt->close();
}

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'receipts';
$extra_label = 'My Receipts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Receipts | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        /* Modern Cards & Table */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: white; }
        
        .table th { font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; font-weight: 800; padding: 18px 15px; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 18px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:hover { background-color: #f8fafc; }

        .amount-pill { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; display: inline-block; }
        
        .btn-print { background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; font-weight: 700; border-radius: 50px; padding: 8px 16px; transition: 0.2s; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-print:hover { background: #e2e8f0; color: #0f172a; }

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
            .card { box-shadow: none !important; border: 1px solid #ccc !important; }
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
            <h2 class="fw-bold mb-1 text-dark">Payment Receipts</h2>
            <p class="text-muted small mb-0">Your full transaction history with downloadable receipts.</p>
        </div>
        <a href="fees.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-wallet2 me-2"></i>View Fee Balance
        </a>
    </div>

    <div class="card">
        <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center no-print">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Receipts</h5>
            <span class="badge rounded-pill bg-light text-secondary border px-3 py-2">
                <?php echo count($payments); ?> Record<?php echo count($payments) === 1 ? '' : 's'; ?>
            </span>
        </div>

        <?php if (empty($payments)): ?>
            <!-- Modern Empty State -->
            <div class="p-5 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-receipt-cutoff fs-1 text-muted"></i>
                </div>
                <h6 class="fw-bold text-dark">No payment records found</h6>
                <p class="text-muted small mb-0">Once a payment is recorded by the finance office, its receipt will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Receipt No.</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end pe-4 no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment):
                            $pd = !empty($payment['payment_date']) ? $payment['payment_date'] : ($payment['created_at'] ?? '');
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">
                                        <?php echo $pd ? htmlspecialchars(date('d M, Y', strtotime($pd))) : '—'; ?>
                                    </div>
                                    <?php if (!empty($payment['created_at'])): ?>
                                        <div class="small text-muted fw-medium">
                                            <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars(date('h:i A', strtotime($payment['created_at']))); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment['reference'])): ?>
                                        <span class="badge bg-light text-secondary border px-3 py-2">#<?php echo htmlspecialchars($payment['reference']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border px-3 py-2">#RCP-<?php echo intval($payment['payment_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-medium"><?php echo htmlspecialchars($payment['notes'] ?: 'Fee Payment'); ?></td>
                                <td>
                                    <span class="badge bg-light text-secondary border px-3 py-2">
                                        <i class="bi bi-credit-card-2-front me-1"></i> <?php echo htmlspecialchars($payment['method'] ?: 'Cash'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="amount-pill">
                                        KES <?php echo number_format((float)$payment['amount'], 2); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4 no-print">
                                    <a href="view_receipt.php?id=<?php echo intval($payment['payment_id']); ?>" target="_blank" class="btn-print">
                                        <i class="bi bi-printer-fill"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 no-print text-center">
        <p class="small text-muted mb-0 fw-medium">
            <i class="bi bi-info-circle-fill me-1 text-primary"></i>
            All payments take up to 24 hours to reflect on your official account statement.
        </p>
    </div>

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