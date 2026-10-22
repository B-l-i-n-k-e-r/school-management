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

// Fetch fee record — uses `amount_total`
$fee_stmt = $conn->prepare("SELECT * FROM fees WHERE student_id = ? LIMIT 1");
$fee_stmt->bind_param("i", $student_id);
$fee_stmt->execute();
$fees = $fee_stmt->get_result()->fetch_assoc();
$fee_stmt->close();

// Compute totals
$total_fees  = (float) ($fees['amount_total'] ?? 0);
$amount_paid = (float) ($fees['amount_paid']  ?? 0);
$balance     = $total_fees - $amount_paid;
$paid_pct    = $total_fees > 0 ? round(($amount_paid / $total_fees) * 100) : 0;

// State flags
$is_paid_off     = ($balance <= 0 && $total_fees > 0);
$is_fully_unpaid = ($amount_paid <= 0 && $total_fees > 0);
$has_no_record   = ($total_fees <= 0);

// Payment history
$payments = [];
$p_stmt = $conn->prepare("
    SELECT payment_id, amount, payment_date, method, reference, notes
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
$active_page = 'fees';
$extra_label = 'Fee Balance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Balance | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        .balance-hero {
            border-radius: 20px; padding: 36px 40px; color: white; position: relative; overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(15,39,68,0.35);
        }
        .balance-hero.paid    { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .balance-hero.owing   { background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%); }
        .balance-hero.partial { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); }
        .balance-hero.none    { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        .balance-hero::after {
            content: ''; position: absolute; right: -60px; top: -60px;
            width: 220px; height: 220px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .balance-hero .amount { font-size: 2.75rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
        .balance-hero .label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; }

        .mini-stat {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(6px);
            border-radius: 14px;
            padding: 14px 18px;
        }

        .progress { height: 10px; border-radius: 6px; background: #e2e8f0; }
        .progress-bar { border-radius: 6px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
        @media (max-width: 576px) {
            .balance-hero { padding: 26px 24px; }
            .balance-hero .amount { font-size: 2rem; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Fee Balance</h2>
            <p class="text-muted small mb-0">
                <strong><?php echo htmlspecialchars($student_display_name); ?></strong>
                &middot; <?php echo htmlspecialchars($student['adm_no'] ?? ''); ?>
                <?php if (!empty($student['form'])): ?>
                    &middot; <?php echo htmlspecialchars($student['form']); ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="receipts.php" class="btn btn-outline-primary rounded-3 px-4 shadow-sm">
            <i class="bi bi-receipt me-2"></i>View Receipts
        </a>
    </div>

    <div class="balance-hero <?php
        if ($has_no_record)       echo 'none';
        elseif ($is_paid_off)     echo 'paid';
        elseif ($is_fully_unpaid) echo 'owing';
        else                      echo 'partial';
    ?> mb-4">

        <div class="row align-items-center g-4 position-relative" style="z-index:2;">

            <div class="col-lg-7">
                <p class="label mb-2">
                    <?php if ($is_paid_off): ?>
                        <i class="bi bi-check-circle-fill me-1"></i> Account Fully Paid
                    <?php elseif ($has_no_record): ?>
                        <i class="bi bi-info-circle me-1"></i> No Fee Record
                    <?php else: ?>
                        <i class="bi bi-cash-stack me-1"></i> Outstanding Balance
                    <?php endif; ?>
                </p>
                <div class="amount mb-3">
                    KES <?php echo number_format(abs($balance), 2); ?>
                </div>

                <?php if (!$has_no_record): ?>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if ($is_paid_off): ?>
                            <span class="badge bg-white text-success rounded-pill px-3 py-2 fw-bold">
                                <i class="bi bi-check-circle me-1"></i>No balance due
                            </span>
                        <?php else: ?>
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold">
                                <i class="bi bi-clock me-1"></i>Payment outstanding
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <?php if (!$has_no_record): ?>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="mini-stat">
                                <div class="label mb-1">Total Payable</div>
                                <div class="fw-bold fs-5">KES <?php echo number_format($total_fees, 2); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="mini-stat">
                                <div class="label mb-1">Amount Paid</div>
                                <div class="fw-bold fs-5">KES <?php echo number_format($amount_paid, 2); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php if (!$has_no_record): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold small text-muted text-uppercase">Payment Progress</span>
                <span class="fw-bold"><?php echo $paid_pct; ?>%</span>
            </div>
            <div class="progress">
                <div class="progress-bar <?php echo $paid_pct >= 100 ? 'bg-success' : ($paid_pct >= 50 ? 'bg-primary' : 'bg-warning'); ?>"
                     role="progressbar"
                     style="width: <?php echo min(100, $paid_pct); ?>%"
                     aria-valuenow="<?php echo $paid_pct; ?>"
                     aria-valuemin="0"
                     aria-valuemax="100">
                </div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">KES <?php echo number_format($amount_paid, 2); ?> paid</small>
                <small class="text-muted">KES <?php echo number_format($total_fees, 2); ?> total</small>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill fs-4"></i>
                <div>
                    <h6 class="fw-bold mb-1">No fee record on file</h6>
                    <p class="mb-0 small">
                        A fee record hasn't been created for your account yet.
                        Please contact the finance office if this is incorrect.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Payment History</h5>
            <span class="badge rounded-pill bg-light text-dark border">
                <?php echo count($payments); ?> record<?php echo count($payments) === 1 ? '' : 's'; ?>
            </span>
        </div>

        <?php if (empty($payments)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-receipt fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted mb-0">No payment records yet.</p>
                <small class="text-muted">Once payments are recorded, they'll appear here.</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small fw-bold text-muted text-uppercase">
                            <th class="ps-4 py-3">Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php echo !empty($p['payment_date']) ? htmlspecialchars(date('d M Y', strtotime($p['payment_date']))) : '—'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['reference'])): ?>
                                        <span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($p['reference']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['notes'] ?: 'Fee payment'); ?></td>
                                <td>
                                    <span class="badge rounded-pill
                                        <?php
                                            $m = strtolower($p['method'] ?? 'cash');
                                            echo match(true) {
                                                str_contains($m, 'mpesa') || str_contains($m, 'm-pesa') => 'bg-success-subtle text-success',
                                                $m === 'cash'                                        => 'bg-primary-subtle text-primary',
                                                str_contains($m, 'bank')                             => 'bg-info-subtle text-info',
                                                default                                              => 'bg-secondary-subtle text-secondary',
                                            };
                                        ?> px-3 py-2">
                                        <?php echo htmlspecialchars($p['method'] ?: 'Cash'); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4 fw-bold text-success">
                                    KES <?php echo number_format((float)$p['amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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