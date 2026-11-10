<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | The Michaelson School</title>
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
            --radius-md: 18px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            color: var(--ink);
            background-color: var(--bg-light);
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Hero ---------- */
        .ab-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .ab-hero::after {
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
        .ab-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: ab-rise 0.8s ease both;
        }
        .ab-tag {
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
        .ab-tag i { color: var(--accent); }
        .ab-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .ab-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.15rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.88);
        }
        .ab-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Layout ---------- */
        .ab-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }
        .ab-duo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .ab-panel {
            border-radius: var(--radius-lg);
            padding: 44px 40px;
            box-shadow: 0 30px 60px -24px rgba(0, 10, 102, 0.22);
        }
        .ab-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            margin-bottom: 22px;
        }
        .ab-panel h2 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin: 0 0 14px;
        }
        .ab-panel p {
            margin: 0;
            line-height: 1.8;
            font-size: 1rem;
        }

        /* Mission: the one bold panel */
        .ab-mission {
            background: var(--primary);
            color: var(--white);
        }
        .ab-mission .ab-icon { background: var(--accent); color: var(--primary); }
        .ab-mission p { color: rgba(255, 255, 255, 0.82); }
        .ab-motto {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 1.15rem;
            font-weight: 700;
            font-style: italic;
            color: var(--accent);
        }

        /* Vision: quiet white panel */
        .ab-vision {
            background: var(--white);
        }
        .ab-vision .ab-icon { background: var(--primary-tint); color: var(--primary); }
        .ab-vision h2 { color: var(--primary); }
        .ab-vision p { color: var(--text-muted); }

        /* ---------- Core values ---------- */
        .ab-values {
            margin-top: 24px;
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 48px 40px;
            box-shadow: 0 30px 60px -24px rgba(0, 10, 102, 0.12);
        }
        .ab-values-head {
            max-width: 620px;
            margin-bottom: 32px;
        }
        .ab-values-head h2 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
            margin: 0 0 10px;
        }
        .ab-values-head p {
            margin: 0;
            line-height: 1.8;
            color: var(--text-muted);
        }
        .ab-values-grid {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .ab-values-grid li {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            background: var(--bg-light);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.35;
        }
        .ab-values-grid i {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.15rem;
        }

        /* ---------- Footer ---------- */
        .ab-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes ab-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .ab-hero-inner { animation: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 960px) {
            .ab-values-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 800px) {
            .ab-hero { padding: 80px 20px 150px; }
            .ab-duo { grid-template-columns: 1fr; }
            .ab-panel { padding: 36px 28px; }
            .ab-values { padding: 36px 24px; }
        }
        @media (max-width: 520px) {
            .ab-values-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="ab-hero">
    <div class="ab-hero-inner">
        <div class="ab-tag"><i class="bi bi-bookmark-star-fill"></i> About us</div>
        <h1>Our Foundation</h1>
        <p>Excellence in education, integrity in character.</p>
        <div class="ab-rule"></div>
    </div>
</section>

<main class="ab-wrap">
    <div class="ab-duo">
        <section class="ab-panel ab-mission">
            <div class="ab-icon"><i class="bi bi-bullseye"></i></div>
            <h2>Our Mission</h2>
            <p>
                The mission of The Michaelson School is to deliver students and services to meet the changing needs of society and thereby contribute to overall school development of the State in particular and Nation in general. We strive to improve the quality of life in our communities through dedicated education.
            </p>
            <div class="ab-motto">"Create State's Best School"</div>
        </section>

        <section class="ab-panel ab-vision">
            <div class="ab-icon"><i class="bi bi-eye-fill"></i></div>
            <h2>Our Vision</h2>
            <p>
                The Michaelson School will be known for excellence in the study of education as the first choice for all students and society. Where quality is a way of life, we aim to be a pioneer institute imparting excellent training. We focus not just on curriculum, but on transferring technology and moral values to the community.
            </p>
        </section>
    </div>

    <section class="ab-values">
        <div class="ab-values-head">
            <h2>Core Values &amp; Beliefs</h2>
            <p>
                Our core values are quality, equity, morality, environmental sustainability, and a strong commitment to the cause of education.
            </p>
        </div>
        <ul class="ab-values-grid">
            <li><i class="bi bi-people-fill"></i> Equitable &amp; Harmonious Growth</li>
            <li><i class="bi bi-lightbulb-fill"></i> Enlightened Vision</li>
            <li><i class="bi bi-bank2"></i> Strong Civic Sense</li>
            <li><i class="bi bi-shield-fill-check"></i> High Moral Standards</li>
            <li><i class="bi bi-globe-europe-africa"></i> Global &amp; Local Awareness</li>
            <li><i class="bi bi-patch-check-fill"></i> Commitment to Quality</li>
        </ul>
    </section>
</main>

<footer class="ab-footer">
    &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>