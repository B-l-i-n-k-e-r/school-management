<?php
/**
 * Shared Admin Topbar
 * Expected variables before include:
 *   $admin_display_name — string (optional, defaults to session adminUser)
 *   $extra_label — string (optional)
 */
$admin_display_name = $admin_display_name ?? ($_SESSION['adminUser'] ?? 'Administrator');
$extra_label        = $extra_label        ?? '';
?>
<div class="topbar">
    <div class="d-flex align-items-center">
        <i class="bi bi-list" id="hamburger" style="cursor:pointer; font-size:1.8rem; color:#34445c;"></i>
        <?php if ($extra_label): ?>
            <span class="fw-bold ms-3 d-none d-md-inline" style="color: #34445c;"><?php echo htmlspecialchars($extra_label); ?></span>
        <?php endif; ?>
    </div>

    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown"
                style="background:#1a3066; color:white; padding:8px 16px; border:none; border-radius:8px; font-weight:700; font-size:0.8rem;">
            <i class="bi bi-person-circle me-1"></i>
            <?php echo htmlspecialchars($admin_display_name); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3 mt-2" style="min-width: 220px;">
            <li class="px-3 pt-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                         style="width:36px;height:36px;">
                        <i class="bi bi-person-fill text-primary"></i>
                    </div>
                    <div class="lh-sm">
                        <div class="fw-bold small text-truncate" style="max-width:150px;">
                            <?php echo htmlspecialchars($admin_display_name); ?>
                        </div>
                        <div class="text-muted" style="font-size:0.7rem;">Administrator</div>
                    </div>
                </div>
            </li>
            <li>
                <a class="dropdown-item py-2 small" href="settings.php">
                    <i class="bi bi-gear-fill me-2 text-primary"></i> Settings
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item py-2 small text-danger" href="adminLogOut.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                </a>
            </li>
        </ul>
    </div>
</div>