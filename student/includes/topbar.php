<?php
/**
 * Shared Student Topbar
 * Expected variables before include:
 *   $student_display_name — string
 *   $extra_label — string (optional)
 */
$student_display_name = $student_display_name ?? 'Student';
$extra_label          = $extra_label          ?? '';
?>
<div class="topbar">
    <div class="d-flex align-items-center">
        <i class="bi bi-list fs-4" id="sidebarToggle" style="cursor:pointer;"></i>
        <?php if ($extra_label): ?>
            <span class="fw-bold ms-3 d-none d-md-inline" style="color: #3182ce;"><?php echo htmlspecialchars($extra_label); ?></span>
        <?php endif; ?>
    </div>

    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i>
            <?php echo htmlspecialchars($student_display_name); ?>
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
                            <?php echo htmlspecialchars($student_display_name); ?>
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
                <a class="dropdown-item py-2 small" href="reset_password.php">
                    <i class="bi bi-key me-2 text-primary"></i> Change Password
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item py-2 small text-danger" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                </a>
            </li>
        </ul>
    </div>
</div>