<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../dashboard.php");
    exit();
}

include('../database/db_connect.php');

// Database Configuration
$host = 'localhost';
$db   = 'school_admin_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

$adm_no = $_SESSION['adm_no'];
$message = "";

// 1. Fetch Student ID
$stmt = $pdo->prepare("SELECT student_id, full_name FROM students WHERE adm_no = ?");
$stmt->execute([$adm_no]);
$student = $stmt->fetch();
$student_id = $student['student_id'];

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_subjects'])) {
    $selected_subjects = $_POST['subjects'] ?? [];

    if (!empty($selected_subjects)) {
        try {
            $pdo->beginTransaction();
            
            // Optional: Clear previous registrations for this semester/term if needed
            // $del = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
            // $del->execute([$student_id]);

            $sql = "INSERT IGNORE INTO student_subjects (student_id, subject_id, registration_date) VALUES (?, ?, NOW())";
            $ins = $pdo->prepare($sql);

            foreach ($selected_subjects as $sub_id) {
                $ins->execute([$student_id, $sub_id]);
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Subjects registered successfully!</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please select at least one subject.</div>";
    }
}

// 3. Fetch Available Subjects
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC");
$available_subjects = $stmt->fetchAll();

// 4. Fetch Already Registered Subjects (to pre-check or disable)
$stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
$stmt->execute([$student_id]);
$registered_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Registration | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-bg: #34445c;
            --topbar-bg: #f8f9fa;
            --accent-yellow: #b38f00;
        }
        body { background-color: #e9ecef; font-family: sans-serif; margin: 0; }
        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 100; }
        .main-content { margin-left: 240px; padding: 30px; min-height: 100vh; box-sizing: border-box; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .subject-row:hover { background-color: #f8f9fa; }
        .form-check-input:checked { background-color: var(--accent-yellow); border-color: var(--accent-yellow); }
        
        /* Make columns fit content perfectly regardless of screen size */
        table.table { width: 100%; table-layout: auto; }
        table.table th, table.table td { white-space: nowrap; width: 1%; }
        table.table th:nth-child(3), table.table td:nth-child(3) { width: auto; white-space: normal; }

        @media (max-width: 992px) {
            .sidebar { display: none !important; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar d-none d-lg-block">
    <div class="p-3 text-center border-bottom border-secondary">
        <h5 class="mb-0">STUDENT PORTAL</h5>
    </div>
    <div class="list-group list-group-flush mt-3">
        <a href="dashboard_view.php" class="list-group-item list-group-item-action bg-transparent text-white border-0"><i class="bi bi-house-door me-2"></i> Home</a>
        <a href="registration.php" class="list-group-item list-group-item-action bg-transparent text-warning border-0"><i class="bi bi-journal-plus me-2"></i> Registration</a>
        <a href="fees.php" class="list-group-item list-group-item-action bg-transparent text-white border-0"><i class="bi bi-cash-stack me-2"></i> Finance</a>
    </div>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold">Subject Registration</h3>
                    <p class="text-muted mb-0">Select the units you will be taking this semester</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary p-2"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="card p-4">
            <form method="POST" action="">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50px">Select</th>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Department</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($available_subjects)): ?>
                                <?php foreach ($available_subjects as $subject): 
                                    // Handle variation in database column keys securely (id vs subject_id)
                                    $current_sub_id = $subject['subject_id'] ?? $subject['id'] ?? 0;
                                    $current_sub_code = $subject['subject_code'] ?? $subject['code'] ?? '';
                                    $current_sub_dept = $subject['department'] ?? $subject['department_id'] ?? '';
                                    
                                    $is_registered = in_array($current_sub_id, $registered_ids);
                                ?>
                                <tr class="subject-row">
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="subjects[]" 
                                                   value="<?php echo $current_sub_id; ?>" 
                                                   id="sub_<?php echo $current_sub_id; ?>"
                                                   <?php echo $is_registered ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($current_sub_code); ?></span></td>
                                    <td><?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?></td>
                                    <td><span class="text-muted small"><?php echo htmlspecialchars($current_sub_dept); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No subjects available for registration.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2 flex-wrap">
                    <button type="reset" class="btn btn-light">Clear Selection</button>
                    <button type="submit" name="register_subjects" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle me-2"></i> Confirm Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>