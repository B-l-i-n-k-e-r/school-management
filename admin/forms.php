<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// ---- Handle Adding New Class ----
if(isset($_POST['add_form'])) {
    $form_name = trim($_POST['form_name']);
    // Detect level & curriculum based on name pattern
    if (stripos($form_name, 'form') !== false) {
        $level = 'Secondary';
        $curr  = '8-4-4';
        // Sort order: Form 1 → 10, Form 2 → 11, etc.
        $order = 10 + (intval(preg_replace('/\D/', '', $form_name)) ?: 0);
    } else {
        $level = 'Primary';
        $curr  = 'CBC';
        $order = intval(preg_replace('/\D/', '', $form_name)) ?: 99;
    }

    $stmt = $conn->prepare("INSERT INTO classes (name, level, curriculum, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $form_name, $level, $curr, $order);
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm py-2 px-3 mx-3 mt-3'>New academic level added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Error: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

// ---- Handle Delete ----
if(isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: forms.php");
    exit();
}

// ---- Fetch all classes + student counts ----
$query = "
    SELECT c.*, 
        (SELECT COUNT(*) FROM students s WHERE TRIM(s.form) = TRIM(c.name)) AS student_count
    FROM classes c
    ORDER BY c.sort_order ASC, c.name ASC
";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | The Michaelson School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-indigo: #6366f1; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; }
        .sidebar-header { background: #dee2e6; padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 1rem; }
        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link i:first-child { font-size: 1.2rem; width: 30px; }
        .nav-link span { flex-grow: 1; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }
        .submenu { background: var(--submenu-bg); list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }
        .topbar { margin-left: 260px; background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: 260px; padding: 40px; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .icon-box { width: 48px; height: 48px; background: #eef2ff; color: var(--primary-indigo); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/45'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav flex-column">
        <div class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn"><i class="bi bi-people-fill"></i><span>Students</span><i class="bi bi-plus small"></i></a>
            <ul class="submenu">
                <li><a href="admission.php">New Admission</a></li>
                <li><a href="student_list.php">Manage Students</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn active"><i class="bi bi-journal-bookmark-fill"></i><span>Academics</span><i class="bi bi-dash small"></i></a>
            <ul class="submenu" style="display: block;">
                <li><a href="forms.php" style="color: #f6e05e;">Manage Forms</a></li>
                <li><a href="subjects.php">Manage Subjects</a></li>
                <li><a href="exams.php">Exam Schedules</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn"><i class="bi bi-cash-stack"></i><span>Finance</span><i class="bi bi-plus small"></i></a>
            <ul class="submenu">
                <li><a href="fee_structure.php">Fee Structure</a></li>
                <li><a href="collections.php">Fee Collections</a></li>
                <li><a href="reports.php">Financial Reports</a></li>
            </ul>
        </div>

        <div class="nav-item"><a href="staff.php" class="nav-link"><i class="bi bi-person-badge-fill"></i><span>Staff Directory</span></a></div>
        
        <div class="nav-item mt-4">
            <a href="adminLogOut.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
        </div>
    </div>
</div>

<div class="topbar">
    <i class="bi bi-list" id="hamburger"></i>
    <div class="dropdown text-nowrap">
        <button class="btn btn-light dropdown-toggle border-0 fw-bold" type="button" data-bs-toggle="dropdown">ADMIN</button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="adminLogOut.php">Logout</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Academic Levels</h2>
            <p class="text-muted small">Configure and manage your school's Form structures</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addFormModal">
            <i class="bi bi-plus-lg me-2"></i>Create New Level
        </button>
    </div>

    <?php echo $message; ?>

    <div class="row g-4">
        <?php if (mysqli_num_rows($result) === 0): ?>
            <div class="col-12">
                <div class="card p-5 text-center text-muted">
                    <i class="bi bi-mortarboard fs-1 d-block mb-3"></i>
                    <h5>No academic levels yet</h5>
                    <p class="mb-0">Click <strong>Create New Level</strong> to add Form 1, Grade 1, etc.</p>
                </div>
            </div>
        <?php else: while($row = mysqli_fetch_assoc($result)): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="icon-box"><i class="bi bi-mortarboard-fill"></i></div>
                        <div class="dropdown">
                            <i class="bi bi-three-dots-vertical" data-bs-toggle="dropdown" style="cursor:pointer"></i>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow text-nowrap">
                                <li>
                                    <a class="dropdown-item small" href="manage_timetable.php?form=<?php echo urlencode($row['name']); ?>">
                                        <i class="bi bi-calendar3 me-2 text-primary"></i>Manage Timetable
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger small" href="?delete=<?php echo $row['class_id']; ?>" onclick="return confirm('Delete level?')">
                                        <i class="bi bi-trash me-2"></i>Delete Level
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($row['name']); ?></h5>
                    <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($row['level']); ?> &middot; <?php echo htmlspecialchars($row['curriculum']); ?></small>
                    <span class="badge rounded-pill mt-2 px-3 py-2" style="background:#eef2ff; color: #6366f1; width: fit-content;">
                        <i class="bi bi-people me-1"></i> <?php echo $row['student_count']; ?> Student<?php echo $row['student_count'] == 1 ? '' : 's'; ?>
                    </span>
                </div>
            </div>
        <?php endwhile; endif; ?>
    </div>
</main>

<div class="modal fade" id="addFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">New Academic Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Level Name</label>
                    <input type="text" name="form_name" class="form-control py-2 rounded-3" placeholder="e.g. Form 1 or Grade 7" required>
                    <p class="text-muted small mt-2">Enter the name of the class or grade level. The system will automatically classify it as Primary (CBC) or Secondary (8-4-4) based on the name.</p>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="add_form" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm">Create Level</button>
                </div>
            </form>
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
            if(icon) icon.classList.replace(isVisible ? 'bi-dash' : 'bi-plus', isVisible ? 'bi-plus' : 'bi-dash');
        });
    });
</script>
</body>
</html>