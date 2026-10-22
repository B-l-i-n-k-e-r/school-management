<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

// Safe query helper
function safeQuery($conn, $sql, $default = null) {
    try {
        $res = $conn->query($sql);
        if (!$res) return $default;
        $row = $res->fetch_assoc();
        return $row ?: $default;
    } catch (Throwable $e) {
        return $default;
    }
}

// School name
$sys = safeQuery($conn, "SELECT school_name FROM system_settings LIMIT 1", ['school_name' => 'The Michaelson School']);
$school_display = $sys['school_name'] ?? 'The Michaelson School';

// Stats — safe against missing tables
$student_count = safeQuery($conn, "SELECT COUNT(*) as total FROM students")['total'] ?? 0;
$staff_count   = safeQuery($conn, "SELECT COUNT(*) as total FROM teachers")['total'] ?? 0;
$subject_count = safeQuery($conn, "SELECT COUNT(*) as total FROM subjects")['total'] ?? 0;

// Fees collected
$total_fees = 0;
$feesRow = safeQuery($conn, "SELECT SUM(amount_paid) as total FROM fees");
if ($feesRow && !empty($feesRow['total'])) {
    $total_fees = (float) $feesRow['total'];
}

// Recent notices
$recent_notices = [];
try {
    $rn = $conn->query("
        SELECT notice_id, title, type, target_audience, created_at
        FROM notices
        ORDER BY created_at DESC
        LIMIT 3
    ");
    if ($rn) {
        while ($row = $rn->fetch_assoc()) $recent_notices[] = $row;
    }
} catch (Throwable $e) {
    // notices table missing — silently skip
}

$active_page = 'dashboard';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?php echo htmlspecialchars($school_display); ?></title>

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

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width); height: 100vh;
            background: var(--sidebar-bg); color: white;
            position: fixed; left: 0; top: 0;
            z-index: 1200; transition: all 0.3s ease;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }

        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar,
        body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header {
            background: var(--sidebar-header); padding: 12px 20px;
            display: flex; align-items: center; gap: 12px; height: 70px;
        }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link {
            color: #d1d5db; padding: 15px 20px; font-size: 0.95rem;
            display: flex; align-items: center; justify-content: space-between;
            text-decoration: none; transition: 0.2s; cursor: pointer;
        }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        /* Topbar */
        .topbar {
            margin-left: var(--sidebar-width); background: #fff;
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        /* Card styles */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .welcome-banner {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 20px; color: white; padding: 40px; margin-bottom: 40px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            background: white; border-radius: 20px; padding: 25px;
            border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); height: 100%;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 20px;
        }

        /* Recent notices */
        .notice-preview-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            height: 100%;
            transition: all 0.2s;
        }
        .notice-preview-card:hover {
            background: white;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px -4px rgba(15,39,68,0.1);
        }
        .notice-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        /* Mobile overlay */
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.4);
            z-index: 1150;
        }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="welcome-banner animate__animated animate__fadeIn">
        <h2 class="fw-bold mb-1">Welcome back to <?php echo htmlspecialchars($school_display); ?>!</h2>
        <p class="mb-0 opacity-75 fw-500">The school systems are currently running smoothly.</p>
    </div>

    <!-- Stat cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <h3 class="fw-bold mb-1"><?php echo (int)$student_count; ?></h3>
                <p class="text-muted small mb-0 fw-semibold">Total Enrollment</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-badge"></i></div>
                <h3 class="fw-bold mb-1"><?php echo (int)$staff_count; ?></h3>
                <p class="text-muted small mb-0 fw-semibold">Active Faculty</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-journal-bookmark"></i></div>
                <h3 class="fw-bold mb-1"><?php echo (int)$subject_count; ?></h3>
                <p class="text-muted small mb-0 fw-semibold">Total Subjects</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-info">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-wallet2"></i></div>
                <h3 class="fw-bold mb-1">KES <?php echo number_format($total_fees / 1000, 1); ?>k</h3>
                <p class="text-muted small mb-0 fw-semibold">Total Fees Collected</p>
            </div>
        </div>
    </div>

    <!-- Chart + Quick Actions -->
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Admission Trends</h5>
                    <select class="form-select form-select-sm w-auto border-0 bg-light">
                        <option>Last 6 Months</option>
                    </select>
                </div>
                <canvas id="registrationChart" height="300"></canvas>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-4">Quick Actions</h5>
                <div class="d-grid gap-3">
                    <a href="admission.php" class="btn btn-light text-start py-3 px-4 border-0 rounded-4">
                        <i class="bi bi-person-plus-fill text-primary me-3"></i>Register Student
                    </a>
                    <a href="staff.php" class="btn btn-light text-start py-3 px-4 border-0 rounded-4">
                        <i class="bi bi-person-lines-fill text-info me-3"></i>Staff Directory
                    </a>
                    <a href="fee_structure.php" class="btn btn-light text-start py-3 px-4 border-0 rounded-4">
                        <i class="bi bi-file-earmark-text-fill text-success me-3"></i>Update Fees
                    </a>
                    <a href="notices.php" class="btn btn-light text-start py-3 px-4 border-0 rounded-4">
                        <i class="bi bi-bell-fill text-warning me-3"></i>Send Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Notices -->
    <div class="card p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:#fef3c7; color:#b45309;">
                    <i class="bi bi-megaphone-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0">Recent Notices</h5>
            </div>
            <a href="notices.php" class="text-decoration-none fw-bold small" style="color: #1a3066;">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($recent_notices)): ?>
            <div class="text-center py-4">
                <i class="bi bi-megaphone fs-1 text-muted d-block mb-2"></i>
                <p class="text-muted small mb-3">No notices posted yet.</p>
                <a href="notices.php" class="btn btn-primary rounded-pill px-4 btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Post First Notice
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($recent_notices as $n):
                    $type = $n['type'] ?? 'General';
                    $type_style = match (strtolower($type)) {
                        'academic'  => 'background:#dbeafe; color:#1e40af;',
                        'event'     => 'background:#dcfce7; color:#166534;',
                        'emergency' => 'background:#fee2e2; color:#991b1b;',
                        default     => 'background:#fef3c7; color:#92400e;',
                    };
                ?>
                    <div class="col-md-4">
                        <a href="notices.php" class="text-decoration-none d-block h-100">
                            <div class="notice-preview-card">
                                <span class="notice-type-badge" style="<?php echo $type_style; ?>">
                                    <?php echo htmlspecialchars($type); ?>
                                </span>
                                <h6 class="fw-bold mb-2 text-truncate-2" style="color:#1e293b; line-height:1.4;">
                                    <?php echo htmlspecialchars($n['title'] ?? ''); ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo !empty($n['created_at']) ? htmlspecialchars(date('d M Y', strtotime($n['created_at']))) : '—'; ?>
                                </small>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Dashboard chart
    const ctx = document.getElementById('registrationChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Admissions',
                    data: [15, 25, 12, 38, 22, 30],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
</script>

<?php include("footer.php"); ?>
</body>
</html>