<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

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
    
    $created_by = intval($_SESSION['admin_id'] ?? 0);

    if ($title === '' || $content === '' || $type === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO notices (title, content, type, target_audience, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $content, $type, $audience, $created_by); 
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

// FIXED INBOX QUERY: Only show messages where the recipient is empty/null (meaning sent to Admin)
$inbox = [];
try {
    $res = $conn->query("SELECT * FROM messages WHERE recipient_email IS NULL OR recipient_email = '' ORDER BY is_read ASC, created_at DESC");
    if ($res) while ($row = $res->fetch_assoc()) $inbox[] = $row;
} catch (Throwable $e) {}

// Unread count (only for messages sent to Admin)
$unread_count = 0;
try {
    $r = $conn->query("SELECT COUNT(*) AS c FROM messages WHERE is_read = 0 AND (recipient_email IS NULL OR recipient_email = '')");
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
        /* CKEditor Modernization */
        .ck-editor__editable { min-height: 200px; border-radius: 0 0 14px 14px !important; }
        .ck.ck-editor__main > .ck-editor__editable { background: var(--bg-light); }
        .ck.ck-editor__main > .ck-editor__editable:focus { background: var(--white); }
        .ck.ck-toolbar { border-radius: 14px 14px 0 0 !important; background: var(--bg-light); border-color: var(--line) !important; }
        
        /* Detail formatting inside table */
        .detail-cell { white-space: normal; min-width: 320px; font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; }
        .detail-cell img { max-width: 100%; height: auto; border-radius: 8px; }
        .detail-cell p { margin-bottom: 4px; }

        /* Message inbox */
        .msg-row {
            padding: 20px;
            border-radius: 16px;
            border: 1.5px solid var(--line);
            background: var(--bg-light);
            margin-bottom: 14px;
            transition: 0.2s;
        }
        .msg-row:hover {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 8px 20px -10px rgba(0,10,102,0.15);
        }
        .msg-row.unread {
            background: var(--primary-tint);
            border-color: var(--primary);
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
        .nav-tabs-custom a:hover {
            color: var(--primary);
            background: rgba(255,255,255,0.6);
        }
        .nav-tabs-custom a.active {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 5px rgba(0,10,102,0.08);
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Notification Center</h1>
            <p>Post announcements and manage incoming messages</p>
        </div>
        <?php if ($unread_count > 0 && $tab !== 'inbox'): ?>
            <div class="ui-actions">
                <a href="notices.php?tab=inbox" class="ui-btn is-primary">
                    <i class="bi bi-envelope-fill"></i> <?php echo $unread_count; ?> Unread Messages
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="nav-tabs-custom">
        <a href="notices.php?tab=notices" class="<?php echo $tab === 'notices' ? 'active' : ''; ?>">
            <i class="bi bi-megaphone-fill"></i> Announcements
        </a>
        <a href="notices.php?tab=inbox" class="<?php echo $tab === 'inbox' ? 'active' : ''; ?>">
            <i class="bi bi-inbox-fill"></i> Inbox
            <?php if ($unread_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-1" style="font-size: 0.65rem;">
                    <?php echo $unread_count; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>">
            <i class="bi bi-info-circle-fill"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- TAB 1: ANNOUNCEMENTS                       -->
    <!-- ========================================== -->
    <?php if ($tab === 'notices'): ?>

        <form method="post" class="panel mb-4">
            <div class="row g-4">
                <div class="col-md-4 ui-field">
                    <label>Category</label>
                    <select name="notif_type" class="ui-input form-select" required>
                        <option value="" selected disabled>Select Category</option>
                        <option value="General">General Announcement</option>
                        <option value="Academic">Academic Alert</option>
                        <option value="Event">School Event</option>
                        <option value="Emergency">Emergency Notice</option>
                    </select>
                </div>
                <div class="col-md-4 ui-field">
                    <label>Audience</label>
                    <select name="audience" class="ui-input form-select" required>
                        <option value="Both">Both Students &amp; Staff</option>
                        <option value="Students">Students Only</option>
                        <option value="Staff">Staff Only</option>
                    </select>
                </div>
                <div class="col-md-4 ui-field">
                    <label>Notice Title</label>
                    <input type="text" name="subject" class="ui-input" placeholder="e.g. Closing Date Postponed" required>
                </div>
                <div class="col-12 ui-field">
                    <label>Detailed Content</label>
                    <textarea name="body" id="editor"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="send_notification" class="ui-btn is-primary px-5">
                        <i class="bi bi-send-fill"></i> Post Notice Now
                    </button>
                </div>
            </div>
        </form>

        <div class="panel is-flush">
            <div class="ui-panel-head">
                <div class="ui-panel-title">
                    <h2>Broadcast History</h2>
                    <span class="ui-count"><?php echo count($logs); ?> Total</span>
                </div>
            </div>

            <?php if (empty($logs)): ?>
                <div class="ui-empty">
                    <i class="bi bi-megaphone"></i>
                    <p>No notices yet</p>
                    <small>Post the first notice using the form above.</small>
                </div>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Details</th>
                                <th>Audience</th>
                                <th>Category</th>
                                <th>Posted</th>
                                <th class="is-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td class="fw-bold text-muted">#<?php echo intval($row['notice_id']); ?></td>
                                    <td><span class="ui-name"><?php echo htmlspecialchars($row['title'] ?? ''); ?></span></td>
                                    <td class="detail-cell"><?php echo $row['content'] ?? ''; ?></td>
                                    <td>
                                        <span class="ui-badge is-soft">
                                            <?php echo htmlspecialchars($row['target_audience'] ?? 'Both'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ui-badge is-navy">
                                            <?php echo htmlspecialchars($row['type'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo !empty($row['created_at']) ? htmlspecialchars(date('d M Y', strtotime($row['created_at']))) : '—'; ?>
                                    </td>
                                    <td class="is-right">
                                        <div class="ui-row-actions">
                                            <button class="ui-icon" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="?delete_id=<?php echo intval($row['notice_id']); ?>" class="ui-icon is-danger" onclick="return confirm('Delete this notice?');">
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
    <!-- TAB 2: INBOX                               -->
    <!-- ========================================== -->
    <?php else: ?>

        <div class="panel">
            <div class="ui-panel-head mb-4">
                <div class="ui-panel-title">
                    <h2>Incoming Messages</h2>
                    <span class="ui-count"><?php echo count($inbox); ?> Total</span>
                </div>
            </div>

            <?php if (empty($inbox)): ?>
                <div class="ui-empty">
                    <i class="bi bi-inbox"></i>
                    <p>No messages yet</p>
                    <small>Student and staff messages will appear here.</small>
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
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                            <div>
                                <span class="sender-pill me-2" style="<?php echo $role_style; ?>">
                                    <?php echo ucfirst($role); ?>
                                </span>
                                <strong class="text-dark fs-6"><?php echo htmlspecialchars($m['sender_name'] ?? 'Unknown'); ?></strong>
                                <?php if (!empty($m['sender_email'])): ?>
                                    <small class="text-muted ms-2 fw-medium"><?php echo htmlspecialchars($m['sender_email']); ?></small>
                                <?php endif; ?>
                                <?php if ($is_unread): ?>
                                    <span class="badge bg-danger ms-2" style="font-size:0.65rem;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted fw-bold">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo !empty($m['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($m['created_at']))) : ''; ?>
                            </small>
                        </div>

                        <h6 class="fw-bold text-primary mb-2"><?php echo htmlspecialchars($m['subject'] ?? ''); ?></h6>
                        <p class="text-muted mb-0" style="white-space: pre-line; font-size: 0.95rem; line-height: 1.6;">
                            <?php echo htmlspecialchars($m['content'] ?? ''); ?>
                        </p>

                        <?php if (!empty($m['admin_reply'])): ?>
                            <div class="p-3 rounded-3 mt-3" style="background:#ecfdf5; border-left: 4px solid #10b981;">
                                <small class="text-uppercase fw-bold" style="color:#047857; font-size:0.7rem; letter-spacing: 0.05em;">
                                    <i class="bi bi-reply-fill me-1"></i>Your reply
                                </small>
                                <p class="mb-0 small mt-2 text-dark"><?php echo nl2br(htmlspecialchars($m['admin_reply'])); ?></p>
                                <?php if (!empty($m['replied_at'])): ?>
                                    <small class="text-muted mt-2 d-block" style="font-size: 0.7rem;">
                                        Sent <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($m['replied_at']))); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="button" class="ui-btn is-navy is-sm" onclick='openReplyModal(<?php echo json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                <i class="bi bi-reply-fill"></i> <?php echo !empty($m['admin_reply']) ? 'Edit Reply' : 'Reply'; ?>
                            </button>
                            <?php if ($is_unread): ?>
                                <a href="?tab=inbox&mark_read=<?php echo intval($m['message_id']); ?>" class="ui-btn is-ghost is-sm">
                                    <i class="bi bi-check2"></i> Mark as read
                                </a>
                            <?php endif; ?>
                            <a href="?tab=inbox&delete_message=<?php echo intval($m['message_id']); ?>" class="ui-btn is-danger is-sm" onclick="return confirm('Delete this message?');">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</main>

<!-- Edit Notice modal (UI classes) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title">Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="notice_id" id="edit_id">
                <div class="row g-4">
                    <div class="col-md-6 ui-field">
                        <label>Category</label>
                        <select name="notif_type" id="edit_type" class="ui-input form-select" required>
                            <option value="General">General Announcement</option>
                            <option value="Academic">Academic Alert</option>
                            <option value="Event">School Event</option>
                            <option value="Emergency">Emergency Notice</option>
                        </select>
                    </div>
                    <div class="col-md-6 ui-field">
                        <label>Audience</label>
                        <select name="audience" id="edit_audience" class="ui-input form-select" required>
                            <option value="Both">Both Students &amp; Staff</option>
                            <option value="Students">Students Only</option>
                            <option value="Staff">Staff Only</option>
                        </select>
                    </div>
                    <div class="col-12 ui-field">
                        <label>Notice Title</label>
                        <input type="text" name="subject" id="edit_title" class="ui-input" required>
                    </div>
                    <div class="col-12 ui-field">
                        <label>Detailed Content</label>
                        <textarea name="body" id="edit_editor"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_notification" class="ui-btn is-primary">
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reply to message modal (UI classes) -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title">Reply to Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="message_id" id="reply_msg_id">

                <div class="p-4 rounded-3 mb-4" style="background:var(--bg-light); border-left: 4px solid var(--primary);">
                    <small class="text-uppercase fw-bold text-muted" style="font-size:0.7rem; letter-spacing: 0.05em;">Original message</small>
                    <p class="mb-1 mt-2 fw-bold text-dark" id="reply_original_subject"></p>
                    <p class="mb-0 text-muted small" id="reply_original_body"></p>
                </div>

                <div class="ui-field">
                    <label>Your Reply</label>
                    <textarea name="reply_text" id="reply_text" rows="5" class="ui-input" required placeholder="Type your reply..."></textarea>
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