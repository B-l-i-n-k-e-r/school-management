<?php
/**
 * Shared Student Sidebar
 */
$active_page = $active_page ?? '';
$acad_active = in_array($active_page, ['registration','course_work','exam_card','results','academic_leave','clearance']);
$fin_active  = in_array($active_page, ['fees','receipts']);
$self_active = in_array($active_page, ['downloads','reset_password','profile']);
?>
<style>
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
        --primary-blue: #000a66;
        --sidebar-bg: #000a66;
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-light); color: var(--ink); margin: 0; overflow-x: hidden; }
    .main-content { margin-left: var(--sidebar-width); padding: 36px 40px 56px; transition: margin-left 0.3s ease; }
    .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }

    /* Sidebar */
    .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: var(--white); position: fixed; left: 0; top: 0; z-index: 1200; transition: left 0.3s ease; overflow-y: auto; padding-bottom: 24px; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.25) transparent; }
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.25); border-radius: 10px; }
    body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
    body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

    .sidebar-header { display: flex; align-items: center; gap: 12px; height: 76px; padding: 14px 20px; margin-bottom: 12px; background: rgba(255, 255, 255, 0.06); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .sidebar-header img { height: 44px; width: 44px; object-fit: contain; background: var(--white); border-radius: 12px; padding: 4px; flex-shrink: 0; }
    .sidebar-header span { color: var(--white); font-weight: 800; font-size: 0.95rem; letter-spacing: 0.08em; text-transform: uppercase; }

    .sidebar .nav-item { border-bottom: none; }
    .sidebar .nav-link { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 2px 12px; padding: 13px 16px; border-radius: 12px; color: rgba(255, 255, 255, 0.78); font-size: 0.93rem; font-weight: 600; text-decoration: none; cursor: pointer; transition: background 0.2s, color 0.2s; }
    .sidebar .nav-link:hover { background: rgba(255, 255, 255, 0.09); color: var(--white); }
    .sidebar .nav-link.active { background: rgba(255, 255, 255, 0.14); color: var(--white); }
    .sidebar .nav-link.active::before { content: ""; position: absolute; left: -12px; top: 10px; bottom: 10px; width: 4px; border-radius: 0 4px 4px 0; background: var(--accent); }
    .sidebar .nav-link > span i { color: rgba(255, 255, 255, 0.9); }

    .submenu { display: none; list-style: none; margin: 2px 12px 6px; padding: 6px; background: rgba(0, 0, 0, 0.2); border-radius: 12px; }
    .submenu li a { display: block; padding: 10px 14px 10px 42px; border-radius: 9px; color: rgba(255, 255, 255, 0.72); font-size: 0.86rem; font-weight: 500; text-decoration: none; transition: background 0.2s, color 0.2s; }
    .submenu li a:hover { background: rgba(255, 255, 255, 0.08); color: var(--accent); }
    .submenu li a.is-current { color: var(--accent); background: rgba(255, 255, 255, 0.08); font-weight: 700; }

    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 10, 102, 0.45); z-index: 1150; }
    .sidebar-overlay.active { display: block; }

    @media (max-width: 992px) {
        .sidebar { left: calc(-1 * var(--sidebar-width)); }
        .sidebar.active { left: 0 !important; }
        .topbar, .main-content { margin-left: 0 !important; }
        .main-content { padding: 24px 18px 48px; }
    }
</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
        <span>Student Portal</span>
    </div>

    <!-- Dashboard -->
    <div class="nav-item">
        <a href="dashboard_view.php" class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span><i class="bi bi-house-door-fill me-2"></i> Home</span>
        </a>
    </div>

    <!-- Academics -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $acad_active ? 'active' : ''; ?>">
            <span><i class="bi bi-journal-text me-2"></i> Academics</span>
        </a>
        <ul class="submenu" style="<?php echo $acad_active ? 'display:block;' : ''; ?>">
            <li><a href="registration.php"   <?php echo $active_page === 'registration'   ? 'class="is-current"' : ''; ?>>Subjects Registration</a></li>
            <li><a href="course_work.php"    <?php echo $active_page === 'course_work'    ? 'class="is-current"' : ''; ?>>Course Work</a></li>
            <li><a href="exam_card.php"      <?php echo $active_page === 'exam_card'      ? 'class="is-current"' : ''; ?>>Exam Card</a></li>
            <li><a href="results.php"        <?php echo $active_page === 'results'        ? 'class="is-current"' : ''; ?>>Exam Results</a></li>
            <li><a href="academic_leave.php" <?php echo $active_page === 'academic_leave' ? 'class="is-current"' : ''; ?>>Academic Leave</a></li>
            <li><a href="clearance.php"      <?php echo $active_page === 'clearance'      ? 'class="is-current"' : ''; ?>>Clearance</a></li>
        </ul>
    </div>

    <!-- Finance -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $fin_active ? 'active' : ''; ?>">
            <span><i class="bi bi-cash-stack me-2"></i> Finance</span>
        </a>
        <ul class="submenu" style="<?php echo $fin_active ? 'display:block;' : ''; ?>">
            <li><a href="fees.php"     <?php echo $active_page === 'fees'     ? 'class="is-current"' : ''; ?>>Fee Balance</a></li>
            <li><a href="receipts.php" <?php echo $active_page === 'receipts' ? 'class="is-current"' : ''; ?>>Receipts</a></li>
        </ul>
    </div>

    <!-- Messages -->
    <div class="nav-item">
        <a href="messages.php" class="nav-link <?php echo $active_page === 'messages' ? 'active' : ''; ?>">
            <span><i class="bi bi-chat-dots-fill me-2"></i> Messages</span>
        </a>
    </div>

    <!-- Self Service -->
    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $self_active ? 'active' : ''; ?>">
            <span><i class="bi bi-person-fill me-2"></i> Self Service</span>
        </a>
        <ul class="submenu" style="<?php echo $self_active ? 'display:block;' : ''; ?>">
            <li><a href="downloads.php"      <?php echo $active_page === 'downloads'      ? 'class="is-current"' : ''; ?>>Downloads</a></li>
            <li><a href="reset_password.php" <?php echo $active_page === 'reset_password' ? 'class="is-current"' : ''; ?>>Reset Password</a></li>
        </ul>
    </div>
</div>