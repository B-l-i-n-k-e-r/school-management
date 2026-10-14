<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../dashboard.php");
    exit();
}

include('../database/db_connect.php');

// Database Configuration
$host = 'localhost';
$db   = 'school_admin_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// Fetch all available downloads
// Table assumed: downloads (id, title, category, file_path, file_size, file_type, created_at)
$stmt = $pdo->query("SELECT * FROM downloads ORDER BY category DESC, created_at DESC");
$files = $stmt->fetchAll();

// Helper to get icons based on file type
function getFileIcon($type) {
    $type = strtolower($type);
    if (strpos($type, 'pdf') !== false) return 'bi-file-earmark-pdf text-danger';
    if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false) return 'bi-file-earmark-zip text-warning';
    if (strpos($type, 'doc') !== false) return 'bi-file-earmark-word text-primary';
    return 'bi-file-earmark-text text-secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads | Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .main-content { padding: 40px; }
        .file-card { 
            border: none; 
            border-radius: 12px; 
            transition: transform 0.2s, box-shadow 0.2s; 
            background: #fff;
        }
        .file-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.05); 
        }
        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: #f8f9fa;
        }
        .category-badge {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 50px;
        }
    </style>
</head>
<body>

<div class="container main-content">
    <div class="row mb-4">
        <div class="col-md-8">
            <h3 class="fw-bold">Student Resources</h3>
            <p class="text-muted">Access course outlines, past papers, and institutional forms.</p>
        </div>
        <div class="col-md-4">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="fileSearch" class="form-control border-start-0" placeholder="Search resources...">
            </div>
        </div>
    </div>

    <div class="row g-4" id="fileGrid">
        <?php if (!empty($files)): ?>
            <?php foreach ($files as $file): ?>
            <div class="col-md-6 col-lg-4 file-item">
                <div class="card file-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-box">
                            <i class="bi <?php echo getFileIcon($file['file_type']); ?>"></i>
                        </div>
                        <span class="badge bg-light text-dark category-badge border">
                            <?php echo htmlspecialchars($file['category']); ?>
                        </span>
                    </div>
                    
                    <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($file['title']); ?></h6>
                    <p class="small text-muted mb-3">
                        <?php echo $file['file_size']; ?> • Added <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                    </p>

                    <div class="mt-auto">
                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-outline-primary btn-sm w-100 fw-bold" download>
                            <i class="bi bi-download me-2"></i> Download
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-folder2-open display-1 text-light"></i>
                <p class="text-muted mt-3">No resources available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple real-time search filter
    document.getElementById('fileSearch').addEventListener('input', function(e) {
        let term = e.target.value.toLowerCase();
        let files = document.querySelectorAll('.file-item');
        
        files.forEach(file => {
            let title = file.querySelector('h6').innerText.toLowerCase();
            let category = file.querySelector('.category-badge').innerText.toLowerCase();
            if(title.includes(term) || category.includes(term)) {
                file.style.display = 'block';
            } else {
                file.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>