<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name, assigned_form, assigned_subject FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];

$assigned_form         = $staff_data['assigned_form']    ?? '';
$assigned_subject_name = $staff_data['assigned_subject'] ?? '';

// Look up subject_id
$subject_id = 0;
if ($assigned_subject_name) {
    $sub_lookup = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ? LIMIT 1");
    $sub_lookup->bind_param("s", $assigned_subject_name);
    $sub_lookup->execute();
    $sub_res = $sub_lookup->get_result()->fetch_assoc();
    $subject_id = (int) ($sub_res['subject_id'] ?? 0);
    $sub_lookup->close();
}

$success_msg = '';
$error_msg   = '';

// Handle new assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = trim($_POST['due_date'] ?? '');

    if (empty($title) || empty($description) || empty($due_date)) {
        $error_msg = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, subject_id, class_name, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $staff_id, $subject_id, $assigned_form, $title, $description, $due_date);
        
        if ($stmt->execute()) {
            $success_msg = "Assignment created successfully!";
        } else {
            $error_msg = "Error creating assignment: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $del_stmt = $conn->prepare("DELETE FROM assignments WHERE assignment_id = ? AND teacher_id = ?");
    $del_stmt->bind_param("ii", $del_id, $staff_id);
    if ($del_stmt->execute()) {
        $success_msg = "Assignment deleted.";
    }
    $del_stmt->close();
}

// Fetch all assignments by this teacher
$assignments = [];
$stmt = $conn->prepare("SELECT * FROM assignments WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $assignments[] = $row;
$stmt->close();

$active_page = 'assignments';
$extra_label = 'Manage Assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --sidebar-width: 240px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        /* Sidebar Styles (Keep your existing ones) */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; border-radius: 5px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; text-transform: uppercase; }
        .nav-item-container { border-bottom: 1px solid #4a5568; }
        .nav-link-custom { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }
        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        /* Modern Card Styles */
        .modern-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; }
        .form-label { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; transition: all 0.2s; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .assignment-item { border-left: 4px solid var(--primary-blue); background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: 0.2s; }
        .assignment-item:hover { background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="fw-bold mb-1">Class Assignments</h2>
            <p class="text-muted small mb-0">Create and manage homework for <?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Create Assignment Form -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle-fill me-2" style="color: var(--primary-blue);"></i>New Assignment</h5>
                
                <?php if (!$assigned_form): ?>
                    <div class="alert alert-warning border-0 rounded-3 small">
                        You have no assigned class. Contact admin.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Assignment Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Chapter 4 Exercises" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Instructions / Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Provide details about the assignment..." required></textarea>
                        </div>

                        <button type="submit" name="create_assignment" class="btn btn-primary w-100">
                            <i class="bi bi-send-fill me-2"></i> Post Assignment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- List of Assignments -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2" style="color: var(--primary-blue);"></i>Posted Assignments</h5>
                    <span class="badge bg-light text-dark border"><?php echo count($assignments); ?> Total</span>
                </div>

                <?php if (empty($assignments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                        <h6 class="fw-bold text-muted">No assignments yet</h6>
                        <p class="text-muted small mb-0">Create your first assignment using the form.</p>
                    </div>
                <?php else: ?>
                    <div class="assignment-list">
                        <?php foreach ($assignments as $row): ?>
                            <div class="assignment-item d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($row['title']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    <div class="d-flex gap-3 small text-muted">
                                        <span><i class="bi bi-calendar-event me-1"></i> Due: <strong><?php echo date('d M Y', strtotime($row['due_date'])); ?></strong></span>
                                        <span><i class="bi bi-clock me-1"></i> Posted: <?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                                    </div>
                                </div>
                                <a href="?delete_id=<?php echo $row['assignment_id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this assignment?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            } else {
                document.body.classList.toggle('sidebar-toggled');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if (icon) icon.classList.replace(isClosed ? 'bi-plus' : 'bi-dash', isClosed ? 'bi-dash' : 'bi-plus');
        });
    });
</script>
</body>
</html>