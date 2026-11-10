<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Activities | The Michaelson School</title>
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
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
        .act-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .act-hero::after {
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
        .act-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: act-rise 0.8s ease both;
        }
        .act-tag {
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
        .act-tag i { color: var(--accent); }
        .act-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .act-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .act-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Layout ---------- */
        .act-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .act-panel {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 44px 40px;
            box-shadow: 0 30px 60px -24px rgba(0, 10, 102, 0.22);
        }
        .act-panel-icon {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 22px;
        }
        .act-panel h2 {
            margin: 0 0 22px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }

        .act-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .act-list li {
            position: relative;
            padding-left: 26px;
            margin-bottom: 14px;
            line-height: 1.7;
            color: var(--text-muted);
            font-size: 0.99rem;
        }
        .act-list li:last-child { margin-bottom: 0; }
        .act-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.62em;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 3px var(--primary-tint);
        }
        .act-list strong { color: var(--ink); }

        /* N.C.C.: the one bold panel, spans the full width */
        .act-featured {
            grid-column: 1 / -1;
            background: var(--primary);
            color: var(--white);
        }
        .act-featured .act-panel-icon { background: var(--accent); color: var(--primary); }
        .act-featured h2 { color: var(--white); }
        .act-featured .act-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 40px;
        }
        .act-featured .act-list li { margin-bottom: 0; color: rgba(255, 255, 255, 0.84); }
        .act-featured .act-list li::before { box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.14); }

        /* ---------- Footer ---------- */
        .act-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes act-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .act-hero-inner { animation: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 860px) {
            .act-hero { padding: 80px 20px 150px; }
            .act-wrap { grid-template-columns: 1fr; }
            .act-panel { padding: 34px 26px; }
            .act-featured .act-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="act-hero">
    <div class="act-hero-inner">
        <div class="act-tag"><i class="bi bi-trophy-fill"></i> Beyond the classroom</div>
        <h1>Student Activities</h1>
        <p>Exercise to keep both body and mind sharp!</p>
        <div class="act-rule"></div>
    </div>
</section>

<main class="act-wrap">
    <section class="act-panel act-featured">
        <div class="act-panel-icon"><i class="bi bi-shield-fill-check"></i></div>
        <h2>N.C.C. Activities</h2>
        <ul class="act-list">
            <li>An associated N.C.C. officer (ANO) LT. Shri R.B. Gandhi oversees activities in the institute.</li>
            <li>Authorized intake of 50 cadets is available with the institute.</li>
            <li>Annual training camps (12 days) are organized twice a year (January &amp; June/July) within the school.</li>
            <li>Our N.C.C. cadets serve the nation during natural disasters, offering essential relief work.</li>
        </ul>
    </section>

    <section class="act-panel">
        <div class="act-panel-icon"><i class="bi bi-trophy-fill"></i></div>
        <h2>Students' Gymkhana</h2>
        <ul class="act-list">
            <li>Elocution competitions and debates are organized every semester with prizes for winners.</li>
            <li>Technical exhibitions allow students to present models and charts.</li>
            <li>Active tree-plantation programs maintain our green campus cover.</li>
            <li>Extra-curricular highlights: Sports tournaments, Quiz competitions, Blood Donation Camps, and Thalassemia testing.</li>
        </ul>
    </section>

    <section class="act-panel">
        <div class="act-panel-icon"><i class="bi bi-compass-fill"></i></div>
        <h2>Career &amp; Cultural Cell</h2>
        <ul class="act-list">
            <li>Dedicated guidance for career pathing and professional development.</li>
            <li>Sports facilities for Cricket, Volleyball, Athletics (100m), and Chess.</li>
            <li>Annual cultural programs and entertainment events.</li>
            <li><strong>Student Chapter (IEI):</strong> Functioning since 2001, organizing industrial visits, technical seminars, and nature education tours.</li>
        </ul>
    </section>
</main>

<footer class="act-footer">
    &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>