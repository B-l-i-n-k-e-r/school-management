<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$staff_name = $staff_data['full_name'] ?? 'Staff';

// Fetch files uploaded by Admin
$files = [];
$d_stmt = $conn->query("SELECT * FROM downloads ORDER BY category ASC, created_at DESC");
if ($d_stmt) {
    while ($row = $d_stmt->fetch_assoc()) $files[] = $row;
}

// Group by category
$grouped = [];
foreach ($files as $f) {
    $grouped[$f['category'] ?? 'Other'][] = $f;
}

function file_icon($type) {
    $t = strtolower($type ?? '');
    return match(true) {
        str_contains($t, 'pdf')                  => 'file-earmark-pdf-fill text-danger',
        str_contains($t, 'zip') || str_contains($t, 'rar') => 'file-earmark-zip-fill text-warning',
        str_contains($t, 'doc')                  => 'file-earmark-word-fill text-primary',
        str_contains($t, 'xls')                  => 'file-earmark-excel-fill text-success',
        default                                  => 'file-earmark-text-fill text-secondary',
    };
}

$staff_display_name = $staff_name;
$active_page = 'downloads';
$extra_label = 'Staff Resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Shared styles from your sample */
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
            --radius-lg: 24px;
        }

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }

        .ui-empty { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .ui-empty i { display: block; font-size: 2.4rem; margin-bottom: 10px; color: var(--primary); opacity: 0.4; }
        .ui-empty p { margin: 0; font-weight: 600; }
        .ui-empty small { display: block; margin-top: 6px; }

        .file-card { border: none; border-radius: 20px; background: var(--white); box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s, box-shadow 0.3s; overflow: hidden; height: 100%; display: flex; flex-direction: column; border: 1px solid var(--line); }
        .file-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); }
        
        .icon-box { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: var(--bg-light); }
        .category-badge { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; padding: 6px 12px; border-radius: 50px; font-weight: 800; }

        .ui-search { position: relative; width: 260px; max-width: 100%; }
        .ui-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .ui-search input { width: 100%; box-sizing: border-box; padding: 11px 16px 11px 42px; border: 1.5px solid var(--line); border-radius: 99px; background: var(--bg-light); font-family: inherit; font-size: 0.9rem; color: var(--ink); }
        .ui-search input:focus { outline: none; border-color: var(--primary); background: var(--white); }

        @media (max-width: 992px) {
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Staff Resources</h1>
            <p>Download official school documents, forms, and academic materials</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <label class="ui-search">
                <i class="bi bi-search"></i>
                <input type="text" id="fileSearch" placeholder="Search resources...">
            </label>
        </div>
    </div>

    <?php if (empty($files)): ?>
        <div class="panel">
            <div class="ui-empty">
                <i class="bi bi-folder2-open"></i>
                <p>No resources uploaded</p>
                <small>The administration has not posted any downloadable files yet.</small>
            </div>
        </div>
    <?php else: ?>
        
        <?php foreach ($grouped as $category => $category_files): ?>
            <div class="mb-5">
                <div class="d-flex align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="color: var(--ink); font-size: 1.1rem;"><?php echo htmlspecialchars($category); ?></h6>
                    <span class="badge bg-light text-secondary border ms-3 px-3 py-1"><?php echo count($category_files); ?> files</span>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($category_files as $file): ?>
                        <div class="col-md-6 col-lg-4 file-item">
                            <div class="file-card p-4">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div class="icon-box">
                                        <i class="bi bi-<?php echo file_icon($file['file_type']); ?>"></i>
                                    </div>
                                    <span class="category-badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($file['file_type'] ?: 'FILE'); ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold mb-2 text-dark text-truncate" title="<?php echo htmlspecialchars($file['title'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($file['title'] ?? ''); ?>
                                </h6>
                                <p class="small text-muted mb-4">
                                    <i class="bi bi-hdd me-1"></i><?php echo htmlspecialchars($file['file_size'] ?? '—'); ?>
                                    &middot; <i class="bi bi-calendar3 me-1"></i><?php echo !empty($file['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($file['created_at']))) : '—'; ?>
                                </p>
                                <a href="<?php echo htmlspecialchars($file['file_path'] ?? '#'); ?>"
                                   class="ui-btn is-primary w-100 mt-auto"
                                   download>
                                    <i class="bi bi-download"></i> Download File
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            } else {
                document.body.classList.toggle('sidebar-toggled');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if (icon) icon.classList.replace(isClosed ? 'bi-plus' : 'bi-dash', isClosed ? 'bi-dash' : 'bi-plus');
        });
    });

    // Live search
    const search = document.getElementById('fileSearch');
    if (search) {
        search.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.file-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>