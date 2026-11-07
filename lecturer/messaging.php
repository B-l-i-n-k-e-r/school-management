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
$staff_query = $conn->query("SELECT full_name, email, assigned_form FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$staff_name = $staff_data['full_name'] ?? 'Staff';
$staff_email = $staff_data['email'] ?? '';
$assigned_form = $staff_data['assigned_form'] ?? '';

$success_msg = '';
$error_msg   = '';

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_type = $_POST['recipient_type'] ?? 'admin';
    $audience_type  = $_POST['audience_type'] ?? 'single';
    $student_id     = (int) ($_POST['student_id'] ?? 0);
    $subject        = trim($_POST['subject'] ?? '');
    $content        = trim($_POST['content'] ?? '');

    if (empty($subject) || empty($content)) {
        $error_msg = "Please fill in both the subject and the message.";
    } elseif ($recipient_type === 'student' && $audience_type === 'single' && $student_id <= 0) {
        $error_msg = "Please select a specific student.";
    } elseif ($recipient_type === 'student' && $audience_type === 'all' && empty($assigned_form)) {
        $error_msg = "You need an assigned class to message all students.";
    } else {
        if ($recipient_type === 'admin') {
            $stmt = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, subject, content, is_read) VALUES ('staff', ?, ?, ?, ?, 0)");
            $stmt->bind_param("ssss", $staff_name, $staff_email, $subject, $content);
            if ($stmt->execute()) $success_msg = "Message sent to Admin successfully!";
            else $error_msg = "Error sending message: " . $stmt->error;
            $stmt->close();
        } else {
            if ($audience_type === 'single') {
                $st_lookup = $conn->prepare("SELECT email, full_name FROM students WHERE student_id = ? LIMIT 1");
                $st_lookup->bind_param("i", $student_id);
                $st_lookup->execute();
                $st_data = $st_lookup->get_result()->fetch_assoc();
                $st_lookup->close();
                
                $recipient_email = $st_data['email'] ?? '';
                
                if (!empty($recipient_email)) {
                    $stmt = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, recipient_email, subject, content, is_read) VALUES ('staff', ?, ?, ?, ?, ?, 0)");
                    $stmt->bind_param("sssss", $staff_name, $staff_email, $recipient_email, $subject, $content);
                    if ($stmt->execute()) $success_msg = "Message sent to student successfully!";
                    else $error_msg = "Error: " . $stmt->error;
                    $stmt->close();
                } else {
                    $error_msg = "Could not find the student's email address.";
                }
            } else {
                $st_lookup = $conn->prepare("SELECT email FROM students WHERE form = ? AND email IS NOT NULL AND email != ''");
                $st_lookup->bind_param("s", $assigned_form);
                $st_lookup->execute();
                $st_res = $st_lookup->get_result();
                
                $count = 0;
                $stmt = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, recipient_email, subject, content, is_read) VALUES ('staff', ?, ?, ?, ?, ?, 0)");
                
                while ($st = $st_res->fetch_assoc()) {
                    $recipient_email = $st['email'];
                    $stmt->bind_param("sssss", $staff_name, $staff_email, $recipient_email, $subject, $content);
                    if ($stmt->execute()) $count++;
                }
                $stmt->close();
                $st_lookup->close();
                
                if ($count > 0) $success_msg = "Message broadcasted to $count students in $assigned_form!";
                else $error_msg = "No students with valid emails found in your class.";
            }
        }
    }
}

// Fetch Announcements for Staff
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM notices WHERE target_audience IN ('Staff', 'Both') ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $announcements[] = $row;
$stmt->close();

// Fetch Message History
$messages = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE sender_email = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $staff_email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $messages[] = $row;
$stmt->close();

