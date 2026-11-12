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
$staff_query = $conn->query("SELECT full_name, email FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$staff_name = $staff_data['full_name'] ?? 'Staff';
$staff_email = $staff_data['email'] ?? '';

// --- Fetch ONLY the pairs this teacher is assigned to ---
$teacher_assignments = [];
$assign_query = $conn->query("
    SELECT ta.class_name, ta.subject_id, s.subject_name 
    FROM teacher_assignments ta 
    JOIN subjects s ON ta.subject_id = s.subject_id 
    WHERE ta.teacher_id = $staff_id
");
if ($assign_query) {
    while ($row = $assign_query->fetch_assoc()) {
        $teacher_assignments[] = $row;
    }
}

$success_msg = '';
$error_msg   = '';

// Handle new submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = trim($_POST['due_date'] ?? '');
    $type        = trim($_POST['assignment_type'] ?? 'Assignment');
    
    // These come from the single dropdown selector
    $selected_pair = $_POST['selected_pair'] ?? '';
    $pair_parts    = explode('|', $selected_pair);
    $class_name    = $pair_parts[0] ?? '';
    $subject_id    = (int) ($pair_parts[1] ?? 0);

    $file_path = null;

    // Handle File Upload
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/assignments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $fileName = time() . '_' . basename($_FILES['assignment_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $targetPath)) {
            $file_path = $targetPath;
        } else {
            $error_msg = "Failed to upload file.";
        }
    }

    if (empty($title) || empty($description) || empty($due_date) || empty($class_name) || $subject_id <= 0) {
        $error_msg = "Please fill in all required fields and select an assignment target.";
    } elseif (empty($error_msg)) {
        // Insert Assignment (CORRECTED bind_param string 'iissssss')
        $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, subject_id, class_name, title, description, due_date, assignment_type, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssss", $staff_id, $subject_id, $class_name, $title, $description, $due_date, $type, $file_path);
        
        if ($stmt->execute()) {
            $success_msg = "$type posted successfully for $class_name!";
            
            // --- NOTIFICATION LOGIC ---
            $st_lookup = $conn->prepare("SELECT email FROM students WHERE form = ? AND email IS NOT NULL AND email != ''");
            $st_lookup->bind_param("s", $class_name);
            $st_lookup->execute();
            $st_res = $st_lookup->get_result();
            
            $msg_subject = "New $type: $title";
            $msg_content = "A new $type has been posted for $class_name. Due date: " . date('d M Y', strtotime($due_date)) . ". Please check your assignments page.";
            
            $msg_stmt = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, recipient_email, subject, content, is_read) VALUES ('staff', ?, ?, ?, ?, ?, 0)");
            
            $count = 0;
            while ($st = $st_res->fetch_assoc()) {
                $recipient_email = $st['email'];
                $msg_stmt->bind_param("sssss", $staff_name, $staff_email, $recipient_email, $msg_subject, $msg_content);
                if ($msg_stmt->execute()) $count++;
            }
            $msg_stmt->close();
            $st_lookup->close();
            
            if ($count > 0) $success_msg .= " Notifications sent to $count students.";
            
        } else {
            $error_msg = "Error creating record: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    
    // Delete file if exists
    $f_stmt = $conn->prepare("SELECT file_path FROM assignments WHERE assignment_id = ? AND teacher_id = ?");
    $f_stmt->bind_param("ii", $del_id, $staff_id);
    $f_stmt->execute();
    $f_res = $f_stmt->get_result()->fetch_assoc();
    if ($f_res && !empty($f_res['file_path']) && file_exists($f_res['file_path'])) {
        unlink($f_res['file_path']);
    }
    $f_stmt->close();

    $del_stmt = $conn->prepare("DELETE FROM assignments WHERE assignment_id = ? AND teacher_id = ?");
    $del_stmt->bind_param("ii", $del_id, $staff_id);
    if ($del_stmt->execute()) {
        $success_msg = "Record deleted.";
    }
    $del_stmt->close();
}

// Fetch all assignments by this teacher
$assignments = [];
$stmt = $conn->prepare("SELECT a.*, s.subject_name FROM assignments a LEFT JOIN subjects s ON a.subject_id = s.subject_id WHERE a.teacher_id = ? ORDER BY a.created_at DESC");
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

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .assignment-item { border-left: 5px solid var(--primary); background: var(--bg-light); border-radius: 12px; padding: 20px; margin-bottom: 16px; transition: all 0.2s; }
        .assignment-item:hover { background: var(--white); box-shadow: 0 8px 24px -10px rgba(0,0,0,0.1); }
        
        .ui-icon { width: 38px; height: 38px; display: inline-grid; place-items: center; border: none; border-radius: 12px; background: var(--white); color: #b42318; font-size: 1rem; cursor: pointer; transition: background 0.2s; }
        .ui-icon:hover { background: #fdeaea; }

        .badge { padding: 6px 12px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; }
        .badge.assignment { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge.cat { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="ui-head">
        <div>
            <h1>Class Assignments & CATs</h1>
            <p>Create and manage homework and continuous assessment tests.</p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Create Assignment Form -->
        <div class="col-lg-5">
            <div class="panel h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle-fill me-2" style="color: var(--primary);"></i>New Upload</h5>
                
                <?php if (empty($teacher_assignments)): ?>
                    <div class="ui-alert is-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>You have no assigned classes or subjects. Please contact the admin to update your profile.</div>
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 ui-field">
                                <label>Type</label>
                                <select name="assignment_type" class="ui-input form-select" required>
                                    <option value="Assignment">Assignment</option>
                                    <option value="CAT">CAT (Test)</option>
                                </select>
                            </div>
                            <div class="col-md-6 ui-field">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="ui-input" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="ui-field mb-3">
                            <label>Target Class & Subject</label>
                            <select name="selected_pair" class="ui-input form-select" required>
                                <option value="" disabled selected>-- Choose Target --</option>
                                <?php foreach ($teacher_assignments as $ta): ?>
                                    <option value="<?php echo htmlspecialchars($ta['class_name'] . '|' . $ta['subject_id']); ?>">
                                        <?php echo htmlspecialchars($ta['class_name'] . ' - ' . $ta['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ui-field mb-3">
                            <label>Title</label>
                            <input type="text" name="title" class="ui-input" placeholder="e.g., Chapter 4 Exercises" required>
                        </div>

                        <div class="ui-field mb-3">
                            <label>Instructions</label>
                            <textarea name="description" class="ui-input" rows="3" placeholder="Provide details..." required></textarea>
                        </div>

                        <div class="ui-field mb-4">
                            <label>Attach File (Optional)</label>
                            <input type="file" name="assignment_file" class="ui-input" style="padding: 9px 12px;">
                            <small class="text-muted mt-1 d-block">Upload PDF, DOC, or Image.</small>
                        </div>

                        <button type="submit" name="create_assignment" class="ui-btn is-primary w-100">
                            <i class="bi bi-cloud-upload-fill"></i> Post & Notify Students
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- List of Assignments -->
        <div class="col-lg-7">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-journal-text me-2" style="color: var(--primary);"></i>Recently Posted</h5>
                    <span class="ui-badge is-navy" style="background: var(--bg-light); color: var(--text-muted); border: 1px solid var(--line);"><?php echo count($assignments); ?> Total</span>
                </div>

                <?php if (empty($assignments)): ?>
                    <div class="ui-empty">
                        <i class="bi bi-inbox"></i>
                        <p>Nothing posted yet</p>
                        <small>Upload your first assignment or CAT.</small>
                    </div>
                <?php else: ?>
                    <div class="assignment-list">
                        <?php foreach ($assignments as $row): 
                            $type_badge = strtolower($row['assignment_type'] ?? 'assignment') === 'cat' ? 'cat' : 'assignment';
                        ?>
                            <div class="assignment-item d-flex justify-content-between align-items-start">
                                <div style="flex: 1;">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge <?php echo $type_badge; ?>"><?php echo htmlspecialchars($row['assignment_type'] ?? 'Assignment'); ?></span>
                                        <span class="badge" style="background: var(--primary-tint); color: var(--primary); border: none;"><i class="bi bi-bookmark me-1"></i><?php echo htmlspecialchars($row['class_name']); ?></span>
                                        <span class="badge" style="background: var(--bg-light); color: var(--text-muted); border: 1px solid var(--line);"><?php echo htmlspecialchars($row['subject_name'] ?? '—'); ?></span>
                                    </div>
                                    <h6 class="fw-bold mb-1" style="color: var(--ink); font-size: 1.05rem;"><?php echo htmlspecialchars($row['title']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    
                                    <div class="d-flex flex-wrap gap-3 small text-muted fw-bold mt-3">
                                        <span><i class="bi bi-calendar-event me-1 text-primary"></i> Due: <?php echo date('d M Y', strtotime($row['due_date'])); ?></span>
                                        <?php if (!empty($row['file_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download class="text-decoration-none" style="color: var(--accent-deep);">
                                                <i class="bi bi-paperclip me-1"></i> Download File
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="?delete_id=<?php echo $row['assignment_id']; ?>" class="ui-icon" onclick="return confirm('Delete this record?');">
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