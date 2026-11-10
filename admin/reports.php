<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

// 1. Total revenue collected
$total_collected = 0;
$res = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM payments");
if ($res && ($row = $res->fetch_assoc())) $total_collected = (float) $row['total'];

// 2. Today's collections
$today_collected = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE payment_date = ?");
$today_date = date('Y-m-d');
$stmt->bind_param("s", $today_date);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $today_collected = (float) $row['total'];
$stmt->close();

// 3. Collections by method (uses correct column: `method`)
$method_data = [];
$res = $conn->query("SELECT method, SUM(amount) AS total FROM payments GROUP BY method ORDER BY total DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $method_data[] = $row;
}

// 4. Revenue by form
$form_report = [];
$res = $conn->query("
    SELECT s.form,
           SUM(p.amount) AS total_paid,
           COUNT(DISTINCT s.student_id) AS student_count
    FROM students s
    LEFT JOIN payments p ON s.student_id = p.student_id
    GROUP BY s.form
    ORDER BY s.form ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $form_report[] = $row;
}

// Figures for the share bars
$max_paid = 0.0;
foreach ($form_report as $r) $max_paid = max($max_paid, (float) ($r['total_paid'] ?? 0));
$method_sum = 0.0;
foreach ($method_data as $m) $method_sum += (float) ($m['total'] ?? 0);

// Colour + icon for a payment method
function pay_method_style($method) {
    $m = strtolower($method ?? 'cash');
    if (str_contains($m, 'mpesa') || str_contains($m, 'm-pesa')) return ['is-mpesa', 'phone'];
    if ($m === 'cash')                                           return ['is-cash', 'cash-coin'];
    if (str_contains($m, 'bank'))                                return ['is-bank', 'bank'];
    if (str_contains($m, 'cheque'))                              return ['is-cheque', 'file-earmark-text'];
    return ['is-other', 'credit-card'];
}

