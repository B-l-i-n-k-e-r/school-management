<?php
/**
 * Shared Admin Sidebar
 * Also carries the shared admin styles (colours, layout, panels),
 * so every admin page that includes this file gets the same look.
 */
$active_page = $active_page ?? '';

$students_active  = in_array($active_page, ['admission', 'student_list']);
$academics_active = in_array($active_page, ['forms', 'subjects', 'exams', 'manage_timetable']);
$finance_active   = in_array($active_page, ['fee_structure', 'collections', 'reports']);
?>
<style>
    /* ---------- Shared admin tokens & layout ---------- */
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

        /* old names, so pages not yet restyled keep working */
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

    .sidebar a:focus-visible,
    .topbar a:focus-visible,
    .topbar button:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 2px;
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
        background-image: none;
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
    /* =====================================================
       Shared page components (used by every admin page)
       ===================================================== */

    /* Page header */
    .ui-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 28px;
    }
    .ui-head h1 {
        margin: 0 0 4px;
        font-size: 1.7rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--primary);
    }
    .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
    .ui-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    /* Buttons */
    .ui-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 22px;
        border: 1.5px solid transparent;
        border-radius: 99px;
        font-family: inherit;
        font-size: 0.9rem;
        font-weight: 800;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s, box-shadow 0.2s, color 0.2s;
    }
    .ui-btn:hover { transform: translateY(-1px); }
    .ui-btn.is-sm { padding: 8px 16px; font-size: 0.82rem; }
    .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
    .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
    .ui-btn.is-navy { background: var(--primary); color: var(--white); }
    .ui-btn.is-navy:hover { background: var(--primary-soft); color: var(--white); }
    .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
    .ui-btn.is-ghost:hover { background: var(--primary-tint); color: var(--primary); }
    .ui-btn.is-success { background: #14633a; color: var(--white); }
    .ui-btn.is-success:hover { background: #0f4f2e; color: var(--white); }
    .ui-btn.is-danger { background: var(--white); border-color: #f3c7c7; color: #b42318; }
    .ui-btn.is-danger:hover { background: #fdeaea; color: #b42318; }

    /* Messages */
    .ui-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 18px;
        border-radius: 16px;
        margin-bottom: 24px;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.5;
    }
    .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
    .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
    .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }
    .ui-alert.is-warning { background: var(--accent-tint); color: var(--accent-deep); }
    .ui-alert.is-info    { background: var(--primary-tint); color: var(--primary); }

    /* Panels that hold a table */
    .panel.is-flush { padding: 0; overflow: hidden; }
    .ui-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 24px 28px;
    }
    .ui-panel-head h2 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: var(--primary);
    }
    .ui-panel-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .ui-count {
        padding: 5px 14px;
        border-radius: 99px;
        background: var(--accent);
        color: var(--primary);
        font-size: 0.8rem;
        font-weight: 800;
        white-space: nowrap;
    }

    /* Tables */
    .ui-table-wrap { overflow-x: auto; }
    .ui-table { width: 100%; border-collapse: collapse; }
    .ui-table th {
        padding: 14px 18px;
        text-align: left;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--bg-light);
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        white-space: nowrap;
    }
    .ui-table td {
        padding: 16px 18px;
        border-bottom: 1px solid var(--line);
        font-size: 0.92rem;
        font-weight: 500;
        color: var(--text-muted);
        vertical-align: middle;
    }
    .ui-table th:first-child, .ui-table td:first-child { padding-left: 28px; }
    .ui-table th:last-child,  .ui-table td:last-child  { padding-right: 28px; }
    .ui-table tbody tr:last-child td { border-bottom: none; }
    .ui-table tbody tr { transition: background-color 0.2s; }
    .ui-table tbody tr:hover { background: var(--primary-tint); }
    .ui-table .is-right  { text-align: right; }
    .ui-table .is-center { text-align: center; }
    .ui-table .nowrap    { white-space: nowrap; }
    .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
    .ui-sub  { display: block; font-size: 0.82rem; color: var(--text-muted); font-weight: 500; }
    .ui-row-actions { display: flex; gap: 8px; justify-content: flex-end; align-items: center; }

    /* Badges */
    .ui-badge {
        display: inline-flex;
        padding: 5px 12px;
        border-radius: 99px;
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .ui-badge.is-navy { background: var(--primary-tint); color: var(--primary); }
    .ui-badge.is-gold { background: var(--accent-tint); color: var(--accent-deep); }
    .ui-badge.is-soft { background: var(--bg-light); border: 1px solid var(--line); color: var(--text-muted); }

    /* Search box */
    .ui-search { position: relative; width: 260px; max-width: 100%; }
    .ui-search i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }
    .ui-search input {
        width: 100%;
        box-sizing: border-box;
        padding: 11px 16px 11px 42px;
        border: 1.5px solid var(--line);
        border-radius: 99px;
        background: var(--bg-light);
        font-family: inherit;
        font-size: 0.9rem;
        color: var(--ink);
    }
    .ui-search input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--white);
    }

    /* Empty state */
    .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
    .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
    .ui-empty p { margin: 0; font-weight: 600; }
    .ui-empty small { display: block; margin-top: 6px; }

    /* Round icon buttons (edit / delete) */
    .ui-icon {
        width: 38px;
        height: 38px;
        display: inline-grid;
        place-items: center;
        border: none;
        border-radius: 12px;
        background: var(--bg-light);
        color: var(--primary);
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .ui-icon:hover { background: var(--primary-tint); }
    .ui-icon.is-danger { color: #b42318; }
    .ui-icon.is-danger:hover { background: #fdeaea; }

    /* Forms */
    .ui-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .ui-form-grid .is-full { grid-column: 1 / -1; }
    .ui-field label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--ink);
    }
    .ui-input {
        width: 100%;
        padding: 13px 16px;
        border: 1.5px solid var(--line);
        border-radius: 14px;
        background-color: var(--bg-light);
        font-family: inherit;
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--ink);
        transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
    }
    .ui-input:focus {
        outline: none;
        border-color: var(--primary);
        background-color: var(--white);
        box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1);
    }

    /* Pop-up windows */
    .modal-content.ui-modal {
        border: none;
        border-radius: 28px;
        box-shadow: 0 40px 80px -20px rgba(0, 10, 102, 0.45);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .ui-modal .modal-header { border: none; padding: 28px 32px 8px; }
    .ui-modal .modal-title { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em; color: var(--primary); }
    .ui-modal .modal-body { padding: 16px 32px 8px; }
    .ui-modal .modal-footer { border: none; padding: 16px 32px 28px; gap: 10px; }

    @media (max-width: 640px) {
        .ui-form-grid { grid-template-columns: 1fr; }
        .ui-modal .modal-header, .ui-modal .modal-body, .ui-modal .modal-footer { padding-left: 22px; padding-right: 22px; }
    }

    /* Printing a page leaves out the menus and buttons */
    @media print {
        .sidebar, .topbar, .sidebar-overlay, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0; }
        .panel { box-shadow: none; }
    }

