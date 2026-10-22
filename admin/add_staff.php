<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$default_pass = "mariba2026";
$school_domain = "michaelson.sc.ke";

// ---- Compute the NEXT staff ID before the form is rendered ----
function getNextStaffId($conn) {
    $year  = date('Y');
    $like  = "STF/%/{$year}";

    $stmt = $conn->prepare("SELECT employee_id FROM teachers WHERE employee_id LIKE ? ORDER BY teacher_id DESC LIMIT 1");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = 1;
    if ($row && preg_match('/STF\/(\d+)\//', $row['employee_id'], $m)) {
        $next = intval($m[1]) + 1;
    }

    return sprintf('STF/%03d/%d', $next, $year);
}

$next_employee_id = getNextStaffId($conn);
$next_email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $next_employee_id)) . '@' . $school_domain;

// ---- Handle submission ----
if (isset($_POST['register_staff'])) {
    $full_name     = trim($_POST['full_name']     ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience    = trim($_POST['experience']    ?? '');
    $password      = password_hash($default_pass, PASSWORD_DEFAULT);
    $role          = "teacher";

    // Regenerate SERVER-SIDE — never trust the client
    $employee_id = getNextStaffId($conn);
    $email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $employee_id)) . '@' . $school_domain;

    // Basic validation
    if ($full_name === '' || $phone === '' || $qualification === '' || $experience === '') {
        $message = "Please fill all fields.";
        $message_type = 'warning';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Insert into users
            $stmt_user = $conn->prepare("
                INSERT INTO users (username, employee_id, email, full_name, password, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_user->bind_param("ssssss",
                $employee_id, $employee_id, $email, $full_name, $password, $role
            );
            $stmt_user->execute();
            $user_id = $conn->insert_id;
            $stmt_user->close();

            // 2. Insert into teachers
            $stmt_staff = $conn->prepare("
                INSERT INTO teachers (user_id, employee_id, full_name, email, phone, qualification, experience) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_staff->bind_param("issssss",
                $user_id, $employee_id, $full_name, $email, $phone, $qualification, $experience
            );
            $stmt_staff->execute();
            $stmt_staff->close();

            $conn->commit();

            $message = "
                <div class='fw-bold mb-2'>Staff member registered successfully!</div>
                <div class='small'>
                    <strong>Employee ID:</strong> " . htmlspecialchars($employee_id) . "<br>
                    <strong>Login Email:</strong> " . htmlspecialchars($email) . "<br>
                    <strong>Password:</strong> " . htmlspecialchars($default_pass) . "
                </div>
            ";
            $message_type = 'success';

            // Refresh the preview for the next staff member
            $next_employee_id = getNextStaffId($conn);
            $next_email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $next_employee_id)) . '@' . $school_domain;

        } catch (Exception $e) {
            $conn->rollback();
            $message = ($e->getCode() == 1062)
                ? "The generated ID or email is already in use. Please try again."
                : "System Error: " . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    }
}

$active_page = 'staff';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff | Admin Portal</title>

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

        .section-heading {
            border-left: 4px solid #f6b612;
            padding-left: 15px;
        }
        .section-heading h5 { font-weight: 800; color: #1e293b; margin: 0; }

        /* Preview box */
        .preview-box {
            background: linear-gradient(135deg, #1a3066 0%, #34445c 100%);
            color: white;
            border-radius: 16px;
            padding: 20px 24px;
        }
        .preview-box .label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.75;
        }
        .preview-box .value {
            font-weight: 700;
            font-size: 1rem;
            font-family: 'SF Mono', Menlo, monospace;
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
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="mb-5">
                <a href="staff.php" class="btn btn-link text-decoration-none text-muted p-0 small fw-bold text-uppercase mb-2 d-inline-block">
                    <i class="bi bi-arrow-left me-2"></i> Back to Directory
                </a>
                <h2 class="fw-bold text-dark mb-1">Staff Onboarding</h2>
                <p class="text-muted mb-0">Fill in the details below. The system will generate the ID and login automatically.</p>
            </div>

            <!-- Preview card -->
            <div class="preview-box mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-magic fs-5"></i>
                    <span class="fw-bold">Next new staff member will receive:</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="label">Employee ID</div>
                        <div class="value"><?php echo htmlspecialchars($next_employee_id); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Login Email</div>
                        <div class="value"><?php echo htmlspecialchars($next_email); ?></div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 p-4 mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="card adm-card p-4 p-md-5">
                <div class="row g-4">

                    <div class="col-12 mb-2">
                        <div class="section-heading">
                            <h5>Personal Information</h5>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control"
                               placeholder="Enter Official Name" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="e.g. 07..." required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Qualification / Primary Department</label>
                        <input type="text" name="qualification" class="form-control"
                               placeholder="e.g. B.Ed Arts (Geography/History)" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Years of Experience</label>
                        <input type="text" name="experience" class="form-control"
                               placeholder="e.g. 5 Years" required>
                    </div>

                    <div class="col-12 mt-5 mb-2">
                        <div class="section-heading">
                            <h5>System Access (auto-generated)</h5>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-control fw-bold"
                               value="<?php echo htmlspecialchars($next_employee_id); ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Login Email</label>
                        <input type="email" class="form-control fw-bold"
                               value="<?php echo htmlspecialchars($next_email); ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Temporary Password</label>
                        <input type="text" class="form-control fw-bold"
                               value="<?php echo htmlspecialchars($default_pass); ?>" readonly>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-text text-warning mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Staff will use these credentials for their first login.
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <button type="submit" name="register_staff" class="btn btn-primary w-100 shadow">
                            <i class="bi bi-person-plus-fill me-2"></i> Register &amp; Generate Account
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</main>

<?php include("footer.php"); ?>
</body>
</html>