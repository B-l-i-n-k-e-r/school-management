<?php
// includes/navbar.php
?>
<div class="topbar">
    <i class="bi bi-list" id="hamburger"></i>
    <div class="dropdown">
        <button class="btn btn-light dropdown-toggle border-0 fw-bold" type="button" data-bs-toggle="dropdown">
            ADMIN
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="adminLogOut.php">Logout</a></li>
        </ul>
    </div>
</div>