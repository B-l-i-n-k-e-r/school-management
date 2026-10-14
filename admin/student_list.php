<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// --- 1. HANDLE SAVING (NEW OR UPDATE) ---
if (isset($_POST['enroll_student'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']); 
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
    $parent_contact = mysqli_real_escape_string($conn, $_POST['parent_contact']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    $student_id = isset($_POST['student_id']) && !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;

    if ($student_id) {
        $stmt = $conn->prepare("UPDATE students SET full_name=?, email=?, gender=?, form=?, parent_name=?, parent_contact=?, address=? WHERE student_id=?");
        $stmt->bind_param("sssssssi", $full_name, $email, $gender, $form, $parent_name, $parent_contact, $address, $student_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Profile updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Update failed.</div>";
        }
    } else {
        $adm_no = "ADM-" . rand(1000, 9999);
        $hashed_pass = password_hash("123456", PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
            $stmt1->bind_param("sss", $adm_no, $email, $hashed_pass);
            $stmt1->execute();
            $user_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO students (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, address, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isssssssss", $user_id, $full_name, $adm_no, $gender, $form, $parent_name, $parent_contact, $email, $address, $hashed_pass);
            $stmt2->execute();

            $conn->commit();
            $message = "<div class='alert alert-success border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Enrollment Successful! ADM: <strong>$adm_no</strong></div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Error: Duplicate Email or Database Issue.</div>";
        }
    }
}

// --- 2. HANDLE DELETION ---
if (isset($_POST['delete_student_id'])) {
    $sid = (int)$_POST['delete_student_id'];
    $res = $conn->query("SELECT user_id FROM students WHERE student_id = $sid");
    if($res && $res->num_rows > 0) {
        $uid = $res->fetch_assoc()['user_id'];
        $conn->query("DELETE FROM students WHERE student_id = $sid");
        $conn->query("DELETE FROM users WHERE user_id = $uid");
        $message = "<div class='alert alert-info border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Student record removed.</div>";
    }
}

$students = $conn->query("SELECT * FROM students ORDER BY student_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | BokinceX School</title>
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

        /* Sidebar & Custom Scrollbar */
        .sidebar {
            width: 260px; height: 100vh;
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

        /* Fit Content Columns */
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.expand { width: auto; white-space: normal; }

        .topbar {
            margin-left: 260px; background: var(--topbar-bg);
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .main-content { margin-left: 260px; padding: 40px; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
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
                <li><a href="admission.php">New Admission</a></li>
                <li><a href="student_list.php" style="color: #f6e05e;">Manage Students</a></li>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Student Directory</h2>
            <p class="text-muted small">View and manage all registered students</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light border rounded-pill px-3 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Export
            </button>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openEnrollModal()">
                <i class="bi bi-plus-lg me-2"></i>Enroll Student
            </button>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="fw-bold m-0" id="enrollModalLabel">Student Enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="student_id" id="form_student_id">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="full_name" id="form_full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email Address</label>
                                <input type="email" name="email" id="form_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Gender</label>
                                <select name="gender" id="form_gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Level / Form</label>
                                <select name="form" id="form_form" class="form-select" required>
                                    <option>Form 1</option>
                                    <option>Form 2</option>
                                    <option>Form 3</option>
                                    <option>Form 4</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Parent Name</label>
                                <input type="text" name="parent_name" id="form_parent_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Parent Contact</label>
                                <input type="text" name="parent_contact" id="form_parent_contact" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Address</label>
                                <input type="text" name="address" id="form_address" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pb-4 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="enroll_student" id="form_submit_btn" class="btn btn-primary rounded-pill px-4">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card adm-card overflow-hidden">
        <div class="card-header bg-white py-4 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0 text-primary">Student Registry</h5>
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                <input type="text" id="studentSearch" class="form-control bg-light border-0" placeholder="Search registry...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="studentTable">
                <thead class="bg-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4">ADM NO</th>
                        <th class="expand">Student Details</th>
                        <th>Form</th>
                        <th class="expand">Parent/Guardian</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $students->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4"><span class="badge bg-secondary rounded-pill"><?php echo $row['adm_no']; ?></span></td>
                        <td class="expand">
                            <div class="fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($row['email']); ?> | <?php echo $row['gender']; ?></div>
                        </td>
                        <td><span class="fw-bold text-primary"><?php echo $row['form']; ?></span></td>
                        <td class="expand">
                            <div class="small fw-bold"><?php echo htmlspecialchars($row['parent_name']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($row['parent_contact']); ?></div>
                        </td>
                        <td class="text-center pe-4">
                            <button class="btn btn-link text-primary p-0 me-2" onclick='editStudent(<?php echo json_encode($row); ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?');">
                                <input type="hidden" name="delete_student_id" value="<?php echo $row['student_id']; ?>">
                                <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash3-fill"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const enrollModal = new bootstrap.Modal(document.getElementById('enrollModal'));

    function openEnrollModal() {
        document.querySelector('form').reset();
        document.getElementById('form_student_id').value = "";
        document.getElementById('enrollModalLabel').innerText = "New Student Enrollment";
        document.getElementById('form_submit_btn').innerText = "Complete Enrollment";
        enrollModal.show();
    }

    function editStudent(data) {
        document.getElementById('form_student_id').value = data.student_id;
        document.getElementById('form_full_name').value = data.full_name;
        document.getElementById('form_email').value = data.email;
        document.getElementById('form_gender').value = data.gender;
        document.getElementById('form_form').value = data.form;
        document.getElementById('form_parent_name').value = data.parent_name;
        document.getElementById('form_parent_contact').value = data.parent_contact;
        document.getElementById('form_address').value = data.address;

        document.getElementById('enrollModalLabel').innerText = "Edit Student Profile";
        document.getElementById('form_submit_btn').innerText = "Update Record";
        enrollModal.show();
    }

    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('sidebarOverlay');

    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    document.getElementById('studentSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#studentTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const submenu = button.nextElementSibling;
            const icon = button.querySelector('.bi-plus, .bi-dash');
            const isVisible = submenu.style.display === "block";
            submenu.style.display = isVisible ? "none" : "block";
            if(icon) icon.classList.replace(isVisible ? 'bi-dash' : 'bi-plus', isVisible ? 'bi-plus' : 'bi-dash');
        });
    });
</script>
</body>
</html>