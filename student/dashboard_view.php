<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../database/db_connect.php');

// Security check
if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

// Fetch student record
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found. Please contact administration.");
}

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? ''; 

// Fetch the Fee Structure total for this student's class
$total_fees = 0;
$structure_stmt = $conn->prepare("SELECT total_amount FROM fee_structure WHERE form = ? LIMIT 1");
if ($structure_stmt) {
    $structure_stmt->bind_param("s", $student_form);
    $structure_stmt->execute();
    $structure_res = $structure_stmt->get_result()->fetch_assoc();
    if ($structure_res) {
        $total_fees = (float) $structure_res['total_amount'];
    }
    $structure_stmt->close();
}

// Fetch how much THIS student has actually paid
$amount_paid = 0;
$fee_stmt = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM fees WHERE student_id = ?");
if ($fee_stmt) {
    $fee_stmt->bind_param("i", $student_id);
    $fee_stmt->execute();
    $fee_res = $fee_stmt->get_result()->fetch_assoc();
    if ($fee_res) {
        $amount_paid = (float) ($fee_res['total_paid'] ?? 0);
    }
    $fee_stmt->close();
}

// Compute balance
$balance = $total_fees - $amount_paid;

// Check for profile photo
$avatar_path = "../images/avatars/" . ($student['avatar'] ?? '');
$has_avatar = !empty($student['avatar']) && file_exists($avatar_path);

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'dashboard';
$extra_label = 'Student Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --accent-yellow: #b38f00;
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

        .profile-container { background: white; border-radius: 20px; display: flex; flex-wrap: wrap; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); gap: 40px; }
        
        .avatar-section { flex: 0 0 200px; text-align: center; }
        
        /* Modern Avatar Image */
        .avatar-circle { 
            width: 150px; height: 150px; background: #e2e8f0; border-radius: 50%; 
            margin: 0 auto; overflow: hidden; border: 5px solid #fff; 
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-circle i { font-size: 5rem; color: #94a3b8; }

        .info-section { flex: 1; min-width: 300px; }
        .info-row { display: flex; flex-wrap: wrap; border-bottom: 1px solid #edf2f7; padding: 14px 0; font-size: 0.95rem; }
        .info-label { flex: 0 0 200px; color: #718096; font-weight: 500; }
        .info-value { flex: 1; color: #0f172a; font-weight: 700; }
        .info-value.accent { color: #0d7d8c; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="mb-4">
        <h2 class="fw-bold mb-1 text-dark">My Profile</h2>
        <p class="text-muted small mb-0">Your official student record</p>
    </div>

    <div class="profile-container">
        <div class="avatar-section">
            <div class="avatar-circle">
                <?php if ($has_avatar): ?>
                    <img src="<?php echo $avatar_path; ?>" alt="Profile Photo">
                <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                <?php endif; ?>
            </div>
            <p class="mt-4 fw-bold text-uppercase mb-1 fs-5 text-dark"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></p>
            <span class="badge bg-secondary-subtle text-secondary border px-3 py-2"><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></span>
        </div>

        <div class="info-section">
            <div class="info-row">
                <div class="info-label">Admission No</div>
                <div class="info-value accent"><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Full Name</div>
                <div class="info-value text-uppercase"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender</div>
                <div class="info-value"><?php echo htmlspecialchars($student['gender'] ?? '—'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Birth</div>
                <div class="info-value">
                    <?php echo !empty($student['dob']) ? htmlspecialchars(date("d M Y", strtotime($student['dob']))) : '—'; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Class / Form</div>
                <div class="info-value"><?php echo htmlspecialchars($student['form'] ?? '—'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Parent Contact</div>
                <div class="info-value"><?php echo htmlspecialchars($student['parent_contact'] ?? '—'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email Address</div>
                <div class="info-value"><?php echo htmlspecialchars($student['email'] ?? '—'); ?></div>
            </div>

            <?php if ($total_fees > 0 || $amount_paid > 0): ?>
                <div class="info-row" style="border-top: 2px solid #e2e8f0; margin-top: 20px; padding-top: 20px;">
                    <div class="info-label">Total Fees (<?php echo htmlspecialchars($student_form); ?>)</div>
                    <div class="info-value">KES <?php echo number_format($total_fees, 2); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Amount Paid</div>
                    <div class="info-value">KES <?php echo number_format($amount_paid, 2); ?></div>
                </div>
                <div class="info-row" style="border-bottom: none;">
                    <div class="info-label fw-bold">Balance</div>
                    <div class="info-value fw-bold fs-5 <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                        KES <?php echo number_format($balance, 2); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="info-row" style="border-bottom: none; margin-top: 20px;">
                    <div class="info-label">Fee Record</div>
                    <div class="info-value text-muted">No fee record on file yet</div>
                </div>
            <?php endif; ?>
        </div>
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