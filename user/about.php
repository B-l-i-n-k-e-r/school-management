<?php
include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | The Michaelson School</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
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

        /* --- Hero Section --- */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 20px;
            text-align: center;
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

        /* --- Content Layout --- */
        .container {
            max-width: 1000px;
            margin: -50px auto 60px;
            padding: 0 20px;
        }
 
        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-left: 6px solid var(--primary);
            transition: transform 0.3s;
        }

        .card:hover { 
            transform: translateY(-5px); 
        }

        .card-header h2 {
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
 
        .icon-circle {
            background: linear-gradient(rgba(12, 107, 231, 0.75), rgba(0, 90, 224, 0.93)), url('../images/school_bg.jpg');
            color: var(--accent);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .paragraph {
            font-size: 1.05rem;
            color: #475569;
            text-align: justify;
        }

        .motto {
            font-weight: 700;
            color: var(--primary);
            display: block;
            margin-top: 15px;
            font-style: italic;
        }

        /* --- List Styles --- */
        .values-list {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .values-list li {
            background: #F1F5F9;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            border-left: 3px solid var(--accent);
        }

        footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }


        @media (max-width: 768px) {
            .hero-section h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>

<section class="hero">
    <h1>Our Foundation</h1>
    <p>Excellence in education, integrity in character.</p>
</section>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="icon-circle">🎯</div>
            <h2>Our Mission</h2>
        </div>
        <p class="paragraph">
            The mission of The Michaelson School is to deliver students and services to meet the changing needs of society and thereby contribute to overall school development of the State in particular and Nation in general. We strive to improve the quality of life in our communities through dedicated education.
        </p>
        <span class="motto">"Create State's Best School"</span>
    </div>

    <div class="card" style="border-left-color: var(--accent);">
        <div class="card-header">
            <div class="icon-circle" style="background: var(--accent); color: var(--primary);">👁️</div>
            <h2>Our Vision</h2>
        </div>
        <p class="paragraph">
           The Michaelson School  will be known for excellence in the study of education as the first choice for all students and society. Where quality is a way of life, we aim to be a pioneer institute imparting excellent training. We focus not just on curriculum, but on transferring technology and moral values to the community.
        </p>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="icon-circle">💎</div>
            <h2>Core Values & Beliefs</h2>
        </div>
        <p class="paragraph">
            Our core values are quality, equity, morality, environmental sustainability, and a strong commitment to the cause of education.
        </p>
        <ul class="values-list">
            <li>Equitable & Harmonious Growth</li>
            <li>Enlightened Vision</li>
            <li>Strong Civic Sense</li>
            <li>High Moral Standards</li>
            <li>Global & Local Awareness</li>
            <li>Commitment to Quality</li>
        </ul>
    </div>
</div>
<script>
function toggleDropdown() {
    // Toggles the dropdown menu and the active state of the button
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    dropdown.classList.toggle("show");
    btn.classList.toggle("active");
}

// Close the dropdown if the user clicks anywhere outside of it
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
    <p>&copy; 2026 The Michaelson School, Kenya. All Rights Reserved.</p>
</footer>

</body>
</html>