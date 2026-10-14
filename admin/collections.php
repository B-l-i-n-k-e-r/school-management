<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// Filter by Student ID if passed from student_list.php
$filter_student_id = isset($_GET['student_id']) ? mysqli_real_escape_string($conn, $_GET['student_id']) : null;

// Fetch Student details if filtered
$filtered_student_name = "";
if ($filter_student_id) {
    $stmt = $conn->prepare("SELECT full_name, adm_no FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $filter_student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($student_data = $result->fetch_assoc()) {
        $filtered_student_name = $student_data['full_name'] . " (" . $student_data['adm_no'] . ")";
    }
    $stmt->close();
}

// Fetch Transactions (Filtered or All)
$query = "SELECT p.*, s.full_name, s.adm_no 
          FROM payments p 
          JOIN students s ON p.student_id = s.student_id";

if ($filter_student_id) {
    $query .= " WHERE p.student_id = '$filter_student_id'";
}

$query .= " ORDER BY p.payment_date DESC, p.payment_id DESC";
$transactions = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collections | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --accent-yellow: #b38f00;
            --submenu-bg: #2c3a4d;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        .sidebar { 
            width: 240px; 
            height: 100vh; 
            background: var(--sidebar-bg); 
            color: white; 
            position: fixed; 
            left: 0; 
            top: 0; 
            overflow-y: auto; 
            z-index: 1200; 
            transition: all 0.3s ease-in-out; 
        }

        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 0.9rem; }
        
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: 240px; background: #f8f9fa; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #ddd; transition: 0.3s; }
        .main-content { margin-left: 240px; padding: 40px; transition: 0.3s; }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 8px 15px; font-size: 0.8rem; border: none; border-radius: 5px; font-weight: bold; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .search-input { border-radius: 12px; border: 1px solid #e2e8f0; height: 45px; padding-left: 45px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; }
            .sidebar-overlay.active { display: block; }
        }

        @media print {
            .sidebar, .topbar, .btn, .search-box, .breadcrumb { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
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
    
    <div class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
    
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-people-fill"></i> Students</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-journal-bookmark-fill"></i> Academics</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php">Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn active"><span><i class="bi bi-cash-stack"></i> Finance</span><i class="bi bi-dash small"></i></a>
        <ul class="submenu" style="display: block;">
            <li><a href="fee_structure.php">Fee Structure</a></li>
            <li><a href="collections.php" style="color: #f6e05e;">Fee Collections</a></li>
            <li><a href="reports.php">Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item"><a href="staff.php" class="nav-link"><i class="bi bi-person-badge-fill"></i> Staff Directory</a></div>
</div>

<div class="topbar">
    <i class="bi bi-list fs-3" id="hamburger" style="cursor:pointer;"></i>
    <div class="dropdown">
        <button class="user-dropdown text-uppercase dropdown-toggle" type="button" data-bs-toggle="dropdown">
            ADMIN
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3 mt-2">
            <li><a class="dropdown-item py-2 small" href="settings.php"><i class="bi bi-person me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 small text-danger" href="adminLogOut.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1">Fee Ledger</h2>
            <p class="text-muted small">
                <?php echo $filter_student_id ? "Showing records for: <strong>$filtered_student_name</strong>" : "Complete transaction history"; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if($filter_student_id): ?>
                <a href="collections.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>View All</a>
            <?php endif; ?>
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Ledger</button>
        </div>
    </div>

    <div class="search-box position-relative mb-4" style="max-width: 400px;">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" class="form-control search-input" placeholder="Filter current list...">
    </div>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-muted small text-uppercase">
                    <tr>
                        <th class="px-4">Date</th>
                        <th>Adm No</th>
                        <th>Student Name</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-end">Amount (KES)</th>
                    </tr>
                </thead>
                <tbody id="collectionTable">
                    <?php if($transactions->num_rows > 0): ?>
                        <?php while($row = $transactions->fetch_assoc()): ?>
                        <tr class="collection-row">
                            <td class="px-4 small"><?php echo date("d M Y", strtotime($row['payment_date'])); ?></td>
                            <td class="fw-bold"><?php echo $row['adm_no']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $row['payment_method']; ?></span></td>
                            <td class="text-muted small"><?php echo $row['reference_no']; ?></td>
                            <td class="text-end fw-bold text-success"><?php echo number_format($row['amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No payment records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
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

    document.getElementById('searchInput').addEventListener('input', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.collection-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });
</script>
</body>
</html>