</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.style.display='none'">
        <span>Admin Portal</span>
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
            <li><a href="admission.php"    <?php echo $active_page === 'admission'    ? 'class="is-current"' : ''; ?>>New Admission</a></li>
            <li><a href="student_list.php" <?php echo $active_page === 'student_list' ? 'class="is-current"' : ''; ?>>Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $academics_active ? 'active' : ''; ?>">
            <span><i class="bi bi-journal-bookmark-fill me-2"></i> Academics</span>
            <i class="bi bi-<?php echo $academics_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $academics_active ? 'display:block;' : ''; ?>">
            <li><a href="forms.php"    <?php echo $active_page === 'forms'    ? 'class="is-current"' : ''; ?>>Manage Forms</a></li>
            <li><a href="subjects.php" <?php echo $active_page === 'subjects' ? 'class="is-current"' : ''; ?>>Manage Subjects</a></li>
            <li><a href="exams.php"    <?php echo $active_page === 'exams'    ? 'class="is-current"' : ''; ?>>Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="javascript:void(0)" class="nav-link dropdown-toggle-btn <?php echo $finance_active ? 'active' : ''; ?>">
            <span><i class="bi bi-cash-stack me-2"></i> Finance</span>
            <i class="bi bi-<?php echo $finance_active ? 'dash' : 'plus'; ?> small"></i>
        </a>
        <ul class="submenu" style="<?php echo $finance_active ? 'display:block;' : ''; ?>">
            <li><a href="fee_structure.php" <?php echo $active_page === 'fee_structure' ? 'class="is-current"' : ''; ?>>Fee Structure</a></li>
            <li><a href="collections.php"   <?php echo $active_page === 'collections'   ? 'class="is-current"' : ''; ?>>Fee Collections</a></li>
            <li><a href="reports.php"       <?php echo $active_page === 'reports'       ? 'class="is-current"' : ''; ?>>Financial Reports</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a href="notices.php" class="nav-link <?php echo $active_page === 'notices' ? 'active' : ''; ?>">
            <span><i class="bi bi-megaphone-fill me-2"></i> Notices</span>
        </a>
    </div>

    <div class="nav-item">
        <a href="downloads.php" class="nav-link <?php echo $active_page === 'downloads' ? 'active' : ''; ?>">
            <span><i class="bi bi-cloud-arrow-down-fill me-2"></i> Downloads</span>
        </a>
    </div>

    <div class="nav-item">
        <a href="staff.php" class="nav-link <?php echo $active_page === 'staff' ? 'active' : ''; ?>">
            <span><i class="bi bi-person-badge-fill me-2"></i> Staff Directory</span>
        </a>
    </div>
</div>