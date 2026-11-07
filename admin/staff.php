<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Assignment (Experience, Employee ID, and specific Class/Subject Pairs) ----
if (isset($_POST['assign_details'])) {
    $staff_id         = intval($_POST['staff_id']);
    $new_employee_id  = trim($_POST['employee_id'] ?? '');
    $experience       = trim($_POST['experience'] ?? '');
    $assignments_json = $_POST['assignments_json'] ?? '[]';
    $assignments      = json_decode($assignments_json, true);

    // 1. Get the OLD employee_id first (to sync the users table)
    $old_id = null;
    $get_old = $conn->prepare("SELECT employee_id FROM teachers WHERE teacher_id = ?");
    $get_old->bind_param("i", $staff_id);
    $get_old->execute();
    $old_result = $get_old->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_id = $old_row['employee_id'];
    }
    $get_old->close();

    $conn->begin_transaction();
    try {
        // Step A: Sync users.employee_id
        if ($old_id !== null) {
            $stmt_user = $conn->prepare("UPDATE users SET employee_id = ? WHERE employee_id = ?");
            $stmt_user->bind_param("ss", $new_employee_id, $old_id);
            $stmt_user->execute();
            $stmt_user->close();
        }

        // Step B: Update basic teacher info
        $stmt_staff = $conn->prepare("UPDATE teachers SET employee_id = ?, experience = ? WHERE teacher_id = ?");
        $stmt_staff->bind_param("ssi", $new_employee_id, $experience, $staff_id);
        $stmt_staff->execute();
        $stmt_staff->close();

        // Step C: Wipe old assignments and insert the new ones
        $del_assign = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ?");
        $del_assign->bind_param("i", $staff_id);
        $del_assign->execute();
        $del_assign->close();

        if (!empty($assignments) && is_array($assignments)) {
            $stmt_assign = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, class_name, subject_id) VALUES (?, ?, ?)");
            foreach ($assignments as $pair) {
                $cls = $pair['class'] ?? '';
                $sub_id = (int)($pair['subject'] ?? 0);
                if ($cls !== '' && $sub_id > 0) {
                    $stmt_assign->bind_param("isi", $staff_id, $cls, $sub_id);
                    $stmt_assign->execute();
                }
            }
            $stmt_assign->close();
        }

        $conn->commit();
        $message = "Profile updated successfully.";
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Update failed: " . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// ---- Handle Delete ----
if (isset($_POST['delete_id'])) {
    $did = intval($_POST['delete_id']);

    // Get user_id to delete linked user account
    $get_user = $conn->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
    $get_user->bind_param("i", $did);
    $get_user->execute();
    $u_res = $get_user->get_result();
    $uid = ($u_row = $u_res->fetch_assoc()) ? intval($u_row['user_id']) : 0;
    $get_user->close();

    $conn->begin_transaction();
    try {
        // Delete assignments
        $conn->query("DELETE FROM teacher_assignments WHERE teacher_id = $did");
        
        // Delete teacher
        $stmt1 = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
        $stmt1->bind_param("i", $did);
        $stmt1->execute();
        $stmt1->close();

        // Delete user
        if ($uid > 0) {
            $stmt2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt2->bind_param("i", $uid);
            $stmt2->execute();
            $stmt2->close();
        }
        $conn->commit();
        $message = "Staff record removed successfully.";
        $message_type = 'info';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Delete failed: " . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// ---- Fetch staff and their assignments ----
$staff = [];
$s_res = mysqli_query($conn, "SELECT * FROM teachers ORDER BY teacher_id DESC");
if ($s_res) {
    while ($row = mysqli_fetch_assoc($s_res)) {
        $tid = $row['teacher_id'];
        $row['assignments'] = [];
        
        // Fetch their specific pairs
        $a_res = $conn->query("
            SELECT ta.class_name, ta.subject_id, s.subject_name 
            FROM teacher_assignments ta 
            JOIN subjects s ON ta.subject_id = s.subject_id 
            WHERE ta.teacher_id = $tid
        ");
        if ($a_res) {
            while ($a = $a_res->fetch_assoc()) {
                $row['assignments'][] = $a;
            }
        }
        $staff[] = $row;
    }
}

// ---- Fetch classes and subjects for the modal ----
$forms_list = [];
$res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order");
if ($res) { while ($f = mysqli_fetch_assoc($res)) $forms_list[] = $f['name']; }

$subjects_list = [];
$res = mysqli_query($conn, "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
if ($res) { while ($s = mysqli_fetch_assoc($res)) $subjects_list[] = $s; }

$active_page = 'staff';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --sidebar-bg: #34445c; --sidebar-header: #dee2e6; --primary-blue: #1a3066; --sidebar-width: 260px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; }
        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }
        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.ps-4 { width: auto; white-space: normal; }

        .avatar-circle { width: 40px; height: 40px; background: #e2e8f0; color: #475569; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
        
        /* Modal Assignment Builder */
        .assign-builder { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; }
        .assign-list { list-style: none; padding: 0; margin: 0; max-height: 150px; overflow-y: auto; }
        .assign-item { display: flex; justify-content: space-between; align-items: center; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; margin-bottom: 6px; }
        .remove-assign { color: #ef4444; cursor: pointer; background: none; border: none; font-size: 1.1rem; }
        select[multiple] { min-height: 100px; }

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

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Staff Directory</h2>
            <p class="text-muted small mb-0">Manage faculty profiles and class/subject assignments</p>
        </div>
        <a href="staff_register.php" class="btn btn-dark rounded-pill px-4">
            <i class="bi bi-person-plus-fill me-2"></i>Register Staff
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 p-3 mb-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card p-0 overflow-hidden">
        <?php if (empty($staff)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-muted">No staff members yet</h5>
                <p class="text-muted mb-3">Click <strong>Register Staff</strong> to add the first one.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase">Staff Member</th>
                            <th class="text-muted small text-uppercase">Employee ID</th>
                            <th class="text-muted small text-uppercase">Experience</th>
                            <th class="text-muted small text-uppercase">Assignments</th>
                            <th class="text-center text-muted small text-uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php echo htmlspecialchars(strtoupper(substr($row['full_name'] ?? '?', 0, 1))); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                Staff ID: #<?php echo intval($row['teacher_id']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <?php echo !empty($row['employee_id']) ? htmlspecialchars($row['employee_id']) : 'N/A'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small fw-bold">
                                        <?php echo !empty($row['experience']) ? htmlspecialchars($row['experience']) : '0 Years'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (empty($row['assignments'])): ?>
                                        <span class="badge bg-light text-muted border">Not Assigned</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                            <?php foreach ($row['assignments'] as $a): ?>
                                                <span class="badge bg-primary-subtle text-primary border px-2 py-1" style="font-size: 0.7rem;">
                                                    <?php echo htmlspecialchars($a['class_name'] . ' - ' . $a['subject_name']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn btn-sm btn-outline-primary border-0 rounded-circle"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignModal<?php echo intval($row['teacher_id']); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="post" onsubmit="return confirm('Remove this staff member?');" class="d-inline">
                                            <input type="hidden" name="delete_id" value="<?php echo intval($row['teacher_id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Assignment Modal -->
                                    <div class="modal fade" id="assignModal<?php echo intval($row['teacher_id']); ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 shadow rounded-4 text-start">
                                                <form method="post" onsubmit="return validateForm(<?php echo intval($row['teacher_id']); ?>)">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="fw-bold mb-0">Edit Staff: <?php echo htmlspecialchars($row['full_name'] ?? ''); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body py-4">
                                                        <input type="hidden" name="staff_id" value="<?php echo intval($row['teacher_id']); ?>">

                                                        <div class="row g-3 mb-4">
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-bold">Employee ID</label>
                                                                <input type="text" name="employee_id" class="form-control bg-light border-0"
                                                                       value="<?php echo htmlspecialchars($row['employee_id'] ?? ''); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label small fw-bold">Years of Experience</label>
                                                                <input type="text" name="experience" class="form-control bg-light border-0"
                                                                       value="<?php echo htmlspecialchars($row['experience'] ?? ''); ?>" placeholder="e.g. 5 Years" required>
                                                            </div>
                                                        </div>

                                                        <hr class="opacity-25 mb-4">

                                                        <!-- Assignment Builder inside Modal -->
                                                        <label class="form-label small fw-bold mb-2">Class & Subject Assignments</label>
                                                        
                                                        <div class="assign-builder mb-3">
                                                            <div class="row g-2 align-items-end mb-3">
                                                                <div class="col-md-4">
                                                                    <select id="class_<?php echo $row['teacher_id']; ?>" class="form-select form-select-sm">
                                                                        <option value="" disabled selected>-- Choose Class --</option>
                                                                        <?php foreach ($forms_list as $form): ?>
                                                                            <option value="<?php echo htmlspecialchars($form); ?>"><?php echo htmlspecialchars($form); ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-5">
                                                                    <select id="subject_<?php echo $row['teacher_id']; ?>" class="form-select form-select-sm" multiple size="1">
                                                                        <?php foreach ($subjects_list as $sub): ?>
                                                                            <option value="<?php echo intval($sub['subject_id']); ?>" data-name="<?php echo htmlspecialchars($sub['subject_name']); ?>">
                                                                                <?php echo htmlspecialchars($sub['subject_name']); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <button type="button" class="btn btn-sm btn-warning w-100 fw-bold" onclick="addAssign(<?php echo $row['teacher_id']; ?>)">
                                                                        <i class="bi bi-plus-lg"></i> Add
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- Hidden JSON -->
                                                            <input type="hidden" name="assignments_json" id="json_<?php echo $row['teacher_id']; ?>" 
                                                                   value='<?php echo htmlspecialchars(json_encode(array_map(function($a) {
                                                                       return ['class' => $a['class_name'], 'subject' => $a['subject_id'], 'subjectName' => $a['subject_name']];
                                                                   }, $row['assignments']))); ?>'>

                                                            <ul class="assign-list" id="list_<?php echo $row['teacher_id']; ?>">
                                                                <!-- Rendered by JS -->
                                                            </ul>
                                                        </div>

                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="assign_details" class="btn btn-dark rounded-pill px-4">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
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

<?php include("footer.php"); ?>

<script>
    // Store assignments per teacher ID
    const teacherAssignments = {};

    // Initialize data from hidden inputs when page loads
    document.addEventListener('DOMContentLoaded', () => {
        <?php foreach ($staff as $row): ?>
            teacherAssignments[<?php echo $row['teacher_id']; ?>] = JSON.parse(document.getElementById('json_<?php echo $row['teacher_id']; ?>').value || '[]');
            renderList(<?php echo $row['teacher_id']; ?>);
        <?php endforeach; ?>
    });

    function addAssign(teacherId) {
        const classSelect = document.getElementById('class_' + teacherId);
        const subjectSelect = document.getElementById('subject_' + teacherId);
        
        const selectedClass = classSelect.value;
        const selectedOptions = Array.from(subjectSelect.selectedOptions);

        if (!selectedClass) { alert('Select a class first.'); return; }
        if (selectedOptions.length === 0) { alert('Select at least one subject.'); return; }

        let dupes = 0;
        selectedOptions.forEach(opt => {
            const subId = opt.value;
            const subName = opt.getAttribute('data-name');

            const exists = teacherAssignments[teacherId].find(a => a.class === selectedClass && a.subject === subId);
            if (exists) {
                dupes++;
            } else {
                teacherAssignments[teacherId].push({ class: selectedClass, subject: subId, subjectName: subName });
            }
        });

        // Reset inputs
        classSelect.value = '';
        subjectSelect.value = '';

        renderList(teacherId);
        if (dupes > 0) alert(`Skipped ${dupes} duplicate assignment(s).`);
    }

    function removeAssign(teacherId, index) {
        teacherAssignments[teacherId].splice(index, 1);
        renderList(teacherId);
    }

    function renderList(teacherId) {
        const list = document.getElementById('list_' + teacherId);
        const jsonInput = document.getElementById('json_' + teacherId);
        
        list.innerHTML = '';
        const arr = teacherAssignments[teacherId];

        if (arr.length === 0) {
            list.innerHTML = '<li class="text-muted small fst-italic">No assignments added.</li>';
            jsonInput.value = '[]';
            return;
        }

        arr.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'assign-item';
            li.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">${item.class}</span>
                    <span class="fw-bold text-dark small">${item.subjectName}</span>
                </div>
                <button type="button" class="remove-assign" onclick="removeAssign(${teacherId}, ${index})">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            `;
            list.appendChild(li);
        });

        jsonInput.value = JSON.stringify(arr);
    }

    function validateForm(teacherId) {
        if (teacherAssignments[teacherId].length === 0) {
            alert('Please add at least one assignment.');
            return false;
        }
        return true;
    }
</script>
</body>
</html>