<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// Handle Adding New Exam
if(isset($_POST['add_exam'])) {
    $exam_code = mysqli_real_escape_string($conn, $_POST['exam_code']);
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $exam_date = mysqli_real_escape_string($conn, $_POST['exam_date']);
    $exam_time = mysqli_real_escape_string($conn, $_POST['exam_time']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);

    $stmt = $conn->prepare("INSERT INTO exams (exam_code, subject_id, form, term, exam_date, exam_time, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room);
    
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__fadeIn'>Exam scheduled successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4'>Error: " . $conn->error . "</div>";
    }
    $stmt->close();
}

// Handle Update Exam
if(isset($_POST['update_exam'])) {
    $exam_id = mysqli_real_escape_string($conn, $_POST['exam_id']);
    $exam_code = mysqli_real_escape_string($conn, $_POST['exam_code']);
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $exam_date = mysqli_real_escape_string($conn, $_POST['exam_date']);
    $exam_time = mysqli_real_escape_string($conn, $_POST['exam_time']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);

    $stmt = $conn->prepare("UPDATE exams SET exam_code=?, subject_id=?, form=?, term=?, exam_date=?, exam_time=?, room=? WHERE exam_id=?");
    $stmt->bind_param("sssssssi", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room, $exam_id);
    
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm rounded-4'>Exam updated successfully!</div>";
    }
    $stmt->close();
}

// Handle Delete (Updated to Prepared Statement)
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()){
        $stmt->close();
        header("Location: exams.php");
        exit();
    }
}

// Fetch Exams
$query = "SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.subject_id ORDER BY e.exam_date ASC, e.exam_time ASC";
$result = mysqli_query($conn, $query);

