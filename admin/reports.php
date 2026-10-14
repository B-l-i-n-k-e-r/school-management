<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

// 1. Get Total Revenue Collected
$total_res = $conn->query("SELECT SUM(amount) as total FROM payments");
$total_collected = $total_res->fetch_assoc()['total'] ?? 0;

// 2. Get Today's Collections
$today = date('Y-m-d');
$today_res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE payment_date = '$today'");
$today_collected = $today_res->fetch_assoc()['total'] ?? 0;

// 3. Get Collection by Payment Method (Safety Check)
$method_data = [];
$check_col = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_method'");
if($check_col && $check_col->num_rows > 0) {
    $method_res = $conn->query("SELECT payment_method, SUM(amount) as total FROM payments GROUP BY payment_method");
    while($row = $method_res->fetch_assoc()) $method_data[] = $row;
}

// 4. Revenue by Form
$form_report = $conn->query("
    SELECT s.form, SUM(p.amount) as total_paid, COUNT(DISTINCT s.student_id) as student_count
    FROM students s
    LEFT JOIN payments p ON s.student_id = p.student_id
    GROUP BY s.form
    ORDER BY s.form ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --accent-yellow: #b38f00;
            --submenu-bg: #2c3a4d;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: 0.3s; }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 0.9rem; }
        
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: 240px; background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: 240px; padding: 40px; }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 8px 15px; font-size: 0.8rem; border: none; border-radius: 5px; font-weight: bold; }

        .card { border: none; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .stats-card { border-radius: 20px; border: none; transition: 0.3s; }
        .stats-card:hover { transform: translateY(-5px); }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; }
            .sidebar-overlay.active { display: block; }
        }

        @media print {
            .sidebar, .topbar, .btn, .no-print, .sidebar-overlay { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .card { box-shadow: none !important; border: 1px solid #eee !important; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav-item">
        <a href="index.php" class="nav-link"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a>
    </div>
    
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-people-fill me-2"></i> Students</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-journal-bookmark-fill me-2"></i> Academics</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php">Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn active">
            <span><i class="bi bi-cash-stack me-2"></i> Finance</span>
            <i class="bi bi-dash small"></i>
        </a>
        <ul class="submenu" style="display: block;">
            <li><a href="fee_structure.php">Fee Structure</a></li>
            <li><a href="collections.php">Fee Collections</a></li>
            <li><a href="reports.php" style="color: #f6e05e;">Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="staff.php" class="nav-link">
            <span><i class="bi bi-person-badge-fill me-2"></i> Staff Directory</span>
        </a>
    </div>
</div>

<div class="topbar no-print">
    <i class="bi bi-list fs-3" id="hamburger" style="cursor:pointer;"></i>
    <div class="dropdown">
        <button class="user-dropdown text-uppercase dropdown-toggle" type="button" data-bs-toggle="dropdown">
            ADMIN
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3 mt-2">
            <li><a class="dropdown-item py-2 small" href="settings.php"><i class="bi bi-person me-2"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 small text-danger" href="adminLogOut.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5 animate__animated animate__fadeInDown">
        <div>
            <h2 class="fw-extrabold text-dark mb-1">Financial Overview</h2>
            <p class="text-muted">Comprehensive revenue and collection analytics for the current period.</p>
        </div>
        <button class="btn btn-dark rounded-pill px-4 shadow-sm no-print" onclick="window.print()">
            <i class="bi bi-printer-fill me-2"></i> Print Report
        </button>
    </div>

    <div class="row g-4 mb-5 animate__animated animate__fadeInUp">
        <div class="col-md-6">
            <div class="card stats-card p-4 bg-primary text-white shadow">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-white-50 small fw-bold mb-1">TOTAL REVENUE COLLECTED</p>
                        <h2 class="fw-bold mb-0">KES <?php echo number_format($total_collected, 2); ?></h2>
                    </div>
                    <i class="bi bi-wallet2 fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stats-card p-4 bg-success text-white shadow">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-white-50 small fw-bold mb-1">TODAY'S COLLECTIONS</p>
                        <h2 class="fw-bold mb-0">KES <?php echo number_format($today_collected, 2); ?></h2>
                    </div>
                    <i class="bi bi-calendar-check fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
        <div class="col-lg-8">
            <div class="card p-4 shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <div class="bg-warning rounded-3 p-2 text-white">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Revenue by Class Level</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr class="text-muted small text-uppercase">
                                <th class="border-0 px-3">Form</th>
                                <th class="border-0 text-center">Student Count</th>
                                <th class="border-0 text-end px-3">Paid Amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $form_report->fetch_assoc()): ?>
                            <tr>
                                <td class="px-3 fw-bold text-dark">Form <?php echo $row['form']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark rounded-pill px-3"><?php echo $row['student_count']; ?></span>
                                </td>
                                <td class="text-end fw-bold text-primary px-3"><?php echo number_format($row['total_paid'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4 shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <div class="bg-info rounded-3 p-2 text-white">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Payment Methods</h5>
                </div>
                <?php if(empty($method_data)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted fs-1"></i>
                        <p class="text-muted small mt-2">No transaction data available.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush mt-2">
                        <?php foreach($method_data as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-bottom-dashed">
                            <div>
                                <span class="fw-bold text-dark d-block"><?php echo $m['payment_method']; ?></span>
                                <small class="text-muted">Direct Channel</small>
                            </div>
                            <span class="fw-bold text-success text-end">KES <?php echo number_format($m['total'], 2); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('overlay');

    const toggleSidebar = () => { 
        sidebar.classList.toggle('active'); 
        if(overlay) overlay.classList.toggle('active'); 
    };

    hamburger.addEventListener('click', (e) => { 
        e.stopPropagation(); 
        toggleSidebar(); 
    });

    if(overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    document.addEventListener('click', function(event) {
        if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
            sidebar.classList.remove('active');
            if(overlay) overlay.classList.remove('active');
        }
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
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
</script>
</body>
</html>