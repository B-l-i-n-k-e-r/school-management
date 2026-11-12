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
$tab = $_GET['tab'] ?? 'outbox';

// --- Handle Sending a New Message ---
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
                $st_lookup = $conn->prepare("SELECT email FROM students WHERE student_id = ? LIMIT 1");
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

// --- Handle Replying to an Incoming Message (Inbox) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $msg_id = intval($_POST['message_id'] ?? 0);
    $reply  = trim($_POST['reply_text'] ?? '');

    if ($msg_id <= 0 || $reply === '') {
        $error_msg = "Reply cannot be empty.";
    } else {
        $stmt = $conn->prepare("UPDATE messages SET admin_reply = ?, replied_at = NOW(), is_read = 1 WHERE message_id = ? AND recipient_email = ?");
        $stmt->bind_param("sis", $reply, $msg_id, $staff_email);
        if ($stmt->execute()) {
            $success_msg = "Reply sent successfully!";
        } else {
            $error_msg = "Reply failed: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Handle Replying to Admin from Outbox ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_admin'])) {
    $msg_id   = intval($_POST['message_id'] ?? 0);
    $subject  = trim($_POST['reply_subject'] ?? '');
    $content  = trim($_POST['reply_content'] ?? '');

    if ($msg_id <= 0 || empty($subject) || empty($content)) {
        $error_msg = "Please fill in both subject and message.";
    } else {
        // Insert a new message to the admin
        $stmt = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, subject, content, is_read) VALUES ('staff', ?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $staff_name, $staff_email, $subject, $content);
        if ($stmt->execute()) {
            $success_msg = "Follow-up message sent to Admin!";
        } else {
            $error_msg = "Error sending reply: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Fetch Announcements ---
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM notices WHERE target_audience IN ('Staff', 'Both') ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $announcements[] = $row;
$stmt->close();

// --- Fetch Outbox ---
$outbox = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE sender_email = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $staff_email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $outbox[] = $row;
$stmt->close();

// --- Fetch Inbox ---
$inbox = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE recipient_email = ? ORDER BY is_read ASC, created_at DESC");
$stmt->bind_param("s", $staff_email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $inbox[] = $row;
$stmt->close();

$unread_count = 0;
foreach ($inbox as $m) if (empty($m['is_read'])) $unread_count++;

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
        .ui-btn.is-navy { background: var(--primary); color: var(--white); }
        .ui-btn.is-navy:hover { background: var(--primary-soft); color: var(--white); }
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); color: var(--primary); }
        .ui-btn.is-danger { background: var(--white); border-color: #f3c7c7; color: #b42318; }
        .ui-btn.is-danger:hover { background: #fdeaea; color: #b42318; }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        /* Message styling */
        .msg-row {
            padding: 24px;
            border-radius: 20px;
            border: 1.5px solid var(--line);
            background: var(--white);
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .msg-row.unread {
            background: #f8fafc;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(0,10,102,0.05);
        }
        .sender-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Tabs */
        .nav-tabs-custom {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            background: var(--bg-light);
            border-radius: 14px;
            border: 1px solid var(--line);
            margin-bottom: 24px;
        }
        .nav-tabs-custom a {
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-tabs-custom a:hover { color: var(--primary); background: rgba(255,255,255,0.6); }
        .nav-tabs-custom a.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,10,102,0.08); }

        .notice-item { border-bottom: 1px solid var(--line); padding: 15px 0; }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }

        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    <div class="ui-head">
        <div>
            <h1>Communication Center</h1>
            <p>Read announcements and communicate directly with Admin or Students.</p>
        </div>
        <?php if ($unread_count > 0 && $tab !== 'inbox'): ?>
            <a href="?tab=inbox" class="ui-btn is-primary">
                <i class="bi bi-envelope-fill"></i> <?php echo $unread_count; ?> Unread Messages
            </a>
        <?php endif; ?>
    </div>

    <?php if ($success_msg): ?>
        <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="nav-tabs-custom">
        <a href="?tab=outbox" class="<?php echo $tab === 'outbox' ? 'active' : ''; ?>">
            <i class="bi bi-send-fill"></i> Outbox
        </a>
        <a href="?tab=inbox" class="<?php echo $tab === 'inbox' ? 'active' : ''; ?>">
            <i class="bi bi-inbox-fill"></i> Inbox
            <?php if ($unread_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-1" style="font-size: 0.65rem;"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

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
                                    <span class="notice-badge" style="background:#e2e8f0; color:#475569; font-size:0.65rem; padding:4px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">
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
                                        echo htmlspecialchars(substr($clean_content, 0, 150)) . (strlen($clean_content) > 150 ? '...' : ''); 
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Tabbed Content -->
        <div class="col-lg-7">
            <div class="panel h-100">
                
                <!-- OUTBOX TAB -->
                <?php if ($tab === 'outbox'): ?>
                    <h5 class="fw-bold mb-4"><i class="bi bi-chat-dots-fill me-2" style="color: var(--primary);"></i>Send a Message</h5>

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
                            <input type="text" name="subject" class="ui-input" placeholder="e.g., Homework update" required>
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
                    <div class="message-history" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                        <?php if (empty($outbox)): ?>
                            <div class="text-center py-4"><p class="text-muted small mb-0">You haven't sent any messages yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($outbox as $msg): ?>
                                <div class="msg-row" style="padding: 20px;">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="sender-pill" style="background:#dbeafe; color:#1e40af;">
                                                Sent
                                            </span>
                                            <strong class="text-dark fs-6">To: <?php echo htmlspecialchars($msg['recipient_email'] ?? 'Admin'); ?></strong>
                                        </div>
                                        <small class="text-muted fw-bold" style="font-size: 0.75rem;"><?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?></small>
                                    </div>
                                    <h6 class="fw-bold text-primary mb-2" style="font-size: 1rem;"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                    <p class="mb-3 text-dark small" style="white-space: pre-line;"><?php echo htmlspecialchars($msg['content']); ?></p>
                                    
                                    <?php if (!empty($msg['admin_reply'])): ?>
                                        <div class="p-3 rounded-4 mt-3 mb-3" style="background:#ecfdf5; border-left: 4px solid #10b981;">
                                            <small class="text-uppercase fw-bold" style="color:#047857; font-size:0.7rem; letter-spacing: 0.05em;">
                                                <i class="bi bi-reply-fill me-1"></i>Admin Reply
                                            </small>
                                            <p class="mb-0 mt-2 text-dark small"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                        </div>

                                        <!-- Reply to Admin Form -->
                                        <form method="POST" class="p-3 rounded-4" style="background: var(--bg-light); border: 1px solid var(--line);">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                            <input type="hidden" name="reply_subject" value="Re: <?php echo htmlspecialchars($msg['subject']); ?>">
                                            <label class="form-label small fw-bold text-muted text-uppercase mb-2">Reply back to Admin</label>
                                            <div class="d-flex gap-2">
                                                <input type="text" name="reply_content" class="ui-input flex-grow-1" placeholder="Type your reply..." required>
                                                <button type="submit" name="reply_to_admin" class="ui-btn is-primary px-4">
                                                    <i class="bi bi-send-fill"></i> Send
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <!-- INBOX TAB -->
                <?php else: ?>
                    <h5 class="fw-bold mb-4"><i class="bi bi-inbox-fill me-2" style="color: var(--primary);"></i>Incoming Messages</h5>
                    
                    <div class="message-history" style="max-height: 700px; overflow-y: auto; padding-right: 10px;">
                        <?php if (empty($inbox)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                                <p class="text-muted small mb-0">No incoming messages yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($inbox as $msg): 
                                $is_unread = empty($msg['is_read']);
                            ?>
                                <div class="msg-row <?php echo $is_unread ? 'unread' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap border-bottom pb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="sender-pill" style="background:#f1f5f9; color:#475569;">
                                                <?php echo htmlspecialchars($msg['sender_role'] ?? 'User'); ?>
                                            </span>
                                            <strong class="text-dark fs-6"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Unknown'); ?></strong>
                                            <small class="text-muted fw-medium ms-2" style="font-size: 0.85rem;"><?php echo htmlspecialchars($msg['sender_email'] ?? ''); ?></small>
                                            <?php if ($is_unread): ?>
                                                <span class="badge bg-primary ms-2" style="font-size:0.65rem;">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted fw-bold">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('d M Y, H:i', strtotime($msg['created_at'])); ?>
                                        </small>
                                    </div>

                                    <h6 class="fw-bold text-primary mb-3" style="font-size: 1.1rem;"><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></h6>
                                    <p class="text-dark mb-4" style="white-space: pre-line; font-size: 0.95rem; line-height: 1.6;">
                                        <?php echo htmlspecialchars($msg['content'] ?? ''); ?>
                                    </p>

                                    <?php if (!empty($msg['admin_reply'])): ?>
                                        <div class="p-4 rounded-4 mb-4" style="background:#ecfdf5; border-left: 4px solid #10b981;">
                                            <small class="text-uppercase fw-bold" style="color:#047857; font-size:0.75rem; letter-spacing: 0.05em;">
                                                <i class="bi bi-reply-fill me-1"></i>Your Reply
                                            </small>
                                            <p class="mb-2 mt-2 text-dark" style="font-size: 0.95rem;"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-3">
                                        <?php if (empty($msg['admin_reply'])): ?>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                <input type="text" name="reply_text" class="ui-input flex-grow-1" placeholder="Type your reply..." required>
                                                <button type="submit" name="send_reply" class="ui-btn is-primary px-4">
                                                    <i class="bi bi-reply-fill"></i> Reply
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-end">
                                                <a href="?tab=inbox&delete_message=<?php echo intval($msg['message_id']); ?>" class="ui-btn is-danger is-sm" onclick="return confirm('Delete this message?');">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

    // Recipient Toggle Logic
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