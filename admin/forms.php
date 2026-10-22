<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Adding New Class ----
if (isset($_POST['add_form'])) {
    $form_name = trim($_POST['form_name'] ?? '');

    if ($form_name === '') {
        $message = "Please enter a level name.";
        $message_type = 'warning';
    } else {
        // Detect level & curriculum based on name pattern
        if (stripos($form_name, 'form') !== false) {
            $level = 'Secondary';
            $curr  = '8-4-4';
            $order = 10 + (intval(preg_replace('/\D/', '', $form_name)) ?: 0);
        } else {
            $level = 'Primary';
            $curr  = 'CBC';
            $order = intval(preg_replace('/\D/', '', $form_name)) ?: 99;
        }

        $stmt = $conn->prepare("INSERT INTO classes (name, level, curriculum, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $form_name, $level, $curr, $order);
        if ($stmt->execute()) {
            $message = "New academic level <strong>" . htmlspecialchars($form_name) . "</strong> added successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: forms.php?deleted=1");
    exit();
}

// ---- Flash for delete ----
if (isset($_GET['deleted'])) {
    $message = "Academic level removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all classes + student counts ----
$classes = [];
$query = "
    SELECT c.*,
        (SELECT COUNT(*) FROM students s WHERE TRIM(s.form) = TRIM(c.name)) AS student_count
    FROM classes c
    ORDER BY c.sort_order ASC, c.name ASC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $classes[] = $row;
}

$active_page = 'forms';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        /* Cards */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s; }
        .adm-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

        .icon-box {
            width: 48px; height: 48px; background: #eef2ff; color: var(--primary-blue);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }

        .class-badge {
            background: #eef2ff;
            color: var(--primary-blue);
            font-weight: 600;
        }

        /* Modal */
        .modal-content { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .modal-header { border: 0; padding: 24px 24px 0; }
        .modal-body { padding: 24px; }
        .modal-footer { border: 0; padding: 0 24px 24px; }

        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select {
            border-radius: 12px; padding: 12px 15px;
            border: 1px solid #e2e8f0; background-color: #f8fafc;
            font-size: 0.9rem; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12);
            border-color: var(--primary-blue);
            background: #fff;
        }

        .btn-primary {
            background: var(--primary-blue); border: none;
            padding: 14px; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

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

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Academic Levels</h2>
            <p class="text-muted small mb-0">Configure and manage your school's Form structures</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addFormModal">
            <i class="bi bi-plus-lg me-2"></i>Create New Level
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (empty($classes)): ?>
            <div class="col-12">
                <div class="card p-5 text-center">
                    <i class="bi bi-mortarboard fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-muted">No academic levels yet</h5>
                    <p class="text-muted mb-3">Click <strong>Create New Level</strong> to add Form 1, Grade 1, etc.</p>
                    <button class="btn btn-primary rounded-pill px-4 mx-auto" style="width: fit-content;"
                            data-bs-toggle="modal" data-bs-target="#addFormModal">
                        <i class="bi bi-plus-lg me-2"></i>Create New Level
                    </button>
                </div>
            </div>
        <?php else: foreach ($classes as $row): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card adm-card p-4">
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
                                    <a class="dropdown-item text-danger small"
                                       href="?delete=<?php echo intval($row['class_id']); ?>"
                                       onclick="return confirm('Delete level?')">
                                        <i class="bi bi-trash me-2"></i>Delete Level
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($row['name']); ?></h5>
                    <small class="text-muted d-block mb-2">
                        <?php echo htmlspecialchars($row['level']); ?>
                        &middot;
                        <?php echo htmlspecialchars($row['curriculum']); ?>
                    </small>
                    <span class="badge rounded-pill mt-2 px-3 py-2 class-badge" style="width: fit-content;">
                        <i class="bi bi-people me-1"></i>
                        <?php echo intval($row['student_count']); ?>
                        Student<?php echo $row['student_count'] == 1 ? '' : 's'; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<!-- Add Level Modal -->
<div class="modal fade" id="addFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-bold mb-0">New Academic Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <label class="form-label">Level Name</label>
                    <input type="text" name="form_name" class="form-control" placeholder="e.g. Form 1 or Grade 7" required>
                    <p class="text-muted small mt-2 mb-0">
                        The system will automatically classify it as <strong>Primary (CBC)</strong> or <strong>Secondary (8-4-4)</strong> based on the name.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_form" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> Create Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>
</body>
</html>