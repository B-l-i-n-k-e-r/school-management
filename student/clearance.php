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

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_clearance'])) {
    $check = $conn->prepare("SELECT id FROM student_clearance WHERE student_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $check->close();
        $ins = $conn->prepare("INSERT INTO student_clearance (student_id, finance_status, library_status, sports_status, hostel_status, dept_status, final_status) VALUES (?, 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'In Progress')");
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

$clearance = null;
$c_stmt = $conn->prepare("SELECT * FROM student_clearance WHERE student_id = ? LIMIT 1");
$c_stmt->bind_param("i", $student_id);
$c_stmt->execute();
$clearance = $c_stmt->get_result()->fetch_assoc();
$c_stmt->close();

function clearance_badge($status) {
    return match ($status) {
        'Cleared' => 'is-success',
        'Pending' => 'is-warning',
        'Blocked' => 'is-danger',
        default   => 'is-navy',
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
        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }

        /* Clearance Custom Cards */
        .dept-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; }
        .status-badge { font-size: 0.85rem; font-weight: 800; letter-spacing: 0.03em; padding: 10px 15px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; text-transform: uppercase; }
        .overall-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white; border-radius: 20px; padding: 30px; position: relative; overflow: hidden; height: 100%; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 10px 25px rgba(0, 10, 102, 0.2); }
        .overall-card::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .overall-title { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9; margin-bottom: 10px; }

        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }
        .ui-badge.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-danger { background: #fdeaea; color: #a42525; }
        
        .bg-primary-subtle { background-color: #eff6ff !important; }
        .bg-info-subtle { background-color: #ecfeff !important; }
        .bg-dark-subtle { background-color: #f1f5f9 !important; }
        .bg-success-subtle { background-color: #ecfdf5 !important; }
        .bg-warning-subtle { background-color: #fffbeb !important; }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Final Clearance</h1>
            <p>Track your clearance across all departments</p>
        </div>
        <?php if ($clearance && $clearance['final_status'] === 'Completed'): ?>
            <button onclick="window.print()" class="ui-btn is-primary"><i class="bi bi-printer-fill"></i> Print Certificate</button>
        <?php elseif (!$clearance): ?>
            <form method="POST" class="m-0">
                <button type="submit" name="initiate_clearance" class="ui-btn is-primary"><i class="bi bi-play-circle-fill"></i> Start Clearance</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>"><i class="bi bi-info-circle-fill"></i><div><?php echo htmlspecialchars($message); ?></div></div>
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
                $badge = clearance_badge($status);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="panel h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="dept-icon bg-<?php echo $d['color']; ?>-subtle text-<?php echo $d['color']; ?>">
                                <i class="bi bi-<?php echo $d['icon']; ?>"></i>
                            </div>
                            <h6 class="fw-bold mb-0 text-dark fs-5"><?php echo $d['label']; ?></h6>
                        </div>
                        <div class="mt-auto">
                            <span class="ui-badge <?php echo $badge; ?>" style="width: 100%; justify-content: center; padding: 12px;">
                                <i class="bi bi-<?php echo clearance_icon($status); ?> me-1"></i> <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-md-6 col-lg-4">
                <div class="overall-card">
                    <div class="overall-title">Overall Status</div>
                    <div class="display-5 fw-bold text-warning mb-3"><?php echo htmlspecialchars($clearance['final_status']); ?></div>
                    <p class="small text-white-50 mb-0" style="line-height: 1.6;">When all departments show <strong class="text-white">Cleared</strong>, you can print your official certificate.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="panel text-center">
            <div class="ui-empty">
                <i class="bi bi-clipboard-check"></i>
                <p>Clearance Not Started</p>
                <small class="mx-auto" style="max-width: 400px;">Once you're ready to leave the institution, click the <strong>Start Clearance</strong> button above.</small>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel mt-4 p-4" style="border-left: 4px solid var(--primary);">
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