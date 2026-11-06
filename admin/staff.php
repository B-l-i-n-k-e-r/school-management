<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Assignment (Class, Subject, Experience) ----
if (isset($_POST['assign_details'])) {
    $staff_id         = intval($_POST['staff_id']);
    $new_employee_id  = trim($_POST['employee_id'] ?? '');
    $assigned_form    = trim($_POST['assigned_form'] ?? '');
    $assigned_subject = trim($_POST['assigned_subject'] ?? '');
    $experience       = trim($_POST['experience'] ?? '');

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

        // Step B: Update teachers
        $stmt_staff = $conn->prepare("
            UPDATE teachers 
            SET employee_id = ?, assigned_form = ?, assigned_subject = ?, experience = ? 
            WHERE teacher_id = ?
        ");
        $stmt_staff->bind_param("ssssi", $new_employee_id, $assigned_form, $assigned_subject, $experience, $staff_id);
        $stmt_staff->execute();
        $stmt_staff->close();

        $conn->commit();
        $message = "Profile for <strong>" . htmlspecialchars($new_employee_id) . "</strong> updated successfully.";
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

    // Also remove the linked user account
    $get_user = $conn->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
    $get_user->bind_param("i", $did);
    $get_user->execute();
    $u_res = $get_user->get_result();
    $uid = ($u_row = $u_res->fetch_assoc()) ? intval($u_row['user_id']) : 0;
    $get_user->close();

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
        $stmt1->bind_param("i", $did);
        $stmt1->execute();
        $stmt1->close();

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

// ---- Fetch staff ----
$staff = [];
$s_res = mysqli_query($conn, "SELECT * FROM teachers ORDER BY teacher_id DESC");
if ($s_res) {
    while ($row = mysqli_fetch_assoc($s_res)) $staff[] = $row;
}

// ---- Fetch classes for dropdown ----
$forms_list = [];
$res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order");
if ($res) {
    while ($f = mysqli_fetch_assoc($res)) $forms_list[] = $f['name'];
}

// ---- Fetch subjects for dropdown ----
$subjects_list = [];
$res = mysqli_query($conn, "SELECT subject_name FROM subjects ORDER BY subject_name");
if ($res) {
    while ($s = mysqli_fetch_assoc($res)) $subjects_list[] = $s['subject_name'];
}

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

        /* Table sizing */
        .table th, .table td { white-space: nowrap; width: 1%; }
        .table td.ps-4 { width: auto; white-space: normal; }

        .avatar-circle {
            width: 40px; height: 40px; background: #e2e8f0; color: #475569;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 0.8rem;
        }

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

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Staff Directory</h2>
            <p class="text-muted small mb-0">Manage faculty profiles and class/subject assignments</p>
        </div>
        <a href="add_staff.php" class="btn btn-dark rounded-pill px-4">
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
                <a href="add_staff.php" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-person-plus-fill me-2"></i>Register Staff
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small text-uppercase">Staff Member</th>
                            <th class="text-muted small text-uppercase">Employee ID</th>
                            <th class="text-muted small text-uppercase">Experience</th>
                            <th class="text-muted small text-uppercase">Class</th>
                            <th class="text-muted small text-uppercase">Subject</th>
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
                                    <span class="badge bg-primary-subtle text-primary border px-3 py-2">
                                        <?php echo !empty($row['assigned_form']) ? htmlspecialchars($row['assigned_form']) : 'Not Assigned'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border px-3 py-2">
                                        <?php echo !empty($row['assigned_subject']) ? htmlspecialchars($row['assigned_subject']) : 'None'; ?>
                                    </span>
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
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow rounded-4 text-start">
                                                <form method="post">
                                                    <div class="modal-header border-0">
                                                        <h6 class="fw-bold mb-0">
                                                            Update Details: <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>
                                                        </h6>
                                                    </div>
                                                    <div class="modal-body py-3">
                                                        <input type="hidden" name="staff_id" value="<?php echo intval($row['teacher_id']); ?>">

                                                        <label class="form-label small fw-bold">Employee ID</label>
                                                        <input type="text" name="employee_id" class="form-control bg-light border-0 mb-3"
                                                               value="<?php echo htmlspecialchars($row['employee_id'] ?? ''); ?>" required>

                                                        <label class="form-label small fw-bold">Years of Experience</label>
                                                        <input type="text" name="experience" class="form-control bg-light border-0 mb-3"
                                                               value="<?php echo htmlspecialchars($row['experience'] ?? ''); ?>"
                                                               placeholder="e.g. 5 Years" required>

                                                        <label class="form-label small fw-bold">Assign Class (Form)</label>
                                                        <select name="assigned_form" class="form-select bg-light border-0 mb-3" required>
                                                            <option value="">Select Form...</option>
                                                            <?php foreach ($forms_list as $form): ?>
                                                                <option value="<?php echo htmlspecialchars($form); ?>"
                                                                        <?php echo ($row['assigned_form'] == $form) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($form); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>

                                                        <label class="form-label small fw-bold">Assign Subject</label>
                                                        <select name="assigned_subject" class="form-select bg-light border-0" required>
                                                            <option value="">Select Subject...</option>
                                                            <?php foreach ($subjects_list as $sub): ?>
                                                                <option value="<?php echo htmlspecialchars($sub); ?>"
                                                                        <?php echo ($row['assigned_subject'] == $sub) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($sub); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="modal-footer border-0">
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
</body>
</html>