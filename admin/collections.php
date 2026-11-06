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

        .search-box { position: relative; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 5; }
        .search-input { border-radius: 12px; border: 1px solid #e2e8f0; height: 45px; padding-left: 45px; font-size: 0.9rem; background: #fff; }
        .search-input:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); }

        .method-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }

        @media print {
            .sidebar, .topbar, .btn, .search-box, .sidebar-overlay { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #eee !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Fee Ledger</h2>
            <p class="text-muted small mb-0">
                <?php if ($filter_student_id > 0): ?>
                    Showing records for: <strong><?php echo htmlspecialchars($filtered_student_name); ?></strong>
                <?php else: ?>
                    Complete transaction history
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($filter_student_id > 0): ?>
                <a href="collections.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2"></i>View All
                </a>
            <?php endif; ?>
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print Ledger
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="search-box mb-4">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" class="form-control search-input" placeholder="Filter current list...">
    </div>

    <!-- Table -->
    <div class="card adm-card p-4">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No payment records</h5>
                <p class="text-muted mb-0">
                    <?php echo $filter_student_id > 0 ? 'This student has no payments yet.' : 'No payments have been recorded yet.'; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-muted small text-uppercase">
                            <th class="ps-4">Date</th>
                            <th>Adm No</th>
                            <th>Student Name</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="text-end pe-4">Amount (KES)</th>
                        </tr>
                    </thead>
                    <tbody id="collectionTable">
                        <?php foreach ($transactions as $row):
                            $method = strtolower($row['method'] ?? 'cash');
                            $method_class = match(true) {
                                str_contains($method, 'mpesa') || str_contains($method, 'm-pesa') => 'bg-success-subtle text-success',
                                $method === 'cash'                                                => 'bg-primary-subtle text-primary',
                                str_contains($method, 'bank')                                     => 'bg-info-subtle text-info',
                                str_contains($method, 'cheque')                                   => 'bg-warning-subtle text-warning',
                                default                                                           => 'bg-secondary-subtle text-secondary',
                            };
                            $method_icon = match(true) {
                                str_contains($method, 'mpesa') || str_contains($method, 'm-pesa') => 'phone',
                                $method === 'cash'                                                => 'cash-coin',
                                str_contains($method, 'bank')                                     => 'bank',
                                str_contains($method, 'cheque')                                   => 'file-earmark-text',
                                default                                                           => 'credit-card',
                            };
                        ?>
                            <tr class="collection-row">
                                <td class="ps-4 small">
                                    <?php echo !empty($row['payment_date']) ? htmlspecialchars(date("d M Y", strtotime($row['payment_date']))) : '—'; ?>
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['adm_no'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name'] ?? '—'); ?></td>
                                <td>
                                    <span class="method-badge <?php echo $method_class; ?>">
                                        <i class="bi bi-<?php echo $method_icon; ?>"></i>
                                        <?php echo htmlspecialchars($row['method'] ?? 'Cash'); ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo htmlspecialchars($row['reference'] ?? '—'); ?>
                                </td>
                                <td class="text-end pe-4 fw-bold text-success">
                                    <?php echo number_format((float)($row['amount'] ?? 0), 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

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