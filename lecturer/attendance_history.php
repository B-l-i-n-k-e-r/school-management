<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

// Fetch school + teacher info
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $staff_data['full_name']     ?? 'Staff';
$assigned_form      = $staff_data['assigned_form'] ?? '';

// Date filter
$filter_date = $_GET['view_date'] ?? date('Y-m-d');

// Fetch attendance records for this teacher's class + date
$records = [];
if ($assigned_form) {
    $stmt = $conn->prepare("
        SELECT a.status, s.full_name, s.adm_no
        FROM attendance a
        JOIN students s ON a.student_id = s.student_id
        WHERE s.form = ? AND a.date = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->bind_param("ss", $assigned_form, $filter_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $records[] = $row;
    $stmt->close();
}

$active_page = 'attendance_history';
$extra_label = $assigned_form ? 'Class: ' . $assigned_form : 'No class assigned';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History | <?php echo htmlspecialchars($school_display); ?></title>
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

        .sidebar { 
            width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); 
            color: white; position: fixed; left: 0; top: 0; overflow-y: auto; 
            z-index: 1200; transition: all 0.3s ease;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; border-radius: 5px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }

        .nav-item-container { border-bottom: 1px solid #4a5568; }
        .nav-link-custom { 
            color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; 
            display: flex; justify-content: space-between; align-items: center; 
            text-decoration: none; transition: 0.3s; cursor: pointer;
        }
        .nav-link-custom:hover, .nav-link-custom.active { background: #4a5568; color: white; }

        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .history-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table th, .table td { white-space: nowrap; padding: 15px 25px; }
        .table th:nth-child(2), .table td:nth-child(2) { white-space: normal; }

        .badge-present { background: #dcfce7; color: #166534; }
        .badge-absent  { background: #fee2e2; color: #991b1b; }
        .badge-late    { background: #fef3c7; color: #92400e; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h3 class="fw-bold mb-1">Attendance History</h3>
            <p class="text-muted mb-0">Records for <strong><?php echo date('d M Y', strtotime($filter_date)); ?></strong></p>
        </div>
        <div class="col-md-4 ms-auto">
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="view_date" class="form-control border-0 shadow-sm" value="<?php echo htmlspecialchars($filter_date); ?>">
                <button type="submit" class="btn btn-primary fw-bold px-4">View</button>
            </form>
        </div>
    </div>

    <div class="history-card p-4">
        <?php if (!$assigned_form): ?>
            <div class="alert alert-warning rounded-4 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                You don't have an assigned class yet. Ask your admin to assign you a form.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th class="border-0">Adm No</th>
                            <th class="border-0">Student Name</th>
                            <th class="border-0 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($records)): ?>
                            <?php foreach ($records as $row):
                                $status_class = 'badge-' . strtolower($row['status'] ?? 'absent');
                            ?>
                                <tr class="border-bottom">
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></td>
                                    <td class="fw-500"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $status_class; ?> rounded-pill px-3 py-2">
                                            <?php echo htmlspecialchars($row['status'] ?? ''); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                    No records found for this date.
                                </td>
                            </tr>
                        <?php endif; ?>
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