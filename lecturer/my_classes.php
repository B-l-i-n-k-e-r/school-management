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

// Fetch school + teacher
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'The Michaelson School';

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$user_data = $staff_query->fetch_assoc() ?: [];

$staff_display_name = $user_data['full_name']        ?? 'Staff';
$assigned_form      = $user_data['assigned_form']    ?? '';
$assigned_subject   = $user_data['assigned_subject'] ?? '';

// Fetch students in the teacher's class
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no, gender FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

$active_page = 'my_classes';
$extra_label = 'My Classes — ' . ($assigned_form ?: 'Not Assigned');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes | <?php echo htmlspecialchars($school_display); ?></title>
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

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #eef2ff; color: #6366f1; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; }

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

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1">My Classes</h3>
            <p class="text-muted small mb-0">
                Students in
                <strong><?php echo htmlspecialchars($assigned_form ?: 'your assigned form'); ?></strong>
                <?php if ($assigned_subject): ?>
                    &middot; Teaching <strong><?php echo htmlspecialchars($assigned_subject); ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <?php if (!empty($students)): ?>
            <span class="badge rounded-pill bg-primary px-3 py-2 fw-bold">
                <i class="bi bi-people-fill me-1"></i>
                <?php echo count($students); ?> Student<?php echo count($students) === 1 ? '' : 's'; ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="card p-4">
        <?php if (!$assigned_form): ?>
            <div class="alert alert-warning rounded-4 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                You don't have an assigned class yet. Ask your admin to assign you a form.
            </div>

        <?php elseif (empty($students)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No students yet</h5>
                <p class="text-muted mb-0">
                    Once students are enrolled in <strong><?php echo htmlspecialchars($assigned_form); ?></strong>,
                    they'll appear here.
                </p>
            </div>

        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th class="border-0">#</th>
                            <th class="border-0">Student</th>
                            <th class="border-0">Adm No</th>
                            <th class="border-0">Gender</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                            <tr class="border-bottom">
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-sm">
                                            <?php echo htmlspecialchars(strtoupper(substr($s['full_name'] ?? '?', 0, 1))); ?>
                                        </span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($s['full_name'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($s['adm_no'] ?? ''); ?></td>
                                <td>
                                    <?php
                                        $gender = $s['gender'] ?? '—';
                                        $gender_class = match($gender) {
                                            'Male'   => 'bg-primary-subtle text-primary',
                                            'Female' => 'bg-danger-subtle text-danger',
                                            default  => 'bg-light text-dark',
                                        };
                                    ?>
                                    <span class="badge rounded-pill <?php echo $gender_class; ?> px-3 py-2">
                                        <?php echo htmlspecialchars($gender); ?>
                                    </span>
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