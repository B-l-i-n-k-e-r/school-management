<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// --- 1. APPROVAL LOGIC ---
if (isset($_POST['approve_app_id'])) {
    $app_id = intval($_POST['approve_app_id']);
    $app_query = $conn->prepare("SELECT * FROM admission_applications WHERE id = ?");
    $app_query->bind_param("i", $app_id);
    $app_query->execute();
    $app = $app_query->get_result()->fetch_assoc();
    $app_query->close();

    if ($app) {
        $user_email = trim($app['email'] ?? '');
        if (!empty($user_email)) {
            $conn->begin_transaction();
            try {
                $adm_no      = "ADM-" . rand(1000, 9999);
                $hashed_pass = password_hash('123456', PASSWORD_DEFAULT);

                // 1. Create user
                $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt1->bind_param("sss", $adm_no, $user_email, $hashed_pass);
                $stmt1->execute();
                $new_user_id = $conn->insert_id;
                $stmt1->close();

                // 2. Create student
                $stmt2 = $conn->prepare("
                    INSERT INTO students
                    (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, dob, address, password)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param("issssssssss",
                    $new_user_id,
                    $app['student_name'],
                    $adm_no,
                    $app['gender'],
                    $app['form_requested'],
                    $app['parent_name'],
                    $app['phone'],
                    $user_email,
                    $app['dob'],
                    $app['address'],
                    $hashed_pass
                );
                $stmt2->execute();
                $stmt2->close();

                // 3. Mark application approved
                $stmt3 = $conn->prepare("UPDATE admission_applications SET status = 'Approved' WHERE id = ?");
                $stmt3->bind_param("i", $app_id);
                $stmt3->execute();
                $stmt3->close();

                $conn->commit();
                $message = "Approved! Student account created with ADM: <strong>" . htmlspecialchars($adm_no) . "</strong>";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// --- 2. REJECTION MESSAGE ---
if (isset($_GET['status']) && $_GET['status'] === 'rejected') {
    $message = "Application has been marked as rejected.";
    $message_type = 'warning';
}

// --- 3. DELETE LOGIC ---
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $get_info = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
    $get_info->bind_param("i", $delete_id);
    $get_info->execute();
    $student_data = $get_info->get_result()->fetch_assoc();
    $get_info->close();

    if ($student_data) {
        $conn->begin_transaction();
        try {
            $u_id = (int) $student_data['user_id'];
            $conn->query("DELETE FROM students WHERE student_id = $delete_id");
            $conn->query("DELETE FROM users WHERE user_id = $u_id");
            $conn->commit();
            $message = "Record deleted successfully.";
            $message_type = 'info';
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// --- 4. FETCH DATA ---
$pending = [];
$p_res = mysqli_query($conn, "SELECT * FROM admission_applications WHERE status = 'pending' ORDER BY id DESC");
if ($p_res) {
    while ($row = mysqli_fetch_assoc($p_res)) $pending[] = $row;
}

$recent = [];
$r_res = mysqli_query($conn, "SELECT * FROM students ORDER BY student_id DESC LIMIT 10");
if ($r_res) {
    while ($row = mysqli_fetch_assoc($r_res)) $recent[] = $row;
}

$active_page = 'admission';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Management | Admin Portal</title>

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
        .small-text { font-size: 0.8rem; color: #64748b; }

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
            <h2 class="fw-bold mb-1">Admission Management</h2>
            <p class="text-muted small mb-0">Process applications and manage the student registry</p>
        </div>
        <div class="d-flex gap-2">
            <a href="student_list.php" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-person-gear me-2"></i>Manage Students
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Pending Applications -->
    <div class="card adm-card mb-5 overflow-hidden">
        <div class="card-header bg-white py-4 px-4 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0 text-primary">Pending Admission Requests</h5>
            <span class="badge rounded-pill bg-light text-dark border">
                <?php echo count($pending); ?> pending
            </span>
        </div>

        <?php if (empty($pending)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted mb-0">No pending applications right now.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4">Student</th>
                            <th>Gender</th>
                            <th>Requested Level</th>
                            <th>Parent Info</th>
                            <th>Address</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <strong><?php echo htmlspecialchars($p['student_name'] ?? ''); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['email'] ?? ''); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($p['gender'] ?? ''); ?></span></td>
                                <td><span class="fw-bold"><?php echo htmlspecialchars($p['form_requested'] ?? ''); ?></span></td>
                                <td>
                                    <small>
                                        <strong><?php echo htmlspecialchars($p['parent_name'] ?? ''); ?></strong><br>
                                        <?php echo htmlspecialchars($p['phone'] ?? ''); ?>
                                    </small>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($p['address'] ?? ''); ?></small></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <form method="POST" class="m-0">
                                            <input type="hidden" name="approve_app_id" value="<?php echo intval($p['id']); ?>">
                                            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Approve</button>
                                        </form>
                                        <a href="reject_app.php?id=<?php echo intval($p['id']); ?>"
                                           class="btn btn-outline-danger btn-sm rounded-pill px-3"
                                           onclick="return confirm('Reject this application?');">
                                            Deny
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

    <!-- Recently Enrolled -->
    <div class="card adm-card overflow-hidden">
        <div class="card-header bg-white py-4 px-4 border-0">
            <h5 class="fw-bold m-0 text-success">Recently Enrolled Students</h5>
        </div>

        <?php if (empty($recent)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                <p class="text-muted mb-0">No students enrolled yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-muted text-uppercase">
                            <th class="ps-4">ADM NO</th>
                            <th>Student Details</th>
                            <th>Gender</th>
                            <th>Form</th>
                            <th>Parent/Guardian</th>
                            <th class="pe-4">Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></strong><br>
                                    <span class="small-text"><?php echo htmlspecialchars($row['email'] ?? ''); ?></span>
                                </td>
                                <td><small><?php echo htmlspecialchars($row['gender'] ?? ''); ?></small></td>
                                <td><span class="fw-bold"><?php echo htmlspecialchars($row['form'] ?? ''); ?></span></td>
                                <td>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($row['parent_name'] ?? ''); ?></div>
                                    <div class="small-text"><?php echo htmlspecialchars($row['parent_contact'] ?? ''); ?></div>
                                </td>
                                <td class="pe-4"><small class="text-muted"><?php echo htmlspecialchars($row['address'] ?? ''); ?></small></td>
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