// Fetch Students
$students = [];
if (!empty($assigned_form)) {
    $stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
    $stmt->bind_param("s", $assigned_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $students[] = $row;
    $stmt->close();
}

$staff_display_name = $staff_name;
$active_page = 'messaging';
$extra_label = 'Communication Center';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging | Staff Portal</title>
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

        /* Sidebar */
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

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        /* Cards */
        .modern-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; }
        .form-label { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; transition: all 0.2s; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        /* Announcements */
        .notice-item { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }
        .notice-badge { font-size: 0.65rem; padding: 4px 8px; border-radius: 50px; font-weight: 700; text-transform: uppercase; }
        .badge-event { background: #dbeafe; color: #1e40af; }
        .badge-academic { background: #dcfce7; color: #166534; }
        .badge-emergency { background: #fee2e2; color: #991b1b; }
        .badge-general { background: #f1f5f9; color: #475569; }

        /* Messages */
        .msg-bubble { padding: 15px; border-radius: 12px; margin-bottom: 15px; }
        .msg-sent { background: #f1f5f9; border-left: 4px solid var(--primary-blue); }
        .msg-reply { background: #ecfdf5; border-left: 4px solid #10b981; margin-left: 20px; }

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
            <h2 class="fw-bold mb-1">Communication Center</h2>
            <p class="text-muted small mb-0">Read announcements and send messages to Admin or Students.</p>
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
        <!-- Left Side: Announcements -->
        <div class="col-lg-5">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2" style="color: #f59e0b;"></i>Staff Announcements</h5>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                        <h6 class="fw-bold text-muted">No announcements</h6>
                        <p class="text-muted small mb-0">Admin notices will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="announcement-list">
                        <?php foreach ($announcements as $not): 
                            $type = strtolower($not['type'] ?? 'general');
                        ?>
                            <div class="notice-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="notice-badge badge-<?php echo $type; ?>">
                                        <?php echo htmlspecialchars($not['type']); ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo date('d M Y', strtotime($not['created_at'])); ?>
                                    </small>
                                </div>
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($not['title']); ?></h6>
                                <div class="text-muted small" style="line-height: 1.5;">
                                    <?php 
                                        $clean_content = strip_tags($not['content']);
                                        $preview = substr($clean_content, 0, 150);
                                        echo htmlspecialchars($preview) . (strlen($clean_content) > 150 ? '...' : ''); 
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Messaging -->
        <div class="col-lg-7">
            <div class="modern-card h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-chat-dots-fill me-2" style="color: var(--primary-blue);"></i>Send a Message</h5>

                <!-- Send Form -->
                <form method="POST" class="mb-5 p-4 rounded-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    
                    <!-- Recipient Type Toggle -->
                    <div class="mb-3">
                        <label class="form-label">Send To</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="send_admin" value="admin" checked onchange="toggleRecipientOptions()">
                                <label class="form-check-label fw-bold" for="send_admin">Administration</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="send_student" value="student" onchange="toggleRecipientOptions()">
                                <label class="form-check-label fw-bold" for="send_student">Student(s)</label>
                            </div>
                        </div>
                    </div>

                    <!-- Student Audience Options (Hidden by default) -->
                    <div class="mb-3 d-none" id="studentOptionsDiv">
                        <label class="form-label">Message Audience</label>
                        <select name="audience_type" id="audience_type" class="form-select mb-2" onchange="toggleSingleStudent()">
                            <option value="all">All Students in <?php echo htmlspecialchars($assigned_form ?: 'my class'); ?></option>
                            <option value="single">A Specific Student</option>
                        </select>

                        <!-- Specific Student Dropdown (Hidden by default) -->
                        <div class="d-none" id="singleStudentDiv">
                            <select name="student_id" id="student_id" class="form-select">
                                <option value="" disabled selected>-- Choose Student --</option>
                                <?php foreach ($students as $st): ?>
                                    <option value="<?php echo $st['student_id']; ?>">
                                        <?php echo htmlspecialchars($st['full_name'] . ' (' . $st['adm_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g., Homework update or Exam reminder" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="content" class="form-control" rows="3" placeholder="Type your message here..." required></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn btn-primary w-100">
                        <i class="bi bi-send-fill me-2"></i> Send Message
                    </button>
                </form>

                <!-- Message History -->
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Recent Sent Messages</h6>
                
                <?php if (empty($messages)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted small mb-0">You haven't sent any messages yet.</p>
                    </div>
                <?php else: ?>
                    <div class="message-history" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                        <?php foreach ($messages as $msg): ?>
                            <!-- Teacher's Original Message -->
                            <div class="msg-bubble msg-sent">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 small text-muted"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></p>
                            </div>

                            <!-- Admin's Reply (if exists) -->
                            <?php if (!empty($msg['admin_reply'])): ?>
                                <div class="msg-bubble msg-reply mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong style="color: #047857; font-size: 0.9rem;"><i class="bi bi-reply-fill me-1"></i> Admin Reply</strong>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <?php echo !empty($msg['replied_at']) ? date('d M Y, H:i', strtotime($msg['replied_at'])) : ''; ?>
                                        </small>
                                    </div>
                                    <p class="mb-0 small text-dark"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                </div>
                            <?php endif; ?>
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

    // --- RECIPIENT TOGGLE LOGIC ---
    function toggleRecipientOptions() {
        const isStudent = document.getElementById('send_student').checked;
        const studentOptionsDiv = document.getElementById('studentOptionsDiv');
        
        if (isStudent) {
            studentOptionsDiv.classList.remove('d-none');
        } else {
            studentOptionsDiv.classList.add('d-none');
            document.getElementById('audience_type').value = 'all';
            toggleSingleStudent();
        }
    }

    function toggleSingleStudent() {
        const audienceType = document.getElementById('audience_type').value;
        const singleStudentDiv = document.getElementById('singleStudentDiv');
        const studentSelect = document.getElementById('student_id');

        if (audienceType === 'single') {
            singleStudentDiv.classList.remove('d-none');
            studentSelect.required = true;
        } else {
            singleStudentDiv.classList.add('d-none');
            studentSelect.required = false;
        }
    }
</script>
</body>
</html>