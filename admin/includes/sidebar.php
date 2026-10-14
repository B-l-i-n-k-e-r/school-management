<?php
// includes/sidebar.php
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/45'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav flex-column">
        <div class="nav-item">
            <a href="index.php" class="nav-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn">
                <i class="bi bi-people-fill"></i>
                <span>Students</span>
                <i class="bi bi-plus small"></i>
            </a>
            <ul class="submenu">
                <li><a href="admission.php">New Admission</a></li>
                <li><a href="student_list.php">Manage Students</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn active">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Academics</span>
                <i class="bi bi-dash small"></i>
            </a>
            <ul class="submenu" style="display: block;">
                <li><a href="forms.php">Manage Forms</a></li>
                <li><a href="subjects.php" style="color: #f6e05e;">Manage Subjects</a></li>
                <li><a href="exams.php">Exam Schedules</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a class="nav-link dropdown-toggle-btn">
                <i class="bi bi-cash-stack"></i>
                <span>Finance</span>
                <i class="bi bi-plus small"></i>
            </a>
            <ul class="submenu">
                <li><a href="fee_structure.php">Fee Structure</a></li>
                <li><a href="collections.php">Fee Collections</a></li>
                <li><a href="reports.php">Financial Reports</a></li>
            </ul>
        </div>

        <div class="nav-item">
            <a href="staff.php" class="nav-link"><i class="bi bi-person-badge-fill"></i><span>Staff Directory</span></a>
        </div>
        
        <div class="nav-item mt-4">
            <a href="adminLogOut.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
        </div>
    </div>
</div>