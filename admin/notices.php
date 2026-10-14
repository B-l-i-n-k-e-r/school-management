<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

// Fetch School Name from Settings
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_display = $sys['school_name'] ?? '';

$message = "";
// Detect current filename to fix the 'Not Found' redirect error
$current_page = basename($_SERVER['PHP_SELF']);

// --- DELETE LOGIC ---
if(isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        header("Location: $current_page?msg=deleted");
        exit();
    }
}

// --- UPDATE LOGIC ---
if(isset($_POST['update_notification'])) {
    $id = $_POST['notice_id'];
    $title = $_POST['subject'];
    $content = $_POST['body'];
    $type = $_POST['notif_type'];
    $audience = $_POST['audience'];
    
    $stmt = $conn->prepare("UPDATE notices SET title=?, content=?, type=?, target_audience=? WHERE notice_id=?");
    $stmt->bind_param("ssssi", $title, $content, $type, $audience, $id);
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__fadeIn'>Notice updated successfully!</div>";
    }
}

// --- CREATE LOGIC ---
if(isset($_POST['send_notification'])) {
    $title = $_POST['subject'];
    $content = $_POST['body'];
    $type = $_POST['notif_type']; 
    $audience = $_POST['audience'];
    $created_by = "Admin"; 
    
    try {
        $stmt = $conn->prepare("INSERT INTO notices (title, content, type, target_audience, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $title, $content, $type, $audience, $created_by);
        if($stmt->execute()) {
            $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__fadeIn'>Notice successfully posted!</div>";
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4'>Error: " . $e->getMessage() . "</div>";
    }
}

$logs = $conn->query("SELECT * FROM notices ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | <?php echo htmlspecialchars($school_display); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --accent-yellow: #b38f00;
            --submenu-bg: #2c3a4d;
            --primary-indigo: #6366f1;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        /* Sidebar Styling */
        .sidebar { 
            width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; 
            position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: 0.3s; 
        }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 0.9rem; }
        
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 55px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: 240px; background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: 240px; padding: 40px; }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 8px 15px; font-size: 0.8rem; border: none; border-radius: 5px; font-weight: bold; }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; }
            .sidebar-overlay.active { display: block; }
        }

        /* Notice Board Specific Styles */
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-control, .form-select { border-radius: 12px; padding: 10px; border: 1px solid #e2e8f0; }
        .btn-primary { background: var(--primary-indigo); border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .ck-editor__editable { min-height: 200px; border-radius: 0 0 12px 12px !important; }
        .detail-cell { white-space: normal; min-width: 300px; font-size: 0.9rem; color: #1e293b; }
        .detail-cell img { max-width: 100%; height: auto; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav-item">
        <a href="index.php" class="nav-link"><span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span></a>
    </div>
    
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-people-fill me-2"></i> Students</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-journal-bookmark-fill me-2"></i> Academics</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php">Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-cash-stack me-2"></i> Finance</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="fee_structure.php">Fee Structure</a></li>
            <li><a href="collections.php">Fee Collections</a></li>
            <li><a href="reports.php">Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="staff.php" class="nav-link"><span><i class="bi bi-person-badge-fill me-2"></i> Staff Directory</span></a>
    </div>
</div>

<div class="topbar">
    <i class="bi bi-list fs-3" id="hamburger" style="cursor:pointer;"></i>
    <div class="dropdown">
        <button class="user-dropdown text-uppercase dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <?php echo htmlspecialchars($_SESSION['adminUser'] ?? 'SYSTEM ADMIN'); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3 mt-2">
            <li><a class="dropdown-item py-2 small" href="settings.php"><i class="bi bi-person me-2"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 small text-danger" href="adminLogOut.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="mb-4">
        <h2 class="fw-bold">Notice Board Management</h2>
        <p class="text-muted">Create and manage internal school announcements.</p>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class='alert alert-warning border-0 shadow-sm rounded-4'>Notice deleted successfully.</div>
    <?php endif; ?>
    <?php echo $message; ?>

    <div class="row g-4">
        <div class="col-lg-12">
            <form method="post" class="card p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Category</label>
                        <select name="notif_type" class="form-select" required>
                            <option value="" selected disabled>Select Category</option>
                            <option value="General">General Announcement</option>
                            <option value="Academic">Academic Alert</option>
                            <option value="Event">School Event</option>
                            <option value="Emergency">Emergency Notice</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Who can see this?</label>
                        <select name="audience" class="form-select" required>
                            <option value="Both">Both Students & Staff</option>
                            <option value="Students">Students Only</option>
                            <option value="Staff">Staff Only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Notice Title</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Closing Date Postponed" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Detailed Content</label>
                        <textarea name="body" id="editor"></textarea>
                    </div>
                    <div class="col-12 mt-4 text-end">
                        <button type="submit" name="send_notification" class="btn btn-primary px-5 shadow">
                            <i class="bi bi-send-fill me-2"></i>Post Notice Now
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <div class="card p-4 mt-4">
                <h5 class="fw-bold mb-4">Broadcast History</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>ID</th>
                                <th>Title</th>
                                <th>Details (Rendered)</th>
                                <th>Audience</th>
                                <th>Category</th>
                                <th>Posted At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['notice_id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="detail-cell"><?php echo $row['content']; ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-3">
                                        <?php echo htmlspecialchars($row['target_audience'] ?? 'Both'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                                        <?php echo htmlspecialchars($row['type']); ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo $row['created_at']; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-light border text-primary" 
                                                onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="?delete_id=<?php echo $row['notice_id']; ?>" 
                                           class="btn btn-sm btn-light border text-danger" 
                                           onclick="return confirm('Are you sure you want to delete this notice?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="notice_id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Category</label>
                        <select name="notif_type" id="edit_type" class="form-select" required>
                            <option value="General">General Announcement</option>
                            <option value="Academic">Academic Alert</option>
                            <option value="Event">School Event</option>
                            <option value="Emergency">Emergency Notice</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Audience</label>
                        <select name="audience" id="edit_audience" class="form-select" required>
                            <option value="Both">Both Students & Staff</option>
                            <option value="Students">Students Only</option>
                            <option value="Staff">Staff Only</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Notice Title</label>
                        <input type="text" name="subject" id="edit_title" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Detailed Content</label>
                        <textarea name="body" id="edit_editor"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="update_notification" class="btn btn-primary px-4">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Logic
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('overlay');

    const toggleSidebar = () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    };

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });

    overlay.addEventListener('click', toggleSidebar);

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sub = btn.nextElementSibling;
            const icon = btn.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if(icon) {
                if(isClosed) { icon.classList.replace('bi-plus', 'bi-dash'); } 
                else { icon.classList.replace('bi-dash', 'bi-plus'); }
            }
        });
    });

    // Editor & Modal Logic
    let mainEditor, modalEditor;
    ClassicEditor.create(document.querySelector('#editor')).then(editor => { mainEditor = editor; });
    ClassicEditor.create(document.querySelector('#edit_editor')).then(editor => { modalEditor = editor; });

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.notice_id;
        document.getElementById('edit_title').value = data.title;
        document.getElementById('edit_type').value = data.type;
        document.getElementById('edit_audience').value = data.target_audience || 'Both';
        if (modalEditor) { modalEditor.setData(data.content); }
        var myModal = new bootstrap.Modal(document.getElementById('editModal'));
        myModal.show();
    }
</script>
</body>
</html>