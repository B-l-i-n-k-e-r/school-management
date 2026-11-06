<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

// 1. Total revenue collected
$total_collected = 0;
$res = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM payments");
if ($row = $res->fetch_assoc()) $total_collected = (float) $row['total'];

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --sidebar-bg: #34445c;
            --sidebar-header: #dee2e6;
            --primary-blue: #1a3066;
            --sidebar-width: 260px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        /* Stats hero cards */
        .stat-hero {
            border-radius: 20px;
            padding: 28px;
            border: none;
            transition: 0.3s;
            color: white;
            height: 100%;
        }
        .stat-hero:hover { transform: translateY(-5px); }
        .stat-hero.total   { background: linear-gradient(135deg, #1a3066, #34445c); }
        .stat-hero.today   { background: linear-gradient(135deg, #047857, #059669); }
        .stat-hero p.label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.75; margin-bottom: 6px; }
        .stat-hero h2 { font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
        .stat-hero .icon-wrap {
            width: 56px; height: 56px; border-radius: 14px;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }

        .method-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 600;
        }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }

        @media print {
            .sidebar, .topbar, .btn, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card, .stat-hero { box-shadow: none !important; }
            .stat-hero { background: white !important; color: black !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Financial Overview</h2>
            <p class="text-muted small mb-0">Comprehensive revenue and collection analytics</p>
        </div>
        <button class="btn btn-dark rounded-pill px-4 no-print" onclick="window.print()">
            <i class="bi bi-printer-fill me-2"></i>Print Report
        </button>
    </div>

    <!-- Hero stats -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="stat-hero total">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="label">Total Revenue Collected</p>
                        <h2>KES <?php echo number_format($total_collected, 2); ?></h2>
                    </div>
                    <div class="icon-wrap"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-hero today">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="label">Today's Collections</p>
                        <h2>KES <?php echo number_format($today_collected, 2); ?></h2>
                    </div>
                    <div class="icon-wrap"><i class="bi bi-calendar-check"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Revenue by form -->
        <div class="col-lg-8">
            <div class="card adm-card p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-3 p-2" style="background:#fef3c7; color:#b45309;">
                        <i class="bi bi-bar-chart-fill fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Revenue by Class Level</h5>
                </div>

                <?php if (empty($form_report)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bar-chart fs-1 text-muted d-block mb-3"></i>
                        <p class="text-muted mb-0">No student data available.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-muted small text-uppercase">
                                    <th class="border-0 ps-4">Form</th>
                                    <th class="border-0 text-center">Student Count</th>
                                    <th class="border-0 text-end pe-4">Paid Amount (KES)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($form_report as $row): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($row['form'] ?? '—'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark rounded-pill px-3">
                                                <?php echo (int)($row['student_count'] ?? 0); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-primary pe-4">
                                            <?php echo number_format((float)($row['total_paid'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment methods -->
        <div class="col-lg-4">
            <div class="card adm-card p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-3 p-2" style="background:#dbeafe; color:#1e40af;">
                        <i class="bi bi-pie-chart-fill fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Payment Methods</h5>
                </div>

                <?php if (empty($method_data)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted fs-1 d-block mb-3"></i>
                        <p class="text-muted small mb-0">No transaction data available.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($method_data as $m):
                            $method = strtolower($m['method'] ?? 'cash');
                            $method_class = match(true) {
                                str_contains($method, 'mpesa') || str_contains($method, 'm-pesa') => 'bg-success-subtle text-success',
                                $method === 'cash'                                                => 'bg-primary-subtle text-primary',
                                str_contains($method, 'bank')                                     => 'bg-info-subtle text-info',
                                str_contains($method, 'cheque')                                   => 'bg-warning-subtle text-warning',
                                default                                                           => 'bg-secondary-subtle text-secondary',
                            };
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                <div>
                                    <span class="method-badge <?php echo $method_class; ?>">
                                        <?php echo htmlspecialchars($m['method'] ?? 'Cash'); ?>
                                    </span>
                                </div>
                                <span class="fw-bold text-success">
                                    KES <?php echo number_format((float)($m['total'] ?? 0), 2); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include("footer.php"); ?>
</body>
</html>