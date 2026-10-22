<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Adding New Exam ----
if (isset($_POST['add_exam'])) {
    $exam_code  = trim($_POST['exam_code'] ?? '');
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $form       = trim($_POST['form'] ?? '');
    $term       = trim($_POST['term'] ?? '');
    $exam_date  = trim($_POST['exam_date'] ?? '');
    $exam_time  = trim($_POST['exam_time'] ?? '');
    $room       = trim($_POST['room'] ?? '');

    if ($exam_code === '' || $subject_id <= 0 || $form === '' || $term === '' || $exam_date === '' || $exam_time === '' || $room === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (exam_code, subject_id, form, term, exam_date, exam_time, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssss", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room);
        if ($stmt->execute()) {
            $message = "Exam <strong>" . htmlspecialchars($exam_code) . "</strong> scheduled successfully!";
            $message_type = 'success';
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Update Exam ----
if (isset($_POST['update_exam'])) {
    $exam_id    = intval($_POST['exam_id'] ?? 0);
    $exam_code  = trim($_POST['exam_code'] ?? '');
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $form       = trim($_POST['form'] ?? '');
    $term       = trim($_POST['term'] ?? '');
    $exam_date  = trim($_POST['exam_date'] ?? '');
    $exam_time  = trim($_POST['exam_time'] ?? '');
    $room       = trim($_POST['room'] ?? '');

    if ($exam_id <= 0) {
        $message = "Invalid exam ID.";
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE exams SET exam_code=?, subject_id=?, form=?, term=?, exam_date=?, exam_time=?, room=? WHERE exam_id=?");
        $stmt->bind_param("sisssssi", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room, $exam_id);
        if ($stmt->execute()) {
            $message = "Exam updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Update failed: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: exams.php?deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Exam schedule removed.";
    $message_type = 'info';
}

// ---- Fetch exams with subject names ----
$exams = [];
$query = "
    SELECT e.*, s.subject_name
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    ORDER BY e.exam_date ASC, e.exam_time ASC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $exams[] = $row;
}

// ---- Fetch subjects for JS filtering ----
$subjects_data = [];
$sub_list = $conn->query("SELECT subject_id, subject_name, form, term FROM subjects ORDER BY subject_name ASC");
while ($s = $sub_list->fetch_assoc()) $subjects_data[] = $s;

// ---- Fetch classes for dropdowns ----
$classes = [];
$c_res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) $classes[] = $row['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

$active_page = 'exams';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedules | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --sidebar-bg: #34445c;
            --sidebar-header: #dee2e6;
            --primary-blue: #1a3066;
            --sidebar-width: 260px;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar {
            width: var(--sidebar-width); height: 100vh;
            background: var(--sidebar-bg); color: white;
            position: fixed; left: 0; top: 0;
            z-index: 1200; transition: all 0.3s ease;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }

        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar,
        body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header {
            background: var(--sidebar-header); padding: 12px 20px;
            display: flex; align-items: center; gap: 12px; height: 70px;
        }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link {
            color: #d1d5db; padding: 15px 20px; font-size: 0.95rem;
            display: flex; align-items: center; justify-content: space-between;
            text-decoration: none; transition: 0.2s; cursor: pointer;
        }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar {
            margin-left: var(--sidebar-width); background: #fff;
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
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
            padding: 14px; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .modal-content { border: none; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .modal-header { border: 0; padding: 24px 24px 0; }
        .modal-body { padding: 24px; }
        .modal-footer { border: 0; padding: 0 24px 24px; }

        .search-container { position: relative; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 5; }
        .search-input { padding-left: 45px; border-radius: 12px; border: 1px solid #e2e8f0; height: 45px; }

        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.4);
            z-index: 1150;
        }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }

        @media print {
            .sidebar, .topbar, .btn, .search-container, .filters-row, .action-col, .sidebar-overlay { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #eee !important; border-radius: 0 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/navbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Exam Schedules</h2>
            <p class="text-muted small mb-0">Filter by form and term to manage schedules</p>
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

    <!-- Filters -->
    <div class="card adm-card p-3 mb-4 filters-row">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="search-container w-100" style="max-width: none;">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search schedule...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="formFilter" class="form-select border-0 bg-light">
                    <option value="">All Forms</option>
                    <?php foreach ($classes as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="termFilter" class="form-select border-0 bg-light">
                    <option value="">All Terms</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Term 2">Term 2</option>
                    <option value="Term 3">Term 3</option>
                </select>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Exam table -->
    <div class="card adm-card overflow-hidden">
        <?php if (empty($exams)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No exams scheduled</h5>
                <p class="text-muted mb-3">Click <strong>Schedule Exam</strong> to add the first one.</p>
                <button class="btn btn-primary rounded-pill px-4 mx-auto" style="width: fit-content;"
                        data-bs-toggle="modal" data-bs-target="#addExamModal">
                    <i class="bi bi-calendar-plus me-2"></i>Schedule Exam
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="examTable">
                    <thead class="bg-light">
                        <tr class="text-muted small text-uppercase">
                            <th class="ps-4">Code</th>
                            <th>Subject & Class</th>
                            <th>Term</th>
                            <th>Date & Time</th>
                            <th>Room</th>
                            <th class="text-center action-col pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $row): ?>
                            <tr class="exam-row">
                                <td class="ps-4">
                                    <span class="fw-bold text-dark code-text"><?php echo htmlspecialchars($row['exam_code'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <h6 class="fw-semibold mb-0 subject-text"><?php echo htmlspecialchars($row['subject_name'] ?? '—'); ?></h6>
                                    <span class="badge rounded-pill small mt-1 form-text" style="background:#eef2ff; color:#4338ca;">
                                        <?php echo htmlspecialchars($row['form'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill term-text" style="background:#fff7ed; color:#9a3412;">
                                        <?php echo htmlspecialchars($row['term'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-medium">
                                        <i class="bi bi-calendar3 text-primary me-1"></i>
                                        <?php echo !empty($row['exam_date']) ? htmlspecialchars(date("M d, Y", strtotime($row['exam_date']))) : '—'; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo !empty($row['exam_time']) ? htmlspecialchars(date("h:i A", strtotime($row['exam_time']))) : '—'; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill room-text">
                                        <?php echo htmlspecialchars($row['room'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td class="text-center action-col pe-4">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button class="btn btn-sm btn-light text-primary border-0"
                                                data-bs-toggle="modal" data-bs-target="#editExamModal"
                                                data-id="<?php echo intval($row['exam_id']); ?>"
                                                data-code="<?php echo htmlspecialchars($row['exam_code'] ?? ''); ?>"
                                                data-subject="<?php echo intval($row['subject_id'] ?? 0); ?>"
                                                data-form="<?php echo htmlspecialchars($row['form'] ?? ''); ?>"
                                                data-term="<?php echo htmlspecialchars($row['term'] ?? ''); ?>"
                                                data-date="<?php echo htmlspecialchars($row['exam_date'] ?? ''); ?>"
                                                data-time="<?php echo htmlspecialchars($row['exam_time'] ?? ''); ?>"
                                                data-room="<?php echo htmlspecialchars($row['room'] ?? ''); ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <a href="?delete=<?php echo intval($row['exam_id']); ?>"
                                           class="btn btn-sm btn-light text-danger border-0"
                                           onclick="return confirm('Cancel this exam?');">
                                            <i class="bi bi-trash3 fs-5"></i>
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

</main>

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-bold mb-0">Schedule New Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Code</label>
                            <input type="text" name="exam_code" class="form-control" placeholder="e.g. END-TERM-2026" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Form</label>
                            <select name="form" id="add_form_sel" class="form-select" required>
                                <option value="">Select Form</option>
                                <?php foreach ($classes as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term" id="add_term_sel" class="form-select" required>
                                <option value="">Select Term</option>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" id="add_sub_sel" class="form-select" disabled required>
                                <option value="">Select Form &amp; Term first</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="exam_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="exam_time" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" class="form-control" placeholder="e.g. Hall A" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_exam" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-calendar-plus me-1"></i>Post Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Exam Modal -->
<div class="modal fade" id="editExamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-bold mb-0">Edit Exam Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Code</label>
                            <input type="text" name="exam_code" id="edit_exam_code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Form</label>
                            <select name="form" id="edit_form_sel" class="form-select" required>
                                <?php foreach ($classes as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term" id="edit_term_sel" class="form-select" required>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" id="edit_sub_sel" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="exam_date" id="edit_exam_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" name="exam_time" id="edit_exam_time" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" id="edit_room" class="form-control" placeholder="e.g. Hall A" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_exam" class="btn btn-dark rounded-pill px-4 fw-bold">
                        <i class="bi bi-save me-1"></i>Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    const subjects = <?php echo json_encode($subjects_data); ?>;

    // ---- Filter table ----
    function applyFilters() {
        const search  = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const formVal = document.getElementById('formFilter')?.value || '';
        const termVal = document.getElementById('termFilter')?.value || '';

        document.querySelectorAll('.exam-row').forEach(row => {
            const code    = row.querySelector('.code-text')?.innerText.toLowerCase() || '';
            const subject = row.querySelector('.subject-text')?.innerText.toLowerCase() || '';
            const room    = row.querySelector('.room-text')?.innerText.toLowerCase() || '';
            const form    = row.querySelector('.form-text')?.innerText.trim() || '';
            const term    = row.querySelector('.term-text')?.innerText.trim() || '';

            const matchesSearch = code.includes(search) || subject.includes(search) || room.includes(search);
            const matchesForm   = formVal === '' || form === formVal;
            const matchesTerm   = termVal === '' || term === termVal;

            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? '' : 'none';
        });
    }

    const searchInput = document.getElementById('searchInput');
    const formFilter  = document.getElementById('formFilter');
    const termFilter  = document.getElementById('termFilter');
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (formFilter)  formFilter.addEventListener('change', applyFilters);
    if (termFilter)  termFilter.addEventListener('change', applyFilters);

    // ---- Dynamic subject dropdown ----
    function updateSubjectOptions(formId, termId, subSelectId, currentVal = null) {
        const f = document.getElementById(formId).value;
        const t = document.getElementById(termId).value;
        const sel = document.getElementById(subSelectId);

        sel.innerHTML = '<option value="">Select Subject...</option>';
        if (f && t) {
            sel.disabled = false;
            const filtered = subjects.filter(s => s.form == f && s.term == t);
            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.subject_id;
                opt.textContent = s.subject_name;
                if (currentVal && s.subject_id == currentVal) opt.selected = true;
                sel.appendChild(opt);
            });
        } else {
            sel.disabled = true;
        }
    }

    document.getElementById('add_form_sel')?.addEventListener('change', () => updateSubjectOptions('add_form_sel', 'add_term_sel', 'add_sub_sel'));
    document.getElementById('add_term_sel')?.addEventListener('change', () => updateSubjectOptions('add_form_sel', 'add_term_sel', 'add_sub_sel'));
    document.getElementById('edit_form_sel')?.addEventListener('change', () => updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel'));
    document.getElementById('edit_term_sel')?.addEventListener('change', () => updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel'));

    // ---- Populate edit modal ----
    const editModal = document.getElementById('editExamModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            document.getElementById('edit_exam_id').value   = b.getAttribute('data-id');
            document.getElementById('edit_exam_code').value = b.getAttribute('data-code');
            document.getElementById('edit_form_sel').value  = b.getAttribute('data-form');
            document.getElementById('edit_term_sel').value  = b.getAttribute('data-term');
            document.getElementById('edit_exam_date').value = b.getAttribute('data-date');
            document.getElementById('edit_exam_time').value = b.getAttribute('data-time');
            document.getElementById('edit_room').value      = b.getAttribute('data-room');
            updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel', b.getAttribute('data-subject'));
        });
    }
</script>
</body>
</html>