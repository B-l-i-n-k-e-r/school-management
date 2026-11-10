<?php
// Fetch staff from the admin portal database
$staff_list = [];
$db_error = false;

try {
    $conn = mysqli_connect("localhost", "root", "", "school_admin_db");
    if ($conn) {
        $result = mysqli_query($conn, "SELECT * FROM staff");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $staff_list[] = $row;
            }
        } else {
            $db_error = true;
        }
        mysqli_close($conn);
    } else {
        $db_error = true;
    }
} catch (Exception $e) {
    $db_error = true;
}

// "John Kamau" -> "JK"
function staff_initials($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory | The Michaelson School</title>
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

        a:focus-visible, input:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }

        /* ---------- Hero ---------- */
        .st-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .st-hero::after {
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
        .st-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: st-rise 0.8s ease both;
        }
        .st-tag {
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
        .st-tag i { color: var(--accent); }
        .st-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .st-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .st-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Directory card ---------- */
        .st-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .st-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 30px 60px -20px rgba(0, 10, 102, 0.22);
            overflow: hidden;
        }
        .st-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 26px 36px;
            border-bottom: 1px solid var(--line);
        }
        .st-card-head h2 {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 0;
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .st-card-head h2 i {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.2rem;
        }
        .st-count {
            padding: 4px 12px;
            border-radius: 99px;
            background: var(--accent);
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* Search */
        .st-search {
            position: relative;
            width: 280px;
            max-width: 100%;
        }
        .st-search i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .st-search input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 16px 12px 42px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background: var(--bg-light);
            font-family: inherit;
            font-size: 0.92rem;
            color: var(--ink);
        }
        .st-search input:focus {
            border-color: var(--primary);
            background: var(--white);
        }

        /* Table */
        .st-scroll { overflow-x: auto; }
        .st-table {
            width: 100%;
            border-collapse: collapse;
        }
        .st-table th {
            padding: 16px 20px;
            text-align: left;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--bg-light);
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }
        .st-table th:first-child, .st-table td:first-child { padding-left: 36px; }
        .st-table th:last-child,  .st-table td:last-child  { padding-right: 36px; }
        .st-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
            font-size: 0.93rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .st-table tbody tr:last-child td { border-bottom: none; }
        .st-table tbody tr { transition: background-color 0.2s; }
        .st-table tbody tr:hover { background: var(--primary-tint); }
        .st-table td a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .st-table td a:hover { text-decoration: underline; }

        .st-person {
            display: flex;
            align-items: center;
            gap: 14px;
            white-space: nowrap;
        }
        .st-avatar {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--primary);
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .st-name {
            color: var(--ink);
            font-size: 1rem;
            font-weight: 700;
        }

        .badge-exp {
            display: inline-flex;
            padding: 6px 14px;
            border-radius: 99px;
            background: var(--accent-tint);
            color: var(--accent-deep);
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .st-empty {
            text-align: center;
            padding: 60px 20px !important;
            color: var(--text-muted);
        }
        .st-empty i {
            display: block;
            font-size: 2.4rem;
            margin-bottom: 12px;
            color: var(--primary);
            opacity: 0.4;
        }
        #noMatch { display: none; }
        #noMatch.is-visible { display: table-row; }

        /* ---------- Footer ---------- */
        .st-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes st-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .st-hero-inner { animation: none; }
        }

        /* ---------- Mobile: each person becomes a card ---------- */
        @media (max-width: 860px) {
            .st-hero { padding: 80px 20px 150px; }
            .st-card { background: transparent; box-shadow: none; overflow: visible; }
            .st-card-head {
                padding: 22px 20px;
                background: var(--white);
                border-radius: var(--radius-lg);
                border-bottom: none;
                margin-bottom: 14px;
                box-shadow: 0 20px 40px -20px rgba(0, 10, 102, 0.22);
            }
            .st-search { width: 100%; }

            .st-table thead { display: none; }
            .st-table, .st-table tbody, .st-table tr, .st-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            .st-table tr {
                margin-bottom: 12px;
                padding: 10px 6px;
                background: var(--white);
                border: 1px solid var(--line);
                border-radius: 20px;
            }
            .st-table td,
            .st-table td:first-child,
            .st-table td:last-child {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                border-bottom: none;
                text-align: right;
            }
            .st-table td:first-child { padding-bottom: 14px; }
            .st-table td[data-label]::before {
                content: attr(data-label);
                font-size: 0.8rem;
                font-weight: 700;
                color: var(--text-muted);
                text-align: left;
            }
            .st-table td.st-empty { display: block; text-align: center; }
            #noMatch.is-visible { display: block; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="st-hero">
    <div class="st-hero-inner">
        <div class="st-tag"><i class="bi bi-people-fill"></i> Our team</div>
        <h1>Staff Directory</h1>
        <p>Dedicated faculty members shaping the future.</p>
        <div class="st-rule"></div>
    </div>
</section>

<main class="st-wrap">
    <div class="st-card">
        <div class="st-card-head">
            <h2>
                <i class="bi bi-person-badge-fill"></i>
                Faculty &amp; Staff
                <?php if (!empty($staff_list)): ?>
                    <span class="st-count"><?php echo count($staff_list); ?></span>
                <?php endif; ?>
            </h2>

            <?php if (!empty($staff_list)): ?>
            <label class="st-search">
                <i class="bi bi-search"></i>
                <input type="search" id="staffSearch" placeholder="Search by name or qualification" aria-label="Search staff">
            </label>
            <?php endif; ?>
        </div>

        <div class="st-scroll">
            <table class="st-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Qualification</th>
                        <th>Experience</th>
                    </tr>
                </thead>
                <tbody id="staffBody">
                    <?php if ($db_error): ?>
                        <tr><td colspan="5" class="st-empty">
                            <i class="bi bi-exclamation-triangle"></i>
                            We can't load the staff list right now. Please try again later.
                        </td></tr>
                    <?php elseif (empty($staff_list)): ?>
                        <tr><td colspan="5" class="st-empty">
                            <i class="bi bi-inbox"></i>
                            No staff members have been added yet.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($staff_list as $row): ?>
                        <tr>
                            <td data-label="Name">
                                <div class="st-person">
                                    <span class="st-avatar" aria-hidden="true"><?php echo htmlspecialchars(staff_initials($row['full_name'] ?? '')); ?></span>
                                    <span class="st-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span>
                                </div>
                            </td>
                            <td data-label="Email">
                                <a href="mailto:<?php echo htmlspecialchars($row['email'] ?? ''); ?>"><?php echo htmlspecialchars($row['email'] ?? ''); ?></a>
                            </td>
                            <td data-label="Phone">
                                <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) ($row['phone'] ?? ''))); ?>"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></a>
                            </td>
                            <td data-label="Qualification"><?php echo htmlspecialchars($row['qualification'] ?? ''); ?></td>
                            <td data-label="Experience">
                                <span class="badge-exp"><?php echo htmlspecialchars($row['experience'] ?? ''); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="noMatch"><td colspan="5" class="st-empty">
                            <i class="bi bi-search"></i>
                            No staff match your search.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<footer class="st-footer">
    &copy; <?php echo date('Y'); ?> The Michaelson School, Kenya. All Rights Reserved.
</footer>

<script>
// Live search: filters the staff rows as you type
(function () {
    const input = document.getElementById("staffSearch");
    if (!input) return;
    const rows = document.querySelectorAll("#staffBody tr:not(#noMatch)");
    const noMatch = document.getElementById("noMatch");

    input.addEventListener("input", function () {
        const term = input.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const show = row.textContent.toLowerCase().includes(term);
            row.style.display = show ? "" : "none";
            if (show) visible++;
        });
        noMatch.classList.toggle("is-visible", visible === 0);
    });
})();
</script>

</body>
</html>