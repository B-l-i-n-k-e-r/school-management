<?php
/**
 * Shared Student Sidebar
 * Expected (optional) variable before include:
 *   $active_page — string, highlights the current menu item
 *                  values: 'dashboard', 'registration', 'course_work', 'exam_card',
 *                          'results', 'academic_leave', 'clearance', 'fees',
 *                          'receipts', 'downloads', 'reset_password', 'profile'
 */
$active_page = $active_page ?? '';
$acad_active = in_array($active_page, ['registration','course_work','exam_card','results','academic_leave','clearance']);
$fin_active  = in_array($active_page, ['fees','receipts']);
$self_active = in_array($active_page, ['downloads','reset_password','profile']);
?>
<div class="overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
        <span>STUDENT PORTAL</span>
    </div>

    <div class="nav-item">
        <a href="dashboard_view.php" class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span><i class="bi bi-house-door-fill me-2"></i> Home</span>
        </a>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $acad_active ? 'active' : ''; ?>">
            <span><i class="bi bi-journal-text me-2"></i> Academics</span>
            <i class="bi bi-<?php echo $acad_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $acad_active ? 'display:block;' : ''; ?>">
            <li><a href="registration.php"   <?php echo $active_page === 'registration'   ? 'style="color:#f6e05e;"' : ''; ?>>Subjects Registration</a></li>
            <li><a href="course_work.php"    <?php echo $active_page === 'course_work'    ? 'style="color:#f6e05e;"' : ''; ?>>Course Work</a></li>
            <li><a href="exam_card.php"      <?php echo $active_page === 'exam_card'      ? 'style="color:#f6e05e;"' : ''; ?>>Exam Card</a></li>
            <li><a href="results.php"        <?php echo $active_page === 'results'        ? 'style="color:#f6e05e;"' : ''; ?>>Exam Results</a></li>
            <li><a href="academic_leave.php" <?php echo $active_page === 'academic_leave' ? 'style="color:#f6e05e;"' : ''; ?>>Academic Leave</a></li>
            <li><a href="clearance.php"      <?php echo $active_page === 'clearance'      ? 'style="color:#f6e05e;"' : ''; ?>>Clearance</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $fin_active ? 'active' : ''; ?>">
            <span><i class="bi bi-cash-stack me-2"></i> Finance</span>
            <i class="bi bi-<?php echo $fin_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $fin_active ? 'display:block;' : ''; ?>">
            <li><a href="fees.php"     <?php echo $active_page === 'fees'     ? 'style="color:#f6e05e;"' : ''; ?>>Fee Balance</a></li>
            <li><a href="receipts.php" <?php echo $active_page === 'receipts' ? 'style="color:#f6e05e;"' : ''; ?>>Receipts</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $self_active ? 'active' : ''; ?>">
            <span><i class="bi bi-person-fill me-2"></i> Self Service</span>
            <i class="bi bi-<?php echo $self_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $self_active ? 'display:block;' : ''; ?>">
            <li><a href="downloads.php"      <?php echo $active_page === 'downloads'      ? 'style="color:#f6e05e;"' : ''; ?>>Downloads</a></li>
            <li><a href="reset_password.php" <?php echo $active_page === 'reset_password' ? 'style="color:#f6e05e;"' : ''; ?>>Reset Password</a></li>
        </ul>
    </div>
</div>