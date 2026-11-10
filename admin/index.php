<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

// Safe query helper
function safeQuery($conn, $sql, $default = null) {
    try {
        $res = $conn->query($sql);
        if (!$res) return $default;
        $row = $res->fetch_assoc();
        return $row ?: $default;
    } catch (Throwable $e) {
        return $default;
    }
}

// 12400 -> "KES 12.4k", 2500000 -> "KES 2.50M"
function format_kes($amount) {
    if ($amount >= 1000000) return 'KES ' . number_format($amount / 1000000, 2) . 'M';
    if ($amount >= 1000)    return 'KES ' . number_format($amount / 1000, 1) . 'k';
    return 'KES ' . number_format($amount);
}

// School name
$sys = safeQuery($conn, "SELECT school_name FROM system_settings LIMIT 1", ['school_name' => 'The Michaelson School']);
$school_display = $sys['school_name'] ?? 'The Michaelson School';

// Stats — safe against missing tables
$student_count = safeQuery($conn, "SELECT COUNT(*) as total FROM students")['total'] ?? 0;
$staff_count   = safeQuery($conn, "SELECT COUNT(*) as total FROM teachers")['total'] ?? 0;
$subject_count = safeQuery($conn, "SELECT COUNT(*) as total FROM subjects")['total'] ?? 0;

// Fees collected
$total_fees = 0;
$feesRow = safeQuery($conn, "SELECT SUM(amount_paid) as total FROM fees");
if ($feesRow && !empty($feesRow['total'])) {
    $total_fees = (float) $feesRow['total'];
}

