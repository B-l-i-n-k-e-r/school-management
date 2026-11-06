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

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');
    $reason     = trim($_POST['reason']     ?? '');

    if ($leave_type === '' || $start_date === '' || $end_date === '' || $reason === '') {
        $message = 'All fields are required.';
        $message_type = 'warning';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $message = 'End date cannot be before the start date.';
        $message_type = 'danger';
    } else {
        $ins = $conn->prepare("
            INSERT INTO academic_leaves
            (student_id, leave_type, start_date, end_date, reason, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $ins->bind_param("issss", $student_id, $leave_type, $start_date, $end_date, $reason);
        if ($ins->execute()) {
            $message = 'Application submitted successfully. Awaiting approval.';
            $message_type = 'success';
        } else {
            $message = 'Failed to submit: ' . $conn->error;
            $message_type = 'danger';
        }
        $ins->close();
    }
}

// Fetch history
$leave_history = [];
$h_stmt = $conn->prepare("SELECT * FROM academic_leaves WHERE student_id = ? ORDER BY created_at DESC");
$h_stmt->bind_param("i", $student_id);
$h_stmt->execute();
$h_res = $h_stmt->get_result();
while ($row = $h_res->fetch_assoc()) $leave_history[] = $row;
$h_stmt->close();

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'academic_leave';
$extra_label = 'Academic Leave';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Leave | <?php echo htmlspecialchars($student_display_name); ?></title>
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
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
        
        .form-label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 16px; border: 1px solid #cbd5e1; font-size: 0.95rem; background: #f8fafc; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 4px rgba(26,48,102,0.1); background: #fff; }
        
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        /* Modern Status Pills */
        .status-badge { font-size: 0.75rem; padding: 6px 14px; border-radius: 50px; font-weight: 800; letter-spacing: 0.03em; display: inline-block; }
        .status-pending  { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .status-approved { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
        .status-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Table Styling */
        .table th { font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; font-weight: 800; padding: 15px; border-bottom: 2px solid #e2e8f0; }
        .table td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:hover { background-color: #f8fafc; }

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
        <h2 class="fw-bold mb-1 text-dark">Academic Leave</h2>
        <p class="text-muted small mb-0">Request a formal temporary suspension of studies</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 d-flex align-items-center mb-4">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'info-circle-fill'; ?> me-2 fs-5"></i>
            <span class="fw-medium"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Application Form -->
        <div class="col-lg-5">
            <div class="card p-4 p-md-5 h-100 bg-white">
                <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i>New Request</h5>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Reason for Leave</label>
                        <select name="leave_type" class="form-select" required>
                            <option value="" disabled selected>Select a category...</option>
                            <option value="Medical">Medical Grounds</option>
                            <option value="Financial">Financial Constraints</option>
                            <option value="Personal">Personal / Compassionate</option>
                            <option value="Work">Work Commitment</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Detailed Explanation</label>
                        <textarea name="reason" class="form-control" rows="4" placeholder="Briefly explain your situation..." required></textarea>
                    </div>

                    <button type="submit" name="apply_leave" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-send-fill me-2"></i>Submit Application
                    </button>
                </form>
            </div>
        </div>

        <!-- History -->
        <div class="col-lg-7">
            <div class="card p-4 p-md-5 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Application History</h5>
                    <span class="badge rounded-pill bg-light text-secondary border px-3 py-2">
                        <?php echo count($leave_history); ?> Request<?php echo count($leave_history) === 1 ? '' : 's'; ?>
                    </span>
                </div>

                <?php if (empty($leave_history)): ?>
                    <div class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-calendar-x fs-1 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark">No requests found</h6>
                        <p class="text-muted small mb-0">You haven't submitted any leave requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_history as $leave): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($leave['leave_type'] ?? ''); ?></td>
                                        <td>
                                            <small class="text-muted d-block fw-medium">
                                                <i class="bi bi-calendar-event me-1"></i> <?php echo !empty($leave['start_date']) ? htmlspecialchars(date('d M Y', strtotime($leave['start_date']))) : '—'; ?>
                                            </small>
                                            <small class="text-muted d-block fw-medium mt-1">
                                                <i class="bi bi-calendar-check me-1"></i> <?php echo !empty($leave['end_date']) ? htmlspecialchars(date('d M Y', strtotime($leave['end_date']))) : '—'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($leave['status'] ?? 'pending'); ?>">
                                                <?php echo htmlspecialchars($leave['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted fw-medium">
                                                <?php echo !empty($leave['created_at']) ? htmlspecialchars(date('d M Y', strtotime($leave['created_at']))) : '—'; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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