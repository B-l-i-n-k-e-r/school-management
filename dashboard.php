<?php
session_start();

// 1. SESSION CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) { 
    header("Location: login.php"); 
    exit(); 
}

include('database/db_connect.php');
$role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'User';

include('admin/header.php'); 
?>

<div class="container mt-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold mb-0">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
            <span class="badge bg-primary text-uppercase mt-2"><?php echo $role; ?> Portal</span>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill">Sign Out</a>
        </div>
    </div>

    <hr class="mb-5">

    <div class="row g-4">
        <?php if ($role == 'admin'): ?>
            <div class="col-md-4">
                <div class="card h-100 p-4 shadow-sm border-0 bg-primary text-white">
                    <h5>Staff Management</h5>
                    <a href="admin/staff.php" class="btn btn-light btn-sm mt-auto">View Staff</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 p-4 shadow-sm border-0">
                    <h5>Admissions</h5>
                    <a href="admin/admission.php" class="btn btn-outline-primary btn-sm mt-auto">Open Module</a>
                </div>
            </div>
        <?php elseif ($role == 'teacher'): ?>
            <div class="col-md-6">
                <div class="card h-100 p-4 shadow-sm border-0 bg-success text-white">
                    <h5>My Classes</h5>
                    <a href="teacher/attendance.php" class="btn btn-light btn-sm mt-auto">Attendance</a>
                </div>
            </div>
        <?php elseif ($role == 'student' || $role == 'user'): ?>
            <div class="col-md-8">
                <div class="card h-100 p-4 shadow-sm border-0">
                    <h5>Report Card</h5>
                    <a href="user/results.php" class="btn btn-primary btn-sm mt-auto w-25">View Results</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('admin/footer.php'); ?>