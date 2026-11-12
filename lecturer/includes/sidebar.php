<?php
/**
 * Shared Lecturer Sidebar
 * Includes shared lecturer styles matching the premium Admin UI.
 */
$active_page = $active_page ?? '';

$attendance_active = in_array($active_page, ['attendance', 'attendance_history']);
$exams_active      = in_array($active_page, ['results', 'grade_sheets']);
$students_active   = in_array($active_page, ['my_classes', 'assignments', 'assignment_submissions', 'discipline']);
?>
<style>
    /* ---------- Shared layout tokens & reset ---------- */
    :root {
        --primary: #000a66;
        --primary-soft: #1a2590;
        --primary-tint: #eef0ff;
        --accent: #facc15;
        --accent-tint: #fff6cc;
        --accent-deep: #8a6a00;
        --bg-light: #f6f7fb;
        --white: #ffffff;
        --ink: #14183a;
        --text-muted: #5b6280;
        --line: #e3e6f2;
        --radius-lg: 24px;
        --sidebar-width: 268px;

        /* Old names kept for compatibility */
        --primary-blue: #000a66;
        --sidebar-bg: #000a66;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--bg-light);
        color: var(--ink);
        margin: 0;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 36px 40px 56px;
        transition: margin-left 0.3s ease;
    }

    .panel {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 28px;
        box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
    }

    /* ---------- Sidebar ---------- */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        color: var(--white);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1200;
        transition: left 0.3s ease;
        overflow-y: auto;
        padding-bottom: 24px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
    }
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.25); border-radius: 10px; }

    body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
    body.sidebar-toggled .topbar,
    body.sidebar-toggled .main-content { margin-left: 0; }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 12px;
        height: 76px;
        padding: 14px 20px;
        margin-bottom: 12px;
        background: rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sidebar-header img {
        height: 44px;
        width: 44px;
        object-fit: contain;
        background: var(--white);
        border-radius: 12px;
        padding: 4px;
        flex-shrink: 0;
    }
    .sidebar-header span {
        color: var(--white);
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.08em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .sidebar .nav-item { border-bottom: none; }
    .sidebar .nav-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin: 2px 12px;
        padding: 13px 16px;
        border-radius: 12px;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.93rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s, color 0.2s;
    }
    .sidebar .nav-link:hover {
        background: rgba(255, 255, 255, 0.09);
        color: var(--white);
    }
    .sidebar .nav-link.active {
        background: rgba(255, 255, 255, 0.14);
        color: var(--white);
    }
    .sidebar .nav-link.active::before {
        content: "";
        position: absolute;
        left: -12px;
        top: 10px;
        bottom: 10px;
        width: 4px;
        border-radius: 0 4px 4px 0;
        background: var(--accent);
    }
    .sidebar .nav-link > span i { color: rgba(255, 255, 255, 0.9); }

    .submenu {
        display: none;
        list-style: none;
        margin: 2px 12px 6px;
        padding: 6px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 12px;
    }
    .submenu li a {
        display: block;
        padding: 10px 14px 10px 42px;
        border-radius: 9px;
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.86rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.2s, color 0.2s;
    }
    .submenu li a:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--accent);
    }
    .submenu li a.is-current {
        color: var(--accent);
        background: rgba(255, 255, 255, 0.08);
        font-weight: 700;
    }

    /* ---------- Mobile menu overlay ---------- */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 10, 102, 0.45);
        z-index: 1150;
    }
    .sidebar-overlay.active { display: block; }

    @media (max-width: 992px) {
        .sidebar { left: calc(-1 * var(--sidebar-width)); }
        .sidebar.active { left: 0 !important; }
        .topbar, .main-content { margin-left: 0 !important; }
        .main-content { padding: 24px 18px 48px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .sidebar, .main-content { transition: none; }
    }
</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
        <span>Staff Portal</span>
    </div>

    <!-- Dashboard -->
    <div class="nav-item">
        <a href="index.php" class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span><i class="bi bi-grid-1x2-fill me-2"></i> Dashboard</span>
        </a>
    </div>

    <!-- Attendance -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $attendance_active ? 'active' : ''; ?>">
            <span><i class="bi bi-calendar-check me-2"></i> Attendance</span>
            <i class="bi bi-<?php echo $attendance_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $attendance_active ? 'display:block;' : ''; ?>">
            <li><a href="attendance.php" <?php echo $active_page === 'attendance' ? 'class="is-current"' : ''; ?>>Daily Marking</a></li>
            <li><a href="attendance_history.php" <?php echo $active_page === 'attendance_history' ? 'class="is-current"' : ''; ?>>History</a></li>
        </ul>
    </div>

    <!-- Examination -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $exams_active ? 'active' : ''; ?>">
            <span><i class="bi bi-pencil-square me-2"></i> Examination</span>
            <i class="bi bi-<?php echo $exams_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $exams_active ? 'display:block;' : ''; ?>">
            <li><a href="results.php" <?php echo $active_page === 'results' ? 'class="is-current"' : ''; ?>>Mark Entry</a></li>
            <li><a href="grade_sheets.php" <?php echo $active_page === 'grade_sheets' ? 'class="is-current"' : ''; ?>>Grade Sheets</a></li>
        </ul>
    </div>

    <!-- Students -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $students_active ? 'active' : ''; ?>">
            <span><i class="bi bi-people-fill me-2"></i> Students</span>
            <i class="bi bi-<?php echo $students_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $students_active ? 'display:block;' : ''; ?>">
            <li><a href="my_classes.php" <?php echo $active_page === 'my_classes' ? 'class="is-current"' : ''; ?>>My Classes</a></li>
            <li><a href="assignments.php" <?php echo $active_page === 'assignments' ? 'class="is-current"' : ''; ?>>Assignments</a></li>
            <li><a href="assignment_submissions.php" <?php echo $active_page === 'assignment_submissions' ? 'class="is-current"' : ''; ?>>View Submissions</a></li>
            <li><a href="discipline.php" <?php echo $active_page === 'discipline' ? 'class="is-current"' : ''; ?>>Discipline</a></li>
        </ul>
    </div>

    <!-- Timetable -->
    <div class="nav-item">
        <a href="timetable.php" class="nav-link <?php echo $active_page === 'timetable' ? 'active' : ''; ?>">
            <span><i class="bi bi-clock me-2"></i> Timetable</span>
        </a>
    </div>

    <!-- Messaging -->
    <div class="nav-item">
        <a href="messaging.php" class="nav-link <?php echo $active_page === 'messaging' ? 'active' : ''; ?>">
            <span><i class="bi bi-chat-dots-fill me-2"></i> Messaging</span>
        </a>
    </div>

    <!-- Downloads -->
    <div class="nav-item">
        <a href="downloads.php" class="nav-link <?php echo $active_page === 'downloads' ? 'active' : ''; ?>">
            <span><i class="bi bi-cloud-arrow-down-fill me-2"></i> Downloads</span>
        </a>
    </div>
</div>