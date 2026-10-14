<?php
include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Overview| The Michaelson School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
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

        
        /* --- Hero Section --- */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        .hero-section h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            margin-bottom: 20px; 
        }
        .hero-section p { 
            max-width: 700px; 
            margin: 0 auto; 
            line-height: 1.8; 
            opacity: 0.9; 
            font-size: 1.1rem; }

        /* --- Admin Entry Bar --- */
        .admin-bar {
            background: var(--accent);
            padding: 15px;
            text-align: center;
            font-weight: 700;
        }

        .admin-bar a { 
            color: #000; 
            text-decoration: none; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            display: inline-block;
            width: max-content;
            max-width: 100%;
        }
        .admin-bar a:hover { 
            text-decoration: underline; 
        }

        /* --- Stats/Content Area --- */
        .container { 
            max-width: 1000px; 
            margin: -40px auto 60px; 
            padding: 0 20px; 
            box-sizing: border-box;}
        
        .info-card {
            background: var(--white);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .stat-box {
            background: var(--bg-light);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }

        .stat-box h2 { 
            color: var(--primary); 
            margin: 0; font-size: 2rem; 
        }
        .stat-box p { 
            margin: 5px 0 0; 
            color: #64748b; 
            font-size: 0.8rem; 
            font-weight: 700; 
            text-transform: uppercase; }

        /* --- Content Layout Tables --- */
        .main-container { 
            max-width: 1200px; 
            margin: 60px auto; 
            padding: 0 20px; 
        }
        .page-header { 
            margin-bottom: 30px; 
            border-left: 5px solid var(--primary); 
            padding-left: 20px; 
        }
        .page-header h1 { 
            font-size: 1.8rem; 
            font-weight: 800; 
            color: var(--primary); 
            margin: 0; }

        .table-responsive {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            overflow-x: auto;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            white-space: nowrap; 
        }
        th { 
            background: #f1f5f9; 
            color: var(--primary); 
            text-align: left; 
            padding: 15px; 
            font-weight: 700; 
            font-size: 0.9rem; 
            text-transform: uppercase; 
        }
        td { 
            padding: 15px; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 0.95rem; 
        }
        .badge-exp { 
            background: #e0f2f1; 
            color: var(--primary); 
            padding: 4px 12px; 
            border-radius: 99px; 
            font-weight: 700; 
            font-size: 0.8rem; }
        
        footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }

    </style>
</head>
<body>

<section class="hero-section">
    <h1>Our Academic Excellence</h1>
    <p>
       The Michaelson School is home to a community of experienced and visionary educators. 
        Our faculty members hold advanced Master's and Doctorate degrees, bringing 
        unparalleled expertise to the classroom.
    </p>
</section>

<div class="container">
    <div class="info-card">
        <div>
            <h3 style="color: var(--primary); font-weight: 800; font-size: 1.5rem;">Leading through Research</h3>
            <p style="line-height: 1.7; color: #475569;">
                Our lecturers don't just instruct; they innovate. Many members of our 19-person staff 
                regularly present research papers at the KNEC Board level. Several have authored 
                popular textbooks used by students across the region.
            </p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-box"><h2>19</h2><p>Total Staff</p></div>
            <div class="stat-box"><h2>100%</h2><p>Post-Graduated</p></div>
            <div class="stat-box"><h2>10+</h2><p>Published Authors</p></div>
            <div class="stat-box"><h2>5+</h2><p>PhD Faculty</p></div>
        </div>
    </div>
</div>

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

<footer>
    © 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>