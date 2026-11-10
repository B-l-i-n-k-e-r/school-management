<?php
// Total staff comes from the staff table managed in the admin portal
$total_staff = 19; // fallback if the query fails
try {
    $staff_conn = mysqli_connect("localhost", "root", "", "school_admin_db");
    if ($staff_conn) {
        $result = mysqli_query($staff_conn, "SELECT COUNT(*) AS total FROM staff");
        if ($result) {
            $total_staff = (int) mysqli_fetch_assoc($result)['total'];
        }
        mysqli_close($staff_conn);
    }
} catch (Exception $e) {
    // keep the fallback number
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Overview | The Michaelson School</title>
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-deep: #b88a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 28px;
            --radius-md: 18px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            color: var(--ink);
            background-color: var(--bg-light);
            -webkit-font-smoothing: antialiased;
        }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 3px;
        }

        /* ---------- Hero ---------- */
        .ms-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .ms-hero::after {
            /* soft gold glow in the corner */
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
        .ms-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: ms-rise 0.8s ease both;
        }
        .ms-hero-tag {
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
        .ms-hero-tag i { color: var(--accent); }
        .ms-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .ms-hero p {
            max-width: 560px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .ms-hero-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Main card ---------- */
        .ms-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .ms-card {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 30px 60px -20px rgba(0, 10, 102, 0.22);
            overflow: hidden;
        }
        .ms-story {
            padding: 56px 52px;
        }
        .ms-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: var(--primary-tint);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            margin-bottom: 24px;
        }
        .ms-story h2 {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
            margin: 0 0 16px;
        }
        .ms-story p {
            margin: 0;
            line-height: 1.8;
            color: var(--text-muted);
            font-size: 1.02rem;
            max-width: 52ch;
        }

        /* ---------- Stats panel ---------- */
        .ms-stats {
            background: var(--primary);
            color: var(--white);
            padding: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-content: center;
        }
        .ms-stat {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-md);
            padding: 24px 20px;
        }
        .ms-stat i {
            color: var(--accent);
            font-size: 1.3rem;
        }
        .ms-stat strong {
            display: block;
            font-size: 2.3rem;
            font-weight: 800;
            line-height: 1;
            margin: 14px 0 8px;
            letter-spacing: -0.02em;
        }
        .ms-stat span {
            font-size: 0.9rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.75);
        }
        /* the one highlighted tile */
        .ms-stat.is-featured {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--primary);
        }
        .ms-stat.is-featured i,
        .ms-stat.is-featured span { color: var(--primary); }

        /* ---------- Footer ---------- */
        .ms-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes ms-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .ms-hero-inner { animation: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 860px) {
            .ms-hero { padding: 80px 20px 150px; }
            .ms-card { grid-template-columns: 1fr; }
            .ms-story { padding: 40px 28px; }
            .ms-stats { padding: 28px 20px; }
        }
        @media (max-width: 420px) {
            .ms-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="ms-hero">
    <div class="ms-hero-inner">
        <div class="ms-hero-tag"><i class="bi bi-mortarboard-fill"></i> Our Faculty</div>
        <h1>Our Academic Excellence</h1>
        <p>
            The Michaelson School is home to a community of experienced and visionary educators.
            Our faculty members hold advanced Master's and Doctorate degrees, bringing
            unparalleled expertise to the classroom.
        </p>
        <div class="ms-hero-rule"></div>
    </div>
</section>

<main class="ms-wrap">
    <div class="ms-card">
        <div class="ms-story">
            <div class="ms-icon"><i class="bi bi-journal-richtext"></i></div>
            <h2>Leading through Research</h2>
            <p>
                Our lecturers don't just instruct; they innovate. Many members of our <?= $total_staff ?>-person staff
                regularly present research papers at the KNEC Board level. Several have authored
                popular textbooks used by students across the region.
            </p>
        </div>

        <div class="ms-stats">
            <div class="ms-stat is-featured">
                <i class="bi bi-people-fill"></i>
                <strong><?= $total_staff ?></strong>
                <span>Total staff</span>
            </div>
            <div class="ms-stat">
                <i class="bi bi-patch-check-fill"></i>
                <strong>100%</strong>
                <span>Post-graduated</span>
            </div>
            <div class="ms-stat">
                <i class="bi bi-book-fill"></i>
                <strong>10+</strong>
                <span>Published authors</span>
            </div>
            <div class="ms-stat">
                <i class="bi bi-award-fill"></i>
                <strong>5+</strong>
                <span>PhD faculty</span>
            </div>
        </div>
    </div>
</main>

<footer class="ms-footer">
    © 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>