<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Adding New Subject ----
if (isset($_POST['add_subject'])) {
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $form         = trim($_POST['form'] ?? '');
    $term         = trim($_POST['term'] ?? '');

    if ($subject_code === '' || $subject_name === '' || $form === '' || $term === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        // Check for duplicate subject_code
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
        $check->bind_param("s", $subject_code);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "Error: Subject code <strong>" . htmlspecialchars($subject_code) . "</strong> already exists!";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, form, term) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $subject_code, $subject_name, $form, $term);
            if ($stmt->execute()) {
                $message = "Subject <strong>" . htmlspecialchars($subject_name) . "</strong> added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error: " . htmlspecialchars($stmt->error);
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// ---- Handle Update Subject ----
if (isset($_POST['update_subject'])) {
    $edit_id      = intval($_POST['edit_id'] ?? 0);
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $form         = trim($_POST['form'] ?? '');
    $term         = trim($_POST['term'] ?? '');

    if ($edit_id <= 0 || $subject_code === '' || $subject_name === '' || $form === '' || $term === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        // Check for duplicate subject_code (excluding self)
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ? LIMIT 1");
        $check->bind_param("si", $subject_code, $edit_id);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "Error: Subject code <strong>" . htmlspecialchars($subject_code) . "</strong> is used by another subject.";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, form = ?, term = ? WHERE subject_id = ?");
            $stmt->bind_param("ssssi", $subject_code, $subject_name, $form, $term, $edit_id);
            if ($stmt->execute()) {
                $message = "Subject updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Update failed: " . htmlspecialchars($stmt->error);
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: subjects.php?deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Subject removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all subjects ----
$subjects = [];
$result = mysqli_query($conn, "SELECT * FROM subjects ORDER BY form ASC, subject_name ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $subjects[] = $row;
}

// ---- Fetch classes for the form dropdown ----
$classes = [];
$c_res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) $classes[] = $row['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

$active_page = 'subjects';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

        /* Table sizing */
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.expand { width: auto; white-space: normal; }

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
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/navbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Subject Management</h2>
            <p class="text-muted small mb-0">Configure subjects by Form and Term</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Subject
        </button>
    </div>

    <!-- Filters -->
    <div class="card adm-card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control bg-light border-0" placeholder="Search subject name or code...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="filterForm" class="form-select border-0 bg-light">
                    <option value="">All Forms</option>
                    <?php foreach ($classes as $cname): ?>
                        <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterTerm" class="form-select border-0 bg-light">
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

    <!-- Subjects table -->
    <div class="card adm-card overflow-hidden">
        <?php if (empty($subjects)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No subjects yet</h5>
                <p class="text-muted mb-3">Click <strong>Add New Subject</strong> to create the first one.</p>
                <button class="btn btn-primary rounded-pill px-4 mx-auto" style="width: fit-content;"
                        data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="bi bi-plus-lg me-2"></i>Add New Subject
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="subjectTable">
                    <thead class="bg-light">
                        <tr class="text-muted small text-uppercase">
                            <th class="ps-4">Code</th>
                            <th class="expand">Subject Name</th>
                            <th>Level</th>
                            <th>Academic Term</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $row): ?>
                            <tr class="subject-row">
                                <td class="ps-4">
                                    <span class="badge bg-secondary rounded-pill subject-id">
                                        <?php echo htmlspecialchars($row['subject_code'] ?? $row['subject_id']); ?>
                                    </span>
                                </td>
                                <td class="expand fw-bold subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill form-val">
                                        <?php echo htmlspecialchars($row['form']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill term-val">
                                        <?php echo htmlspecialchars($row['term']); ?>
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <button class="btn btn-link text-primary p-0 me-2"
                                            data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                            data-edit-id="<?php echo intval($row['subject_id']); ?>"
                                            data-code="<?php echo htmlspecialchars($row['subject_code'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($row['subject_name']); ?>"
                                            data-form="<?php echo htmlspecialchars($row['form']); ?>"
                                            data-term="<?php echo htmlspecialchars($row['term']); ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="?delete=<?php echo intval($row['subject_id']); ?>"
                                       class="btn btn-link text-danger p-0"
                                       onclick="return confirm('Delete this subject?');">
                                        <i class="bi bi-trash3-fill"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-bold mb-0">New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. ENG101" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. English Literature" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Form / Level</label>
                            <select name="form" class="form-select" required>
                                <?php foreach ($classes as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term" class="form-select" required>
                                <option>Term 1</option>
                                <option>Term 2</option>
                                <option>Term 3</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_subject" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i>Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="fw-bold mb-0">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Form / Level</label>
                            <select name="form" id="edit_form" class="form-select" required>
                                <?php foreach ($classes as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term" id="edit_term" class="form-select" required>
                                <option>Term 1</option>
                                <option>Term 2</option>
                                <option>Term 3</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_subject" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-save me-1"></i>Update Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    // Live filter — search + form + term
    const searchInput = document.getElementById('searchInput');
    const filterForm  = document.getElementById('filterForm');
    const filterTerm  = document.getElementById('filterTerm');
    const rows        = document.querySelectorAll('.subject-row');

    function filterTable() {
        const searchText = (searchInput?.value || '').toLowerCase();
        const formValue  = filterForm?.value || '';
        const termValue  = filterTerm?.value || '';

        rows.forEach(row => {
            const name    = row.querySelector('.subject-name')?.textContent.toLowerCase() || '';
            const code    = row.querySelector('.subject-id')?.textContent.toLowerCase() || '';
            const rowForm = row.querySelector('.form-val')?.textContent.trim() || '';
            const rowTerm = row.querySelector('.term-val')?.textContent.trim() || '';

            const matchesSearch = name.includes(searchText) || code.includes(searchText);
            const matchesForm   = formValue === '' || rowForm === formValue;
            const matchesTerm   = termValue === '' || rowTerm === termValue;

            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (filterForm)  filterForm.addEventListener('change', filterTable);
    if (filterTerm)  filterTerm.addEventListener('change', filterTable);

    // Edit modal — populate fields from data attributes
    const editModal = document.getElementById('editSubjectModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_id').value   = btn.getAttribute('data-edit-id');
            document.getElementById('edit_code').value = btn.getAttribute('data-code');
            document.getElementById('edit_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_form').value = btn.getAttribute('data-form');
            document.getElementById('edit_term').value = btn.getAttribute('data-term');
        });
    }
</script>
</body>
</html>