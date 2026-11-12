<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { die("Student record not found."); }

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? '';

// Fetch Fee Structure (Expected Fees)
$total_fees = 0;
$structure_stmt = $conn->prepare("SELECT total_amount FROM fee_structure WHERE form = ? LIMIT 1");
if ($structure_stmt) {
    $structure_stmt->bind_param("s", $student_form);
    $structure_stmt->execute();
    $structure_res = $structure_stmt->get_result()->fetch_assoc();
    if ($structure_res) $total_fees = (float) $structure_res['total_amount'];
    $structure_stmt->close();
}

// Fetch Payments (Amount Paid)
$amount_paid = 0;
$fee_stmt = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM fees WHERE student_id = ?");
if ($fee_stmt) {
    $fee_stmt->bind_param("i", $student_id);
    $fee_stmt->execute();
    $fee_res = $fee_stmt->get_result()->fetch_assoc();
    if ($fee_res) $amount_paid = (float) ($fee_res['total_paid'] ?? 0);
    $fee_stmt->close();
}

$balance = $total_fees - $amount_paid;
$paid_pct = $total_fees > 0 ? round(($amount_paid / $total_fees) * 100) : 0;

$is_paid_off     = ($balance <= 0 && $total_fees > 0);
$is_fully_unpaid = ($amount_paid <= 0 && $total_fees > 0);
$has_no_record   = ($total_fees <= 0);

