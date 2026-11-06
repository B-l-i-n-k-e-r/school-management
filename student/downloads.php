<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

// Fetch student
$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

// Fetch downloads
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
        str_contains($t, 'pdf')                  => 'file-earmark-pdf text-danger',
        str_contains($t, 'zip') || str_contains($t, 'rar') => 'file-earmark-zip text-warning',
        str_contains($t, 'doc')                  => 'file-earmark-word text-primary',
        str_contains($t, 'xls')                  => 'file-earmark-excel text-success',
        default                                  => 'file-earmark-text text-secondary',
    };
}

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'downloads';
$extra_label = 'Downloads';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-bg: #34445c; --submenu-bg: #2c3a4d; --primary-blue: #1a3066; --sidebar-width: 240px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: #dee2e6; padding: 10px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; }
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .user-dropdown { background: var(--primary-blue); color: white; padding: 6px 15px; font-size: 0.8rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 700; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .file-card { border: none; border-radius: 16px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .file-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; background: #f8fafc; }
        .category-badge { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; padding: 4px 10px; border-radius: 50px; font-weight: 800; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Student Resources</h2>
            <p class="text-muted small mb-0">Course outlines, past papers, and institutional forms</p>
        </div>
        <div class="input-group shadow-sm" style="max-width: 320px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="fileSearch" class="form-control border-start-0" placeholder="Search resources...">
        </div>
    </div>

    <?php if (empty($files)): ?>
        <div class="card p-5 text-center">
            <i class="bi bi-folder2-open fs-1 text-muted d-block mb-3"></i>
            <h5 class="fw-bold text-muted">No resources available</h5>
            <p class="text-muted mb-0">Check back later — the school will post downloadable material here.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $category => $category_files): ?>
            <div class="mb-5">
                <h5 class="fw-bold mb-3 text-uppercase small text-muted" style="letter-spacing: 0.1em;">
                    <i class="bi bi-folder-fill me-2 text-primary"></i><?php echo htmlspecialchars($category); ?>
                </h5>
                <div class="row g-4">
                    <?php foreach ($category_files as $file): ?>
                        <div class="col-md-6 col-lg-4 file-item">
                            <div class="file-card p-3 h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="icon-box">
                                        <i class="bi bi-<?php echo file_icon($file['file_type']); ?>"></i>
                                    </div>
                                    <span class="category-badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($file['file_type'] ?: 'FILE'); ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($file['title'] ?? ''); ?></h6>
                                <p class="small text-muted mb-3">
                                    <?php echo htmlspecialchars($file['file_size'] ?? '—'); ?>
                                    &middot; <?php echo !empty($file['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($file['created_at']))) : '—'; ?>
                                </p>
                                <a href="<?php echo htmlspecialchars($file['file_path'] ?? '#'); ?>"
                                   class="btn btn-outline-primary btn-sm w-100 fw-bold rounded-3 mt-auto"
                                   download>
                                    <i class="bi bi-download me-2"></i>Download
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