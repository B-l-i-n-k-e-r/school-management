<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../dashboard.php");
    exit();
}

include('../database/db_connect.php');

$adm_no = $_SESSION['adm_no'];

// Database Configuration
$host = 'localhost';
$db   = 'school_admin_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// 1. Fetch Student Details
$stmt = $pdo->prepare("SELECT student_id, adm_no, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();

// 2. Fetch Fee Records (Filtered by student_id for security)
$stmt = $pdo->prepare("SELECT * FROM fees WHERE student_id = ?");
$stmt->execute([$student['student_id']]);
$fees = $stmt->fetchAll(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Balance | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-bg: #34445c;
            --topbar-bg: #f8f9fa;
            --accent-yellow: #b38f00;
            --text-main: #2c3e50;
            --row-border: #edf2f7;
            --submenu-bg: #2c3a4d;
        }

        body { background-color: #e9ecef; font-family: sans-serif; overflow-x: hidden; }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            left: 0; top: 0;
            overflow-y: auto;
            z-index: 1100;
            transition: all 0.3s ease;
        }
        
        .sidebar-header { 
            background: #dee2e6; 
            padding: 10px 15px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-header img { height: 40px; } 
        .sidebar-header span { color: #1a3066; font-weight: bold; font-size: 0.9rem; }

        .nav-item { border-bottom: 1px solid #4a5568; }
        
        .nav-link { 
            color: #cbd5e0; 
            padding: 12px 20px; 
            font-size: 0.9rem; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            transition: 0.3s;
            cursor: pointer;
        }
        
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        .nav-link i:first-child { margin-right: 10px; }

        .submenu {
            background: var(--submenu-bg);
            display: none; 
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .submenu li a {
            color: #fff;
            padding: 10px 20px 10px 45px;
            display: block;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
        }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0; }
            .topbar, .main-content { margin-left: 0 !important; }
            .overlay.active { display: block; }
        }

        .topbar {
            margin-left: 240px;
            background: var(--topbar-bg);
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 5px 15px; font-size: 0.8rem; cursor: pointer; border: none; }

        .main-content { margin-left: 240px; padding: 30px; transition: all 0.3s ease; }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo">
        <span>STUDENT PORTAL</span>
    </div>
    
    <div class="nav-item">
        <a href="dashboard_view.php" class="nav-link"><i class="bi bi-house-door-fill"></i> Home</a>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-cash-stack"></i> Finance</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu" style="display: block;">
            <li><a href="fees.php" class="text-warning">Fee Balance</a></li>
            <li><a href="receipts.php">Receipts</a></li>
        </ul>
    </div>
</div>

<div class="topbar">
    <i class="bi bi-list fs-4" id="hamburger-toggle" style="cursor:pointer;"></i>
    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown">
            <?php echo htmlspecialchars($student['full_name']); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../admin/adminLogOut.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="fw-bold">Financial Statement</h3>
            <p class="text-muted">Account overview for <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['adm_no']); ?>)</p>
        </div>
    </div>

    <?php if (!empty($fees)): ?>
        <?php foreach ($fees as $row): 
            $balance = $row['total_fees'] - $row['amount_paid'];
        ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <div class="text-muted small fw-bold text-uppercase">Total Payable</div>
                    <div class="h2 fw-bold text-dark">Ksh. <?php echo number_format($row['total_fees'], 2); ?></div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-success text-white">
                    <div class="small fw-bold text-uppercase opacity-75">Amount Paid</div>
                    <div class="h2 fw-bold">Ksh. <?php echo number_format($row['amount_paid'], 2); ?></div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 <?php echo ($balance > 0) ? 'bg-danger text-white' : 'bg-light text-muted'; ?>">
                    <div class="small fw-bold text-uppercase opacity-75">Outstanding Balance</div>
                    <div class="h2 fw-bold">Ksh. <?php echo number_format($balance, 2); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="mb-0 fw-bold">Payment History</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small fw-bold text-muted">
                            <th class="ps-4">Date</th>
                            <th>Receipt No.</th>
                            <th>Description</th>
                            <th>Mode</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- This is a static example; usually you would loop through a 'payments' table here -->
                        <tr>
                            <td class="ps-4">10 Jan 2026</td>
                            <td><span class="badge bg-light text-dark border">#RCP-8821</span></td>
                            <td>Term 1 Tuition Fees</td>
                            <td>Online/M-Pesa</td>
                            <td class="text-end pe-4 fw-bold text-success">Ksh. 15,000.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="bi bi-info-circle fs-1 mb-3 d-block"></i>
            No financial records found for this account.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburger = document.getElementById('hamburger-toggle');

    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const submenu = button.nextElementSibling;
            const icon = button.querySelector('.bi-plus, .bi-dash');
            
            if (submenu.style.display === "block") {
                submenu.style.display = "none";
                icon.classList.replace('bi-dash', 'bi-plus');
            } else {
                submenu.style.display = "block";
                icon.classList.replace('bi-plus', 'bi-dash');
            }
        });
    });
</script>

</body>
</html>
<?php include('../admin/footer.php'); ?>