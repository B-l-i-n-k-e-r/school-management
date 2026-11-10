<?php
// Highlights the link for the page the visitor is on
$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
    'index.php'     => 'Home',
    'about.php'     => 'About',
    'year.php'      => 'Classes',
    'staff.php'     => 'Staff',
    'admission.php' => 'Admission',
    'activity.php'  => 'Activities',
    'feedback.php'  => 'Feedback',
    'contact.php'   => 'Contact',
];
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
    :root {
        --primary: #000a66;
        --primary-tint: #eef0ff;
        --accent: #facc15;
        --bg-light: #f6f7fb;
        --white: #ffffff;
        --ink: #14183a;
        --nav-text: #4a5173;
        --line: #e3e6f2;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        margin: 0;
    }

    /* ---------- Header bar ---------- */
    .site-header {
        position: sticky;
        top: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        box-sizing: border-box;
        padding: 12px 5%;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--line);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .brand-side {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        flex-shrink: 0;
    }
    .brand-side img {
        height: 46px;
        width: auto;
        display: block;
        border-radius: 10px;
    }
    .brand-side h2 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.2;
        color: var(--primary);
    }

    /* ---------- Links ---------- */
    .nav-links {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        flex-grow: 1;
    }
    .nav-links > a {
        position: relative;
        padding: 10px 14px;
        border-radius: 10px;
        text-decoration: none;
        color: var(--nav-text);
        font-weight: 600;
        font-size: 0.93rem;
        white-space: nowrap;
        transition: color 0.2s, background 0.2s;
    }
    .nav-links > a:hover {
        color: var(--primary);
        background: var(--primary-tint);
    }
    .nav-links > a.active {
        color: var(--primary);
        font-weight: 700;
    }
    .nav-links > a.active::after {
        content: "";
        position: absolute;
        left: 14px;
        right: 14px;
        bottom: 3px;
        height: 3px;
        border-radius: 99px;
        background: var(--accent);
    }

    .nav-links a:focus-visible,
    .dropbtn:focus-visible,
    .menu-toggle:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 2px;
    }

    /* ---------- Login dropdown ---------- */
    .dropdown {
        position: relative;
        display: inline-block;
        margin-left: 10px;
    }
    .dropbtn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 11px 20px;
        border: none;
        border-radius: 99px;
        background: var(--primary);
        color: var(--white);
        font-family: inherit;
        font-size: 0.93rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s, transform 0.2s;
    }
    .dropbtn:hover { background: #1a2590; }
    .dropbtn i.bi-chevron-down { transition: transform 0.25s; }
    .dropbtn.active i.bi-chevron-down { transform: rotate(180deg); }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        min-width: 230px;
        padding: 8px;
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: 16px;
        box-shadow: 0 20px 40px -12px rgba(0, 10, 102, 0.25);
    }
    .dropdown-content.show {
        display: block;
        animation: dropIn 0.18s ease-out;
    }
    @keyframes dropIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: none; }
    }

    .dropdown-content a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        text-decoration: none;
        color: var(--ink);
        font-size: 0.9rem;
        font-weight: 600;
        transition: background 0.2s;
    }
    .dropdown-content a i {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: var(--primary-tint);
        color: var(--primary);
        font-size: 1rem;
        transition: background 0.2s, color 0.2s;
    }
    .dropdown-content a:hover { background: var(--primary-tint); }
    .dropdown-content a:hover i {
        background: var(--accent);
        color: var(--primary);
    }

    /* ---------- Mobile menu button (hidden on desktop) ---------- */
    .menu-toggle {
        display: none;
        width: 44px;
        height: 44px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--white);
        color: var(--primary);
        font-size: 1.5rem;
        cursor: pointer;
        place-items: center;
    }

    /* ---------- Tablet & mobile ---------- */
    @media (max-width: 1040px) {
        .site-header { padding: 10px 20px; }
        .menu-toggle { display: grid; }

        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
            padding: 14px 20px 20px;
            background: var(--white);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 24px 30px -20px rgba(0, 10, 102, 0.25);
            max-height: calc(100vh - 70px);
            overflow-y: auto;
        }
        .nav-links.open { display: flex; }

        .nav-links > a { padding: 14px; font-size: 1rem; }
        .nav-links > a.active {
            background: var(--primary-tint);
        }
        .nav-links > a.active::after { display: none; }

        .dropdown { margin: 10px 0 0; display: block; }
        .dropbtn { width: 100%; justify-content: center; padding: 14px; }
        .dropdown-content {
            position: static;
            margin-top: 8px;
            box-shadow: none;
            background: var(--bg-light);
        }
    }

    @media (max-width: 420px) {
        .brand-side h2 { font-size: 1rem; }
        .brand-side img { height: 40px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .dropdown-content.show { animation: none; }
        .dropbtn i.bi-chevron-down { transition: none; }
    }
</style>

<header class="site-header">
    <a class="brand-side" href="index.php" aria-label="The Michaelson School home">
        <img src="../images/logo.jpg" alt="The Michaelson School Logo">
        <h2>The Michaelson School</h2>
    </a>

    <button class="menu-toggle" id="menuToggle" type="button"
            aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"
            onclick="toggleMenu()">
        <i class="bi bi-list"></i>
    </button>

    <nav class="nav-links" id="navLinks" aria-label="Main">
        <?php foreach ($nav_items as $file => $label): ?>
            <a href="<?= $file ?>"<?= $current_page === $file ? ' class="active" aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>

        <div class="dropdown">
            <button class="dropbtn" id="loginBtn" type="button"
                    aria-haspopup="true" aria-expanded="false" onclick="toggleDropdown()">
                <i class="bi bi-person-lock"></i> Login <i class="bi bi-chevron-down" style="font-size: 0.7rem;"></i>
            </button>
            <div class="dropdown-content" id="loginDropdown">
                <a href="../login.php"><i class="bi bi-person-badge"></i> Student Login</a>
                <a href="../lecturer/staffLogin.php"><i class="bi bi-briefcase"></i> Lecturer Login</a>
                <a href="../admin/adminLoginForm.php"><i class="bi bi-shield-check"></i> Admin Login</a>
            </div>
        </div>
    </nav>
</header>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    const isOpen = dropdown.classList.toggle("show");
    btn.classList.toggle("active", isOpen);
    btn.setAttribute("aria-expanded", isOpen);
}

function toggleMenu() {
    const menu = document.getElementById("navLinks");
    const btn = document.getElementById("menuToggle");
    const isOpen = menu.classList.toggle("open");
    btn.setAttribute("aria-expanded", isOpen);
    btn.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
    btn.querySelector("i").className = isOpen ? "bi bi-x-lg" : "bi bi-list";
}

function closeLoginDropdown() {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    dropdown.classList.remove("show");
    btn.classList.remove("active");
    btn.setAttribute("aria-expanded", "false");
}

// Close the login menu when clicking elsewhere
document.addEventListener("click", function (event) {
    if (!event.target.closest(".dropdown")) closeLoginDropdown();
});

// Close with the Escape key
document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeLoginDropdown();
});
</script>