$active_page = 'reports';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Headline numbers ---------- */
        .rp-hero {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 20px;
            margin-bottom: 26px;
        }
        .rp-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 30px 32px;
            border-radius: 26px;
        }
        .rp-card.is-total {
            background: linear-gradient(115deg, var(--primary) 0%, var(--primary-soft) 100%);
            color: var(--white);
            box-shadow: 0 30px 60px -28px rgba(0, 10, 102, 0.55);
        }
        .rp-card.is-total::after {
            content: "";
            position: absolute;
            right: -70px;
            bottom: -110px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.3), transparent 70%);
        }
        .rp-card.is-today {
            background: var(--accent);
            color: var(--primary);
            box-shadow: 0 30px 60px -32px rgba(250, 204, 21, 0.9);
        }
        .rp-card > * { position: relative; z-index: 1; }
        .rp-card p { margin: 0 0 6px; font-size: 0.85rem; font-weight: 700; opacity: 0.8; }
        .rp-card h2 { margin: 0; font-size: clamp(1.5rem, 2.6vw, 2.1rem); font-weight: 800; letter-spacing: -0.02em; }
        .rp-icon {
            flex-shrink: 0;
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            font-size: 1.5rem;
        }
        .is-total .rp-icon { background: rgba(255, 255, 255, 0.14); color: var(--accent); }
        .is-today .rp-icon { background: var(--primary); color: var(--accent); }

        /* ---------- Two panels ---------- */
        .rp-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .rp-title { display: flex; align-items: center; gap: 12px; }
        .rp-title i {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* share bars */
        .rp-bar { height: 8px; margin-top: 8px; border-radius: 99px; background: var(--primary-tint); overflow: hidden; }
        .rp-bar span { display: block; height: 100%; border-radius: 99px; background: var(--primary); }
        .rp-bar.is-gold span { background: var(--accent); }
        .rp-money { color: var(--primary); font-weight: 800; white-space: nowrap; }

        .rp-methods { list-style: none; margin: 0; padding: 0 28px 24px; }
        .rp-method { padding: 16px 0; border-bottom: 1px solid var(--line); }
        .rp-method:last-child { border-bottom: none; }
        .rp-method-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .rp-method-top strong { color: #14633a; font-weight: 800; white-space: nowrap; }
        .rp-pct { margin-top: 6px; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); }

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

        @media (max-width: 992px) {
            .rp-hero, .rp-grid { grid-template-columns: 1fr; }
        }
        @media print {
            .rp-card { background: #fff !important; color: #000 !important; border: 1px solid #ddd; box-shadow: none !important; }
            .rp-card::after { display: none; }
            .rp-icon { display: none; }
            .rp-bar { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Financial overview</h1>
            <p>Revenue and collection analytics</p>
        </div>
        <div class="ui-actions no-print">
            <a href="collections.php" class="ui-btn is-ghost"><i class="bi bi-receipt"></i> Fee ledger</a>
            <button type="button" class="ui-btn is-navy" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> Print report
            </button>
        </div>
    </div>

    <!-- Headline numbers -->
    <section class="rp-hero">
        <div class="rp-card is-total">
            <div>
                <p>Total revenue collected</p>
                <h2>KES <?php echo number_format($total_collected, 2); ?></h2>
            </div>
            <div class="rp-icon"><i class="bi bi-wallet2"></i></div>
        </div>
        <div class="rp-card is-today">
            <div>
                <p>Today's collections</p>
                <h2>KES <?php echo number_format($today_collected, 2); ?></h2>
            </div>
            <div class="rp-icon"><i class="bi bi-calendar-check"></i></div>
        </div>
    </section>

    <div class="rp-grid">

        <!-- Revenue by form -->
        <section class="panel is-flush">
            <div class="ui-panel-head">
                <div class="rp-title">
                    <i class="bi bi-bar-chart-fill"></i>
                    <h2>Revenue by class level</h2>
                </div>
            </div>

            <?php if (empty($form_report)): ?>
                <div class="ui-empty">
                    <i class="bi bi-bar-chart"></i>
                    <p>No student data available.</p>
                </div>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th class="is-center">Students</th>
                                <th style="min-width:200px;">Paid amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($form_report as $row):
                                $paid  = (float) ($row['total_paid'] ?? 0);
                                $share = $max_paid > 0 ? round($paid / $max_paid * 100) : 0;
                            ?>
                                <tr>
                                    <td><span class="ui-name"><?php echo htmlspecialchars($row['form'] ?? '—'); ?></span></td>
                                    <td class="is-center"><span class="ui-badge is-navy"><?php echo (int)($row['student_count'] ?? 0); ?></span></td>
                                    <td>
                                        <span class="rp-money"><?php echo number_format($paid, 2); ?></span>
                                        <div class="rp-bar is-gold" aria-hidden="true"><span style="width: <?php echo $share; ?>%;"></span></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Payment methods -->
        <section class="panel is-flush">
            <div class="ui-panel-head">
                <div class="rp-title">
                    <i class="bi bi-pie-chart-fill"></i>
                    <h2>Payment methods</h2>
                </div>
            </div>

            <?php if (empty($method_data)): ?>
                <div class="ui-empty">
                    <i class="bi bi-inbox"></i>
                    <p>No transaction data available.</p>
                </div>
            <?php else: ?>
                <ul class="rp-methods">
                    <?php foreach ($method_data as $m):
                        [$m_class, $m_icon] = pay_method_style($m['method'] ?? 'cash');
                        $amt = (float) ($m['total'] ?? 0);
                        $pct = $method_sum > 0 ? round($amt / $method_sum * 100) : 0;
                    ?>
                        <li class="rp-method">
                            <div class="rp-method-top">
                                <span class="pay-badge <?php echo $m_class; ?>">
                                    <i class="bi bi-<?php echo $m_icon; ?>"></i>
                                    <?php echo htmlspecialchars($m['method'] ?? 'Cash'); ?>
                                </span>
                                <strong>KES <?php echo number_format($amt, 2); ?></strong>
                            </div>
                            <div class="rp-bar" aria-hidden="true"><span style="width: <?php echo $pct; ?>%;"></span></div>
                            <div class="rp-pct"><?php echo $pct; ?>% of all payments</div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php include("footer.php"); ?>
</body>
</html>