<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";
$default_pass = "mariba2026"; 

if(isset($_POST['register_staff'])) {
    $employee_id   = trim($_POST['employee_id']);
    $full_name     = trim($_POST['full_name']);
    $email         = trim($_POST['email']);
    $phone         = trim($_POST['phone']);
    $qualification = trim($_POST['qualification']);
    $experience    = trim($_POST['experience']);
    $password      = password_hash($default_pass, PASSWORD_DEFAULT);
    $role          = "teacher";   // must match the enum: admin|teacher|student

    $conn->begin_transaction();

    try {
        // 1. Insert into users
        $stmt_user = $conn->prepare("
            INSERT INTO users (username, employee_id, email, full_name, password, role) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_user->bind_param("ssssss", 
            $employee_id, $employee_id, $email, $full_name, $password, $role
        );
        $stmt_user->execute();
        $user_id = $conn->insert_id;
        $stmt_user->close();

        // 2. Insert into teachers
        $stmt_staff = $conn->prepare("
            INSERT INTO teachers (user_id, employee_id, full_name, email, phone, qualification, experience) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_staff->bind_param("issssss", 
            $user_id, $employee_id, $full_name, $email, $phone, $qualification, $experience
        );
        $stmt_staff->execute();
        $stmt_staff->close();

        $conn->commit();
        $message = "
        <div class='alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4'>
            <div class='d-flex align-items-center'>
                <i class='bi bi-check-circle-fill fs-3 me-3'></i>
                <div>
                    <h6 class='fw-bold mb-0'>Staff member registered successfully!</h6>
                    <small>Username (ID): <strong>" . htmlspecialchars($employee_id) . "</strong> | Email: <strong>" . htmlspecialchars($email) . "</strong></small>
                </div>
            </div>
        </div>";
    } catch (Exception $e) {
        $conn->rollback();
        if ($e->getCode() == 1062) {
            $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4 p-4'>Error: The Employee ID <strong>" . htmlspecialchars($employee_id) . "</strong> or Email <strong>" . htmlspecialchars($email) . "</strong> is already in use.</div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4 p-4'>System Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff | The Michaelson School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-bg: #34445c; --accent-yellow: #b38f00; --submenu-bg: #2c3a4d; }
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
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 0.9rem; }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(179, 143, 0, 0.1); border-color: var(--accent-yellow); }
        .btn-primary { background: var(--sidebar-bg); border: none; padding: 14px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #1a2533; transform: translateY(-2px); }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; }
            .sidebar-overlay.active { display: block; }
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
    <div class="nav-item"><a href="index.php" class="nav-link"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a></div>
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-people-fill me-2"></i> Students</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-journal-bookmark-fill me-2"></i> Academics</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php">Exam Schedules</a></li>
        </ul>
    </div>
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-cash-stack me-2"></i> Finance</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="fee_structure.php">Fee Structure</a></li>
            <li><a href="collections.php">Fee Collections</a></li>
            <li><a href="reports.php">Financial Reports</a></li>
        </ul>
    </div>
    <div class="nav-item"><a href="staff.php" class="nav-link active"><span><i class="bi bi-person-badge-fill me-2"></i> Staff Directory</span></a></div>
</div>

<div class="topbar">
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
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="mb-5">
                <a href="staff.php" class="btn btn-link text-decoration-none text-muted p-0 small fw-bold text-uppercase mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-2"></i> Back to Directory
                </a>
                <h2 class="fw-bold text-dark mb-1">Staff Onboarding</h2>
                <p class="text-muted">Fill in the details below to create a new staff profile and system login.</p>
            </div>

            <?php echo $message; ?>

            <form method="post" class="card p-4 p-md-5">
                <div class="row g-4">
                    <div class="col-12 border-start border-4 border-warning ps-3 mb-2">
                        <h5 class="fw-bold mb-0">Professional Identity</h5>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Employee ID (Used as Username)</label>
                        <input type="text" name="employee_id" class="form-control" placeholder="e.g. STF/001/2026" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter Official Name" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 07..." required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Qualification / Primary Department</label>
                        <input type="text" name="qualification" class="form-control" placeholder="e.g. B.Ed Arts (Geography/History)" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Years of Experience</label>
                        <input type="text" name="experience" class="form-control" placeholder="e.g. 5 Years" required>
                    </div>

                    <div class="col-12 mt-5 border-start border-4 border-warning ps-3 mb-2">
                        <h5 class="fw-bold mb-0">System Access</h5>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">System Email</label>
                        <input type="email" name="email" class="form-control" placeholder="staff.name@school.com" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Temporary Password</label>
                        <input type="text" class="form-control fw-bold" value="<?php echo htmlspecialchars($default_pass); ?>" readonly>
                        <div class="form-text text-warning"><i class="bi bi-info-circle me-1"></i> Staff will use this for their first login.</div>
                    </div>
                    
                    <div class="col-12 mt-5">
                        <button type="submit" name="register_staff" class="btn btn-primary w-100 shadow">
                            <i class="bi bi-person-plus-fill me-2"></i> Register & Generate Account
                        </button>
                    </div>
                </div>
            </form>
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

    if(overlay) overlay.addEventListener('click', toggleSidebar);

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            
            sub.style.display = isClosed ? "block" : "none";
            
            if(icon) icon.classList.replace(isClosed ? 'bi-plus' : 'bi-dash', isClosed ? 'bi-dash' : 'bi-plus');
        });
    });
</script>
</body>
</html>