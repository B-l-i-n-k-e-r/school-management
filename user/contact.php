<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | The Michaelson School</title>
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

        a:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 3px;
        }

        /* ---------- Hero ---------- */
        .ct-hero {
            position: relative;
            background:
                linear-gradient(100deg, rgba(0, 10, 102, 0.94) 0%, rgba(0, 10, 102, 0.78) 55%, rgba(0, 10, 102, 0.45) 100%),
                url('../images/school_bg.jpg') center / cover no-repeat;
            color: var(--white);
            padding: 110px 24px 170px;
            overflow: hidden;
        }
        .ct-hero::after {
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
        .ct-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            animation: ct-rise 0.8s ease both;
        }
        .ct-tag {
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
        .ct-tag i { color: var(--accent); }
        .ct-hero h1 {
            font-size: clamp(2.2rem, 5.2vw, 3.9rem);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 22px;
            max-width: 14ch;
        }
        .ct-hero p {
            max-width: 520px;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.88);
        }
        .ct-rule {
            width: 72px;
            height: 5px;
            border-radius: 99px;
            background: var(--accent);
            margin-top: 34px;
        }

        /* ---------- Layout ---------- */
        .ct-wrap {
            max-width: 1100px;
            margin: -100px auto 0;
            padding: 0 24px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 24px;
        }

        .ct-info {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 44px 40px;
            box-shadow: 0 30px 60px -24px rgba(0, 10, 102, 0.22);
        }
        .ct-info h2 {
            margin: 0 0 28px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }

        .ct-item {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 22px 0;
            border-top: 1px solid var(--line);
        }
        .ct-item:first-of-type { border-top: none; padding-top: 0; }
        .ct-item:last-child { padding-bottom: 0; }

        .ct-icon {
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.35rem;
        }
        .ct-details h3 {
            margin: 0 0 4px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .ct-details p,
        .ct-details a {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 600;
            line-height: 1.6;
            color: var(--ink);
            text-decoration: none;
            word-break: break-word;
        }
        .ct-details a { color: var(--primary); }
        .ct-details a:hover { text-decoration: underline; }

        /* Map: the one bold panel */
        .ct-map {
            background: var(--primary);
            color: var(--white);
            border-radius: var(--radius-lg);
            padding: 44px 40px;
            box-shadow: 0 30px 60px -24px rgba(0, 10, 102, 0.3);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            min-height: 380px;
            position: relative;
            overflow: hidden;
        }
        .ct-map::before {
            content: "";
            position: absolute;
            right: -90px;
            top: -90px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.22), transparent 70%);
        }
        .ct-map-icon {
            position: relative;
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: var(--accent);
            color: var(--primary);
            font-size: 1.9rem;
            margin-bottom: 26px;
        }
        .ct-map h2 {
            position: relative;
            margin: 0 0 12px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .ct-map p {
            position: relative;
            margin: 0 0 28px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.82);
            max-width: 32ch;
        }
        .ct-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 26px;
            border-radius: 99px;
            background: var(--accent);
            color: var(--primary);
            font-weight: 800;
            font-size: 0.97rem;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .ct-btn:hover {
            background: #ffd93d;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -10px rgba(250, 204, 21, 0.8);
        }

        /* ---------- Footer ---------- */
        .ct-footer {
            background: var(--primary);
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            padding: 30px 20px;
            margin-top: 90px;
            font-size: 0.92rem;
            border-top: 4px solid var(--accent);
        }

        @keyframes ct-rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .ct-hero-inner { animation: none; }
            .ct-btn { transition: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 860px) {
            .ct-hero { padding: 80px 20px 150px; }
            .ct-wrap { grid-template-columns: 1fr; }
            .ct-info, .ct-map { padding: 34px 26px; }
            .ct-map { min-height: 0; }
        }
    </style>
</head>
<body>

<?php include("../includes/header.php"); ?>

<section class="ct-hero">
    <div class="ct-hero-inner">
        <div class="ct-tag"><i class="bi bi-chat-dots-fill"></i> Contact</div>
        <h1>Get in Touch</h1>
        <p>Have questions? We'd love to hear from you.</p>
        <div class="ct-rule"></div>
    </div>
</section>

<main class="ct-wrap">
    <section class="ct-info">
        <h2>How to reach us</h2>

        <div class="ct-item">
            <div class="ct-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="ct-details">
                <h3>Our location</h3>
                <p>The Principal, The Michaelson School,<br>
                NTR Society, Upper Road,<br>
                NTR - 40417-16, Kenya</p>
            </div>
        </div>

        <div class="ct-item">
            <div class="ct-icon"><i class="bi bi-telephone-fill"></i></div>
            <div class="ct-details">
                <h3>Phone &amp; fax</h3>
                <a href="tel:+254705681215">+254 705 681215</a>
            </div>
        </div>

        <div class="ct-item">
            <div class="ct-icon"><i class="bi bi-envelope-fill"></i></div>
            <div class="ct-details">
                <h3>Direct email</h3>
                <a href="mailto:vinniemariba2004@gmail.com">vinniemariba2004@gmail.com</a>
            </div>
        </div>

        <div class="ct-item">
            <div class="ct-icon"><i class="bi bi-clock-fill"></i></div>
            <div class="ct-details">
                <h3>Office hours</h3>
                <p>Monday to Saturday, 9:00 AM to 6:00 PM</p>
            </div>
        </div>
    </section>

    <section class="ct-map">
        <div class="ct-map-icon"><i class="bi bi-map-fill"></i></div>
        <h2>Find us on Google Maps</h2>
        <p>Get directions to the school from wherever you are.</p>
        <a class="ct-btn" href="https://maps.google.com" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right"></i> Open in Google Maps
        </a>
    </section>
</main>

<footer class="ct-footer">
    &copy; 2026 The Michaelson School, Kenya. All Rights Reserved.
</footer>

</body>
</html>