// Fetch Subjects for the dropdown (used in JS filtering)
$subjects_data = [];
$sub_list = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
while($s = $sub_list->fetch_assoc()) { $subjects_data[] = $s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedules | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --accent-yellow: #b38f00;
            --submenu-bg: #2c3a4d;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        /* Sidebar Styles */
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease-in-out; }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 0.9rem; }
        
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        /* Topbar & Main Content */
        .topbar { margin-left: 240px; background: #f8f9fa; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #ddd; transition: 0.3s; }
        .main-content { margin-left: 240px; padding: 40px; transition: 0.3s; }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 8px 15px; font-size: 0.8rem; border: none; border-radius: 5px; font-weight: bold; }
        
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .badge-form { background: #eef2ff; color: #4338ca; border: 1px solid #e0e7ff; }
        .badge-term { background: #fff7ed; color: #9a3412; border: 1px solid #ffedd5; }
        
        .search-container { position: relative; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-input { padding-left: 45px; border-radius: 12px; border: 1px solid #e2e8f0; height: 45px; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1100; opacity: 0; transition: opacity 0.3s; }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .overlay.active { display: block; opacity: 1; }
        }

        @media print {
            .sidebar, .topbar, .btn, .search-container, .filters-row, .action-col { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #eee !important; border-radius: 0 !important; }
            .table-responsive { overflow: visible !important; }
            table { width: 100% !important; }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></div>
    
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-people-fill"></i> Students</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn active"><span><i class="bi bi-journal-bookmark-fill"></i> Academics</span><i class="bi bi-dash small"></i></a>
        <ul class="submenu" style="display: block;">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php" style="color: #f6e05e;">Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn"><span><i class="bi bi-cash-stack"></i> Finance</span><i class="bi bi-plus small"></i></a>
        <ul class="submenu">
            <li><a href="fee_structure.php">Fee Structure</a></li>
            <li><a href="collections.php">Fee Collections</a></li>
            <li><a href="reports.php">Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item"><a href="staff.php" class="nav-link"><i class="bi bi-person-badge-fill"></i> Staff Directory</a></div>
</div>

<div class="topbar">
    <i class="bi bi-list fs-3" id="hamburger" style="cursor:pointer;"></i>
    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown">ADMIN</button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-person me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="adminLogOut.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1">Exam Schedules</h2>
            <p class="text-muted small">Filter by form and term to manage schedules</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExamModal">
                <i class="bi bi-calendar-plus me-2"></i>Schedule Exam
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4 filters-row">
        <div class="col-md-6">
            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="form-control search-input" placeholder="Search Schedule...">
            </div>
        </div>
        <div class="col-md-3">
            <select id="formFilter" class="form-select border-0 bg-white shadow-sm rounded-3 py-2">
                <option value="">All Forms</option>
                <option value="Form 1">Form 1</option><option value="Form 2">Form 2</option>
                <option value="Form 3">Form 3</option><option value="Form 4">Form 4</option>
            </select>
        </div>
        <div class="col-md-3">
            <select id="termFilter" class="form-select border-0 bg-white shadow-sm rounded-3 py-2">
                <option value="">All Terms</option>
                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option><option value="Term 3">Term 3</option>
            </select>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="examTable">
                <thead>
                    <tr class="text-muted small text-uppercase">
                        <th class="px-4">Code</th>
                        <th>Subject & Class</th>
                        <th>Term</th>
                        <th>Date & Time</th>
                        <th>Room</th>
                        <th class="text-center action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="exam-row">
                        <td class="px-4"><span class="fw-bold text-dark code-text"><?php echo $row['exam_code']; ?></span></td>
                        <td>
                            <h6 class="fw-semibold mb-0 subject-text"><?php echo $row['subject_name']; ?></h6>
                            <span class="badge badge-form rounded-pill small mt-1 form-text">Form <?php echo $row['form']; ?></span>
                        </td>
                        <td><span class="badge badge-term rounded-pill term-text"><?php echo $row['term']; ?></span></td>
                        <td>
                            <div class="small fw-medium"><i class="bi bi-calendar3 text-primary me-1"></i> <?php echo date("M d, Y", strtotime($row['exam_date'])); ?></div>
                            <div class="small text-muted"><i class="bi bi-clock me-1"></i> <?php echo date("h:i A", strtotime($row['exam_time'])); ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border px-3 py-2 rounded-pill room-text"><?php echo $row['room']; ?></span></td>
                        <td class="text-center action-col">
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-sm btn-light text-primary border-0" 
                                    data-bs-toggle="modal" data-bs-target="#editExamModal"
                                    data-id="<?php echo $row['exam_id']; ?>" data-code="<?php echo $row['exam_code']; ?>"
                                    data-subject="<?php echo $row['subject_id']; ?>" data-form="<?php echo $row['form']; ?>"
                                    data-term="<?php echo $row['term']; ?>" data-date="<?php echo $row['exam_date']; ?>"
                                    data-time="<?php echo $row['exam_time']; ?>" data-room="<?php echo $row['room']; ?>">
                                    <i class="bi bi-pencil-square fs-5"></i>
                                </button>
                                <a href="?delete=<?php echo $row['exam_id']; ?>" class="btn btn-sm btn-light text-danger border-0" onclick="return confirm('Cancel this exam?')">
                                    <i class="bi bi-trash3 fs-5"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">Schedule New Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Exam Code</label>
                            <input type="text" name="exam_code" class="form-control" placeholder="e.g. END-TERM-2024" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Form</label>
                            <select name="form" id="add_form_sel" class="form-select" required>
                                <option value="">Select Form</option>
                                <option value="1">Form 1</option><option value="2">Form 2</option>
                                <option value="3">Form 3</option><option value="4">Form 4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Term</label>
                            <select name="term" id="add_term_sel" class="form-select" required>
                                <option value="">Select Term</option>
                                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option><option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Subject</label>
                            <select name="subject_id" id="add_sub_sel" class="form-select" disabled required>
                                <option value="">Select Form & Term first</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-bold small text-muted">DATE</label><input type="date" name="exam_date" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-bold small text-muted">TIME</label><input type="time" name="exam_time" class="form-control" required></div>
                        <div class="col-12"><label class="form-label fw-bold small text-muted">ROOM</label><input type="text" name="room" class="form-control" placeholder="e.g. Hall A" required></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="add_exam" class="btn btn-primary w-100 py-3 rounded-pill fw-bold">Post Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editExamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 20px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">Edit Exam Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Exam Code</label>
                            <input type="text" name="exam_code" id="edit_exam_code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Form</label>
                            <select name="form" id="edit_form_sel" class="form-select" required>
                                <option value="1">Form 1</option><option value="2">Form 2</option><option value="3">Form 3</option><option value="4">Form 4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Term</label>
                            <select name="term" id="edit_term_sel" class="form-select" required>
                                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option><option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Subject</label>
                            <select name="subject_id" id="edit_sub_sel" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Date</label>
                            <input type="date" name="exam_date" id="edit_exam_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Time</label>
                            <input type="time" name="exam_time" id="edit_exam_time" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Room</label>
                            <input type="text" name="room" id="edit_room" class="form-control" placeholder="e.g. Hall A" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="update_exam" class="btn btn-dark w-100 py-3 rounded-pill fw-bold">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const subjects = <?php echo json_encode($subjects_data); ?>;

    // Sidebar & Hamburger Logic
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburger = document.getElementById('hamburger');

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });

    // Submenu Toggle
    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sub = btn.nextElementSibling;
            const icon = btn.querySelector('.bi-plus, .bi-dash');
            const show = sub.style.display !== "block";
            sub.style.display = show ? "block" : "none";
            if(icon) icon.classList.replace(show ? 'bi-plus' : 'bi-dash', show ? 'bi-dash' : 'bi-plus');
        });
    });

    // Client-side Filter Logic
    function applyFilters() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const formFilt = document.getElementById('formFilter').value;
        const termFilt = document.getElementById('termFilter').value;
        
        document.querySelectorAll('.exam-row').forEach(row => {
            const code = row.querySelector('.code-text').innerText.toLowerCase();
            const subject = row.querySelector('.subject-text').innerText.toLowerCase();
            const room = row.querySelector('.room-text').innerText.toLowerCase();
            const form = row.querySelector('.form-text').innerText;
            const term = row.querySelector('.term-text').innerText;

            const matchesSearch = code.includes(search) || subject.includes(search) || room.includes(search);
            const matchesForm = !formFilt || form.includes(formFilt);
            const matchesTerm = !termFilt || term === termFilt;

            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? "" : "none";
        });
    }

    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('formFilter').addEventListener('change', applyFilters);
    document.getElementById('termFilter').addEventListener('change', applyFilters);

    // Dynamic Subject Select Logic
    function updateSubjectOptions(formId, termId, subSelectId, currentVal = null) {
        const f = document.getElementById(formId).value;
        const t = document.getElementById(termId).value;
        const sel = document.getElementById(subSelectId);
        
        sel.innerHTML = '<option value="">Select Subject...</option>';
        if(f && t) {
            sel.disabled = false;
            const filtered = subjects.filter(s => s.form == f && s.term == t);
            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.subject_id; opt.textContent = s.subject_name;
                if(currentVal && s.subject_id == currentVal) opt.selected = true;
                sel.appendChild(opt);
            });
        } else { sel.disabled = true; }
    }

    document.getElementById('add_form_sel').addEventListener('change', () => updateSubjectOptions('add_form_sel', 'add_term_sel', 'add_sub_sel'));
    document.getElementById('add_term_sel').addEventListener('change', () => updateSubjectOptions('add_form_sel', 'add_term_sel', 'add_sub_sel'));
    document.getElementById('edit_form_sel').addEventListener('change', () => updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel'));
    document.getElementById('edit_term_sel').addEventListener('change', () => updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel'));

    const editModal = document.getElementById('editExamModal');
    editModal.addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('edit_exam_id').value = b.getAttribute('data-id');
        document.getElementById('edit_exam_code').value = b.getAttribute('data-code');
        document.getElementById('edit_form_sel').value = b.getAttribute('data-form');
        document.getElementById('edit_term_sel').value = b.getAttribute('data-term');
        document.getElementById('edit_exam_date').value = b.getAttribute('data-date');
        document.getElementById('edit_exam_time').value = b.getAttribute('data-time');
        document.getElementById('edit_room').value = b.getAttribute('data-room');
        updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel', b.getAttribute('data-subject'));
    });
</script>
</body>
</html>