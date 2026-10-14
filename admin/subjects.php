<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// Handle Adding New Subject
if(isset($_POST['add_subject'])) {
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    
    $check = $conn->query("SELECT subject_id FROM subjects WHERE subject_id = '$subject_id'");
    if($check->num_rows > 0) {
        $message = "<div class='alert alert-danger border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Error: Subject ID already exists!</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_id, subject_name, form, term) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $subject_id, $subject_name, $form, $term);
        if($stmt->execute()) {
            $message = "<div class='alert alert-success border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Subject added successfully!</div>";
        }
        $stmt->close();
    }
}

// Handle Update Subject
if(isset($_POST['update_subject'])) {
    $old_id = mysqli_real_escape_string($conn, $_POST['old_subject_id']);
    $new_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);

    $stmt = $conn->prepare("UPDATE subjects SET subject_id = ?, subject_name = ?, form = ?, term = ? WHERE subject_id = ?");
    $stmt->bind_param("sssss", $new_id, $subject_name, $form, $term, $old_id);
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm py-2 px-3 mx-3 mt-3'>Subject updated successfully!</div>";
    }
    $stmt->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $conn->query("DELETE FROM subjects WHERE subject_id = '$id'");
    header("Location: subjects.php");
    exit();
}

$query = "SELECT * FROM subjects ORDER BY form ASC, subject_name ASC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --sidebar-header: #dee2e6;
            --primary-indigo: #6366f1;
            --topbar-bg: #ffffff;
            --nav-hover: #41516a;
            --submenu-bg: #2c3a4d;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        /* Sidebar & Custom Scrollbar */
        .sidebar {
            width: 260px; height: 100vh;
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

        .sidebar-header { 
            background: var(--sidebar-header); padding: 12px 20px; 
            display: flex; align-items: center; gap: 12px; height: 70px;
        }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { 
            color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; 
            display: flex; align-items: center; text-decoration: none; transition: 0.2s;
            cursor: pointer;
        }
        .nav-link i:first-child { font-size: 1.2rem; width: 30px; }
        .nav-link span { flex-grow: 1; }
        .nav-link:hover, .nav-link.active { background: var(--nav-hover); color: white; }

        .submenu { background: var(--submenu-bg); list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar {
            margin-left: 260px; background: var(--topbar-bg);
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .main-content { margin-left: 260px; padding: 40px; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        /* Fit Content Columns */
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.expand { width: auto; white-space: normal; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.4);
            z-index: 1150;
        }

        @media (max-width: 992px) {
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .topbar, .main-content { margin-left: 0 !important; }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>

<?php include('includes/navbar.php'); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Subject Management</h2>
            <p class="text-muted small">Configure subjects by Form and Term</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Subject
        </button>
    </div>

    <div class="card p-3 mb-4 border-0 shadow-sm rounded-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control bg-light border-0" placeholder="Search subject name or ID...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="filterForm" class="form-select border-0 bg-light">
                    <option value="">All Forms</option>
                    <option value="1">Form 1</option>
                    <option value="2">Form 2</option>
                    <option value="3">Form 3</option>
                    <option value="4">Form 4</option>
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

    <?php echo $message; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="subjectTable">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase">
                        <th class="ps-4">ID</th>
                        <th class="expand">Subject Name</th>
                        <th>Level</th>
                        <th>Academic Term</th>
                        <th class="text-center pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="subject-row">
                        <td class="ps-4"><span class="badge bg-secondary rounded-pill subject-id"><?php echo htmlspecialchars($row['subject_id']); ?></span></td>
                        <td class="expand fw-bold subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill form-val">Form <?php echo $row['form']; ?></span></td>
                        <td><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill term-val"><?php echo $row['term']; ?></span></td>
                        <td class="text-center pe-4">
                            <button class="btn btn-link text-primary p-0 me-2" data-bs-toggle="modal" data-bs-target="#editSubjectModal" 
                                    data-id="<?php echo $row['subject_id']; ?>" 
                                    data-name="<?php echo $row['subject_name']; ?>"
                                    data-form="<?php echo $row['form']; ?>"
                                    data-term="<?php echo $row['term']; ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="?delete=<?php echo urlencode($row['subject_id']); ?>" class="btn btn-link text-danger p-0" onclick="return confirm('Delete subject?')">
                                <i class="bi bi-trash3-fill"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Code</label>
                        <input type="text" name="subject_id" class="form-control" placeholder="e.g. ENG101" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. English Literature" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Form</label>
                            <select name="form" class="form-select" required>
                                <option value="1">Form 1</option><option value="2">Form 2</option>
                                <option value="3">Form 3</option><option value="4">Form 4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Term</label>
                            <select name="term" class="form-select" required>
                                <option>Term 1</option><option>Term 2</option><option>Term 3</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="add_subject" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="old_subject_id" id="edit_old_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Code</label>
                        <input type="text" name="subject_id" id="edit_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Subject Name</label>
                        <input type="text" name="subject_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Form</label>
                            <select name="form" id="edit_form" class="form-select" required>
                                <option value="1">Form 1</option><option value="2">Form 2</option>
                                <option value="3">Form 3</option><option value="4">Form 4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Term</label>
                            <select name="term" id="edit_term" class="form-select" required>
                                <option>Term 1</option><option>Term 2</option><option>Term 3</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="update_subject" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('sidebarOverlay');

    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const submenu = button.nextElementSibling;
            const icon = button.querySelector('.bi-plus, .bi-dash');
            const isVisible = submenu.style.display === "block";
            submenu.style.display = isVisible ? "none" : "block";
            if(icon) icon.classList.replace(isVisible ? 'bi-dash' : 'bi-plus', isVisible ? 'bi-plus' : 'bi-dash');
        });
    });

    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    const filterTerm = document.getElementById('filterTerm');
    const rows = document.querySelectorAll('.subject-row');

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const formValue = filterForm.value;
        const termValue = filterTerm.value;

        rows.forEach(row => {
            const name = row.querySelector('.subject-name').textContent.toLowerCase();
            const id = row.querySelector('.subject-id').textContent.toLowerCase();
            const rowForm = row.querySelector('.form-val').textContent;
            const rowTerm = row.querySelector('.term-val').textContent;

            const matchesSearch = name.includes(searchText) || id.includes(searchText);
            const matchesForm = formValue === "" || rowForm.includes(formValue);
            const matchesTerm = termValue === "" || rowTerm === termValue;

            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? "" : "none";
        });
    }

    searchInput.addEventListener('input', filterTable);
    filterForm.addEventListener('change', filterTable);
    filterTerm.addEventListener('change', filterTable);

    document.getElementById('editSubjectModal').addEventListener('show.bs.modal', event => {
        const btn = event.relatedTarget;
        document.getElementById('edit_old_id').value = btn.getAttribute('data-id');
        document.getElementById('edit_id').value = btn.getAttribute('data-id');
        document.getElementById('edit_name').value = btn.getAttribute('data-name');
        document.getElementById('edit_form').value = btn.getAttribute('data-form');
        document.getElementById('edit_term').value = btn.getAttribute('data-term');
    });
</script>
</body>
</html>