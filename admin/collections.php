<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// Filter by student_id if passed from student_list.php
$filter_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Fetch filtered student
$filtered_student_name = '';
if ($filter_student_id > 0) {
    $stmt = $conn->prepare("SELECT full_name, adm_no FROM students WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("i", $filter_student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($student_data = $res->fetch_assoc()) {
        $filtered_student_name = $student_data['full_name'] . ' (' . $student_data['adm_no'] . ')';
    }
    $stmt->close();
}

// Fetch transactions
$transactions = [];
if ($filter_student_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, s.full_name, s.adm_no
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.student_id
        WHERE p.student_id = ?
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ");
    $stmt->bind_param("i", $filter_student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $transactions[] = $row;
    $stmt->close();
} else {
    $res = $conn->query("
        SELECT p.*, s.full_name, s.adm_no
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.student_id
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) $transactions[] = $row;
    }
}

// Summary of what is listed
$list_total   = 0.0;
$list_largest = 0.0;
foreach ($transactions as $t) {
    $amt = (float) ($t['amount'] ?? 0);
    $list_total += $amt;
    if ($amt > $list_largest) $list_largest = $amt;
}

// Colour + icon for a payment method
function pay_method_style($method) {
    $m = strtolower($method ?? 'cash');
    if (str_contains($m, 'mpesa') || str_contains($m, 'm-pesa')) return ['is-mpesa', 'phone'];
    if ($m === 'cash')                                           return ['is-cash', 'cash-coin'];
    if (str_contains($m, 'bank'))                                return ['is-bank', 'bank'];
    if (str_contains($m, 'cheque'))                              return ['is-cheque', 'file-earmark-text'];
    return ['is-other', 'credit-card'];
}

$active_page = 'collections';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collections | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Summary cards ---------- */
        .pay-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 26px;
        }
        .pay-stat {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 22px 24px;
            border-radius: 24px;
            background: var(--white);
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
        }
        .pay-stat-icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.35rem;
        }
        .pay-stat span { display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); }
        .pay-stat strong { display: block; font-size: 1.45rem; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); line-height: 1.2; }
        .pay-stat.is-featured { background: var(--primary); }
        .pay-stat.is-featured .pay-stat-icon { background: var(--accent); color: var(--primary); }
        .pay-stat.is-featured span { color: rgba(255, 255, 255, 0.75); }
        .pay-stat.is-featured strong { color: var(--white); }

        /* ---------- Method badges ---------- */
        .pay-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .pay-badge.is-mpesa  { background: #e7f7ee; color: #14633a; }
        .pay-badge.is-cash   { background: var(--primary-tint); color: var(--primary); }
        .pay-badge.is-bank   { background: #e3f2fd; color: #0b5394; }
        .pay-badge.is-cheque { background: var(--accent-tint); color: var(--accent-deep); }
        .pay-badge.is-other  { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }

        .pay-amount { color: #14633a; font-weight: 800; white-space: nowrap; }

        .pay-who {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            margin-bottom: 22px;
            border-radius: 99px;
            background: var(--accent-tint);
            color: var(--accent-deep);
            font-size: 0.88rem;
            font-weight: 700;
        }

        @media (max-width: 992px) {
            .pay-stats { grid-template-columns: 1fr; }
        }
        @media print {
            .pay-stat { box-shadow: none; border: 1px solid #ddd; background: #fff !important; }
            .pay-stat strong, .pay-stat span { color: #000 !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Fee ledger</h1>
            <p>Complete transaction history</p>
        </div>
        <div class="ui-actions no-print">
            <?php if ($filter_student_id > 0): ?>
                <a href="collections.php" class="ui-btn is-ghost"><i class="bi bi-arrow-left"></i> View all</a>
            <?php endif; ?>
            <button type="button" class="ui-btn is-ghost" onclick="window.print()">
                <i class="bi bi-printer"></i> Print ledger
            </button>
        </div>
    </div>

    <?php if ($filter_student_id > 0 && $filtered_student_name): ?>
        <div class="pay-who">
            <i class="bi bi-person-fill"></i> Showing records for <?php echo htmlspecialchars($filtered_student_name); ?>
        </div>
    <?php endif; ?>

    <!-- Summary -->
    <section class="pay-stats">
        <div class="pay-stat is-featured">
            <div class="pay-stat-icon"><i class="bi bi-wallet2"></i></div>
            <div>
                <span><?php echo $filter_student_id > 0 ? 'Total paid' : 'Total collected'; ?></span>
                <strong>KES <?php echo number_format($list_total, 2); ?></strong>
            </div>
        </div>
        <div class="pay-stat">
            <div class="pay-stat-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <span>Payments recorded</span>
                <strong><?php echo count($transactions); ?></strong>
            </div>
        </div>
        <div class="pay-stat">
            <div class="pay-stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <span>Largest payment</span>
                <strong>KES <?php echo number_format($list_largest, 2); ?></strong>
            </div>
        </div>
    </section>

    <!-- Table -->
    <section class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Transactions</h2>
                <span class="ui-count"><?php echo count($transactions); ?></span>
            </div>
            <label class="ui-search no-print">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Filter current list..." aria-label="Filter payments">
            </label>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="ui-empty">
                <i class="bi bi-receipt"></i>
                <p>No payment records</p>
                <small><?php echo $filter_student_id > 0 ? 'This student has no payments yet.' : 'No payments have been recorded yet.'; ?></small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Adm no</th>
                            <th>Student name</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="is-right">Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody id="collectionTable">
                        <?php foreach ($transactions as $row):
                            [$m_class, $m_icon] = pay_method_style($row['method'] ?? 'cash');
                        ?>
                            <tr class="collection-row">
                                <td class="nowrap">
                                    <?php echo !empty($row['payment_date']) ? htmlspecialchars(date("d M Y", strtotime($row['payment_date']))) : '—'; ?>
                                </td>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($row['adm_no'] ?? '—'); ?></span></td>
                                <td><span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? '—'); ?></span></td>
                                <td>
                                    <span class="pay-badge <?php echo $m_class; ?>">
                                        <i class="bi bi-<?php echo $m_icon; ?>"></i>
                                        <?php echo htmlspecialchars($row['method'] ?? 'Cash'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['reference'] ?? '—'); ?></td>
                                <td class="is-right pay-amount"><?php echo number_format((float)($row['amount'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include("footer.php"); ?>

<script>
    // Live filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.collection-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>