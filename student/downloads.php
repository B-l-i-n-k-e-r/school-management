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

// Fetch Admin-uploaded downloads
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

        .file-card { border: none; border-radius: 20px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s, box-shadow 0.3s; overflow: hidden; height: 100%; display: flex; flex-direction: column; }
        .file-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); }
        
        .icon-box { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #f8fafc; }
        .category-badge { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; padding: 6px 12px; border-radius: 50px; font-weight: 800; }

        .sys-card { background: linear-gradient(135deg, #1a3066 0%, #2d4a8a 100%); color: white; border-radius: 20px; padding: 25px; height: 100%; display: flex; flex-direction: column; position: relative; overflow: hidden; text-decoration: none; }
        .sys-card::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .sys-card:hover { color: white; transform: translateY(-5px); box-shadow: 0 12px 25px rgba(26, 48, 102, 0.3); }
        .sys-icon { font-size: 2rem; margin-bottom: 15px; color: #fbbf24; }

        .search-box { max-width: 350px; width: 100%; }
        .search-box input { border-radius: 50px 0 0 50px; padding-left: 20px; border: 1px solid #cbd5e1; }
        .search-box .input-group-text { border-radius: 0 50px 50px 0; border-left: none; border-color: #cbd5e1; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-start mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1 text-dark">Student Resources</h2>
            <p class="text-muted small mb-0">Download official documents, forms, and study materials</p>
        </div>
        <div class="input-group search-box shadow-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="fileSearch" class="form-control border-start-0" placeholder="Search resources...">
        </div>
    </div>

    <!-- SECTION 1: System Generated Documents -->
    <div class="mb-5">
        <h5 class="fw-bold mb-3 text-uppercase small text-muted" style="letter-spacing: 0.1em;">
            <i class="bi bi-cpu-fill me-2 text-primary"></i>System Generated Documents
        </h5>
        <div class="row g-4">
            
            <!-- Exam Card -->
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-person-vcard-fill sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Exam Card</h6>
                    <small class="text-white-50 mb-3">Official exam pass</small>
                    <a href="print_document.php?type=exam_card" target="_blank" class="btn btn-light text-primary fw-bold rounded-pill mt-2 w-100">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                </div>
            </div>

            <!-- Timetable -->
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-calendar3 sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Class Timetable</h6>
                    <small class="text-white-50 mb-3">Weekly class schedule</small>
                    <a href="print_document.php?type=timetable" target="_blank" class="btn btn-light text-primary fw-bold rounded-pill mt-2 w-100">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                </div>
            </div>

            <!-- Fee Structure -->
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-list-columns-reverse sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Fee Structure</h6>
                    <small class="text-white-50 mb-3">Expected fees breakdown</small>
                    <a href="print_document.php?type=fee_structure" target="_blank" class="btn btn-light text-primary fw-bold rounded-pill mt-2 w-100">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                </div>
            </div>

            <!-- Fee Statement -->
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-receipt-cutoff sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Fee Statement</h6>
                    <small class="text-white-50 mb-3">Financial statement</small>
                    <a href="print_document.php?type=fees" target="_blank" class="btn btn-light text-primary fw-bold rounded-pill mt-2 w-100">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- SECTION 2: Uploaded Study Materials -->
    <?php if (empty($files)): ?>
        <div class="card p-5 text-center mt-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-folder2-open fs-1 text-muted"></i>
            </div>
            <h5 class="fw-bold text-dark">No resources uploaded</h5>
            <p class="text-muted mb-0">The administration has not posted any downloadable files yet.</p>
        </div>
    <?php else: ?>
        <h5 class="fw-bold mb-3 text-uppercase small text-muted" style="letter-spacing: 0.1em;">
            <i class="bi bi-folder-fill me-2 text-primary"></i>School Resources
        </h5>
        
        <?php foreach ($grouped as $category => $category_files): ?>
            <div class="mb-5">
                <div class="d-flex align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark fs-5"><?php echo htmlspecialchars($category); ?></h6>
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
                                   class="btn btn-primary w-100 fw-bold rounded-pill mt-auto"
                                   download>
                                    <i class="bi bi-download me-2"></i>Download File
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