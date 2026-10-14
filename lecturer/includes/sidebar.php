<?php
/**
 * Shared Lecturer Sidebar
 * Expected (optional) variables before include:
 *   $active_page — string, used to highlight current menu item
 *                  values: 'dashboard', 'attendance', 'attendance_history',
 *                          'results', 'grade_sheets', 'timetable',
 *                          'my_classes', 'assignments', 'discipline',
 *                          'messaging', 'profile', 'settings'
 */
$active_page = $active_page ?? '';
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
        <span>STAFF PORTAL</span>
    </div>
    
    <div class="nav-item-container">
        <a href="index.php" class="nav-link-custom <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
        </a>
    </div>
    
    <div class="nav-item-container">
        <a class="nav-link-custom dropdown-toggle-btn <?php echo in_array($active_page, ['attendance','attendance_history']) ? 'active' : ''; ?>">
            <span><i class="bi bi-calendar-check me-2"></i> Attendance</span>
            <i class="bi bi-<?php echo in_array($active_page, ['attendance','attendance_history']) ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo in_array($active_page, ['attendance','attendance_history']) ? 'display:block;' : ''; ?>">
            <li><a href="attendance.php" <?php echo $active_page === 'attendance' ? 'style="color: #f6e05e;"' : ''; ?>>Daily Marking</a></li>
            <li><a href="attendance_history.php" <?php echo $active_page === 'attendance_history' ? 'style="color: #f6e05e;"' : ''; ?>>History</a></li>
        </ul>
    </div>
    
    <div class="nav-item-container">
        <a class="nav-link-custom dropdown-toggle-btn <?php echo in_array($active_page, ['results','grade_sheets']) ? 'active' : ''; ?>">
            <span><i class="bi bi-pencil-square me-2"></i> Examination</span>
            <i class="bi bi-<?php echo in_array($active_page, ['results','grade_sheets']) ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo in_array($active_page, ['results','grade_sheets']) ? 'display:block;' : ''; ?>">
            <li><a href="results.php" <?php echo $active_page === 'results' ? 'style="color: #f6e05e;"' : ''; ?>>Mark Entry</a></li>
            <li><a href="grade_sheets.php" <?php echo $active_page === 'grade_sheets' ? 'style="color: #f6e05e;"' : ''; ?>>Grade Sheets</a></li>
        </ul>
    </div>
    
    <div class="nav-item-container">
        <a class="nav-link-custom dropdown-toggle-btn <?php echo in_array($active_page, ['my_classes','assignments','discipline']) ? 'active' : ''; ?>">
            <span><i class="bi bi-people-fill me-2"></i> Students</span>
            <i class="bi bi-<?php echo in_array($active_page, ['my_classes','assignments','discipline']) ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo in_array($active_page, ['my_classes','assignments','discipline']) ? 'display:block;' : ''; ?>">
            <li><a href="my_classes.php" <?php echo $active_page === 'my_classes' ? 'style="color: #f6e05e;"' : ''; ?>>My Classes</a></li>
            <li><a href="assignments.php" <?php echo $active_page === 'assignments' ? 'style="color: #f6e05e;"' : ''; ?>>Assignments</a></li>
            <li><a href="discipline.php" <?php echo $active_page === 'discipline' ? 'style="color: #f6e05e;"' : ''; ?>>Discipline</a></li>
        </ul>
    </div>
    
    <div class="nav-item-container">
        <a href="timetable.php" class="nav-link-custom <?php echo $active_page === 'timetable' ? 'active' : ''; ?>">
            <span><i class="bi bi-clock me-2"></i> Timetable</span>
        </a>
    </div>
    
    <div class="nav-item-container">
        <a href="messaging.php" class="nav-link-custom <?php echo $active_page === 'messaging' ? 'active' : ''; ?>">
            <span><i class="bi bi-chat-dots-fill me-2"></i> Messaging</span>
        </a>
    </div>
</div>