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
$staff_query = $conn->query("SELECT full_name, assigned_form FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$assigned_form = $staff_data['assigned_form'] ?? '';

$success_msg = '';
$error_msg   = '';

// Handle new incident submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_incident'])) {
    $student_id    = (int) ($_POST['student_id'] ?? 0);
    $severity      = trim($_POST['severity'] ?? '');
    $incident_date = trim($_POST['incident_date'] ?? '');
    $description   = trim($_POST['description'] ?? '');

    if ($student_id <= 0 || empty($severity) || empty($incident_date) || empty($description)) {
        $error_msg = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO discipline (student_id, teacher_id, severity, incident_date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $student_id, $staff_id, $severity, $incident_date, $description);
        
        if ($stmt->execute()) {
            $success_msg = "Incident logged successfully!";
        } else {
            $error_msg = "Error logging incident: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    // Only allow the teacher who logged it to delete it
    $del_stmt = $conn->prepare("DELETE FROM discipline WHERE id = ? AND teacher_id = ?");
    $del_stmt->bind_param("ii", $del_id, $staff_id);
    if ($del_stmt->execute()) {
        $success_msg = "Incident record deleted.";
    }
    $del_stmt->close();
}

// Fetch students in this teacher's class
$students = [];
if ($assigned_form) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

// Fetch disciplinary history for this teacher's class
$incidents = [];
$query = "SELECT d.*, s.full_name, s.adm_no 
          FROM discipline d 
          JOIN students s ON d.student_id = s.student_id 
          WHERE s.form = ? 
          ORDER BY d.incident_date DESC, d.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $incidents[] = $row;
    $stmt->close();
}

$active_page = 'discipline';
$extra_label = 'Discipline Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discipline | Staff Portal</title>
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

        /* Sidebar Styles */
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

        /* Custom Component Styles */
        .modern-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; }
        .form-label { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        /* Incident Timeline List */
        .incident-item { border-left: 5px solid #e2e8f0; background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 15px; transition: 0.2s; }
        .incident-item:hover { background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .incident-minor { border-left-color: #f59e0b; } /* Yellow */
        .incident-major { border-left-color: #ea580c; } /* Orange */
        .incident-severe { border-left-color: #dc2626; } /* Red */

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
            <h2 class="fw-bold mb-1">Discipline Management</h2>
            <p class="text-muted small mb-0">Log and track behavioral incidents for <?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></p>
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
        <!-- Log New Incident Form -->
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-shield-exclamation me-2" style="color: #dc2626;"></i>Log Incident</h5>
                
                <?php if (!$assigned_form): ?>
                    <div class="alert alert-warning border-0 rounded-3 small">
                        You have no assigned class. Contact admin.
                    </div>
                <?php elseif (empty($students)): ?>
                    <div class="alert alert-info border-0 rounded-3 small">
                        No students found in your class.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="" disabled selected>-- Choose Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['student_id']; ?>">
                                        <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['adm_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Severity Level</label>
                            <select name="severity" class="form-select" required>
                                <option value="Minor">Minor (Warning)</option>
                                <option value="Major">Major (Detention/Parent Call)</option>
                                <option value="Severe">Severe (Suspension/Principal)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Incident</label>
                            <input type="date" name="incident_date" class="form-control" required max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Describe what happened..." required></textarea>
                        </div>

                        <button type="submit" name="log_incident" class="btn btn-primary w-100" style="background:#dc2626;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Log Incident
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- History of Incidents -->
        <div class="col-lg-8">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2" style="color: var(--primary-blue);"></i>Incident History</h5>
                    <span class="badge bg-light text-dark border"><?php echo count($incidents); ?> Records</span>
                </div>

                <?php if (empty($incidents)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-emoji-smile fs-1 text-success d-block mb-3"></i>
                        <h6 class="fw-bold text-muted">No incidents logged</h6>
                        <p class="text-muted small mb-0">Your class has a clean discipline record.</p>
                    </div>
                <?php else: ?>
                    <div class="incident-list">
                        <?php foreach ($incidents as $row): 
                            $severity_class = 'incident-' . strtolower($row['severity']);
                        ?>
                            <div class="incident-item <?php echo $severity_class; ?> d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($row['full_name']); ?></h6>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['adm_no']); ?></span>
                                        <span class="badge bg-danger"><?php echo htmlspecialchars($row['severity']); ?></span>
                                    </div>
                                    <p class="text-muted small mb-2 mt-2"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    <div class="d-flex gap-3 small text-muted">
                                        <span><i class="bi bi-calendar-x me-1"></i> Incident: <strong><?php echo date('d M Y', strtotime($row['incident_date'])); ?></strong></span>
                                        <span><i class="bi bi-clock me-1"></i> Logged: <?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                                    </div>
                                </div>
                                <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this incident record?');">
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
    // Sidebar Toggle Logic
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

    // Submenu Toggle Logic
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