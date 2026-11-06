<?php
// 1. Database Connection
include("../includes/header.php");

$host = 'localhost';
$db   = 'school_admin_db'; 
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
$stmt = $pdo->query("SELECT * FROM standard ORDER BY sort_order ASC");
$standards = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Structure | The Michaelson School</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --bg: #f8fafc;
            --white: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* --- Modern Hero Section --- */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 100px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Decorative background circles */
        .hero::before, .hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
        }
        .hero::before {
            width: 400px; height: 400px;
            top: -100px; left: -100px;
        }
        .hero::after {
            width: 300px; height: 300px;
            bottom: -50px; right: -50px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            letter-spacing: -1.5px;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 1.2rem;
            color: #94a3b8;
            font-weight: 400;
        }

        /* --- Container & Table --- */
        .container {
            max-width: 1100px;
            margin: -50px auto 80px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .table-card {
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-header {
            padding: 24px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.25rem;
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f8fafc;
        }

        th {
            padding: 16px 30px;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            font-weight: 500;
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr {
            transition: background-color 0.2s ease;
        }

        tr:hover {
            background-color: #f1f5f9;
        }

        /* --- Badges --- */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .badge-primary {
            background-color: #eff6ff;
            color: var(--accent);
        }

        .badge-secondary {
            background-color: #f1f5f9;
            color: var(--text-muted);
        }

        /* --- Footer --- */
        footer {
            background: var(--primary);
            color: #94a3b8;
            text-align: center;
            padding: 40px 20px;
            font-size: 0.9rem;
        }

        /* --- Mobile Responsiveness --- */
        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr { 
                margin-bottom: 15px; 
                border: 1px solid var(--border); 
                border-radius: 12px;
                padding: 10px;
            }
            td { 
                border-bottom: none; 
                padding: 12px 20px; 
                display: flex; 
                justify-content: space-between; 
                align-items: center;
            }
            td::before { 
                content: attr(data-label); 
                font-weight: 700; 
                color: var(--text-muted);
                font-size: 0.8rem;
                text-transform: uppercase;
            }
            .table-card {
                background: transparent;
                box-shadow: none;
                border: none;
            }
            .table-header { display: none; }
        }
    </style>
</head>
<body>
 
<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Academic Structure</h1>
        <p>Explore our comprehensive curriculum from Primary to Secondary levels.</p>
    </div>
</section>

<!-- Main Content -->
<div class="container">
    <div class="table-card">
        <div class="table-header">
            <h3><i class="bi bi-journal-bookmark-fill me-2" style="color: var(--accent);"></i> Available Classes</h3>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Form / Grade</th>
                    <th>Level</th>
                    <th>Curriculum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($standards)): ?>
                    <?php foreach ($standards as $row): ?>
                    <tr>
                        <!-- Using data-label for mobile responsive view -->
                        <td data-label="Form/Grade">
                            <strong style="color: var(--primary); font-size: 1.05rem;">
                                <?php echo htmlspecialchars($row['form'] ?? 'N/A'); ?>
                            </strong>
                        </td>
                        <td data-label="Level">
                            <span class="badge <?php echo ($row['level'] == 'Primary') ? 'badge-primary' : 'badge-secondary'; ?>">
                                <?php echo htmlspecialchars($row['level'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td data-label="Curriculum">
                            <span style="color: var(--text-muted); font-weight: 600;">
                                <?php echo htmlspecialchars($row['curriculum'] ?? 'N/A'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 50px; color: var(--text-muted);">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            No records found in the database.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Existing dropdown script from your code
function toggleDropdown() {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    if (dropdown && btn) {
        dropdown.classList.toggle("show");
        btn.classList.toggle("active");
    }
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
    <p>&copy; <?php echo date('Y'); ?> The Michaelson School, Kenya. All Rights Reserved.</p>
</footer>

</body>
</html>