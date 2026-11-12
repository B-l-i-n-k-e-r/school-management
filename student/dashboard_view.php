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
$extra_label = 'My Profile';
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
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-tint: #fff6cc;
            --accent-deep: #8a6a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 24px;
        }

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-badge { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 800; white-space: nowrap; }
        .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
        .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
        .ui-badge.is-success { background: #e7f7ee; color: #14633a; }

        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: var(--primary); color: var(--accent); font-weight: 800; font-size: 3rem; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 20px -5px rgba(0,10,102,0.3); overflow: hidden; border: 4px solid var(--white); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .info-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 6px; }
        .info-value { font-weight: 700; color: var(--ink); font-size: 1.05rem; margin-bottom: 0; }

        .fee-box { padding: 20px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .fee-box.expected { background: #eff6ff; border-left: 5px solid #3b82f6; }
        .fee-box.expected h4 { color: #1e3a8a; margin: 0; font-weight: 800; }
        .fee-box.paid { background: #ecfdf5; border-left: 5px solid #10b981; }
        .fee-box.paid h4 { color: #065f46; margin: 0; font-weight: 800; }
        .fee-box.balance { background: #fef2f2; border-left: 5px solid #ef4444; }
        .fee-box.balance.clear { background: #ecfdf5; border-left: 5px solid #10b981; }
        .fee-box.balance h4 { color: #991b1b; margin: 0; font-weight: 800; }
        .fee-box.balance.clear h4 { color: #065f46; }
        .fee-box .icon { font-size: 2rem; opacity: 0.3; }

        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>My Profile</h1>
            <p>Your official student record</p>
        </div>
        <span class="ui-badge is-navy fs-6 px-3 py-2"><i class="bi bi-mortarboard me-1"></i> <?php echo htmlspecialchars($student_form); ?></span>
    </div>

    <div class="row g-4">
        <!-- Left Side: Student Info -->
        <div class="col-lg-5">
            <div class="panel h-100">
                <div class="d-flex flex-column align-items-center text-center mb-4">
                    <div class="profile-avatar mb-3">
                        <?php if ($has_avatar): ?>
                            <img src="<?php echo $avatar_path; ?>" alt="Profile Photo">
                        <?php else: ?>
                            <?php echo htmlspecialchars(strtoupper(substr($student_display_name, 0, 1))); ?>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-bold mb-1 text-uppercase" style="color: var(--ink);"><?php echo htmlspecialchars($student_display_name); ?></h4>
                    <p class="text-muted mb-3"><i class="bi bi-hash"></i> <?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></p>
                    
                    <div class="d-flex gap-2">
                        <span class="ui-badge is-navy"><i class="bi bi-person-badge me-1"></i> Student</span>
                        <?php if (!empty($student['gender'])): ?>
                            <span class="ui-badge is-gold"><?php echo htmlspecialchars($student['gender']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="text-muted opacity-25">

                <div class="row g-3">
                    <div class="col-12">
                        <p class="info-label">Date of Birth</p>
                        <p class="info-value"><?php echo !empty($student['dob']) ? htmlspecialchars(date("d M Y", strtotime($student['dob']))) : '—'; ?></p>
                    </div>
                    <div class="col-12">
                        <p class="info-label">Parent Contact</p>
                        <p class="info-value"><?php echo htmlspecialchars($student['parent_contact'] ?? '—'); ?></p>
                    </div>
                    <div class="col-12">
                        <p class="info-label">Email Address</p>
                        <p class="info-value text-truncate"><?php echo htmlspecialchars($student['email'] ?? '—'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Fee Statement -->
        <div class="col-lg-7">
            <div class="panel h-100">
                <h5 class="fw-bold mb-4" style="color: var(--primary);"><i class="bi bi-wallet2 me-2"></i>Fee Statement</h5>

                <?php if ($total_fees == 0 && $amount_paid == 0): ?>
                    <div class="ui-alert is-warning m-0">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><strong>No Fee Records Found.</strong><br>The administration has not yet published a fee structure for your class or recorded any payments.</div>
                    </div>
                <?php else: ?>
                    <div class="fee-box expected">
                        <div>
                            <small class="text-uppercase fw-bold text-primary mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Expected Fees</small>
                            <h4>KES <?php echo number_format($total_fees, 2); ?></h4>
                        </div>
                        <i class="bi bi-receipt icon text-primary"></i>
                    </div>

                    <div class="fee-box paid">
                        <div>
                            <small class="text-uppercase fw-bold text-success mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Total Amount Paid</small>
                            <h4>KES <?php echo number_format($amount_paid, 2); ?></h4>
                        </div>
                        <i class="bi bi-check-circle icon text-success"></i>
                    </div>

                    <div class="fee-box balance <?php echo $balance <= 0 ? 'clear' : ''; ?>">
                        <div>
                            <small class="text-uppercase fw-bold <?php echo $balance <= 0 ? 'text-success' : 'text-danger'; ?> mb-1 d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">Outstanding Balance</small>
                            <h4>KES <?php echo number_format($balance, 2); ?></h4>
                        </div>
                        <i class="bi <?php echo $balance <= 0 ? 'bi-emoji-smile' : 'bi-exclamation-octagon'; ?> icon <?php echo $balance <= 0 ? 'text-success' : 'text-danger'; ?>"></i>
                    </div>

                    <?php if ($balance <= 0): ?>
                        <div class="p-3 rounded-3 text-center mt-3" style="background: #ecfdf5; color: #065f46; border: 1px dashed #10b981;">
                            <i class="bi bi-patch-check-fill me-2"></i> <strong>Your fees are fully paid. Thank you!</strong>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Submenu Toggle Logic - MUST BE ON EVERY PAGE
    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            
            sub.style.display = isClosed ? "block" : "none";
            
            if (icon) {
                if (isClosed) icon.classList.replace('bi-plus', 'bi-dash');
                else icon.classList.replace('bi-dash', 'bi-plus');
            }
        });
    });
</script>
</body>
</html>