<?php
include("../includes/header.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Michaelson School Staffs | The Michaelson School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
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

.container {
            max-width: 1500px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .table-responsive {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            overflow-x: auto;
        }

         .table-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

 table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--primary);
            color: white;
        }

        th {
            padding: 20px;
            text-align: left;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1.5px;
        }

        td {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
        }

        /* Fit columns to content */
        th, td { 
            width: 1%; 
            white-space: nowrap; 
        }
        th:first-child, td:first-child { 
            width: auto; 
        }
            .badge-exp { 
                background: #e0f2f1; 
                color: var(--primary); 
                padding: 4px 12px; 
                border-radius: 99px; 
                font-weight: 700; 
                font-size: 0.8rem; 
            }
        
        footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
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

   </style>
</head>
<body>
<section class="hero">
    
        <h1>Staffs Directory</h1>
        <p>Dedicated faculty members shaping the future.</p>
</section>
<div class="container">
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Qualification</th>
                    <th>Experience</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $conn = mysqli_connect("localhost", "root", "", "school_admin_db");
                if (!$conn) {
                    echo "<tr><td colspan='5' style='color:red; text-align:center;'>Database connection error</td></tr>";
                } else {
                    $result = mysqli_query($conn, "SELECT * FROM staff");
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_array($result)) {
                            echo "<tr>";
                            echo "<td style='font-weight:600;'>" . htmlspecialchars($row['full_name']) . "</td>";
                            echo "<td style='color:var(--primary);'>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['qualification']) . "</td>";
                            echo "<td><span class='badge-exp'>" . htmlspecialchars($row['experience']) . "</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No records found.</td></tr>";
                    }
                    mysqli_close($conn);
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<footer>
    &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

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

</body>
</html>