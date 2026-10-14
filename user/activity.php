<?php
include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Activities | The Michaelson School</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
         :root {
             --primary: #000a66;
            --accent: #FFC107;
            --bg: #f8fafc94;
            --white: #ffffff;
            --text: #334155;
            --text-muted: #64748b; 
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            line-height: 1.6;
        }

        /* --- Page Layout --- */
        .main-wrapper {
            display: grid;
            grid-template-columns: 250px 1fr;
            max-width: 1200px;
            margin: 40px auto;
            gap: 30px;
            padding: 0 20px;
        }

        /* --- Sidebar --- */
        .sidebar {
            background: var(--white);
            padding: 20px;
            border-radius: 15px;
            height: fit-content;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .sidebar h3 { color: var(--primary); font-size: 1rem; margin-bottom: 20px; border-bottom: 2px solid var(--bg); padding-bottom: 10px; }

        .side-link {
            display: block;
            padding: 12px 15px;
            color: var(--text);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            font-weight: 500;
            transition: 0.3s;
        }

        .side-link:hover, .side-link.active {
            background: var(--primary);
            color: var(--white);
        }

        /* --- Activity Cards --- */
        .content-area h1 { color: var(--primary); margin-bottom: 30px; }

       .card {
            background: var(--white);
            width: auto;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-left: 6px solid var(--primary);
            transition: transform 0.3s;
            align-self: center;
        }

        .card:hover { transform: translateY(-5px); }

        .card-header h2 {
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-list li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .activity-list li::before {
            content: "▹";
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: bold;
        }

        /* --- Footer --- */
        footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: auto;
        }

.hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 20px;
            text-align: center;
        }

        @media (max-width: 850px) {
           /* .main-wrapper { grid-template-columns: 1fr; }*/
            .sidebar { display: none; }
            .values-list { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2rem; }
        }

        .hero h1 { 
            font-size: 3rem; 
            margin: 0; 
            letter-spacing: -1px; 
    }
    
    .hero p { 
        opacity: 0.9; 
        font-size: 1.1rem; 
    }
        .brand-side .logo-circle { 
        background: white; 
        width: 140px; 
        height: auto; 
        border-radius: 50%; 
        display: flex; 
        align-items: right; 
        justify-content: right; 
        margin-bottom: 25px; 
        padding: 15px; 
    }
        .brand-side .logo-circle img { 
            width: 75%; 
            height: 75%; 
        }
        .brand-side h2 { 
            font-weight: 800; 
            font-size: 1.4rem; 
            margin-bottom: 9px; 
            letter-spacing: -0.5px;
            color: var(--primary);
        }

        .container {
            max-width: 1000px;
            margin: -50px auto 60px;
            padding: 0 20px;
        }

    </style>
</head>
<body>

<section class="hero">
    <h1>Students Activities</h1>
    <p>Excersice to keep both body and mind sharp!</p>
</section>

<div class="container">
        <div class="card">
            <div class="card-header">
            <h2>🎖️ N.C.C. Activities</h2></div>
            <ul class="activity-list">
                <li>An associated N.C.C. officer (ANO) LT. Shri R.B. Gandhi oversees activities in the institute.</li>
                <li>Authorized intake of 50 cadets is available with the institute.</li>
                <li>Annual training camps (12 days) are organized twice a year (January & June/July) within the school.</li>
                <li>Our N.C.C. cadets serve the nation during natural disasters, offering essential relief work.</li>
            </ul>
        </div>

        <div class="card" style="border-top-color: #2a9d8f;">
            <div class="card-header">
            <h2>🏆 Students' Gymkhana</h2></div>
            <ul class="activity-list">
                <li>Elocution competitions and debates are organized every semester with prizes for winners.</li>
                <li>Technical exhibitions allow students to present models and charts.</li>
                <li>Active tree-plantation programs maintain our green campus cover.</li>
                <li>Extra-curricular highlights: Sports tournaments, Quiz competitions, Blood Donation Camps, and Thalassemia testing.</li>
            </ul>
        </div>

        <div class="card" >
            <div class="card-header">
            <h2>🤝 Career & Cultural Cell</h2></div>
            <ul class="activity-list">
                <li>Dedicated guidance for career pathing and professional development.</li>
                <li>Sports facilities for Cricket, Volleyball, Athletics (100m), and Chess.</li>
                <li>Annual cultural programs and entertainment events.</li>
                <li><strong>Student Chapter (IEI):</strong> Functioning since 2001, organizing industrial visits, technical seminars, and nature education tours.</li>
            </ul>
        </div>
    </main>
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
    &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>