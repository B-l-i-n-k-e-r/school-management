<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// --- 1. HANDLE SAVING (NEW OR UPDATE) ---
if (isset($_POST['enroll_student'])) {
    $full_name      = trim($_POST['full_name']      ?? '');
    $email          = trim($_POST['email']          ?? '');
    $gender         = trim($_POST['gender']         ?? '');
    $form           = trim($_POST['form']           ?? '');
    $parent_name    = trim($_POST['parent_name']    ?? '');
    $parent_contact = trim($_POST['parent_contact'] ?? '');
    $address        = trim($_POST['address']        ?? '');

    $student_id = isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;

    if ($student_id) {
        // --- UPDATE existing student ---
        $stmt = $conn->prepare("UPDATE students SET full_name=?, email=?, gender=?, form=?, parent_name=?, parent_contact=?, address=? WHERE student_id=?");
        $stmt->bind_param("sssssssi", $full_name, $email, $gender, $form, $parent_name, $parent_contact, $address, $student_id);
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Update failed.";
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        // --- INSERT new student ---
        $adm_no      = "ADM-" . rand(1000, 9999);
        $hashed_pass = password_hash("123456", PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            // Create user
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
            $stmt1->bind_param("sss", $adm_no, $email, $hashed_pass);
            $stmt1->execute();
            $user_id = $conn->insert_id;
            $stmt1->close();

            // Create student
            $stmt2 = $conn->prepare("
                INSERT INTO students
                (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, address, password)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param("isssssssss",
                $user_id, $full_name, $adm_no, $gender, $form,
                $parent_name, $parent_contact, $email, $address, $hashed_pass
            );
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            $message = "Enrollment successful! ADM No: <strong>" . htmlspecialchars($adm_no) . "</strong>";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: Duplicate email or database issue.";
            $message_type = 'danger';
        }
    }
}

// --- 2. HANDLE DELETION ---
if (isset($_POST['delete_student_id'])) {
    $sid = (int) $_POST['delete_student_id'];
    $res = $conn->query("SELECT user_id FROM students WHERE student_id = $sid");
    if ($res && $res->num_rows > 0) {
        $uid = (int) $res->fetch_assoc()['user_id'];
        $conn->query("DELETE FROM students WHERE student_id = $sid");
        $conn->query("DELETE FROM users WHERE user_id = $uid");
        $message = "Student record removed.";
        $message_type = 'info';
    }
}

// --- 3. FETCH ALL STUDENTS ---
$students = [];
$s_res = $conn->query("SELECT * FROM students ORDER BY student_id DESC");
if ($s_res) {
    while ($row = $s_res->fetch_assoc()) $students[] = $row;
}

$active_page = 'student_list';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Admin Portal</title>

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

        /* Table sizing — fit content columns */
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.expand { width: auto; white-space: normal; }

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

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Student Directory</h2>
            <p class="text-muted small mb-0">View and manage all registered students</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light border rounded-pill px-3" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Export
            </button>
            <button class="btn btn-primary rounded-pill px-4" onclick="openEnrollModal()">
                <i class="bi bi-plus-lg me-2"></i>Enroll Student
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Enroll / Edit Modal -->
    <div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="fw-bold m-0" id="enrollModalLabel">Student Enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="student_id" id="form_student_id">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="full_name" id="form_full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email Address</label>
                                <input type="email" name="email" id="form_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Gender</label>
                                <select name="gender" id="form_gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Level / Form</label>
                                <select name="form" id="form_form" class="form-select" required>
                                    <?php
                                    $classes = [];
                                    $c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
                                    if ($c_res) {
                                        while ($c = $c_res->fetch_assoc()) $classes[] = $c['name'];
                                    }
                                    if (empty($classes)) {
                                        $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
                                    }
                                    foreach ($classes as $cname) {
                                        echo '<option>' . htmlspecialchars($cname) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Parent Name</label>
                                <input type="text" name="parent_name" id="form_parent_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Parent Contact</label>
                                <input type="text" name="parent_contact" id="form_parent_contact" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Address</label>
                                <input type="text" name="address" id="form_address" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pb-4 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="enroll_student" id="form_submit_btn" class="btn btn-primary rounded-pill px-4">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Registry table -->
    <div class="card adm-card overflow-hidden">
        <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold m-0 text-primary">Student Registry</h5>
                <span class="badge rounded-pill bg-light text-dark border">
                    <?php echo count($students); ?> student<?php echo count($students) === 1 ? '' : 's'; ?>
                </span>
            </div>
            <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                <input type="text" id="studentSearch" class="form-control bg-light border-0" placeholder="Search registry...">
            </div>
        </div>

        <?php if (empty($students)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted mb-0">No students enrolled yet.</p>
                <small class="text-muted">Click <strong>Enroll Student</strong> to add the first one.</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="studentTable">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4">ADM NO</th>
                            <th class="expand">Student Details</th>
                            <th>Form</th>
                            <th class="expand">Parent/Guardian</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span>
                                </td>
                                <td class="expand">
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></div>
                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($row['email'] ?? ''); ?>
                                        <?php if (!empty($row['gender'])): ?>
                                            | <?php echo htmlspecialchars($row['gender']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($row['form'] ?? ''); ?></span></td>
                                <td class="expand">
                                    <div class="small fw-bold"><?php echo htmlspecialchars($row['parent_name'] ?? ''); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['parent_contact'] ?? ''); ?></div>
                                </td>
                                <td class="text-center pe-4">
                                    <button class="btn btn-link text-primary p-0 me-2"
                                            onclick='editStudent(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student?');">
                                        <input type="hidden" name="delete_student_id" value="<?php echo intval($row['student_id']); ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php include("footer.php"); ?>

<script>
    const enrollModal = new bootstrap.Modal(document.getElementById('enrollModal'));

    function openEnrollModal() {
        document.getElementById('form_student_id').value = "";
        document.getElementById('form_full_name').value = "";
        document.getElementById('form_email').value = "";
        document.getElementById('form_gender').value = "Male";
        document.getElementById('form_parent_name').value = "";
        document.getElementById('form_parent_contact').value = "";
        document.getElementById('form_address').value = "";

        document.getElementById('enrollModalLabel').innerText = "New Student Enrollment";
        document.getElementById('form_submit_btn').innerText = "Complete Enrollment";
        enrollModal.show();
    }

    function editStudent(data) {
        document.getElementById('form_student_id').value      = data.student_id;
        document.getElementById('form_full_name').value       = data.full_name;
        document.getElementById('form_email').value           = data.email;
        document.getElementById('form_gender').value          = data.gender;
        document.getElementById('form_form').value            = data.form;
        document.getElementById('form_parent_name').value     = data.parent_name;
        document.getElementById('form_parent_contact').value  = data.parent_contact;
        document.getElementById('form_address').value         = data.address;

        document.getElementById('enrollModalLabel').innerText = "Edit Student Profile";
        document.getElementById('form_submit_btn').innerText = "Update Record";
        enrollModal.show();
    }

    // Live search
    const studentSearch = document.getElementById('studentSearch');
    if (studentSearch) {
        studentSearch.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#studentTable tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>