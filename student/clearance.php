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
$message = '';
$message_type = '';

// Fetch student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];

// Initiate clearance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_clearance'])) {
    $check = $conn->prepare("SELECT id FROM student_clearance WHERE student_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $check->close();
        $ins = $conn->prepare("
            INSERT INTO student_clearance
            (student_id, finance_status, library_status, sports_status, hostel_status, dept_status, final_status)
            VALUES (?, 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'In Progress')
        ");
        $ins->bind_param("i", $student_id);
        if ($ins->execute()) {
            $message = 'Clearance process initiated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Failed to start clearance.';
            $message_type = 'danger';
        }
        $ins->close();
    } else {
        $check->close();
    }
}

// Fetch clearance
$clearance = null;
$c_stmt = $conn->prepare("SELECT * FROM student_clearance WHERE student_id = ? LIMIT 1");
$c_stmt->bind_param("i", $student_id);
$c_stmt->execute();
$clearance = $c_stmt->get_result()->fetch_assoc();
$c_stmt->close();

function clearance_class($status) {
    return match ($status) {
        'Cleared' => 'bg-success-subtle text-success border border-success-subtle',
        'Pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
        'Blocked' => 'bg-danger-subtle text-danger border border-danger-subtle',
        default   => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
    };
}

function clearance_icon($status) {
    return match ($status) {
        'Cleared' => 'check-circle-fill',
        'Pending' => 'clock-fill',
        'Blocked' => 'x-circle-fill',
        default   => 'dash-circle',
    };
}

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'clearance';
$extra_label = 'Final Clearance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance | <?php echo htmlspecialchars($student_display_name); ?></title>
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

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
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

        /* --- MODERN UI STYLES --- */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; background: white; }
        
        .dept-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
        .status-badge { font-size: 0.85rem; font-weight: 800; letter-spacing: 0.03em; padding: 10px 15px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; text-transform: uppercase; }

        /* Hero Overall Card */
        .overall-card { 
            background: linear-gradient(135deg, #1a3066 0%, #2d4a8a 100%); 
            color: white; 
            border-radius: 20px; 
            padding: 30px; 
            position: relative; 
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(26, 48, 102, 0.2);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .overall-card::after {
            content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        .overall-title { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9; margin-bottom: 10px; }

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

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1 text-dark">Final Clearance</h2>
            <p class="text-muted small mb-0">Track your clearance across all departments</p>
        </div>
        
        <?php if ($clearance && $clearance['final_status'] === 'Completed'): ?>
            <button onclick="window.print()" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-2"></i>Print Certificate
            </button>
        <?php elseif (!$clearance): ?>
            <form method="POST" class="m-0">
                <button type="submit" name="initiate_clearance" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-play-circle-fill me-2"></i>Start Clearance
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 d-flex align-items-center mb-4">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'info-circle-fill'; ?> me-2 fs-5"></i>
            <span class="fw-medium"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($clearance): ?>
        <div class="row g-4">
            <?php
            $departments = [
                ['label' => 'Finance Office',  'key' => 'finance_status', 'icon' => 'bank',        'color' => 'primary'],
                ['label' => 'Library',         'key' => 'library_status', 'icon' => 'book',        'color' => 'info'],
                ['label' => 'Academic Dept',   'key' => 'dept_status',    'icon' => 'building',    'color' => 'dark'],
                ['label' => 'Hostels/Housing', 'key' => 'hostel_status',  'icon' => 'house',       'color' => 'success'],
                ['label' => 'Sports & Games',  'key' => 'sports_status',  'icon' => 'trophy',      'color' => 'warning'],
            ];
            foreach ($departments as $d):
                $status = $clearance[$d['key']] ?? 'Pending';
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="dept-icon bg-<?php echo $d['color']; ?> bg-opacity-10 text-<?php echo $d['color']; ?>">
                                <i class="bi bi-<?php echo $d['icon']; ?>"></i>
                            </div>
                            <h6 class="fw-bold mb-0 text-dark fs-5"><?php echo $d['label']; ?></h6>
                        </div>
                        <div class="mt-auto">
                            <span class="status-badge <?php echo clearance_class($status); ?>">
                                <i class="bi bi-<?php echo clearance_icon($status); ?>"></i>
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Overall Status Hero Card -->
            <div class="col-md-6 col-lg-4">
                <div class="overall-card">
                    <div class="overall-title">Overall Status</div>
                    <div class="display-5 fw-bold text-warning mb-3"><?php echo htmlspecialchars($clearance['final_status']); ?></div>
                    <p class="small text-white-50 mb-0" style="line-height: 1.6;">
                        When all departments show <strong class="text-white">Cleared</strong>, you can print your official certificate.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="card p-5 text-center">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-4" style="width: 100px; height: 100px; margin: 0 auto;">
                <i class="bi bi-clipboard-check text-muted" style="font-size: 3rem;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Clearance Not Started</h4>
            <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                Once you're ready to leave the institution, click the <strong>Start Clearance</strong> button above. 
                Different departments will then verify and mark your status as cleared.
            </p>
        </div>
    <?php endif; ?>

    <!-- Instructions Card -->
    <div class="card p-4 p-md-5 mt-4 border-start border-4 border-primary">
        <div class="d-flex align-items-center gap-3 mb-3">
            <i class="bi bi-info-circle-fill fs-3 text-primary"></i>
            <h5 class="fw-bold mb-0 text-dark">Clearance Instructions</h5>
        </div>
        <ul class="small text-muted mb-0" style="line-height: 2;">
            <li>Ensure all library books are returned before visiting the Librarian.</li>
            <li>Finance clearance is automatic once your fee balance reaches <strong>KES 0.00</strong>.</li>
            <li>Visit your respective department head for academic clearance.</li>
            <li>Keep checking this page to monitor your progress.</li>
        </ul>
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