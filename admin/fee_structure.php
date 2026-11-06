<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Adding/Updating Fee Structure ----
if (isset($_POST['save_fee'])) {
    $form     = trim($_POST['form'] ?? '');
    $term     = trim($_POST['term'] ?? '');
    $tuition  = (float) ($_POST['tuition'] ?? 0);
    $boarding = (float) ($_POST['boarding'] ?? 0);
    $activity = (float) ($_POST['activity'] ?? 0);
    $others   = (float) ($_POST['others'] ?? 0);
    $total    = $tuition + $boarding + $activity + $others;

    if ($form === '' || $term === '') {
        $message = "Form and Term are required.";
        $message_type = 'warning';
    } else {
        $check = $conn->prepare("SELECT id FROM fee_structure WHERE form = ? AND term = ? LIMIT 1");
        $check->bind_param("ss", $form, $term);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE fee_structure SET amount=?, tuition=?, boarding=?, activity=?, others=?, total_amount=? WHERE form=? AND term=?");
            $stmt->bind_param("dddddsss", $total, $tuition, $boarding, $activity, $others, $total, $form, $term);
        } else {
            $stmt = $conn->prepare("INSERT INTO fee_structure (form, term, amount, tuition, boarding, activity, others, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdddddd", $form, $term, $total, $tuition, $boarding, $activity, $others, $total);
        }

        if ($stmt->execute()) {
            $message = "Fee structure for <strong>" . htmlspecialchars($form) . " — " . htmlspecialchars($term) . "</strong> saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM fee_structure WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: fee_structure.php?deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Fee structure removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all fee structures ----
$structures = [];
$res = $conn->query("SELECT * FROM fee_structure ORDER BY form ASC, term ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $structures[] = $row;
}

// ---- Fetch classes for dropdowns ----
$classes = [];
$c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) $classes[] = $row['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

$active_page = 'fee_structure';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure | Admin Portal</title>

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
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.px-4, .table th.px-4 { width: auto; }

        .search-area { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 0.9rem; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 14px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .modal-content { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .modal-header { border: 0; padding: 24px 24px 0; }
        .modal-body { padding: 24px; }
        .modal-footer { border: 0; padding: 0 24px 24px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }

        @media print {
            .sidebar, .topbar, .btn, .action-col, .search-area, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
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
            <h2 class="fw-bold mb-1">Fee Structure</h2>
            <p class="text-muted small mb-0">Configure and manage mandatory school fees</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="resetModal()">
                <i class="bi bi-plus-lg me-2"></i>New Structure
            </button>
        </div>
    </div>

    <div class="search-area">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 12px 0 0 12px;"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search by amount or keyword..." style="border-radius: 0 12px 12px 0;">
                </div>
            </div>
            <div class="col-md-3">
                <select id="filterForm" class="form-select">
                    <option value="">All Forms</option>
                    <?php foreach ($classes as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterTerm" class="form-select">
                    <option value="">All Terms</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Term 2">Term 2</option>
                    <option value="Term 3">Term 3</option>
                </select>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card adm-card p-4">
        <?php if (empty($structures)): ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No fee structure yet</h5>
                <p class="text-muted mb-3">Click <strong>New Structure</strong> to create the first one.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-muted small text-uppercase">
                            <th class="ps-4">Form</th>
                            <th>Term</th>
                            <th class="text-end">Tuition</th>
                            <th class="text-end">Boarding</th>
                            <th class="text-end">Activity</th>
                            <th class="text-end">Others</th>
                            <th class="text-end">Total (KES)</th>
                            <th class="text-center action-col pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($structures as $row): ?>
                            <tr class="fee-row">
                                <td class="ps-4 fw-bold row-form"><?php echo htmlspecialchars($row['form'] ?? ''); ?></td>
                                <td class="row-term">
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?php echo htmlspecialchars($row['term'] ?? ''); ?></span>
                                </td>
                                <td class="text-end"><?php echo number_format((float)($row['tuition']  ?? 0), 2); ?></td>
                                <td class="text-end"><?php echo number_format((float)($row['boarding'] ?? 0), 2); ?></td>
                                <td class="text-end"><?php echo number_format((float)($row['activity'] ?? 0), 2); ?></td>
                                <td class="text-end"><?php echo number_format((float)($row['others']   ?? 0), 2); ?></td>
                                <td class="text-end fw-bold text-primary"><?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?></td>
                                <td class="text-center action-col pe-4">
                                    <button class="btn btn-sm btn-light text-primary border-0 edit-btn"
                                            data-bs-toggle="modal" data-bs-target="#feeModal"
                                            data-form="<?php echo htmlspecialchars($row['form'] ?? ''); ?>"
                                            data-term="<?php echo htmlspecialchars($row['term'] ?? ''); ?>"
                                            data-tuition="<?php echo (float)($row['tuition']  ?? 0); ?>"
                                            data-boarding="<?php echo (float)($row['boarding'] ?? 0); ?>"
                                            data-activity="<?php echo (float)($row['activity'] ?? 0); ?>"
                                            data-others="<?php echo (float)($row['others']   ?? 0); ?>">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>
                                    <a href="?delete=<?php echo intval($row['id']); ?>"
                                       class="btn btn-sm btn-light text-danger border-0"
                                       onclick="return confirm('Delete this record?');">
                                        <i class="bi bi-trash3 fs-5"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="feeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add / Edit Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Form</label>
                            <select name="form" id="m_form" class="form-select" required>
                                <?php foreach ($classes as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term" id="m_term" class="form-select" required>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Tuition Fee</label><input type="number" step="0.01" name="tuition" id="m_tuition" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Boarding Fee</label><input type="number" step="0.01" name="boarding" id="m_boarding" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Activity Fee</label><input type="number" step="0.01" name="activity" id="m_activity" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Other Charges</label><input type="number" step="0.01" name="others" id="m_others" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_fee" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    const searchInput = document.getElementById('searchInput');
    const filterForm  = document.getElementById('filterForm');
    const filterTerm  = document.getElementById('filterTerm');
    const tableRows   = document.querySelectorAll('.fee-row');

    function performFilter() {
        const query  = (searchInput?.value || '').toLowerCase();
        const f_form = filterForm?.value || '';
        const f_term = filterTerm?.value || '';
        tableRows.forEach(row => {
            const text    = row.innerText.toLowerCase();
            const rowForm = row.querySelector('.row-form')?.innerText.trim() || '';
            const rowTerm = row.querySelector('.row-term')?.innerText.trim() || '';
            const matchesSearch = text.includes(query);
            const matchesForm   = f_form === '' || rowForm === f_form;
            const matchesTerm   = f_term === '' || rowTerm === f_term;
            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', performFilter);
    if (filterForm)  filterForm.addEventListener('change', performFilter);
    if (filterTerm)  filterTerm.addEventListener('change', performFilter);

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalTitle').innerText = "Edit Fee Structure";
            document.getElementById('m_form').value     = this.dataset.form;
            document.getElementById('m_term').value     = this.dataset.term;
            document.getElementById('m_tuition').value  = this.dataset.tuition;
            document.getElementById('m_boarding').value = this.dataset.boarding;
            document.getElementById('m_activity').value = this.dataset.activity;
            document.getElementById('m_others').value   = this.dataset.others;
        });
    });

    function resetModal() {
        document.getElementById('modalTitle').innerText = "New Fee Structure";
        document.querySelector('#feeModal form').reset();
    }
</script>
</body>
</html>