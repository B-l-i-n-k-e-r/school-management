<?php
// Ensure session is started to check roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the user role from session (fallback to empty if not logged in)
$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCEM Pro | School Management System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --sidebar-bg: #0f172a; 
            --primary-indigo: #6366f1; 
            --glass-bg: rgba(255, 255, 255, 0.9);
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f8fafc; 
            color: #1e293b; 
            margin: 0;
        }

        .sidebar { 
            min-height: 100vh; 
            background: var(--sidebar-bg); 
            color: white; 
            padding: 1.5rem; 
            position: fixed;
            width: 16.666667%; /* Matches col-md-2 */
            z-index: 1000;
        }

        .nav-link { 
            color: #94a3b8; 
            padding: 0.8rem 1rem; 
            border-radius: 12px; 
            margin-bottom: 0.5rem; 
            display: flex; 
            align-items: center; 
            text-decoration: none; 
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }

        .nav-link.active { 
            background: var(--primary-indigo); 
            color: #fff; 
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35); 
        }

        .main-content {
            margin-left: 16.666667%; 
            padding: 3rem;
            min-height: 100vh;
        }

        .section-label {
            color: #475569;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 1.5rem 0 0.5rem 1rem;
        }

        .brand-text {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0">
        <nav class="col-md-2 d-none d-md-block sidebar shadow-lg">
            <div class="brand-text">
                SCEM <span class="text-primary">PRO</span>
            </div>
            
            <div class="nav flex-column">
                <a href="/dashboard.php" class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill me-3"></i> Dashboard
                </a>

                <?php if ($userRole === 'admin'): ?>
                    <div class="section-label">Administration</div>
                    <a href="/admin/general.php" class="nav-link <?php echo ($currentPage == 'general.php') ? 'active' : ''; ?>">
                        <i class="bi bi-people-fill me-3"></i> Staff Directory
                    </a>
                    <a href="/admin/admission.php" class="nav-link <?php echo ($currentPage == 'admission.php') ? 'active' : ''; ?>">
                        <i class="bi bi-person-plus-fill me-3"></i> Admissions
                    </a>
                    <a href="/admin/course.php" class="nav-link <?php echo ($currentPage == 'course.php') ? 'active' : ''; ?>">
                        <i class="bi bi-journal-bookmark-fill me-3"></i> Standards
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'teacher'): ?>
                    <div class="section-label">Academic Tools</div>
                    <a href="/teacher/attendance.php" class="nav-link <?php echo ($currentPage == 'attendance.php') ? 'active' : ''; ?>">
                        <i class="bi bi-calendar-check me-3"></i> Attendance
                    </a>
                    <a href="/teacher/results.php" class="nav-link <?php echo ($currentPage == 'results.php') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-bar-graph me-3"></i> Exam Marks
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'student'): ?>
                    <div class="section-label">Student Portal</div>
                    <a href="/student/my_results.php" class="nav-link <?php echo ($currentPage == 'my_results.php') ? 'active' : ''; ?>">
                        <i class="bi bi-award-fill me-3"></i> My Reports
                    </a>
                    <a href="/student/fees.php" class="nav-link <?php echo ($currentPage == 'fees.php') ? 'active' : ''; ?>">
                        <i class="bi bi-wallet2 me-3"></i> Fee Status
                    </a>
                <?php endif; ?>
                
                <a href="/admin/adminLogOut.php" class="text-danger nav-link">
    <i class="bi bi-box-arrow-left me-2"></i> Logout
</a>
            </div>
        </nav>

        <main class="col-md-10 main-content">