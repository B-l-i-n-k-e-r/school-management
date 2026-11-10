<?php
/**
 * Shared Lecturer Topbar (Matching Admin UI)
 * Expected variables before include:
 *   $staff_display_name — string
 *   $extra_label — string (optional)
 */
$staff_display_name = $staff_display_name ?? 'Staff';
$extra_label        = $extra_label        ?? '';
?>
<style>
    .topbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        margin-left: var(--sidebar-width, 268px);
        height: 72px;
        padding: 0 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--line, #e3e6f2);
        transition: margin-left 0.3s ease;
    }
    @media (max-width: 992px) {
        .topbar { padding: 0 20px; margin-left: 0 !important; }
    }

    .topbar-burger {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border: 1px solid var(--line, #e3e6f2);
        border-radius: 12px;
        background: var(--white, #fff);
        color: var(--primary, #000a66);
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        transition: background 0.2s;
    }
    .topbar-burger:hover { background: var(--primary-tint, #eef0ff); }

    .topbar-title {
        margin-left: 16px;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: var(--primary, #000a66);
    }

    .topbar .user-dropdown {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: 99px;
        background: var(--primary, #000a66);
        color: var(--white, #fff);
        font-family: inherit;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        transition: background 0.2s;
    }
    .topbar .user-dropdown:hover,
    .topbar .user-dropdown:focus,
    .topbar .user-dropdown.show {
        background: var(--primary-soft, #1a2590);
        color: var(--white, #fff);
    }
    .topbar .user-dropdown i { color: var(--accent, #facc15); font-size: 1.05rem; }

    .topbar .dropdown-menu {
        min-width: 230px;
        margin-top: 10px !important;
        padding: 8px;
        border: 1px solid var(--line, #e3e6f2);
        border-radius: 18px;
        box-shadow: 0 24px 40px -16px rgba(0, 10, 102, 0.3);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .topbar-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 10px 12px;
        margin-bottom: 6px;
        border-bottom: 1px solid var(--line, #e3e6f2);
    }
    .topbar-avatar {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: var(--primary, #000a66);
        color: var(--accent, #facc15);
        font-size: 1.1rem;
    }
    .topbar-user strong {
        display: block;
        max-width: 150px;
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--ink, #14183a);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .topbar-user span {
        font-size: 0.76rem;
        font-weight: 600;
        color: var(--text-muted, #5b6280);
    }
    .topbar .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--ink, #14183a);
    }
    .topbar .dropdown-item i { color: var(--primary, #000a66); }
    .topbar .dropdown-item:hover { background: var(--primary-tint, #eef0ff); color: var(--ink, #14183a); }
    .topbar .dropdown-item.is-danger,
    .topbar .dropdown-item.is-danger i { color: #b42318; }
    .topbar .dropdown-item.is-danger:hover { background: #fdeaea; }
    .topbar .dropdown-divider { margin: 6px 4px; border-color: var(--line, #e3e6f2); }
</style>

<div class="topbar">
    <div class="d-flex align-items-center">
        <button type="button" class="topbar-burger" id="hamburger" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <?php if ($extra_label): ?>
            <span class="topbar-title d-none d-md-inline"><?php echo htmlspecialchars($extra_label); ?></span>
        <?php endif; ?>
    </div>

    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i>
            <?php echo htmlspecialchars($staff_display_name); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li class="topbar-user">
                <div class="topbar-avatar"><i class="bi bi-person-fill"></i></div>
                <div>
                    <strong><?php echo htmlspecialchars($staff_display_name); ?></strong>
                    <span>Staff Member</span>
                </div>
            </li>
            <li>
                <a class="dropdown-item" href="profile.php">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="settings.php">
                    <i class="bi bi-gear-fill"></i> Settings
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <!-- Note: Changed to staff logout -->
                <a class="dropdown-item is-danger" href="staffLogout.php">
                    <i class="bi bi-box-arrow-right"></i> Sign out
                </a>
            </li>
        </ul>
    </div>
</div>