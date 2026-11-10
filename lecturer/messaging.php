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

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .notice-item { border-bottom: 1px solid var(--line); padding: 15px 0; }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }
        .notice-badge { font-size: 0.65rem; padding: 4px 8px; border-radius: 50px; font-weight: 800; text-transform: uppercase; }
        .badge-event { background: #dbeafe; color: #1e40af; }
        .badge-academic { background: #dcfce7; color: #166534; }
        .badge-emergency { background: #fee2e2; color: #991b1b; }
        .badge-general { background: #f1f5f9; color: #475569; }

        .msg-bubble { padding: 15px; border-radius: 12px; margin-bottom: 15px; }
        .msg-sent { background: var(--bg-light); border-left: 4px solid var(--primary); }
        .msg-reply { background: #ecfdf5; border-left: 4px solid #10b981; margin-left: 20px; }

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
            <h1>Communication Center</h1>
            <p>Read announcements and send messages to Admin or Students.</p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Side: Announcements -->
        <div class="col-lg-5">
            <div class="panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2" style="color: #f59e0b;"></i>Staff Announcements</h5>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="ui-empty" style="padding: 20px;">
                        <i class="bi bi-inbox"></i>
                        <p>No announcements</p>
                        <small>Admin notices will appear here.</small>
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
            <div class="panel h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-chat-dots-fill me-2" style="color: var(--primary);"></i>Send a Message</h5>

                <!-- Send Form -->
                <form method="POST" class="mb-5 p-4 rounded-4" style="background: var(--bg-light); border: 1px solid var(--line);">
                    
                    <div class="ui-field mb-3">
                        <label>Send To</label>
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

                    <div class="mb-3 d-none" id="studentOptionsDiv">
                        <label class="form-label small fw-bold text-muted text-uppercase">Audience</label>
                        <select name="audience_type" id="audience_type" class="ui-input form-select mb-2" onchange="toggleSingleStudent()">
                            <option value="all">All Students in <?php echo htmlspecialchars($assigned_form ?: 'my class'); ?></option>
                            <option value="single">A Specific Student</option>
                        </select>

                        <div class="d-none" id="singleStudentDiv">
                            <select name="student_id" id="student_id" class="ui-input form-select">
                                <option value="" disabled selected>-- Choose Student --</option>
                                <?php foreach ($students as $st): ?>
                                    <option value="<?php echo $st['student_id']; ?>">
                                        <?php echo htmlspecialchars($st['full_name'] . ' (' . $st['adm_no'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="ui-field mb-3">
                        <label>Subject</label>
                        <input type="text" name="subject" class="ui-input" placeholder="e.g., Homework update or Exam reminder" required>
                    </div>
                    
                    <div class="ui-field mb-3">
                        <label>Message</label>
                        <textarea name="content" class="ui-input" rows="3" placeholder="Type your message here..." required></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="ui-btn is-primary w-100">
                        <i class="bi bi-send-fill"></i> Send Message
                    </button>
                </form>

                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Recent Sent Messages</h6>
                
                <?php if (empty($messages)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted small mb-0">You haven't sent any messages yet.</p>
                    </div>
                <?php else: ?>
                    <div class="message-history" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                        <?php foreach ($messages as $msg): ?>
                            <div class="msg-bubble msg-sent">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 small text-muted"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></p>
                            </div>

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
    // Sidebar Toggle
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

    // Recipient toggle
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