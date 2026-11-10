<?php
// 1. Database Connection
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
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-tint: #fff6cc;
            --accent-deep: #8a6a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 28px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            color: var(--ink);
            background-color: var(--bg-light);
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Hero ---------- */
        .cl-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .cl-hero::after {
            content: "";
            position: absolute;
            right: -120px;
            bottom: -160px;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.28), transparent 70%);
            pointer-events: none;
        }
        .cl-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: cl-rise 0.8s ease both;
        }
        .cl-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(6px);
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 26px;
        }
        .cl-tag i { color: var(--accent); }
        .cl-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .cl-hero p {
            max-width: 540px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .cl-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Table card ---------- */
        .cl-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .cl-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 30px 60px -20px rgba(0, 10, 102, 0.22);
            overflow: hidden;
        }
        .cl-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 28px 36px;
            border-bottom: 1px solid var(--line);
        }
        .cl-card-head h2 {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0;
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .cl-card-head h2 i {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.2rem;
        }
        .cl-count {
            padding: 8px 16px;
            border-radius: 99px;
            background: var(--accent);
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .cl-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cl-table th {
            padding: 16px 36px;
            text-align: left;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--bg-light);
            border-bottom: 1px solid var(--line);
        }
        .cl-table td {
            padding: 20px 36px;
            border-bottom: 1px solid var(--line);
            font-size: 0.97rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .cl-table tbody tr:last-child td { border-bottom: none; }
        .cl-table tbody tr { transition: background-color 0.2s; }
        .cl-table tbody tr:hover { background: var(--primary-tint); }

        .cl-form {
            color: var(--primary);
            font-size: 1.05rem;
            font-weight: 800;
        }

        /* ---------- Level badges ---------- */
        .cl-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .cl-badge.is-primary {
            background: var(--primary-tint);
            color: var(--primary);
        }
        .cl-badge.is-secondary {
            background: var(--accent-tint);
            color: var(--accent-deep);
        }

        /* ---------- Empty state ---------- */
        .cl-empty {
            text-align: center;
            padding: 60px 20px !important;
            color: var(--text-muted);
        }
        .cl-empty i {
            display: block;
            font-size: 2.4rem;
            margin-bottom: 12px;
            color: var(--primary);
            opacity: 0.4;
        }

        /* ---------- Footer ---------- */
        .cl-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes cl-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .cl-hero-inner { animation: none; }
        }

        /* ---------- Mobile: each row becomes a small card ---------- */
        @media (max-width: 768px) {
            .cl-hero { padding: 80px 20px 150px; }
            .cl-card-head { padding: 22px 20px; }
            .cl-card-head h2 { font-size: 1.1rem; }

            .cl-card { background: transparent; box-shadow: none; }
            .cl-card-head {
                background: var(--white);
                border-radius: var(--radius-lg);
                border-bottom: none;
                margin-bottom: 14px;
                box-shadow: 0 20px 40px -20px rgba(0, 10, 102, 0.22);
            }

            .cl-table thead { display: none; }
            .cl-table, .cl-table tbody, .cl-table tr, .cl-table td { display: block; width: 100%; box-sizing: border-box; }
            .cl-table tr {
                margin-bottom: 12px;
                padding: 8px 6px;
                background: var(--white);
                border: 1px solid var(--line);
                border-radius: 18px;
            }
            .cl-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                border-bottom: none;
            }
            .cl-table td::before {
                content: attr(data-label);
                font-size: 0.8rem;
                font-weight: 700;
                color: var(--text-muted);
            }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="cl-hero">
    <div class="cl-hero-inner">
        <div class="cl-tag"><i class="bi bi-journal-bookmark-fill"></i> Classes</div>
        <h1>Academic Structure</h1>
        <p>Explore our comprehensive curriculum from Primary to Secondary levels.</p>
        <div class="cl-rule"></div>
    </div>
</section>

<main class="cl-wrap">
    <div class="cl-card">
        <div class="cl-card-head">
            <h2><i class="bi bi-mortarboard-fill"></i> Available Classes</h2>
            <?php if (!empty($standards)): ?>
                <span class="cl-count"><?php echo count($standards); ?> classes</span>
            <?php endif; ?>
        </div>

        <table class="cl-table">
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
                        <td data-label="Form / Grade">
                            <span class="cl-form"><?php echo htmlspecialchars($row['form'] ?? 'N/A'); ?></span>
                        </td>
                        <td data-label="Level">
                            <span class="cl-badge <?php echo (($row['level'] ?? '') === 'Primary') ? 'is-primary' : 'is-secondary'; ?>">
                                <?php echo htmlspecialchars($row['level'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td data-label="Curriculum">
                            <?php echo htmlspecialchars($row['curriculum'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="cl-empty">
                            <i class="bi bi-inbox"></i>
                            No classes have been added yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<footer class="cl-footer">
    &copy; <?php echo date('Y'); ?> The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>