<?php
include("../includes/header.php");
// 1. Database Connection
$host = 'localhost';
$db   = 'school_admin_db'; // Matches your screenshot
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// 2. Fetch Data
$stmt = $pdo->query("SELECT * FROM standard");
$standards = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Fees | The Michaelson School</title>
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


        /* --- Container & Table --- */
        .container {
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            color: var(--primary);
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 10px;
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

        tr:hover { 
            background-color: #76b4f1; 
        }

        .currency-tag {
            color: #64748b;
            font-size: 0.8rem;
            margin-right: 4px;
        }

        .fees-amount {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.1rem;
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
    <div class="section-title">
        <h1>Fee Structure & Enrollment</h1>
        <p>Positioning your future for academic success</p>
        </div>
</section>

<div class="container">
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th><i class="bi bi-bookmark-star me-2"></i> Years</th>
                    <th><i class="bi bi-people me-2"></i> Intake</th>
                    <th><i class="bi bi-cash-stack me-2"></i> Annual Fees (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($standards)): ?>
                    <?php foreach ($standards as $row): ?>
                    <tr>
                        <td><span style="color: var(--primary)"><?php echo htmlspecialchars($row['year'] ?? 'N/A'); ?></span></td>
                        <td><?php echo htmlspecialchars($row['intake'] ?? '0'); ?> Seats</td>
                        <td>
                            <span class="currency-tag">Ksh</span>
                            <span class="fees-amount">
                                <?php 
                                    // Fix for the number_format float error
                                    echo number_format(floatval($row['fees'])); 
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 50px; color: #94a3b8;">
                            Database connection active, but no records found in 'Years' table.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
    <p>&copy; 2026 The Michaelson School, Kenya. All Rights Reserved.</p>
</footer>

</body>
</html>