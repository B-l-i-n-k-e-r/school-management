<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

// Fetch student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, email, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$student_id = (int) $student['student_id'];
$student_email = $student['email'] ?? '';

// Handle marking a message as read
if (isset($_GET['mark_read'])) {
    $msg_id = intval($_GET['mark_read']);
    // Security: Only mark as read if the recipient_email matches this student
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_email = ?");
    $stmt->bind_param("is", $msg_id, $student_email);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to prevent accidental re-submission on refresh
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

// Fetch Direct Messages (Unread first, then by date)
$messages = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE recipient_email = ? ORDER BY is_read ASC, created_at DESC");
$stmt->bind_param("s", $student_email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $messages[] = $row;
$stmt->close();

// Count unread
$unread_count = 0;
foreach ($messages as $m) {
    if (empty($m['is_read'])) $unread_count++;
}

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
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-blue: #1a3066; --sidebar-width: 240px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: #dee2e6; padding: 10px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; }
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .user-dropdown { background: var(--primary-blue); color: white; padding: 6px 15px; font-size: 0.8rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 700; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        /* Modern UI */
        .modern-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.03); padding: 30px; }
        
        /* Announcements */
        .notice-item { border-bottom: 1px solid #e2e8f0; padding: 20px 0; }
        .notice-item:last-child { border-bottom: none; padding-bottom: 0; }
        .notice-badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 50px; font-weight: 800; text-transform: uppercase; }
        .badge-event { background: #dbeafe; color: #1e40af; }
        .badge-academic { background: #dcfce7; color: #166534; }
        .badge-emergency { background: #fee2e2; color: #991b1b; }
        .badge-general { background: #f1f5f9; color: #475569; }

        /* Direct Messages (Chat Bubbles) */
        .chat-container { display: flex; flex-direction: column; gap: 20px; }
        .chat-bubble { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; border-top-left-radius: 4px; padding: 20px; position: relative; transition: 0.2s; }
        
        /* Unread styling */
        .chat-bubble.unread { background: #eff6ff; border-color: #bfdbfe; border-left: 4px solid #3b82f6; }
        
        .chat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
        .teacher-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; }
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

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
            <h2 class="fw-bold mb-1 text-dark">Communication Center</h2>
            <p class="text-muted small mb-0">View announcements and direct messages from your teachers</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Side: Announcements -->
        <div class="col-lg-5">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2 text-warning"></i>School Announcements</h5>
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

        <!-- Right Side: Direct Messages from Teachers -->
        <div class="col-lg-7">
            <div class="modern-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Teacher Messages</h5>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $unread_count; ?> Unread</span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-all me-1"></i>All Read</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-chat-square-text fs-1 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark">No messages yet</h6>
                        <p class="text-muted small mb-0">When your teachers send you direct messages, they will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="chat-container">
                        <?php foreach ($messages as $msg): 
                            $is_unread = empty($msg['is_read']);
                        ?>
                            <div class="chat-bubble <?php echo $is_unread ? 'unread' : ''; ?>">
                                <div class="chat-header">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="teacher-avatar">
                                            <?php echo htmlspecialchars(strtoupper(substr($msg['sender_name'] ?? 'T', 0, 1))); ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Teacher'); ?></h6>
                                                <?php if ($is_unread): ?>
                                                    <span class="badge bg-primary" style="font-size: 0.6rem;">NEW</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-person-badge me-1"></i>Staff Member
                                            </small>
                                        </div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?>
                                    </small>
                                </div>
                                <h6 class="fw-bold text-primary mb-2"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                <p class="mb-3 text-dark" style="font-size: 0.95rem; line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </p>
                                
                                <!-- Mark as Read Button -->
                                <?php if ($is_unread): ?>
                                    <div class="text-end mt-3 pt-3 border-top">
                                        <a href="?mark_read=<?php echo $msg['message_id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm">
                                            <i class="bi bi-check2-circle me-1"></i> Mark as Read
                                        </a>
                                    </div>
                                <?php endif; ?>
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