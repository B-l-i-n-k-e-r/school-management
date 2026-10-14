<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 temporarily if you want to see errors
require_once("adminOnly.php");
include('../database/db_connect.php');

// Safe query helper — returns null on failure instead of throwing
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

// School name (falls back to a default)
$sys = safeQuery($conn, "SELECT school_name FROM system_settings LIMIT 1", ['school_name' => 'The Michaelson School']);
$school_display = $sys['school_name'] ?? 'The Michaelson School';

// Real-time stats — safe against missing tables
$student_count = safeQuery($conn, "SELECT COUNT(*) as total FROM students")['total'] ?? 0;

// Try teachers (what you have)
$staff_count = safeQuery($conn, "SELECT COUNT(*) as total FROM teachers")['total'] ?? 0;

// 'standard' table doesn't exist — try 'subjects' as a placeholder, or return 0
$form_count = safeQuery($conn, "SELECT COUNT(*) as total FROM subjects")['total'] ?? 0;

// No fees summary view yet — return 0
$total_fees = 0;

// Try to sum from fees table if it has an amount column
$feesRow = safeQuery($conn, "SELECT SUM(amount) as total FROM fees");
if ($feesRow && !empty($feesRow['total'])) {
    $total_fees = (float) $feesRow['total'];
}
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
            --primary-indigo: #6366f1;
            --topbar-bg: #ffffff;
            --nav-hover: #41516a;
            --submenu-bg: #2c3a4d;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }
        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link i:first-child { font-size: 1.2rem; width: 30px; }
        .nav-link span { flex-grow: 1; }
        .nav-link:hover, .nav-link.active { background: var(--nav-hover); color: white; }
        .submenu { background: var(--submenu-bg); list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }
        .topbar { margin-left: 260px; background: var(--topbar-bg); height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: 260px; padding: 40px; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }
        .welcome-banner { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 20px; color: white; padding: 40px; margin-bottom: 40px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .stat-card { background: white; border-radius: 20px; padding: 25px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); height: 100%; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 20px; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        @media (max-width: 992px) {
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">
    <div class="welcome-banner animate__animated animate__fadeIn">
        <h2 class="fw-800 mb-1">Welcome back to <?php echo htmlspecialchars($school_display); ?>!</h2>
        <p class="mb-0 opacity-75 fw-500">The school systems are currently running smoothly.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <h3 class="fw-800 mb-1"><?php echo $student_count; ?></h3>
                <p class="text-muted small mb-0 fw-600">Total Enrollment</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-badge"></i></div>
                <h3 class="fw-800 mb-1"><?php echo $staff_count; ?></h3>
                <p class="text-muted small mb-0 fw-600">Active Faculty</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-building"></i></div>
                <h3 class="fw-800 mb-1"><?php echo $form_count; ?></h3>
                <p class="text-muted small mb-0 fw-600">Total Subjects</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-info">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-wallet2"></i></div>
                <h3 class="fw-800 mb-1">KES <?php echo number_format($total_fees / 1000, 1); ?>k</h3>
                <p class="text-muted small mb-0 fw-600">Total Fees Collected</p>
            </div>
        </div>
    </div>

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
                        <i class="bi bi-bell-fill me-3" style="color: #6366f1;"></i>Send Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('sidebarOverlay');

    const toggleSidebar = () => {
        sidebar?.classList.toggle('active');
        overlay?.classList.toggle('active');
    };

    hamburger?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });

    overlay?.addEventListener('click', toggleSidebar);

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sub = btn.nextElementSibling;
            const icon = btn.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if(icon) {
                if(isClosed) {
                    icon.classList.replace('bi-plus', 'bi-dash');
                } else {
                    icon.classList.replace('bi-dash', 'bi-plus');
                }
            }
        });
    });

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
</body>
</html>