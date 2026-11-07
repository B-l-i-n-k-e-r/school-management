<?php
/**
 * Shared Lecturer Topbar
 * Expected variables before include:
 *   $staff_display_name — string
 *   $extra_label — string (optional)
 *   $page_title — string (optional fallback)
 */
$staff_display_name = $staff_display_name ?? 'Staff';
$page_title         = $page_title         ?? '';
$extra_label        = $extra_label        ?? '';
?>
<style>
    /* Topbar specific styles to match the modern UI */
    .topbar { 
        margin-left: var(--sidebar-width); 
        background: #fff; 
        height: 70px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 0 30px; 
        border-bottom: 1px solid #e2e8f0; 
        transition: all 0.3s ease; 
        position: sticky;
        top: 0;
        z-index: 1100;
    }
    
    .topbar-user-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        padding: 5px 15px 5px 5px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s;
        text-decoration: none;
        color: var(--text);
    }
    
    .topbar-user-btn:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    
    .topbar-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.9rem;
    }

    .dropdown-menu-modern {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        padding: 8px;
        min-width: 240px;
    }
    
    .dropdown-item-modern {
        border-radius: 10px;
        padding: 10px 15px;
        font-weight: 600;
        color: #475569;
        transition: 0.2s;
        font-size: 0.9rem;
    }
    
    .dropdown-item-modern:hover {
        background: #f1f5f9;
        color: var(--primary-blue);
    }
    
    .dropdown-item-modern.danger:hover {
        background: #fef2f2;
        color: #dc2626;
    }
</style>

<div class="topbar">
    <div class="d-flex align-items-center">
        <i class="bi bi-list me-3" id="sidebarToggle" style="cursor:pointer; font-size:1.6rem; color:var(--primary-blue);"></i>
        <?php if ($extra_label): ?>
            <h5 class="fw-bold mb-0 text-dark d-none d-md-inline" style="font-size: 1.1rem;"><?php echo htmlspecialchars($extra_label); ?></h5>
        <?php elseif ($page_title): ?>
            <h5 class="fw-bold mb-0 text-dark d-none d-md-inline" style="font-size: 1.1rem;"><?php echo htmlspecialchars($page_title); ?></h5>
        <?php endif; ?>
    </div>

    <div class="dropdown">
        <button class="topbar-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="topbar-avatar">
                <?php echo htmlspecialchars(strtoupper(substr($staff_display_name, 0, 1))); ?>
            </div>
            <span class="fw-bold small d-none d-sm-inline"><?php echo htmlspecialchars($staff_display_name); ?></span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-modern mt-2">
            <!-- User Header -->
            <li class="px-3 py-3 border-bottom mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="topbar-avatar" style="width: 45px; height: 45px; font-size: 1.2rem;">
                        <?php echo htmlspecialchars(strtoupper(substr($staff_display_name, 0, 1))); ?>
                    </div>
                    <div class="lh-sm">
                        <div class="fw-bold text-dark text-truncate" style="max-width:150px;">
                            <?php echo htmlspecialchars($staff_display_name); ?>
                        </div>
                        <div class="text-muted small">
                            <i class="bi bi-shield-check text-success me-1"></i>Staff Account
                        </div>
                    </div>
                </div>
            </li>

            <!-- Links -->
            <li>
                <a class="dropdown-item dropdown-item-modern d-flex align-items-center gap-2" href="profile.php">
                    <i class="bi bi-person-circle fs-5 text-primary"></i> My Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item dropdown-item-modern d-flex align-items-center gap-2" href="settings.php">
                    <i class="bi bi-gear-fill fs-5 text-secondary"></i> Account Settings
                </a>
            </li>

            <li><hr class="dropdown-divider opacity-50"></li>

            <!-- Logout -->
            <li>
                <a class="dropdown-item dropdown-item-modern danger d-flex align-items-center gap-2" href="../admin/adminLogOut.php">
                    <i class="bi bi-box-arrow-right fs-5"></i> Sign Out
                </a>
            </li>
        </ul>
    </div>
</div>