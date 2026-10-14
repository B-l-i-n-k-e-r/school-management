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
$status = "";

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Validate if passwords match
    if ($new_password !== $confirm_password) {
        $status = "<div class='alert alert-danger border-0 shadow-sm'>New passwords do not match.</div>";
    } elseif (strlen($new_password) < 6) {
        $status = "<div class='alert alert-danger border-0 shadow-sm'>Password must be at least 6 characters.</div>";
    } else {
        // 2. Fetch current hashed password from DB
        $stmt = $pdo->prepare("SELECT password FROM students WHERE adm_no = ?");
        $stmt->execute([$adm_no]);
        $user = $stmt->fetch();

        // 3. Verify current password
        if ($user && password_verify($current_password, $user['password'])) {
            // 4. Hash new password and update
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE students SET password = ? WHERE adm_no = ?");
            
            if ($update->execute([$new_hashed, $adm_no])) {
                $status = "<div class='alert alert-success border-0 shadow-sm'>Password updated successfully!</div>";
            }
        } else {
            $status = "<div class='alert alert-danger border-0 shadow-sm'>Current password is incorrect.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Security | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .main-content { padding: 60px 0; }
        .card { border: none; border-radius: 16px; }
        .input-group-text { background-color: transparent; border-inline-end: none; }
        .form-control { border-inline-start: none; padding-block: 10px; }
        .form-control:focus { box-shadow: none; border-color: #dee2e6; }
        .btn-primary { padding-block: 12px; border-radius: 10px; font-weight: 600; }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="text-center mb-4">
                <div class="bg-white d-inline-block p-3 rounded-circle shadow-sm mb-3">
                    <i class="bi bi-shield-lock text-primary fs-2"></i>
                </div>
                <h3 class="fw-bold">Security Settings</h3>
                <p class="text-muted">Update your password to keep your account secure.</p>
            </div>

            <?php echo $status; ?>

            <div class="card shadow-sm p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><i class="bi bi-key"></i></span>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                        </div>
                    </div>

                    <hr class="my-4 opacity-50">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><i class="bi bi-check2-circle"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                        </div>
                    </div>

                    <button type="submit" name="update_password" class="btn btn-primary w-100 mb-2">
                        Update Password
                    </button>
                    <a href="dashboard_view.php" class="btn btn-link w-100 text-decoration-none text-muted small">Cancel and Return</a>
                </form>
            </div>

            <div class="mt-4 p-3 bg-white rounded-3 border-start border-4 border-info shadow-sm">
                <p class="mb-0 small text-muted">
                    <i class="bi bi-lightbulb me-2 text-info"></i>
                    <strong>Tip:</strong> Use a combination of letters, numbers, and symbols to create a strong password.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>