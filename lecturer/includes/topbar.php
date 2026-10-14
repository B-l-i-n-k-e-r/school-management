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
<div class="topbar">
    <div class="d-flex align-items-center">
        <i class="bi bi-list me-3" id="sidebarToggle" style="cursor:pointer; font-size:1.5rem; color:var(--primary-blue);"></i>
        <?php if ($extra_label): ?>
            <span class="fw-bold text-primary d-none d-md-inline"><?php echo htmlspecialchars($extra_label); ?></span>
        <?php elseif ($page_title): ?>
            <span class="fw-bold text-primary d-none d-md-inline"><?php echo htmlspecialchars($page_title); ?></span>
        <?php endif; ?>
    </div>

    <div class="dropdown">
        <button class="btn btn-sm btn-primary dropdown-toggle fw-bold d-flex align-items-center gap-2"
                type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i>
            <span><?php echo htmlspecialchars($staff_display_name); ?></span>
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
                            <?php echo htmlspecialchars($staff_display_name); ?>
                        </div>
                        <div class="text-muted" style="font-size:0.7rem;">Signed in</div>
                    </div>
                </div>
            </li>

            <li>
                <a class="dropdown-item py-2 small" href="profile.php">
                    <i class="bi bi-person me-2 text-primary"></i> My Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item py-2 small" href="settings.php">
                    <i class="bi bi-gear-fill me-2 text-primary"></i> Settings
                </a>
            </li>

            <li><hr class="dropdown-divider"></li>

            <li>
                <a class="dropdown-item py-2 small text-danger" href="../admin/adminLogOut.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>