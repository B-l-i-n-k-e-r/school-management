<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../database/db_connect.php');

// Security Check
if (!isset($_SESSION['adm_no']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

// Fetch Student Data
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ?");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student record not found. Please contact administration.");
}

// Financial Calculations

$host = 'localhost';
$db   = 'school_admin_db'; // Matches your screenshot
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

$stmt = $pdo->query("SELECT * FROM fees");
$fees = $stmt->fetchAll(); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Profile</title>
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

        /* Sidebar Styling */
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

        /* Mobile Responsive Logic */
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
            .topbar, .main-content, .footer-text { margin-left: 0 !important; }
            .overlay.active { display: block; }
        }

        .topbar {
            margin-left: 240px;
            background: var(--topbar-bg);
            height: 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 5px 15px; font-size: 0.8rem; cursor: pointer; border: none; }

        .main-content { margin-left: 240px; padding: 20px; transition: all 0.3s ease; }
        .profile-header { color: #3182ce; font-size: 1.5rem; margin-bottom: 20px; }
        
        .profile-container {
            background: white;
            border-radius: 4px;
            display: flex;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .avatar-section { flex: 0 0 200px; text-align: center; }
        .avatar-circle {
            width: 150px; height: 150px;
            background: #ddd; border-radius: 50%;
            margin: 0 auto; overflow: hidden;
            border: 5px solid #f6e05e;
        }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }

        .info-section { flex: 1; margin-left: 50px; }
        .info-row {
            display: flex;
            border-bottom: 1px solid var(--row-border);
            padding: 10px 0;
            font-size: 0.9rem;
        }
        .info-label { width: 300px; color: #718096; }
        .info-value { color: #38b2ac; font-weight: 500; }
        
        .fin-total { color: #718096; }
        .fin-balance { color: #38b2ac; }
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
        <a href="dashboard_view.php" class="nav-link active"><i class="bi bi-house-door-fill"></i> Home</a>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-journal-text"></i> Academics</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="registration.php">Subjects Registration</a></li>
            <li><a href="course_work.php">Course Work</a></li>
            <li><a href="exam_card.php">Exam Card</a></li>
            <li><a href="results.php">Exam Results</a></li>
            <li><a href="academic_leave.php">Academic Leave</a></li>
            <li><a href="clearance.php">Clearance</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-cash-stack"></i> Finance</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="fees.php">Fee Balance</a></li>
            <li><a href="receipts.php">Receipts</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-person-fill"></i> Self Service</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="downloads.php">Downloads</a></li>
            <li><a href="reset_password.php">Reset Password</a></li>
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
    <div class="profile-header">Profile</div>

    <div class="profile-container">
        <div class="avatar-section">
            <div class="avatar-circle">
                <img src="../images/student_avatar.jpg" alt="Avatar">
            </div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <div class="info-label">Admission No</div>
                <div class="info-value"><?php echo htmlspecialchars($student['adm_no']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Full Name</div>
                <div class="info-value text-uppercase"><?php echo htmlspecialchars($student['full_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender</div>
                <div class="info-value">Male</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Birth</div>
                <div class="info-value"><?php echo date("n/j/Y", strtotime($student['dob'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Class / Form</div>
                <div class="info-value"><?php echo htmlspecialchars($student['form'] ?? 'None'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone Number</div>
                <div class="info-value"><?php echo htmlspecialchars($student['parent_contact'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email Address</div>
                <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
            </div>
<?php if (!empty($fees)): ?>
                    <?php foreach ($fees as $row): ?>
            <div class="info-row mt-4">
                <div class="info-label fin-total">Total Billed</div>
                <div class="info-value fin-total">Ksh.<?php echo number_format($row['total_fees'], 2); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label fin-total">Total Paid</div>
                <div class="info-value fin-total">Ksh.<?php echo number_format($row['amount_paid'], 2); ?></div>
            </div>
            <div class="info-row" style="border-bottom: none;">
                <div class="info-label fin-balance">Balance</div>
                <div class="info-value fin-balance">Ksh. <?php echo number_format($balance = $row['total_fees'] - $row['amount_paid'], 2); ?></div>
             <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 50px; color: #94a3b8;">
                            Database connection active, but no records found in 'standard' table.
                        </td>
                    </tr>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburger = document.getElementById('hamburger-toggle');

    // Sidebar Toggle Logic
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Handle Sidebar Dropdowns
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