// Payment history
$payments = [];
$p_stmt = $conn->prepare("SELECT payment_id, amount, payment_date, method, reference, notes FROM payments WHERE student_id = ? ORDER BY payment_date DESC, payment_id DESC");
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
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .panel.is-flush { padding: 0; overflow: hidden; }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); }
        
        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        
        .ui-panel-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 24px 28px; border-bottom: 1px solid var(--line); }
        .ui-panel-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ui-panel-title h2 { margin: 0; font-size: 1.15rem; font-weight: 800; letter-spacing: -0.01em; color: var(--primary); }
        .ui-count { padding: 5px 14px; border-radius: 99px; background: var(--accent); color: var(--primary); font-size: 0.8rem; font-weight: 800; white-space: nowrap; }
        .ui-table-wrap { overflow-x: auto; }
        .ui-table { width: 100%; border-collapse: collapse; }
        .ui-table th { padding: 14px 18px; text-align: left; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); background: var(--bg-light); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); white-space: nowrap; }
        .ui-table td { padding: 16px 18px; border-bottom: 1px solid var(--line); font-size: 0.92rem; font-weight: 500; color: var(--text-muted); vertical-align: middle; }
        .ui-table th:first-child, .ui-table td:first-child { padding-left: 28px; }
        .ui-table th:last-child,  .ui-table td:last-child  { padding-right: 28px; }
        .ui-table tbody tr:last-child td { border-bottom: none; }
        .ui-table tbody tr:hover { background: var(--primary-tint); }
        .ui-table .is-center { text-align: center; }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }
        .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }
        .amount-pill { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; display: inline-block; }
        
        .progress { height: 12px; border-radius: 10px; background: #e2e8f0; overflow: hidden; }
        .progress-bar { border-radius: 10px; }
        
        .fee-box { padding: 20px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .fee-box.expected { background: #eff6ff; border-left: 5px solid #3b82f6; }
        .fee-box.expected h4 { color: #1e3a8a; margin: 0; font-weight: 800; }
        .fee-box.paid { background: #ecfdf5; border-left: 5px solid #10b981; }
        .fee-box.paid h4 { color: #065f46; margin: 0; font-weight: 800; }
        .fee-box.balance { background: #fef2f2; border-left: 5px solid #ef4444; }
        .fee-box.balance.clear { background: #ecfdf5; border-left: 5px solid #10b981; }
        .fee-box.balance h4 { color: #991b1b; margin: 0; font-weight: 800; }
        .fee-box.balance.clear h4 { color: #065f46; }
        .fee-box .icon { font-size: 2rem; opacity: 0.3; }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Fee Statement</h1>
            <p><strong><?php echo htmlspecialchars($student_display_name); ?></strong> &middot; <?php echo htmlspecialchars($student_form); ?></p>
        </div>
        <a href="receipts.php" class="ui-btn is-primary"><i class="bi bi-receipt"></i> View Receipts</a>
    </div>

    <div class="row g-4 mb-4">
        <!-- Left: Fee Cards -->
        <div class="col-lg-7">
            <div class="panel h-100">
                <h5 class="fw-bold mb-4" style="color: var(--primary);"><i class="bi bi-wallet2 me-2"></i>Balance Overview</h5>
                
                <?php if ($has_no_record): ?>
                    <div class="ui-alert is-warning m-0"><i class="bi bi-exclamation-circle-fill"></i><div><strong>No Fee Record Found.</strong><br>The finance office has not yet published a fee structure for your class.</div></div>
                <?php else: ?>
                    <div class="fee-box expected">
                        <div>
                            <small class="text-uppercase fw-bold text-primary mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Expected Fees</small>
                            <h4>KES <?php echo number_format($total_fees, 2); ?></h4>
                        </div>
                        <i class="bi bi-receipt icon text-primary"></i>
                    </div>

                    <div class="fee-box paid">
                        <div>
                            <small class="text-uppercase fw-bold text-success mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Amount Paid</small>
                            <h4>KES <?php echo number_format($amount_paid, 2); ?></h4>
                        </div>
                        <i class="bi bi-check-circle icon text-success"></i>
                    </div>

                    <div class="fee-box balance <?php echo $balance <= 0 ? 'clear' : ''; ?>">
                        <div>
                            <small class="text-uppercase fw-bold <?php echo $balance <= 0 ? 'text-success' : 'text-danger'; ?> mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Outstanding Balance</small>
                            <h4>KES <?php echo number_format($balance, 2); ?></h4>
                        </div>
                        <i class="bi <?php echo $balance <= 0 ? 'bi-emoji-smile' : 'bi-exclamation-octagon'; ?> icon <?php echo $balance <= 0 ? 'text-success' : 'text-danger'; ?>"></i>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-muted text-uppercase">Payment Progress</span>
                            <span class="fw-bold"><?php echo $paid_pct; ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar <?php echo $paid_pct >= 100 ? 'bg-success' : ($paid_pct >= 50 ? 'bg-primary' : 'bg-warning'); ?>" role="progressbar" style="width: <?php echo min(100, $paid_pct); ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Info Card -->
        <div class="col-lg-5">
            <div class="panel h-100 d-flex flex-column justify-content-center text-center p-4" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white;">
                <i class="bi bi-info-circle-fill fs-1 mb-3" style="color: var(--accent);"></i>
                <h5 class="fw-bold mb-2">Need Help?</h5>
                <p class="small text-white-50 mb-0">If you believe there is an error with your fee statement, please visit the finance office with your payment receipts.</p>
            </div>
        </div>
    </div>

    <div class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Payment History</h2>
                <span class="ui-count"><?php echo count($payments); ?> Records</span>
            </div>
        </div>

        <?php if (empty($payments)): ?>
            <div class="ui-empty">
                <i class="bi bi-receipt"></i>
                <p>No payments yet</p>
                <small>Once payments are recorded, they will appear here.</small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th class="is-center">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo !empty($p['payment_date']) ? htmlspecialchars(date('d M Y', strtotime($p['payment_date']))) : '—'; ?></td>
                                <td>
                                    <?php if (!empty($p['reference'])): ?>
                                        <span class="ui-badge is-soft">#<?php echo htmlspecialchars($p['reference']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-medium"><?php echo htmlspecialchars($p['notes'] ?: 'Fee Payment'); ?></td>
                                <td>
                                    <span class="ui-badge is-navy"><?php echo htmlspecialchars($p['method'] ?: 'Cash'); ?></span>
                                </td>
                                <td class="is-center">
                                    <span class="amount-pill">KES <?php echo number_format((float)$p['amount'], 2); ?></span>
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