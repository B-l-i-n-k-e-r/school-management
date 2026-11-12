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

$stmt = $conn->prepare("SELECT student_id, adm_no, full_name, form FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

$files = [];
$d_stmt = $conn->query("SELECT * FROM downloads ORDER BY category ASC, created_at DESC");
if ($d_stmt) {
    while ($row = $d_stmt->fetch_assoc()) $files[] = $row;
}

$grouped = [];
foreach ($files as $f) {
    $grouped[$f['category'] ?? 'Other'][] = $f;
}

function file_icon($type) {
    $t = strtolower($type ?? '');
    return match(true) {
        str_contains($t, 'pdf') => 'file-earmark-pdf-fill text-danger',
        str_contains($t, 'zip') || str_contains($t, 'rar') => 'file-earmark-zip-fill text-warning',
        str_contains($t, 'doc') => 'file-earmark-word-fill text-primary',
        str_contains($t, 'xls') => 'file-earmark-excel-fill text-success',
        default => 'file-earmark-text-fill text-secondary',
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
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        
        .file-card { border: none; border-radius: 20px; background: var(--white); box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s, box-shadow 0.3s; overflow: hidden; height: 100%; display: flex; flex-direction: column; border: 1.5px solid var(--line); }
        .file-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); }
        .icon-box { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: var(--bg-light); }
        .category-badge { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; padding: 6px 12px; border-radius: 50px; font-weight: 800; }
        
        .sys-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white; border-radius: 20px; padding: 25px; height: 100%; display: flex; flex-direction: column; position: relative; overflow: hidden; text-decoration: none; }
        .sys-card::after { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .sys-card:hover { color: white; transform: translateY(-5px); box-shadow: 0 12px 25px rgba(26, 48, 102, 0.3); }
        .sys-icon { font-size: 2rem; margin-bottom: 15px; color: var(--accent); }
        
        .ui-search { position: relative; width: 260px; max-width: 100%; }
        .ui-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .ui-search input { width: 100%; box-sizing: border-box; padding: 11px 16px 11px 42px; border: 1.5px solid var(--line); border-radius: 99px; background: var(--bg-light); font-family: inherit; font-size: 0.9rem; color: var(--ink); }
        .ui-search input:focus { outline: none; border-color: var(--primary); background: var(--white); }
        
        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Student Resources</h1>
            <p>Download official documents, forms, and study materials</p>
        </div>
        <label class="ui-search">
            <i class="bi bi-search"></i>
            <input type="text" id="fileSearch" placeholder="Search resources...">
        </label>
    </div>

    <!-- System Generated Documents -->
    <div class="mb-5">
        <h5 class="fw-bold mb-3 text-uppercase small text-muted" style="letter-spacing: 0.1em;">
            <i class="bi bi-cpu-fill me-2" style="color: var(--primary);"></i>System Generated Documents
        </h5>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-person-vcard-fill sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Exam Card</h6>
                    <small class="text-white-50 mb-3">Official exam pass</small>
                    <a href="print_document.php?type=exam_card" target="_blank" class="ui-btn is-primary w-100 mt-2">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-calendar3 sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Class Timetable</h6>
                    <small class="text-white-50 mb-3">Weekly class schedule</small>
                    <a href="print_document.php?type=timetable" target="_blank" class="ui-btn is-primary w-100 mt-2">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-list-columns-reverse sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Fee Structure</h6>
                    <small class="text-white-50 mb-3">Expected fees breakdown</small>
                    <a href="print_document.php?type=fee_structure" target="_blank" class="ui-btn is-primary w-100 mt-2">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="sys-card">
                    <i class="bi bi-receipt-cutoff sys-icon"></i>
                    <h6 class="fw-bold mb-1 mt-auto">Fee Statement</h6>
                    <small class="text-white-50 mb-3">Financial statement</small>
                    <a href="print_document.php?type=fees" target="_blank" class="ui-btn is-primary w-100 mt-2">
                        <i class="bi bi-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Uploaded Study Materials -->
    <?php if (empty($files)): ?>
        <div class="panel text-center">
            <div class="ui-empty">
                <i class="bi bi-folder2-open"></i>
                <p>No resources uploaded</p>
                <small>The administration has not posted any downloadable files yet.</small>
            </div>
        </div>
    <?php else: ?>
        <h5 class="fw-bold mb-3 text-uppercase small text-muted" style="letter-spacing: 0.1em;">
            <i class="bi bi-folder-fill me-2" style="color: var(--primary);"></i>School Resources
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

    document.getElementById('fileSearch')?.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.file-item').forEach(item => {
            const text = item.innerText.toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
    });
</script>
</body>
</html>