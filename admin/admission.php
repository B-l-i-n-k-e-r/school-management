<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// --- 1. APPROVAL LOGIC ---
if (isset($_POST['approve_app_id'])) {
    $app_id = $_POST['approve_app_id'];
    $app_query = $conn->prepare("SELECT * FROM admission_applications WHERE id = ?");
    $app_query->bind_param("i", $app_id);
    $app_query->execute();
    $app = $app_query->get_result()->fetch_assoc();

    if ($app) {
        $user_email = trim($app['email']);
        if (!empty($user_email)) {
            $conn->begin_transaction();
            try {
                $adm_no = "ADM-" . rand(1000, 9999); 
                $hashed_pass = password_hash('123456', PASSWORD_DEFAULT);

                $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt1->bind_param("sss", $adm_no, $user_email, $hashed_pass);
                $stmt1->execute();
                
                $new_user_id = $conn->insert_id;

                $stmt2 = $conn->prepare("INSERT INTO students (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, dob, address, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("issssssssss", 
                    $new_user_id, $app['student_name'], $adm_no, $app['gender'], $app['form_requested'], 
                    $app['parent_name'], $app['phone'], $user_email, $app['dob'], 
                    $app['address'], $hashed_pass 
                );
                $stmt2->execute();

                $stmt3 = $conn->prepare("UPDATE admission_applications SET status = 'Approved' WHERE id = ?");
                $stmt3->bind_param("i", $app_id);
                $stmt3->execute();

                $conn->commit();
                $message = "<div class='alert alert-success border-0 shadow-sm rounded-4'>Approved! Student account created with ADM: $adm_no</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// --- 2. REJECTION MESSAGE CHECK ---
if (isset($_GET['status']) && $_GET['status'] == 'rejected') {
    $message = "<div class='alert alert-warning border-0 shadow-sm rounded-4'>Application has been marked as rejected.</div>";
}

// --- 3. DELETE LOGIC ---
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $get_info = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
    $get_info->bind_param("i", $delete_id);
    $get_info->execute();
    $student_data = $get_info->get_result()->fetch_assoc();
    
    if ($student_data) {
        $conn->begin_transaction();
        try {
            $u_id = $student_data['user_id'];
            $conn->query("DELETE FROM students WHERE student_id = $delete_id");
            $conn->query("DELETE FROM users WHERE user_id = $u_id");
            $conn->commit();
            $message = "<div class='alert alert-info border-0 shadow-sm rounded-4'>Record deleted successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

$registry = mysqli_query($conn, "SELECT * FROM students ORDER BY student_id DESC LIMIT 10");
$pending = mysqli_query($conn, "SELECT * FROM admission_applications WHERE status = 'pending'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Management | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .sidebar {
            width: 260px; height: 100vh;
            background: var(--sidebar-bg); color: white;
            position: fixed; left: 0; top: 0;
            z-index: 1200; transition: all 0.3s ease;
        }
        .sidebar-header { 
            background: var(--sidebar-header); padding: 12px 20px; 
            display: flex; align-items: center; gap: 12px; height: 70px;
        }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { 
            color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; 
            display: flex; align-items: center; text-decoration: none; transition: 0.2s;
            cursor: pointer;
        }
        .nav-link i:first-child { font-size: 1.2rem; width: 30px; }
        .nav-link span { flex-grow: 1; }
        .nav-link:hover, .nav-link.active { background: var(--nav-hover); color: white; }

        .submenu { background: var(--submenu-bg); list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar {
            margin-left: 260px; background: var(--topbar-bg);
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .main-content { margin-left: 260px; padding: 40px; transition: all 0.3s ease; }
        
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .small-text { font-size: 0.8rem; color: #64748b; }
        
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.4);
            z-index: 1150;
        }

        @media (max-width: 992px) {
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/45'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav flex-column">
        <div class="nav-item">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn active">
                <i class="bi bi-people-fill"></i>
                <span>Students</span>
                <i class="bi bi-dash small"></i>
            </a>
            <ul class="submenu" style="display: block;">
                <li><a href="admission.php" style="color: #f6e05e;">New Admission</a></li>
                <li><a href="student_list.php">Manage Students</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Academics</span>
                <i class="bi bi-plus small"></i>
            </a>
            <ul class="submenu">
                <li><a href="forms.php">Manage Forms</a></li>
                <li><a href="subjects.php">Manage Subjects</a></li>
                <li><a href="exams.php">Exam Schedules</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn">
                <i class="bi bi-cash-stack"></i>
                <span>Finance</span>
                <i class="bi bi-plus small"></i>
            </a>
            <ul class="submenu">
                <li><a href="fee_structure.php">Fee Structure</a></li>
                <li><a href="collections.php">Fee Collections</a></li>
                <li><a href="reports.php">Financial Reports</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a href="staff.php" class="nav-link"><i class="bi bi-person-badge-fill"></i><span>Staff Directory</span></a>
        </div>
        
        <div class="nav-item mt-4">
            <a href="adminLogOut.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
        </div>
    </div>
</div>

<div class="topbar">
    <i class="bi bi-list" id="hamburger"></i>
    <div class="dropdown">
        <button class="btn btn-light dropdown-toggle border-0 fw-bold" type="button" data-bs-toggle="dropdown">
            ADMIN
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="adminLogOut.php">Logout</a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1">Admission Management</h2>
            <p class="text-muted small">Process applications and manage the student registry</p>
        </div>
        <div class="d-flex gap-2 text-nowrap">
            <a href="student_list.php" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-person-gear me-2"></i>Manage Students
            </a>
            <a href="student_add.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="bi bi-plus-lg me-2"></i>Add Student
            </a>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card adm-card mb-5 overflow-hidden">
        <div class="card-header bg-white py-4 border-0">
            <h5 class="fw-bold m-0 text-primary">Pending Admission Requests</h5>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light text-nowrap">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4" style="width: 25%;">Student Name</th>
                        <th style="width: 10%;">Gender</th>
                        <th style="width: 15%;">Requested Level</th>
                        <th style="width: 20%;">Parent Info</th>
                        <th style="width: 15%;">Address</th>
                        <th class="text-end pe-4" style="width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p = mysqli_fetch_assoc($pending)): ?>
                    <tr>
                        <td class="ps-4 text-nowrap">
                            <strong><?php echo htmlspecialchars($p['student_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                        </td>
                        <td class="text-nowrap"><span class="badge bg-light text-dark"><?php echo htmlspecialchars($p['gender']); ?></span></td>
                        <td class="text-nowrap"><span class="fw-bold"><?php echo htmlspecialchars($p['form_requested']); ?></span></td>
                        <td class="text-nowrap">
                            <small>
                                <strong><?php echo htmlspecialchars($p['parent_name']); ?></strong><br>
                                <?php echo htmlspecialchars($p['phone']); ?>
                            </small>
                        </td>
                        <td class="text-nowrap"><small class="text-muted"><?php echo htmlspecialchars($p['address']); ?></small></td>
                        <td class="text-end pe-4 text-nowrap">
                            <div class="d-flex gap-2 justify-content-end align-items-center">
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="approve_app_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">Approve</button>
                                </form>
                                
                                <a href="reject_app.php?id=<?php echo $p['id']; ?>" 
                                   class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm"
                                   onclick="return confirm('Are you sure you want to reject this application?');">
                                    Deny
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card adm-card overflow-hidden">
        <div class="card-header bg-white py-4 border-0">
            <h5 class="fw-bold m-0 text-success">Recently Enrolled Students</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-nowrap">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4" style="width: 15%;">ADM NO</th>
                        <th style="width: 25%;">Student Details</th>
                        <th style="width: 10%;">Gender</th>
                        <th style="width: 15%;">Form</th>
                        <th style="width: 20%;">Parent/Guardian</th>
                        <th class="pe-4" style="width: 15%;">Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($registry)): ?>
                    <tr>
                        <td class="ps-4 text-nowrap"><span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($row['adm_no']); ?></span></td>
                        <td class="text-nowrap">
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <span class="small-text"><?php echo htmlspecialchars($row['email']); ?></span>
                        </td>
                        <td class="text-nowrap"><small><?php echo htmlspecialchars($row['gender']); ?></small></td>
                        <td class="text-nowrap"><span class="fw-bold text-indigo"><?php echo htmlspecialchars($row['form']); ?></span></td>
                        <td class="text-nowrap">
                            <div class="fw-bold small"><?php echo htmlspecialchars($row['parent_name']); ?></div>
                            <div class="small-text"><?php echo htmlspecialchars($row['parent_contact']); ?></div>
                        </td>
                        <td class="pe-4 text-nowrap"><small class="text-muted"><?php echo htmlspecialchars($row['address']); ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('sidebarOverlay');

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const submenu = button.nextElementSibling;
            const icon = button.querySelector('.bi-plus, .bi-dash');
            const isVisible = submenu.style.display === "block";
            
            submenu.style.display = isVisible ? "none" : "block";
            if(icon) {
                icon.classList.replace(isVisible ? 'bi-dash' : 'bi-plus', isVisible ? 'bi-plus' : 'bi-dash');
            }
        });
    });
</script>
</body>
</html>