// Recent notices
$recent_notices = [];
try {
    $rn = $conn->query("
        SELECT notice_id, title, type, target_audience, created_at
        FROM notices
        ORDER BY created_at DESC
        LIMIT 3
    ");
    if ($rn) {
        while ($row = $rn->fetch_assoc()) $recent_notices[] = $row;
    }
} catch (Throwable $e) {
    // notices table missing — silently skip
}

$active_page = 'dashboard';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?php echo htmlspecialchars($school_display); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* Shared admin styles (sidebar, top bar, colours) live in includes/sidebar.php and includes/navbar.php */

        /* ---------- Welcome banner ---------- */
        .dash-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            padding: 40px 44px;
            border-radius: 28px;
            color: var(--white);
            background: linear-gradient(115deg, var(--primary) 0%, var(--primary-soft) 100%);
            box-shadow: 0 30px 60px -28px rgba(0, 10, 102, 0.55);
        }
        .dash-hero::after {
            content: "";
            position: absolute;
            right: -90px;
            bottom: -140px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.32), transparent 70%);
        }
        .dash-hero > * { position: relative; z-index: 1; }
        .dash-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 16px;
            padding: 7px 14px;
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.82rem;
            font-weight: 600;
        }
        .dash-date i { color: var(--accent); }
        .dash-hero h1 {
            margin: 0 0 8px;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .dash-hero p.sub {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 1rem;
        }

        /* ---------- Stat cards ---------- */
        .dash-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .dash-stat {
            background: var(--white);
            border-radius: 24px;
            padding: 26px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
            transition: transform 0.2s;
        }
        .dash-stat:hover { transform: translateY(-4px); }
        .dash-stat-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 22px;
        }
        .dash-stat strong {
            display: block;
            margin-bottom: 6px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }
        .dash-stat span {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        /* the one highlighted card */
        .dash-stat.is-featured {
            background: var(--accent);
        }
        .dash-stat.is-featured .dash-stat-icon { background: var(--primary); color: var(--accent); }
        .dash-stat.is-featured strong,
        .dash-stat.is-featured span { color: var(--primary); }

        /* ---------- Chart + quick actions ---------- */
        .dash-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        .dash-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }
        .dash-head h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .dash-pill {
            padding: 6px 14px;
            border-radius: 99px;
            background: var(--bg-light);
            border: 1px solid var(--line);
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .dash-pill.is-sample {
            background: var(--accent-tint);
            border-color: transparent;
            color: var(--accent-deep);
        }
        .dash-chart { position: relative; height: 300px; }

        .dash-actions { display: grid; gap: 12px; }
        .dash-action {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: var(--bg-light);
            color: var(--ink);
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
        }
        .dash-action i.lead {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--white);
            color: var(--primary);
            font-size: 1.05rem;
            transition: background 0.2s;
        }
        .dash-action i.go { margin-left: auto; color: var(--text-muted); font-size: 0.85rem; }
        .dash-action:hover { background: var(--primary-tint); transform: translateX(3px); color: var(--ink); }
        .dash-action:hover i.lead { background: var(--accent); }

        /* ---------- Recent notices ---------- */
        .dash-title-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--accent-tint);
            color: var(--accent-deep);
            font-size: 1.15rem;
        }
        .dash-title { display: flex; align-items: center; gap: 14px; }
        .dash-link {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        .dash-link:hover { text-decoration: underline; }

        .dash-notices {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .dash-notice {
            display: block;
            height: 100%;
            padding: 20px;
            border-radius: 20px;
            background: var(--bg-light);
            border: 1px solid var(--line);
            text-decoration: none;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
        }
        .dash-notice:hover {
            background: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 18px 30px -20px rgba(0, 10, 102, 0.35);
        }
        .dash-badge {
            display: inline-block;
            margin-bottom: 12px;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .dash-notice h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.45;
            color: var(--ink);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .dash-notice small { font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }
        .dash-notice small i { margin-right: 4px; }

        .dash-empty { text-align: center; padding: 28px 12px; color: var(--text-muted); }
        .dash-empty i { display: block; font-size: 2.2rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .dash-empty p { margin: 0 0 16px; font-size: 0.92rem; }
        .dash-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 99px;
            background: var(--accent);
            color: var(--primary);
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .dash-btn:hover { background: #ffd93d; color: var(--primary); }

        @keyframes dash-rise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }
        .dash-hero { animation: dash-rise 0.6s ease both; }
        @media (prefers-reduced-motion: reduce) {
            .dash-hero { animation: none; }
            .dash-stat, .dash-action, .dash-notice { transition: none; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 1200px) {
            .dash-stats { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 992px) {
            .dash-grid { grid-template-columns: 1fr; }
            .dash-notices { grid-template-columns: 1fr; }
            .dash-hero { padding: 30px 24px; }
        }
        @media (max-width: 520px) {
            .dash-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <section class="dash-hero">
        <p class="dash-date"><i class="bi bi-calendar3"></i> <?php echo date('l, j F Y'); ?></p>
        <h1>Welcome back to <?php echo htmlspecialchars($school_display); ?>!</h1>
        <p class="sub">Here is an overview of the school today.</p>
    </section>

    <!-- Stat cards -->
    <section class="dash-stats">
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-people-fill"></i></div>
            <strong><?php echo (int)$student_count; ?></strong>
            <span>Total enrollment</span>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-person-badge-fill"></i></div>
            <strong><?php echo (int)$staff_count; ?></strong>
            <span>Active faculty</span>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            <strong><?php echo (int)$subject_count; ?></strong>
            <span>Total subjects</span>
        </div>
        <div class="dash-stat is-featured">
            <div class="dash-stat-icon"><i class="bi bi-wallet2"></i></div>
            <strong><?php echo format_kes($total_fees); ?></strong>
            <span>Total fees collected</span>
        </div>
    </section>

    <!-- Chart + Quick Actions -->
    <section class="dash-grid">
        <div class="panel">
            <div class="dash-head">
                <h2>Admission trends</h2>
                <div>
                    <span class="dash-pill is-sample">Sample data</span>
                    <span class="dash-pill">Last 6 months</span>
                </div>
            </div>
            <div class="dash-chart">
                <canvas id="registrationChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="dash-head">
                <h2>Quick actions</h2>
            </div>
            <div class="dash-actions">
                <a href="admission.php" class="dash-action">
                    <i class="bi bi-person-plus-fill lead"></i> Register student <i class="bi bi-chevron-right go"></i>
                </a>
                <a href="staff.php" class="dash-action">
                    <i class="bi bi-person-lines-fill lead"></i> Staff directory <i class="bi bi-chevron-right go"></i>
                </a>
                <a href="fee_structure.php" class="dash-action">
                    <i class="bi bi-file-earmark-text-fill lead"></i> Update fees <i class="bi bi-chevron-right go"></i>
                </a>
                <a href="notices.php" class="dash-action">
                    <i class="bi bi-bell-fill lead"></i> Send notifications <i class="bi bi-chevron-right go"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Recent Notices -->
    <section class="panel">
        <div class="dash-head">
            <div class="dash-title">
                <div class="dash-title-icon"><i class="bi bi-megaphone-fill"></i></div>
                <h2>Recent notices</h2>
            </div>
            <a href="notices.php" class="dash-link">View all <i class="bi bi-arrow-right ms-1"></i></a>
        </div>

        <?php if (empty($recent_notices)): ?>
            <div class="dash-empty">
                <i class="bi bi-megaphone"></i>
                <p>No notices posted yet.</p>
                <a href="notices.php" class="dash-btn"><i class="bi bi-plus-lg"></i> Post first notice</a>
            </div>
        <?php else: ?>
            <div class="dash-notices">
                <?php foreach ($recent_notices as $n):
                    $type = $n['type'] ?? 'General';
                    $type_style = match (strtolower($type)) {
                        'academic'  => 'background:#dbeafe; color:#1e40af;',
                        'event'     => 'background:#dcfce7; color:#166534;',
                        'emergency' => 'background:#fee2e2; color:#991b1b;',
                        default     => 'background:#fef3c7; color:#92400e;',
                    };
                ?>
                    <a href="notices.php" class="dash-notice">
                        <span class="dash-badge" style="<?php echo $type_style; ?>">
                            <?php echo htmlspecialchars($type); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($n['title'] ?? ''); ?></h3>
                        <small>
                            <i class="bi bi-clock"></i>
                            <?php echo !empty($n['created_at']) ? htmlspecialchars(date('d M Y', strtotime($n['created_at']))) : '—'; ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    // Dashboard chart (sample numbers until it is connected to real admissions data)
    const ctx = document.getElementById('registrationChart');
    if (ctx) {
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#5b6280';
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Admissions',
                    data: [15, 25, 12, 38, 22, 30],
                    borderColor: '#000a66',
                    backgroundColor: 'rgba(0, 10, 102, 0.07)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#facc15',
                    pointBorderColor: '#000a66',
                    pointBorderWidth: 2
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e3e6f2' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });
    }
</script>

<?php include("footer.php"); ?>
</body>
</html>