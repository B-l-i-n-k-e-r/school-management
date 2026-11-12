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
        .ui-btn.is-sm { padding: 8px 16px; font-size: 0.82rem; }
        
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
        .ui-table .is-right { text-align: right; }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }
        .amount-pill { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; display: inline-block; }
        
        .ui-search { position: relative; width: 260px; max-width: 100%; }
        .ui-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .ui-search input { width: 100%; box-sizing: border-box; padding: 11px 16px 11px 42px; border: 1.5px solid var(--line); border-radius: 99px; background: var(--bg-light); font-family: inherit; font-size: 0.9rem; color: var(--ink); }
        .ui-search input:focus { outline: none; border-color: var(--primary); background: var(--white); }
        
        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        
        @media (max-width: 992px) { .main-content { padding: 20px; } }
        @media print { .sidebar, .topbar, .no-print, .sidebar-overlay, .ui-head, .ui-panel-head { display: none !important; } .main-content { margin-left: 0 !important; padding: 0 !important; } .panel { box-shadow: none !important; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head no-print">
        <div>
            <h1>Payment Receipts</h1>
            <p>Your full transaction history with downloadable receipts.</p>
        </div>
        <a href="fees.php" class="ui-btn is-ghost"><i class="bi bi-arrow-left"></i> Back to Fee Balance</a>
    </div>

    <div class="panel is-flush">
        <div class="ui-panel-head no-print">
            <div class="ui-panel-title">
                <h2>Receipt History</h2>
                <span class="ui-count"><?php echo count($payments); ?> Records</span>
            </div>
            <?php if (!empty($payments)): ?>
                <label class="ui-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="receiptSearch" placeholder="Search receipts..." aria-label="Search receipts">
                </label>
            <?php endif; ?>
        </div>

        <?php if (empty($payments)): ?>
            <div class="ui-empty">
                <i class="bi bi-receipt-cutoff"></i>
                <p>No payment records found</p>
                <small>Once a payment is recorded by the finance office, its receipt will appear here.</small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table" id="receiptsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Receipt No.</th>
                            <th>Description</th>
                            <th>Method</th>
                            <th class="is-right">Amount</th>
                            <th class="is-right no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment):
                            $pd = !empty($payment['payment_date']) ? $payment['payment_date'] : ($payment['created_at'] ?? '');
                        ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <?php echo $pd ? htmlspecialchars(date('d M, Y', strtotime($pd))) : '—'; ?>
                                    <?php if (!empty($payment['created_at'])): ?>
                                        <div class="small text-muted fw-medium"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars(date('h:i A', strtotime($payment['created_at']))); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment['reference'])): ?>
                                        <span class="ui-badge is-soft">#<?php echo htmlspecialchars($payment['reference']); ?></span>
                                    <?php else: ?>
                                        <span class="ui-badge is-soft">#RCP-<?php echo intval($payment['payment_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-medium"><?php echo htmlspecialchars($payment['notes'] ?: 'Fee Payment'); ?></td>
                                <td>
                                    <span class="ui-badge is-navy">
                                        <i class="bi bi-credit-card-2-front me-1"></i> <?php echo htmlspecialchars($payment['method'] ?: 'Cash'); ?>
                                    </span>
                                </td>
                                <td class="is-right">
                                    <span class="amount-pill">KES <?php echo number_format((float)$payment['amount'], 2); ?></span>
                                </td>
                                <td class="is-right no-print">
                                    <a href="view_receipt.php?id=<?php echo intval($payment['payment_id']); ?>" target="_blank" class="ui-btn is-primary is-sm">
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

    // Search functionality
    document.getElementById('receiptSearch')?.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#receiptsTable tbody tr').forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
</script>
</body>
</html>