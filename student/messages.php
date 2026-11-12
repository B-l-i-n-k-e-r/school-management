<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];
$message = '';
$message_type = '';

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, email, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];
$student_email = $student['email'] ?? '';
$student_name = $student['full_name'] ?? 'Student';

// Handle Replying to a Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $teacher_email = trim($_POST['teacher_email'] ?? '');
    $subject       = trim($_POST['subject'] ?? '');
    $content       = trim($_POST['content'] ?? '');

    if (empty($teacher_email) || empty($subject) || empty($content)) {
        $message = 'All fields are required to send a reply.';
        $message_type = 'danger';
    } else {
        // Insert into messages table
        $ins = $conn->prepare("INSERT INTO messages (sender_role, sender_name, sender_email, recipient_email, subject, content, is_read) VALUES ('student', ?, ?, ?, ?, ?, 0)");
        $ins->bind_param("sssss", $student_name, $student_email, $teacher_email, $subject, $content);
        if ($ins->execute()) {
            $message = 'Reply sent successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to send reply: ' . $conn->error;
            $message_type = 'danger';
        }
        $ins->close();
    }
}

// Handle marking a message as read
if (isset($_GET['mark_read'])) {
    $msg_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_email = ?");
    $stmt->bind_param("is", $msg_id, $student_email);
    $stmt->execute();
    $stmt->close();
    header("Location: messages.php");
    exit();
}

// Fetch Announcements
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM notices WHERE target_audience IN ('Students', 'Both') ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $announcements[] = $row;
$stmt->close();

// Fetch Direct Messages
$messages = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE recipient_email = ? ORDER BY is_read ASC, created_at DESC");
$stmt->bind_param("s", $student_email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $messages[] = $row;
$stmt->close();

$unread_count = 0;
foreach ($messages as $m) { if (empty($m['is_read'])) $unread_count++; }

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'messages';
$extra_label = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-sm { padding: 8px 16px; font-size: 0.82rem; }
        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
        
        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }

        .modern-card { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        
        .notice-item { border-bottom: 1px solid var(--line); padding: 20px 0; }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }
        .notice-badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 50px; font-weight: 800; text-transform: uppercase; }
        .badge-event { background: #dbeafe; color: #1e40af; }
        .badge-academic { background: #dcfce7; color: #166534; }
        .badge-emergency { background: #fee2e2; color: #991b1b; }
        .badge-general { background: #f1f5f9; color: #475569; }
        
        .chat-container { display: flex; flex-direction: column; gap: 20px; }
        .chat-bubble { background: var(--bg-light); border: 1px solid var(--line); border-radius: 16px; border-top-left-radius: 4px; padding: 20px; position: relative; transition: 0.2s; }
        .chat-bubble.unread { background: var(--primary-tint); border-color: var(--primary); border-left: 4px solid var(--primary); }
        .chat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--line); padding-bottom: 12px; }
        .teacher-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; }
        
        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }
        
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
            <p>View announcements and direct messages from your teachers</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>"><i class="bi bi-info-circle-fill"></i><div><?php echo htmlspecialchars($message); ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Side: Announcements -->
        <div class="col-lg-5">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2" style="color: #f59e0b;"></i>School Announcements</h5>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="ui-empty">
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
                                    <small class="text-muted fw-bold" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i><?php echo date('d M Y', strtotime($not['created_at'])); ?>
                                    </small>
                                </div>
                                <h6 class="fw-bold mb-2 text-dark"><?php echo htmlspecialchars($not['title']); ?></h6>
                                <div class="text-muted small" style="line-height: 1.6;">
                                    <?php 
                                        $clean_content = strip_tags($not['content']);
                                        echo nl2br(htmlspecialchars($clean_content));
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Direct Messages -->
        <div class="col-lg-7">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots-fill me-2" style="color: var(--primary);"></i>Teacher Messages</h5>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $unread_count; ?> Unread</span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-all me-1"></i>All Read</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($messages)): ?>
                    <div class="ui-empty">
                        <i class="bi bi-chat-square-text"></i>
                        <p>No messages yet</p>
                        <small>When your teachers send you direct messages, they will appear here.</small>
                    </div>
                <?php else: ?>
                    <div class="chat-container">
                        <?php foreach ($messages as $msg): 
                            $is_unread = empty($msg['is_read']);
                            $teacher_name = $msg['sender_name'] ?? 'Teacher';
                        ?>
                            <div class="chat-bubble <?php echo $is_unread ? 'unread' : ''; ?>">
                                <div class="chat-header">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="teacher-avatar">
                                            <?php echo htmlspecialchars(strtoupper(substr($teacher_name, 0, 1))); ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <!-- Replaced "Staff Member" with "Teacher" -->
                                                <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($teacher_name); ?> <small class="text-muted fw-normal" style="font-size: 0.75rem;">(Teacher)</small></h6>
                                                <?php if ($is_unread): ?>
                                                    <span class="badge bg-primary" style="font-size: 0.6rem;">NEW</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted fw-bold" style="font-size: 0.75rem;">
                                        <?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?>
                                    </small>
                                </div>
                                <h6 class="fw-bold text-primary mb-2"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                <p class="mb-3 text-dark" style="font-size: 0.95rem; line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </p>
                                
                                <!-- Actions: Reply & Mark Read -->
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                    <button type="button" class="ui-btn is-primary is-sm" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $msg['message_id']; ?>">
                                        <i class="bi bi-reply-fill"></i> Reply
                                    </button>

                                    <?php if ($is_unread): ?>
                                        <a href="?mark_read=<?php echo $msg['message_id']; ?>" class="ui-btn is-ghost border border-secondary is-sm">
                                            <i class="bi bi-check2-circle"></i> Mark as Read
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Reply Modal -->
                            <div class="modal fade" id="replyModal<?php echo $msg['message_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content ui-modal">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reply to <?php echo htmlspecialchars($teacher_name); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="teacher_email" value="<?php echo htmlspecialchars($msg['sender_email'] ?? ''); ?>">
                                                
                                                <div class="ui-field mb-3">
                                                    <label>Subject</label>
                                                    <input type="text" name="subject" class="ui-input" value="Re: <?php echo htmlspecialchars($msg['subject']); ?>" required>
                                                </div>

                                                <div class="ui-field mb-4">
                                                    <label>Message</label>
                                                    <textarea name="content" class="ui-input" rows="4" placeholder="Type your reply here..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="send_reply" class="ui-btn is-primary">
                                                    <i class="bi bi-send-fill"></i> Send Reply
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
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