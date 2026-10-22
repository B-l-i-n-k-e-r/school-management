<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

// School name
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? 'School';

$message = '';
$message_type = '';

// Which tab is active
$tab = $_GET['tab'] ?? 'notices';
if (!in_array($tab, ['notices', 'inbox'])) $tab = 'notices';

// ============================================
// NOTICES — Delete
// ============================================
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: notices.php?msg=deleted");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Notice deleted successfully.";
    $message_type = 'warning';
}

// ============================================
// NOTICES — Update
// ============================================
if (isset($_POST['update_notification'])) {
    $id       = intval($_POST['notice_id'] ?? 0);
    $title    = trim($_POST['subject'] ?? '');
    $content  = $_POST['body'] ?? '';
    $type     = trim($_POST['notif_type'] ?? '');
    $audience = trim($_POST['audience'] ?? 'Both');

    if ($id <= 0 || $title === '' || $content === '') {
        $message = "Title and content are required.";
        $message_type = 'warning';
    } else {
        $stmt = $conn->prepare("UPDATE notices SET title=?, content=?, type=?, target_audience=? WHERE notice_id=?");
        $stmt->bind_param("ssssi", $title, $content, $type, $audience, $id);
        if ($stmt->execute()) {
            $message = "Notice updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Update failed: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ============================================
// NOTICES — Create
// ============================================
if (isset($_POST['send_notification'])) {
    $title    = trim($_POST['subject'] ?? '');
    $content  = $_POST['body'] ?? '';
    $type     = trim($_POST['notif_type'] ?? '');
    $audience = trim($_POST['audience'] ?? 'Both');
    
    // FIX: created_by must be an integer. Fallback to 0 if not found in session.
    $created_by = intval($_SESSION['admin_id'] ?? 0);

    if ($title === '' || $content === '' || $type === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO notices (title, content, type, target_audience, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $content, $type, $audience, $created_by); // FIX: changed last 's' to 'i'
        if ($stmt->execute()) {
            $message = "Notice successfully posted!";
            $message_type = 'success';
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ============================================
// INBOX — Mark as read
// ============================================
if (isset($_GET['mark_read'])) {
    $id = intval($_GET['mark_read']);
    $conn->query("UPDATE messages SET is_read = 1 WHERE message_id = $id");
    header("Location: notices.php?tab=inbox");
    exit();
}

// ============================================
// INBOX — Delete message
// ============================================
if (isset($_GET['delete_message'])) {
    $id = intval($_GET['delete_message']);
    $stmt = $conn->prepare("DELETE FROM messages WHERE message_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: notices.php?tab=inbox&msg=msg_deleted");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'msg_deleted') {
    $message = "Message deleted.";
    $message_type = 'info';
}

// ============================================
// INBOX — Send reply
// ============================================
if (isset($_POST['send_reply'])) {
    $msg_id = intval($_POST['message_id'] ?? 0);
    $reply  = trim($_POST['reply_text'] ?? '');

    if ($msg_id <= 0 || $reply === '') {
        $message = "Reply cannot be empty.";
        $message_type = 'warning';
    } else {
        $stmt = $conn->prepare("UPDATE messages SET admin_reply = ?, replied_at = NOW(), is_read = 1 WHERE message_id = ?");
        $stmt->bind_param("si", $reply, $msg_id);
        if ($stmt->execute()) {
            $message = "Reply saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Reply failed: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ============================================
// FETCH DATA
// ============================================
$logs = [];
$res = $conn->query("SELECT * FROM notices ORDER BY created_at DESC");
if ($res) while ($row = $res->fetch_assoc()) $logs[] = $row;

$inbox = [];
try {
    $res = $conn->query("SELECT * FROM messages ORDER BY is_read ASC, created_at DESC");
    if ($res) while ($row = $res->fetch_assoc()) $inbox[] = $row;
} catch (Throwable $e) {
    // messages table missing — silently skip
}

// Unread count
$unread_count = 0;
try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM messages WHERE is_read = 0");
    if ($r) $unread_count = (int) $r->fetch_assoc()['c'];
} catch (Throwable $e) {}

$active_page = 'notices';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>

    <style>
        :root {
            --sidebar-bg: #34445c;
            --sidebar-header: #dee2e6;
            --primary-blue: #1a3066;
            --sidebar-width: 260px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

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
            padding: 12px 25px; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        /* Tabs */
        .nav-tabs-custom {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            background: #f1f5f9;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .nav-tabs-custom a {
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-tabs-custom a:hover {
            color: #0f172a;
            background: rgba(255,255,255,0.6);
        }
        .nav-tabs-custom a.active {
            background: white;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(15,39,68,0.08);
        }

        /* CKEditor */
        .ck-editor__editable { min-height: 200px; border-radius: 0 0 12px 12px !important; }
        .ck.ck-editor__main > .ck-editor__editable { background: #f8fafc; }
        .ck.ck-editor__main > .ck-editor__editable:focus { background: #fff; }
        .ck.ck-toolbar { border-radius: 12px 12px 0 0 !important; background: #f1f5f9; }

        .detail-cell { white-space: normal; min-width: 320px; font-size: 0.9rem; color: #1e293b; line-height: 1.5; }
        .detail-cell img { max-width: 100%; height: auto; border-radius: 8px; }
        .detail-cell p { margin-bottom: 4px; }

        /* Message inbox */
        .msg-row {
            padding: 18px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            margin-bottom: 12px;
            transition: 0.2s;
        }
        .msg-row:hover {
            background: white;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px -4px rgba(15,39,68,0.08);
        }
        .msg-row.unread {
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .sender-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .modal-content { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .modal-header { border: 0; padding: 24px 24px 0; }
        .modal-body { padding: 24px; }
        .modal-footer { border: 0; padding: 0 24px 24px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
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

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Notification Center</h2>
            <p class="text-muted small mb-0">Post announcements and manage incoming messages</p>
        </div>
        <?php if ($unread_count > 0): ?>
            <a href="notices.php?tab=inbox" class="btn btn-primary rounded-pill px-4 position-relative">
                <i class="bi bi-envelope-fill me-1"></i>
                <?php echo $unread_count; ?> unread
            </a>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="nav-tabs-custom">
        <a href="notices.php?tab=notices" class="<?php echo $tab === 'notices' ? 'active' : ''; ?>">
            <i class="bi bi-megaphone-fill"></i>
            Announcements
        </a>
        <a href="notices.php?tab=inbox" class="<?php echo $tab === 'inbox' ? 'active' : ''; ?>">
            <i class="bi bi-inbox-fill"></i>
            Inbox
            <?php if ($unread_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-1" style="font-size: 0.65rem;">
                    <?php echo $unread_count; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- TAB 1: ANNOUNCEMENTS (existing content)   -->
    <!-- ========================================== -->
    <?php if ($tab === 'notices'): ?>

        <form method="post" class="card adm-card p-4 mb-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="notif_type" class="form-select" required>
                        <option value="" selected disabled>Select Category</option>
                        <option value="General">General Announcement</option>
                        <option value="Academic">Academic Alert</option>
                        <option value="Event">School Event</option>
                        <option value="Emergency">Emergency Notice</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Audience</label>
                    <select name="audience" class="form-select" required>
                        <option value="Both">Both Students &amp; Staff</option>
                        <option value="Students">Students Only</option>
                        <option value="Staff">Staff Only</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notice Title</label>
                    <input type="text" name="subject" class="form-control"
                           placeholder="e.g. Closing Date Postponed" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Detailed Content</label>
                    <textarea name="body" id="editor"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="send_notification" class="btn btn-primary px-5">
                        <i class="bi bi-send-fill me-2"></i>Post Notice Now
                    </button>
                </div>
            </div>
        </form>

        <div class="card adm-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Broadcast History</h5>
                <span class="badge rounded-pill bg-light text-dark border">
                    <?php echo count($logs); ?> notice<?php echo count($logs) === 1 ? '' : 's'; ?>
                </span>
            </div>

            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-megaphone fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-muted">No notices yet</h5>
                    <p class="text-muted mb-0">Post the first notice using the form above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-muted small text-uppercase">
                                <th class="ps-3">ID</th>
                                <th>Title</th>
                                <th>Details</th>
                                <th>Audience</th>
                                <th>Category</th>
                                <th>Posted</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-muted">#<?php echo intval($row['notice_id']); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                                    <td class="detail-cell"><?php echo $row['content'] ?? ''; ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-3">
                                            <?php echo htmlspecialchars($row['target_audience'] ?? 'Both'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                                            <?php echo htmlspecialchars($row['type'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo !empty($row['created_at']) ? htmlspecialchars(date('d M Y', strtotime($row['created_at']))) : '—'; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button class="btn btn-sm btn-light border text-primary"
                                                    onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="?delete_id=<?php echo intval($row['notice_id']); ?>"
                                               class="btn btn-sm btn-light border text-danger"
                                               onclick="return confirm('Delete this notice?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <!-- ========================================== -->
    <!-- TAB 2: INBOX (new section)                 -->
    <!-- ========================================== -->
    <?php else: ?>

        <div class="card adm-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2" style="background:#dbeafe; color:#1e40af;">
                        <i class="bi bi-inbox-fill fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Incoming Messages</h5>
                </div>
                <span class="badge rounded-pill bg-light text-dark border">
                    <?php echo count($inbox); ?> message<?php echo count($inbox) === 1 ? '' : 's'; ?>
                </span>
            </div>

            <?php if (empty($inbox)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-muted">No messages yet</h5>
                    <p class="text-muted mb-0">Student and staff messages will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($inbox as $m):
                    $is_unread = empty($m['is_read']);
                    $role = $m['sender_role'] ?? 'student';
                    $role_style = match($role) {
                        'teacher', 'staff' => 'background:#dcfce7; color:#166534;',
                        'student'          => 'background:#dbeafe; color:#1e40af;',
                        default            => 'background:#f1f5f9; color:#475569;',
                    };
                ?>
                    <div class="msg-row <?php echo $is_unread ? 'unread' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2 flex-wrap">
                            <div>
                                <span class="sender-pill me-2" style="<?php echo $role_style; ?>">
                                    <?php echo ucfirst($role); ?>
                                </span>
                                <strong class="text-dark"><?php echo htmlspecialchars($m['sender_name'] ?? 'Unknown'); ?></strong>
                                <?php if (!empty($m['sender_email'])): ?>
                                    <small class="text-muted ms-2"><?php echo htmlspecialchars($m['sender_email']); ?></small>
                                <?php endif; ?>
                                <?php if ($is_unread): ?>
                                    <span class="badge bg-danger ms-2" style="font-size:0.65rem;">New</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo !empty($m['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($m['created_at']))) : ''; ?>
                            </small>
                        </div>

                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($m['subject'] ?? ''); ?></h6>
                        <p class="text-muted small mb-3" style="white-space: pre-line;">
                            <?php echo htmlspecialchars($m['content'] ?? ''); ?>
                        </p>

                        <?php if (!empty($m['admin_reply'])): ?>
                            <div class="p-3 rounded-3 mb-3" style="background:#ecfdf5; border-left: 3px solid #059669;">
                                <small class="text-uppercase fw-bold" style="color:#047857; font-size:0.65rem;">
                                    <i class="bi bi-reply-fill me-1"></i>Your reply
                                </small>
                                <p class="mb-0 small mt-1"><?php echo nl2br(htmlspecialchars($m['admin_reply'])); ?></p>
                                <?php if (!empty($m['replied_at'])): ?>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        Sent <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($m['replied_at']))); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button"
                                    class="btn btn-sm rounded-pill px-3"
                                    style="background:#1a3066; color:white;"
                                    onclick='openReplyModal(<?php echo json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                <i class="bi bi-reply-fill me-1"></i>
                                <?php echo !empty($m['admin_reply']) ? 'Edit Reply' : 'Reply'; ?>
                            </button>
                            <?php if ($is_unread): ?>
                                <a href="?tab=inbox&mark_read=<?php echo intval($m['message_id']); ?>"
                                   class="btn btn-sm btn-light border rounded-pill px-3">
                                    <i class="bi bi-check2 me-1"></i>Mark as read
                                </a>
                            <?php endif; ?>
                            <a href="?tab=inbox&delete_message=<?php echo intval($m['message_id']); ?>"
                               class="btn btn-sm btn-light border text-danger rounded-pill px-3"
                               onclick="return confirm('Delete this message?');">
                                <i class="bi bi-trash me-1"></i>Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</main>

<!-- Edit Notice modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="notice_id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="notif_type" id="edit_type" class="form-select" required>
                            <option value="General">General Announcement</option>
                            <option value="Academic">Academic Alert</option>
                            <option value="Event">School Event</option>
                            <option value="Emergency">Emergency Notice</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Audience</label>
                        <select name="audience" id="edit_audience" class="form-select" required>
                            <option value="Both">Both Students &amp; Staff</option>
                            <option value="Students">Students Only</option>
                            <option value="Staff">Staff Only</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notice Title</label>
                        <input type="text" name="subject" id="edit_title" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Detailed Content</label>
                        <textarea name="body" id="edit_editor"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_notification" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reply to message modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Reply to Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="message_id" id="reply_msg_id">

                <div class="p-3 rounded-3 mb-3" style="background:#f8fafc; border-left: 3px solid #1a3066;">
                    <small class="text-uppercase fw-bold text-muted" style="font-size:0.7rem;">Original message</small>
                    <p class="mb-1 small mt-1"><strong id="reply_original_subject"></strong></p>
                    <p class="mb-0 small text-muted" id="reply_original_body"></p>
                </div>

                <label class="form-label">Your Reply</label>
                <textarea name="reply_text" id="reply_text" rows="5" class="form-control" required
                          placeholder="Type your reply..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="send_reply" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-send-fill me-1"></i>Send Reply
                </button>
            </div>
        </form>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    let modalEditor = null;
    if (document.querySelector('#edit_editor')) {
        ClassicEditor.create(document.querySelector('#edit_editor'))
            .then(editor => { modalEditor = editor; })
            .catch(err => console.error(err));
    }

    let mainEditor = null;
    if (document.querySelector('#editor')) {
        ClassicEditor.create(document.querySelector('#editor'))
            .then(editor => { mainEditor = editor; })
            .catch(err => console.error(err));
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value        = data.notice_id;
        document.getElementById('edit_title').value     = data.title;
        document.getElementById('edit_type').value      = data.type;
        document.getElementById('edit_audience').value  = data.target_audience || 'Both';

        if (modalEditor) modalEditor.setData(data.content || '');

        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function openReplyModal(data) {
        document.getElementById('reply_msg_id').value              = data.message_id;
        document.getElementById('reply_original_subject').innerText = data.subject;
        document.getElementById('reply_original_body').innerText    = data.content;
        document.getElementById('reply_text').value                 = data.admin_reply || '';

        new bootstrap.Modal(document.getElementById('replyModal')).show();
    }
</script>
</body>
</html>