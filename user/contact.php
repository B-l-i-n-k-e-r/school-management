<?php
include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | The Michaelson School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
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
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
        }

        .contact-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .contact-header h1 {
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 10px;
        }

        /* --- Grid & Cards --- */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
        }

        .info-card {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        .map-card {
            background: #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 450px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }

        .icon-box {
            background: #f0fdfa;
            color: var(--primary);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 20px;
        }

        .details h4 {
            margin: 0 0 5px 0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
        }

        .details p, .details a {
            margin: 0;
            font-weight: 500;
            line-height: 1.6;
            color: var(--text-dark);
            text-decoration: none;
        }

        /* --- Footer --- */
        footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }

        @media (max-width: 850px) {
            header { padding: 1rem 5%; }
            .contact-grid { grid-template-columns: 1fr; }
            nav { display: none; } /* Simplified for mobile */
        }

    </style>
</head>
<body>

<div class="container">
    <div class="contact-header">
        <h1>Get in Touch</h1>
        <p>Have questions? We'd love to hear from you.</p>
    </div>

    <div class="contact-grid">
        <div class="info-card">
            <div class="contact-item">
                <div class="icon-box"><i class="bi bi-geo-alt"></i></div>
                <div class="details">
                    <h4>Our Location</h4>
                    <p>The Principal, The Michaelson School,<br>
                    NTR Society, Upper Road,<br>
                    NTR - 40417-16, Kenya</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="icon-box"><i class="bi bi-telephone"></i></div>
                <div class="details">
                    <h4>Phone & Fax</h4>
                    <p>+254705681215</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="icon-box"><i class="bi bi-envelope"></i></div>
                <div class="details">
                    <h4>Direct Email</h4>
                    <p><a href="mailto:vinniemariba2004@gmail.com">viniemariba2004@gmail.com</a></p>
                </div>
            </div>

            <div class="contact-item">
                <div class="icon-box"><i class="bi bi-clock"></i></div>
                <div class="details">
                    <h4>Office Hours</h4>
                    <p>Mon - Sat: <strong style ="color: green; "></style>9:00 AM </strong> To <strong style="color: red;" >6:00 PM</strong></p>
                </div>
            </div>
        </div>

        <div class="map-card">
            <i class="bi bi-map-fill" style="font-size: 4rem; color: #94a3b8; margin-bottom: 15px;"></i>
            <p style="font-weight: 600; color: #64748b;">Find us on Google Maps</p>
            <a href="https://maps.google.com" target="_blank" 
               style="background: var(--primary); color: white; padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 10px;">
               View Satellite Map
            </a>
        </div>
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
        &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
    </div>
</footer>

</body>
</html>