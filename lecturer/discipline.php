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

// Fetch disciplinary history
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
        /* Shared styles from your sample */
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-tint: #fff6cc;
            --accent-deep: #8a6a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 24px;
        }

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-danger { background: #dc2626; color: var(--white); }
        .ui-btn.is-danger:hover { background: #b91c1c; color: var(--white); }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        /* Incident Timeline List */
        .incident-item { border-left: 5px solid var(--line); background: var(--bg-light); border-radius: 12px; padding: 20px; margin-bottom: 16px; transition: all 0.2s; }
        .incident-item:hover { background: var(--white); box-shadow: 0 8px 24px -10px rgba(0,0,0,0.1); }
        
        .incident-minor { border-left-color: #f59e0b; } /* Yellow */
        .incident-major { border-left-color: #ea580c; } /* Orange */
        .incident-severe { border-left-color: #dc2626; } /* Red */
        
        .ui-icon { width: 38px; height: 38px; display: inline-grid; place-items: center; border: none; border-radius: 12px; background: var(--white); color: #b42318; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        .ui-icon:hover { background: #fdeaea; }

        @media (max-width: 992px) {
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="ui-head">
        <div>
            <h1>Discipline Management</h1>
            <p>Log and track behavioral incidents for <strong><?php echo htmlspecialchars($assigned_form ?: 'your class'); ?></strong></p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Log New Incident Form -->
        <div class="col-lg-4">
            <div class="panel h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-shield-exclamation me-2" style="color: #dc2626;"></i>Log Incident</h5>
                
                <?php if (!$assigned_form): ?>
                    <div class="ui-alert is-warning m-0"><i class="bi bi-exclamation-triangle-fill"></i><div>You have no assigned class. Contact admin.</div></div>
                <?php elseif (empty($students)): ?>
                    <div class="ui-alert is-info m-0"><i class="bi bi-info-circle-fill"></i><div>No students found in your class.</div></div>
                <?php else: ?>
                    <form method="POST">
                        <div class="ui-field mb-3">
                            <label>Select Student</label>
                            <select name="student_id" class="ui-input form-select" required>
                                <option value="" disabled selected>-- Choose Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['student_id']; ?>">
                                        <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['adm_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ui-field mb-3">
                            <label>Severity Level</label>
                            <select name="severity" class="ui-input form-select" required>
                                <option value="Minor">Minor (Warning)</option>
                                <option value="Major">Major (Detention/Parent Call)</option>
                                <option value="Severe">Severe (Suspension/Principal)</option>
                            </select>
                        </div>

                        <div class="ui-field mb-3">
                            <label>Date of Incident</label>
                            <input type="date" name="incident_date" class="ui-input" required max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="ui-field mb-4">
                            <label>Description</label>
                            <textarea name="description" class="ui-input" rows="4" placeholder="Describe what happened..." required></textarea>
                        </div>

                        <button type="submit" name="log_incident" class="ui-btn is-danger w-100">
                            <i class="bi bi-exclamation-triangle-fill"></i> Log Incident
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- History of Incidents -->
        <div class="col-lg-8">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2" style="color: var(--primary);"></i>Incident History</h5>
                    <span class="ui-badge is-navy"><?php echo count($incidents); ?> Records</span>
                </div>

                <?php if (empty($incidents)): ?>
                    <div class="ui-empty">
                        <i class="bi bi-emoji-smile text-success" style="opacity: 1;"></i>
                        <p>No incidents logged</p>
                        <small>Your class has a clean discipline record.</small>
                    </div>
                <?php else: ?>
                    <div class="incident-list">
                        <?php foreach ($incidents as $row): 
                            $severity_class = 'incident-' . strtolower($row['severity']);
                            $badge_class = match(strtolower($row['severity'])) {
                                'minor' => 'bg-warning text-dark',
                                'major' => 'bg-orange text-white', // Note: bg-orange is custom in bootstrap 5
                                'severe' => 'bg-danger text-white',
                                default => 'bg-secondary text-white'
                            };
                        ?>
                            <div class="incident-item <?php echo $severity_class; ?> d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <h6 class="fw-bold mb-0" style="color: var(--ink); font-size: 1.05rem;"><?php echo htmlspecialchars($row['full_name']); ?></h6>
                                        <span class="ui-badge is-navy" style="padding: 3px 10px; font-size: 0.7rem;"><?php echo htmlspecialchars($row['adm_no']); ?></span>
                                        <span class="badge <?php echo $badge_class; ?>" style="border-radius: 50px; font-weight: 800; font-size: 0.75rem;"><?php echo htmlspecialchars($row['severity']); ?></span>
                                    </div>
                                    <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    <div class="d-flex gap-3 small text-muted fw-bold">
                                        <span><i class="bi bi-calendar-x me-1 text-danger"></i> Incident: <?php echo date('d M Y', strtotime($row['incident_date'])); ?></span>
                                        <span><i class="bi bi-clock me-1 text-secondary"></i> Logged: <?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                                    </div>
                                </div>
                                <a href="?delete_id=<?php echo $row['id']; ?>" class="ui-icon" onclick="return confirm('Delete this incident record?');">
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