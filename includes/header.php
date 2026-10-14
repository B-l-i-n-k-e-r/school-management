<!DOCTYPE html>
<html lang="en">
    <style>
        :root {
            --primary: #000a66;
            --accent: #facc15;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --primary-dark: #000a66; 
            --text-muted: #64748b; 
            --nav-text: #475569;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            color: #1e293b;
            background-color: var(--bg-light);
        }

        /* --- Navigation --- */
        header {
            background: var(--white);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-sizing: border-box;
        }

        .brand-side {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-side img { 
            height: 50px; 
            width: auto; 
            display: block;
        }

        .brand-side h2 { 
            font-weight: 800; 
            font-size: 1.3rem; 
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.5px;
            color: #1e293b;
        }

        .nav-links { 
            display: flex; 
            align-items: center;
            justify-content: flex-end;
            gap: 20px;
            flex-grow: 1;
        }

        .nav-links a { 
            text-decoration: none; 
            color: var(--nav-text); 
            font-weight: 700; 
            font-size: 0.95rem; 
            transition: color 0.3s; 
            white-space: nowrap;
        }

        .nav-links a:hover { color: #2d99f1; }
        .nav-links a.active { color: var(--primary); }

        /* --- Clickable Dropdown Logic --- */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background-color: var(--primary);
            color: white;
            padding: 10px 22px;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .dropbtn:hover { background-color: #2d99f1; }
        .dropbtn.active i.bi-chevron-down { transform: rotate(180deg); }
        .dropbtn i.bi-chevron-down { transition: transform 0.3s; }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            min-width: 180px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid #f1f5f9;
        }

        .dropdown-content.show { display: block; animation: fadeIn 0.2s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-content a {
            color: var(--white);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .dropdown-content a:last-child { border-bottom: none; }
        .dropdown-content a:hover { background-color: #f8fafc; color: #2d99f1; }
/* --- Media Queries for Responsive Window Scaling --- */
        @media (max-width: 1100px) {
            header { padding: 1rem 3%; }
            .nav-links { gap: 12px; }
            .nav-links a { font-size: 0.88rem; }
        }

        @media (max-width: 930px) {
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 1rem 20px;
            }
            .nav-links {
                justify-content: center;
                width: 100%;
                flex-wrap: wrap;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
            .hero-section h1 { font-size: 2rem; }
        }
    </style>
    <body>

<header>
    <div class="brand-side">
        <img src="../images/logo.jpg" alt="The Michaelson School Logo">
        <h2>The Michaelson School</h2>
    </div>
    
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="about.php">About</a>
        <a href="year.php">Years</a>
        <a href="staff.php">Staff</a>
        <a href="admission.php">Admission</a>
        <a href="activity.php">Activities</a>
        <a href="feedback.php">Feedback</a>
        <a href="contact.php">Contact</a>

        <div class="dropdown">
            <button class="dropbtn" id="loginBtn" onclick="toggleDropdown()">
                <i class="bi bi-person-lock"></i> Login <i class="bi bi-chevron-down" style="font-size: 0.7rem;"></i>
            </button>
            <div class="dropdown-content" id="loginDropdown">
                <a href="../login.php"><i class="bi bi-person-badge me-2"></i> Student Login</a>
                <a href="../lecturer/staffLogin.php"><i class="bi bi-briefcase me-2"></i> Lecturer Login</a>
                <a href="../admin/adminLoginForm.php"><i class="bi bi-shield-check me-2"></i> Admin Login</a>
            </div>
        </div>
    </div>
</header>
<script>
function toggleDropdown() {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    dropdown.classList.toggle("show");
    btn.classList.toggle("active");
}

window.onclick = function(event) {
    if (!event.target.closest('.dropdown')) {
        const dropdowns = document.getElementsByClassName("dropdown-content");
        const buttons = document.getElementsByClassName("dropbtn");
        
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
        for (let j = 0; j < buttons.length; j++) {
            buttons[j].classList.remove('active');
        }
    }
}
</script>
</body>
</html>