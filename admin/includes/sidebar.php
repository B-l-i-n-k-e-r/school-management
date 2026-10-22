<?php
/**
 * Shared Admin Sidebar
 * Expected (optional) variable before include:
 *   $active_page — string, highlights the current menu item
 *                  values: 'dashboard', 'admission', 'student_list',
 *                          'forms', 'subjects', 'exams', 'manage_timetable',
 *                          'fee_structure', 'collections', 'reports',
 *                          'staff', 'notices', 'settings'
 */
$active_page = $active_page ?? '';

$students_active  = in_array($active_page, ['admission', 'student_list']);
$academics_active = in_array($active_page, ['forms', 'subjects', 'exams', 'manage_timetable']);
$finance_active   = in_array($active_page, ['fee_structure', 'collections', 'reports']);
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
        <span>ADMIN PORTAL</span>
    </div>

    <div class="nav-item">
        <a href="index.php" class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span><i class="bi bi-grid-fill me-2"></i> Dashboard</span>
        </a>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $students_active ? 'active' : ''; ?>">
            <span><i class="bi bi-people-fill me-2"></i> Students</span>
            <i class="bi bi-<?php echo $students_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $students_active ? 'display:block;' : ''; ?>">
            <li><a href="admission.php"    <?php echo $active_page === 'admission'    ? 'style="color:#f6e05e;"' : ''; ?>>New Admission</a></li>
            <li><a href="student_list.php" <?php echo $active_page === 'student_list' ? 'style="color:#f6e05e;"' : ''; ?>>Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $academics_active ? 'active' : ''; ?>">
            <span><i class="bi bi-journal-bookmark-fill me-2"></i> Academics</span>
            <i class="bi bi-<?php echo $academics_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $academics_active ? 'display:block;' : ''; ?>">
            <li><a href="forms.php"      <?php echo $active_page === 'forms'      ? 'style="color:#f6e05e;"' : ''; ?>>Manage Forms</a></li>
            <li><a href="subjects.php"   <?php echo $active_page === 'subjects'   ? 'style="color:#f6e05e;"' : ''; ?>>Manage Subjects</a></li>
            <li><a href="exams.php"      <?php echo $active_page === 'exams'      ? 'style="color:#f6e05e;"' : ''; ?>>Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $finance_active ? 'active' : ''; ?>">
            <span><i class="bi bi-cash-stack me-2"></i> Finance</span>
            <i class="bi bi-<?php echo $finance_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $finance_active ? 'display:block;' : ''; ?>">
            <li><a href="fee_structure.php" <?php echo $active_page === 'fee_structure' ? 'style="color:#f6e05e;"' : ''; ?>>Fee Structure</a></li>
            <li><a href="collections.php"   <?php echo $active_page === 'collections'   ? 'style="color:#f6e05e;"' : ''; ?>>Fee Collections</a></li>
            <li><a href="reports.php"       <?php echo $active_page === 'reports'       ? 'style="color:#f6e05e;"' : ''; ?>>Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="notices.php" class="nav-link <?php echo $active_page === 'notices' ? 'active' : ''; ?>">
            <span><i class="bi bi-megaphone-fill me-2"></i> Notices</span>
        </a>
    </div>

    <div class="nav-item">
        <a href="staff.php" class="nav-link <?php echo $active_page === 'staff' ? 'active' : ''; ?>">
            <span><i class="bi bi-person-badge-fill me-2"></i> Staff Directory</span>
        </a>
    </div>
</div>